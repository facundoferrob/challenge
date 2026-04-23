<?php

namespace App\Importing\Suppliers;

use App\Importing\AbstractSupplierImporter;
use App\Importing\DTO\SupplierProductDTO;
use App\Importing\Support\SpreadsheetReader;

/**
 * Acme tariff format.
 *
 * Sheet 1 columns: Referencia | Marca | EAN13 | Descripción | Familia |
 *                  Subfamilia | Unidad | Dim (L×A×P) | Precio 1+ | Precio 10+ | Precio 100+
 * Sheet 2 ("Impuestos"): Referencia | Pais | Unidad | Tasa
 *
 * Column → meaning is declared explicitly below (no regex inference).
 */
class AcmeImporter extends AbstractSupplierImporter
{
    /** Price tier column → the min_quantity it represents. */
    private const TIER_COLUMNS = [
        'Precio 1+'   => 1,
        'Precio 10+'  => 10,
        'Precio 100+' => 100,
    ];

    /** Name of the sheet that holds the taxes, and its column mapping. */
    private const TAX_SHEET = 'Impuestos';

    private const TAX_COLUMNS = [
        'reference' => 'Referencia',
        'country'   => 'Pais',
        'unit'      => 'Unidad',
        'rate'      => 'Tasa',
    ];

    public function code(): string
    {
        return 'acme';
    }

    public function name(): string
    {
        return 'Acme Distribuciones';
    }

    public function parse(string $filepath): iterable
    {
        $taxesByRef = $this->readTaxes($filepath);

        foreach (SpreadsheetReader::readAssoc($filepath) as $row) {
            $reference = $this->cleanString($row['Referencia'] ?? null);
            $brand = $this->cleanString($row['Marca'] ?? null);
            if ($reference === null || $brand === null) {
                continue;
            }

            yield new SupplierProductDTO(
                supplierCode: $this->code(),
                reference: $reference,
                brand: $brand,
                ean: $this->cleanString($row['EAN13'] ?? null),
                description: $this->cleanString($row['Descripción'] ?? $row['Descripcion'] ?? null),
                family: $this->cleanString($row['Familia'] ?? null),
                subfamily: $this->cleanString($row['Subfamilia'] ?? null),
                unit: $this->cleanString($row['Unidad'] ?? null),
                dimensions: $this->parseDimensions(
                    $this->cleanString($row['Dim (L×A×P)'] ?? $row['Dim (LxAxP)'] ?? null)
                ),
                priceTiers: $this->extractTiers($row),
                taxes: $taxesByRef[$reference] ?? [],
            );
        }
    }

    /**
     * @param  array<string,mixed>  $row
     * @return array<int, array{min_quantity: int, price: float, currency: string}>
     */
    private function extractTiers(array $row): array
    {
        $tiers = [];
        foreach (self::TIER_COLUMNS as $header => $minQuantity) {
            $value = $row[$header] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $tiers[] = [
                'min_quantity' => $minQuantity,
                'price'        => (float) $value,
                'currency'     => 'EUR',
            ];
        }

        return $tiers;
    }

    /**
     * Read the "Impuestos" sheet if present. `readAssoc()` returns an empty
     * array when the sheet doesn't exist, so we don't need a pre-check.
     *
     * @return array<string, array<int, array{country_code: string, rate: ?float, unit: ?string, type: string}>>
     */
    private function readTaxes(string $filepath): array
    {
        $out = [];
        foreach (SpreadsheetReader::readAssoc($filepath, self::TAX_SHEET) as $row) {
            $ref = $this->cleanString($row[self::TAX_COLUMNS['reference']] ?? null);
            $country = $this->cleanString(
                $row[self::TAX_COLUMNS['country']] ?? $row['País'] ?? null,
            );
            if ($ref === null || $country === null) {
                continue;
            }

            $rateRaw = $row[self::TAX_COLUMNS['rate']] ?? null;
            $out[$ref] ??= [];
            $out[$ref][] = [
                'country_code' => strtoupper($country),
                'rate'         => ($rateRaw === null || $rateRaw === '') ? null : (float) $rateRaw,
                'unit'         => $this->cleanString($row[self::TAX_COLUMNS['unit']] ?? null),
                'type'         => 'vat',
            ];
        }

        return $out;
    }

    /**
     * @return array<string, float|string>
     */
    private function parseDimensions(?string $raw): array
    {
        if ($raw === null) {
            return [];
        }
        $parts = preg_split('/\s*[x×]\s*/iu', $raw);
        if ($parts === false || count($parts) < 2) {
            return ['raw' => $raw];
        }

        $keys = ['length', 'width', 'depth'];
        $out = [];
        foreach ($parts as $i => $p) {
            if (! isset($keys[$i])) {
                break;
            }
            $out[$keys[$i]] = (float) $p;
        }

        return $out;
    }
}
