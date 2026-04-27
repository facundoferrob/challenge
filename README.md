# Importador de Tarifas de Proveedores

Sistema Laravel para importar tarifas en Excel de múltiples proveedores —
cada uno con su propio formato— y consultarlas vía una API HTTP.

El **punto crítico del diseño** es que agregar un proveedor nuevo **no
requiere modificar código existente**: sólo se agrega una clase nueva, y
el resto del sistema la descubre sola.

---

## 1. Cómo correr el proyecto desde cero

Requisitos: PHP 8.3+ y Composer.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed

# Generar los dos Excel de ejemplo
php tools/generate-samples.php

# Importar (el supplier es siempre obligatorio — nada de auto-detectar)
php artisan tariffs:import storage/app/samples/acme_tariff.xlsx --supplier=acme
php artisan tariffs:import storage/app/samples/global_supply_tariff.xlsx --supplier=global_supply

# Levantar la API
php artisan serve
curl 'http://127.0.0.1:8000/api/products?brand=FastenPro'
curl 'http://127.0.0.1:8000/api/products/FastenPro/GS-A-100'

# Tests
php artisan test
```

Archivos Excel de ejemplo incluidos (se generan con `tools/generate-samples.php`):
- `storage/app/samples/acme_tariff.xlsx` — formato "wide" con hoja separada para impuestos.
- `storage/app/samples/global_supply_tariff.xlsx` — formato "long" (una fila por tramo de precio), unidad embebida en el header, IVA por país en columnas.

---

## 2. Modelo de datos

```
 suppliers ──┐                                  ┌── brands
             │                                  │
             │        ┌──────── products ───────┤
             │        │                         │
             │        │                         ├── families (self-ref: parent_id)
             │        │                         │
             └────────┘                         │
                                                ├── product_price_tiers  (N tramos por producto)
                                                │
                                                └── product_taxes ──┬── countries
                                                                    │
                                                                    └── (rate | amount | unit | type)
```

| Tabla | Campos clave | Por qué así |
|---|---|---|
| `suppliers` | `code` unique, `name`, `default_tax_rate` (decimal, nullable) | El `code` es el identificador estable que usan el flag `--supplier=` y la clase importer. `default_tax_rate` es el **fallback** que un consumidor del catálogo usa cuando no hay registro específico en `product_taxes` para un par (producto, país); se llena fuera del import (seed / admin). |
| `brands` | `name` unique | Tabla propia. Un mismo proveedor distribuye varias marcas; una misma marca puede venir de varios proveedores. |
| `families` | `name`, `parent_id` (FK self) | **Self-referencing**: una sola tabla maneja "familia" y "subfamilia". Si mañana aparece un tercer nivel, no hace falta migración. Alternativa descartada: dos tablas separadas (`families` + `subfamilies`) — rigidiza el modelo. |
| `countries` | `code` (ISO-2) unique | Catálogo normalizado. Se crea on-the-fly si un Excel trae un código desconocido. |
| `products` | `supplier_id`, `brand_id`, `family_id`, `reference`, `ean` NULL, `description` NULL, `unit`, `dimensions` JSON | **`unique(supplier_id, reference)`**: la referencia es del proveedor; dos proveedores distintos pueden compartir el string "SKU-1" y ser cosas distintas. `dimensions` en JSON porque la forma varía por producto (sólo peso; largo×ancho×prof; radio). |
| `product_price_tiers` | `product_id`, `min_quantity`, `price`, `currency` | Tabla aparte. `unique(product_id, min_quantity)`. El número de tramos varía por proveedor y por producto; meterlos como columnas `tier_1..tier_N` dejaría muchas NULL y no escalaría. |
| `product_taxes` | `product_id`, `country_id`, `unit`, `type`, `rate` NULL, `amount` NULL | Tabla aparte. Un mismo producto puede tener impuestos distintos por país y por unidad. Soporta tanto tasa (%) como importe fijo, discriminados por `type`. |

Índices: `products(brand_id)`, `products(ean)`, `products(supplier_id, reference)` (unique), `product_price_tiers(product_id, min_quantity)` (unique), `product_taxes(product_id, country_id, unit, type)` (unique).

---

## 3. Decisiones de diseño

### 3.1 Extensibilidad — Strategy pattern con auto-registro

El corazón del challenge: cómo hacer que **agregar un proveedor nuevo no toque código existente**.

Elegí **Strategy pattern con auto-registro en el service container**:

1. Cada formato = una clase en `app/Importing/Suppliers/` que **extiende** `App\Importing\AbstractSupplierImporter` (implementación base con helpers de lectura de celdas) **o**, en el caso mínimo, implementa directamente la interfaz `App\Importing\SupplierImporter`.
2. `ImportingServiceProvider::register()` escanea ese namespace, instancia cada clase y la agrega al container con `$this->app->tag(..., 'supplier.importer')`.
3. `ImporterRegistry` recibe la colección taggeada y expone `resolve(string $code): SupplierImporter` — un lookup chato por código que tira excepción con la lista de códigos conocidos si el que piden no existe.
4. `ImportTariffsCommand` depende de la `ImporterRegistry` y del `Persister` — nunca sabe qué proveedores existen.

**Resultado**: agregar un proveedor es literalmente crear un archivo:

```
app/Importing/Suppliers/NuevoProveedorImporter.php
```

El provider lo encuentra, el registry lo registra, el command lo usa. **Cero modificaciones** al código existente (OCP satisfecho).

**Alternativas descartadas**:

- **Config YAML por proveedor**: más simple para mapeos triviales, pero el enunciado pide transformaciones no triviales (unidad embebida en header `Peso (kg)`, reshape wide→long, taxes en hoja aparte vs. columnas por país). En config terminás inventando un mini-DSL que se convierte en un lenguaje de programación a medias. Mejor tener PHP tipado de entrada.
- **Config + hooks híbrido**: dos mecanismos para lo mismo — más superficie para documentar y mantener. Sobredimensionado para el scope.

### 3.2 Mapping de columnas explícito dentro de cada importer

Una vez que el registry eligió la clase, **el importer no adivina** qué significa cada columna. El mapping vive como constantes en la clase:

```php
// AcmeImporter
private const TIER_COLUMNS = ['Precio 1+' => 1, 'Precio 10+' => 10, 'Precio 100+' => 100];
private const TAX_SHEET    = 'Impuestos';

