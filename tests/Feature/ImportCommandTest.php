<?php

namespace Tests\Feature;

use App\Models\PriceTier;
use App\Models\Product;
use App\Models\Tax;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ExcelBuilder;
use Tests\TestCase;

class ImportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_acme_file_end_to_end(): void
    {
        $file = ExcelBuilder::write([
            'Productos' => [
                ['Referencia', 'Marca', 'EAN13', 'Descripción', 'Familia', 'Subfamilia', 'Unidad', 'Dim (L×A×P)', 'Precio 1+', 'Precio 10+'],
                ['R-1', 'Acme', '1234', 'Tornillo', 'Tornillería', 'Tornillos', 'unidad', '10×5×5', 0.25, 0.20],
            ],
            'Impuestos' => [
                ['Referencia', 'Pais', 'Unidad', 'Tasa'],
                ['R-1', 'ES', 'unidad', 0.21],
            ],
        ]);

        $this->artisan('tariffs:import', ['file' => $file, '--supplier' => 'acme'])
            ->expectsOutputToContain('Created: 1, Updated: 0')
            ->assertSuccessful();

        $this->assertSame(1, Product::count());
        $product = Product::with(['brand', 'priceTiers', 'taxes.country'])->first();
        $this->assertSame('Acme', $product->brand->name);
        $this->assertSame(2, $product->priceTiers->count());
        $this->assertSame(1, $product->taxes->count());
        $this->assertSame('ES', $product->taxes->first()->country->code);

        @unlink($file);
    }

    public function test_reimport_is_idempotent(): void
    {
        $file = ExcelBuilder::write([
            'Productos' => [
                ['Referencia', 'Marca', 'Familia', 'Precio 1+'],
                ['R-1', 'Acme', 'F', 1.00],
            ],
        ]);

        $this->artisan('tariffs:import', ['file' => $file, '--supplier' => 'acme'])->assertSuccessful();
        $this->artisan('tariffs:import', ['file' => $file, '--supplier' => 'acme'])
            ->expectsOutputToContain('Created: 0, Updated: 1')
            ->assertSuccessful();

        $this->assertSame(1, Product::count());
        $this->assertSame(1, PriceTier::count(), 'price tiers must not duplicate on re-import');
        $this->assertSame(0, Tax::count());

        @unlink($file);
    }

    public function test_fails_when_supplier_option_is_missing(): void
    {
        $file = ExcelBuilder::write([
            'Tariff' => [
                ['REF', 'Manufacturer', 'Part Number', 'Qty From', 'Price EUR'],
                ['G-1', 'FastenPro', 'EAN1', 1, 2.0],
            ],
        ]);

        $this->artisan('tariffs:import', ['file' => $file])
            ->expectsOutputToContain('Missing required option --supplier')
            ->assertFailed();

        $this->assertSame(0, Product::count());

        @unlink($file);
    }

    public function test_fails_when_supplier_code_is_unknown(): void
    {
        $file = ExcelBuilder::write([
            'Productos' => [
                ['Referencia', 'Marca', 'Familia', 'Precio 1+'],
                ['R-1', 'Acme', 'F', 1.00],
            ],
        ]);

        $this->artisan('tariffs:import', ['file' => $file, '--supplier' => 'nonexistent'])
            ->expectsOutputToContain("Unknown supplier 'nonexistent'")
            ->assertFailed();

        $this->assertSame(0, Product::count());

        @unlink($file);
    }
}