// GlobalSupplyImporter
private const TAX_COLUMNS = ['IVA_ES' => 'ES', 'IVA_FR' => 'FR', 'IVA_DE' => 'DE'];
```

Sin regex. Leer la clase te dice qué columnas espera; si Acme agrega `Precio 500+`, es una línea de diff + un test. Una columna rara ("Precio de oferta") que un regex agarraría por error, el mapping explícito la ignora. Mover este mapping a una columna JSON en `suppliers` con los defaults como fallback (Opción C) queda en §6 como mejora.

### 3.3 Identificación explícita del supplier (`--supplier=` obligatorio)

El comando exige `--supplier=<code>`. No hay auto-detect por headers. Si el código no existe: `Unknown supplier 'X'. Known suppliers: [acme, global_supply]`.

Decisión tomada por dos razones:
- **Separación**: parsear un formato y clasificar de qué supplier es un archivo son problemas distintos. Un `supports(array $headers)` obliga a cada importer a saber cómo distinguirse de sus pares — acoplamiento implícito entre clases que deberían ser independientes. Sin auto-detect la interfaz baja a `code()`, `name()`, `parse()`.
- **Costos asimétricos**: clasificar mal = productos bajo el supplier equivocado = precios fantasma en el catálogo. Un *hard fail* por código desconocido se ve y se arregla en 30 segundos.

Si mañana hace falta "tirame un archivo y adiviná", se agrega un `SupplierDetector` aparte.

### 3.4 Responsabilidades separadas (SRP)

Una clase por responsabilidad:
- **`SupplierImporter`** (interface): contrato — `code()`, `name()`, `parse()`.
- **`AbstractSupplierImporter`**: clase base con un único helper, `cleanString()`, que normaliza el valor crudo de PhpSpreadsheet (`null` / `''` / whitespace) a `?string`.
- **`Persister`**: persiste **un** DTO en transacción, idempotente, stateless. Devuelve `PersistResult::Created|Updated`.
- **`ImportReport`**: acumula los resultados del run (creados, actualizados, errores). El comando lo arma.
- **`ImportTariffsCommand`**: orquesta. No sabe de formatos ni de DB.

Cada clase cambia por una sola razón.

### 3.5 DTOs canónicos

Los importers normalizan a un único objeto `SupplierProductDTO` que nada sabe de Excel. Sus campos hijos (`priceTiers`, `taxes`) son arrays asociativos planos cuyas keys ya matchean los `fillable` de los Eloquent correspondientes — el `Persister` los pasa casi directo (`createMany($dto->priceTiers)`).

Por qué un sólo DTO y no tres (decisión deliberada): `SupplierProductDTO` es un aggregate con significado (un producto completo); meter `PriceTierDTO` y `TaxDTO` para 3 y 5 campos cada uno era ceremonia que sólo agregaba plumbing al mapear DTO → Eloquent

- Facilita agregar otras fuentes de datos después (API, CSV, JSON) sin tocar la capa de DB.

### 3.6 Otras decisiones

- **`phpoffice/phpspreadsheet` directo**, sin `maatwebsite/excel` — para que la lógica de parsing quede a la vista.
- **Idempotencia**: `Product::updateOrCreate(['supplier_id', 'reference'])` + `delete+recreate` de hijos. Re-importar no duplica (test explícito).
- **SQLite**: cero setup. Migración a MySQL/Postgres es sólo cambiar el `DB_*` del `.env`.
- **Importación síncrona** vía `artisan tariffs:import`. El `Persister` está aislado — mover a un job encolado es cambio menor (§6).
- **API**: `GET /api/products?brand=&reference=&per_page=` paginado (default 50, máx 200) + `GET /api/products/{brand}/{reference}` con tiers y taxes embebidos. `ProductResource` + eager loading.
- **Validación + errores en JSON**: `ListProductsRequest` valida los query params (devuelve **422** con `{message, errors}` si vienen como array, no-numéricos, fuera de rango, etc.); `bootstrap/app.php` fuerza que **todas las respuestas de errores en `/api/*`** (404, 422, 500, 405) se rendericen como JSON, sin importar el header `Accept` del cliente. Sin esto, Laravel default devuelve HTML para 404/500 — que es lo que típicamente "explota" en clientes API. Mensajes 404 limpios: `{"message":"Product not found"}` cuando un producto no existe (vía `abort(404, ...)` en el controller, sin leakear nombres de modelos), `{"message":"Resource not found"}` cuando la URL no matchea ninguna ruta.

---

## 4. Supuestos tomados

- **EAN es opcional** (lo dice el enunciado). Nullable en la DB y en los DTOs.
- **Referencia pertenece al proveedor**. La unicidad es `(supplier_id, reference)`, no sólo `reference`.
- **Currency por proveedor**: cada importer decide qué moneda reportar (Global Supply la trae explícita "EUR"; Acme se asume EUR por default). En un escenario real vendría del Excel o sería configurable por proveedor.
- **Impuestos**: soporto tanto `rate` (porcentaje decimal) como `amount` (fijo por unidad); ambos nullable y discriminados por `type`. Los dos formatos de ejemplo sólo traen `rate`.
- **Precios sin impuestos (*tax-exclusive*)**: el enunciado señala que *"algunos proveedores incluyen impuestos en el precio, otros no"*. En esta implementación **asumo que los precios vienen netos**. El importer no se mete con el IVA — el `Persister` guarda los precios tal como llegan en `product_price_tiers.price`.
- **Impuestos en lookup**: para determinar qué IVA aplicar al vender un producto a un país, el consumidor del catálogo consulta `product_taxes` filtrando por `product_id` + `country_id`. Si no hay registro específico, usa como fallback `suppliers.default_tax_rate` — una tasa nullable que se setea por admin / seeder, no por el importer. Así el modelo soporta los dos escenarios del enunciado (taxes específicos por producto-país + un default genérico del proveedor) sin contaminar el pipeline de importación.
- **Identificación del supplier**: el caller lo declara explícitamente con `--supplier=<code>`. No auto-detectamos por headers — ver sección 3.3 para la argumentación completa.

---

## 5. Tests

18 tests, todos en verde (`php artisan test`):

- **`tests/Unit/ImporterRegistryTest`** — el registry resuelve por código y tira excepción con la lista de códigos conocidos cuando el pedido no existe. Crítico porque es la pieza que asegura OCP.
- **`tests/Unit/AcmeImporterTest`** — parsing del formato wide: múltiples tramos de precio, dimensiones `L×A×P`, celdas vacías, taxes desde segunda hoja.
- **`tests/Unit/GlobalSupplyImporterTest`** — parsing del formato long: agrupación por REF, extracción de unidad desde el header `Peso (kg)`, split de `Category / Sub`, IVA como columnas por país.
- **`tests/Feature/ImportCommandTest`** — end-to-end del comando de import: persistencia, idempotencia, errores limpios cuando falta `--supplier` o el código es desconocido.
- **`tests/Feature/ProductApiTest`** — listado paginado filtrado por brand; show por brand+reference con tiers y taxes embebidos; 404 en casos inexistentes; **errores siempre en JSON**; **validación 422** para `per_page` no-numérico, fuera de rango, brand como array, page inválido.

**Qué dejé afuera deliberadamente**:
- **Autenticación**: fuera del scope del enunciado.
- **UI**: innecesario en esta etapa.
- **Performance con 100k+ filas**: el diseño (paginación, índices, eager loading, upsert en transacción por producto) soporta volumen razonable; medirlo escapa al alcance. Nota honesta: hoy `SpreadsheetReader::readAssoc()` carga toda la hoja en memoria antes de parsear — aceptable para archivos típicos de tarifas (miles de filas) pero no para decenas de miles. Convertirlo en un generator real (`yield` fila-a-fila) está dentro de la sección de mejoras.
- **Validación exhaustiva campo a campo**: queda cubierta colateralmente por los tests de importers. Un `rules[]` por importer sería una buena mejora (ver abajo).

---

## 6. Qué mejoraría con más tiempo

- **Split `SourceReader` / `SupplierMapper`** — independencia del formato de archivo (xlsx/json/csv). Hoy los importers llaman a `SpreadsheetReader` directo. Una interfaz `SourceReader` con *streams con nombre* (`stream('products')`, `stream('taxes')`), elegido por extensión; los importers quedan format-agnostic.
- **Mapping de columnas en DB ***: columna JSON `column_mapping` en `suppliers` con los defaults de la clase como fallback. Permite ajustar nombres de columnas sin deploy.
- **Importación async con queue**: el `Persister` ya está aislado, trivial moverlo a un Job.
- **Validación declarativa por importer** (`rules(): array`) que el Persister valida antes de persistir; filas inválidas se reportan sin abortar el run.
- **Tabla de reporte por import** (`imports` + `import_errors`) para auditar qué se importó y qué falló.
- **Chunked reader**: `readAssoc` carga la hoja entera — convertir a generator real para archivos muy grandes.
- **Detección de locale** (separador decimal `,` vs `.`, monedas por país).
- **OpenAPI** con Scramble o `l5-swagger`.
- **UI admin** mínima para listar imports y errores.

---

## 7. Estructura relevante

```
app/
  Importing/
    SupplierImporter.php              ← contrato puro (interface)
    AbstractSupplierImporter.php      ← base + helper cleanString()
    ImporterRegistry.php              ← resolve(string $code): SupplierImporter
    Persister.php                     ← DTO → DB (idempotente, stateless)
    PersistResult.php                 ← enum Created | Updated
    ImportReport.php                  ← stats por run (SRP fuera del Persister)
    Support/SpreadsheetReader.php     ← wrapper sobre PhpSpreadsheet
    DTO/SupplierProductDTO.php        ← único DTO; tiers/taxes son arrays planos
    Suppliers/
      AcmeImporter.php                ← formato con hoja Impuestos separada
      GlobalSupplyImporter.php        ← formato una-fila-por-tramo
  Console/Commands/ImportTariffsCommand.php
  Http/Controllers/Api/ProductController.php
  Http/Resources/ProductResource.php
  Models/{Supplier,Brand,Family,Country,Product,PriceTier,Tax}.php
  Providers/ImportingServiceProvider.php   ← auto-descubrimiento

database/migrations/2026_04_22_0000*.php   ← 8 migraciones propias
database/seeders/CountrySeeder.php

tests/
  Support/ExcelBuilder.php            ← helper para generar xlsx in-memory
  Unit/{AcmeImporter,GlobalSupplyImporter,ImporterRegistry}Test.php
  Feature/{ImportCommand,ProductApi}Test.php

tools/generate-samples.php            ← genera los dos xlsx de ejemplo
storage/app/samples/*.xlsx
```
