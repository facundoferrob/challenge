<?php

namespace App\Importing\Suppliers;

use App\Importing\AbstractSupplierImporter;
use App\Importing\DTO\SupplierProductDTO;
use App\Importing\Support\SpreadsheetReader;

/**
 * Global Supply tariff format.
 *
 * One row per (product, price tier). Product-level fields (manufacturer,
 * weight, taxes) are repeated across rows of the same REF; we group by REF
 * to consolidate.
 *
 * Columns: REF | Manufacturer | Part Number | Category / Sub |
 *          Peso (kg)          ← unit embedded in header
 *          Qty From | Price EUR
 *          IVA_ES | IVA_FR | IVA_DE  ← tax rate per country as columns
 *
 * Column → meaning is declared explicitly below (no regex inference).
 */
class GlobalSupplyImporter extends AbstractSupplierImporter
{
    private const COLUMN_REFERENCE = 'REF';
    private const COLUMN_BRAND = 'Manufacturer';
    private const COLUMN_EAN = 'Part Number';
    private const COLUMN_CATEGORY = 'Category / Sub';
    private const COLUMN_WEIGHT = 'Peso (kg)';
    private const COLUMN_TIER_QTY = 'Qty From';
    private const COLUMN_TIER_PRICE = 'Price EUR';

    private const WEIGHT_UNIT = 'kg';
    private const CURRENCY = 'EUR';

    /** VAT column header → ISO-2 country code. */
    private const TAX_COLUMNS = [
        'IVA_ES' => 'ES',
        'IVA_FR' => 'FR',
        'IVA_DE' => 'DE',
    ];

    public function code(): string
    {
        return 'global_supply';
    }

    public function name(): string
    {
        return 'Global Supply Co';
    }

    public function parse(string $filepath): iterable
    {
        /** @var array<string, array{base: array<string,mixed>, tiers: array<int, array{min_quantity: int, price: float, currency: string}>}> $grouped */
        $grouped = [];

        foreach (SpreadsheetReader::readAssoc($filepath) as $row) {
            $ref = $this->cleanString($row[self::COLUMN_REFERENCE] ?? null);
            if ($ref === null) {
                continue;
            }

            $grouped[$ref] ??= [
                'base' => $this->extractBase($row, $ref),
                'tiers' => [],
            ];

            $tier = $this->extractTier($row);
            if ($tier !== null) {
                $grouped[$ref]['tiers'][] = $tier;
            }
        }

        foreach ($grouped as $data) {
            $tiers = $data['tiers'];
            usort($tiers, fn (array $a, array $b) => $a['min_quantity'] <=> $b['min_quantity']);

            yield new SupplierProductDTO(
                supplierCode: $this->code(),
                reference: $data['base']['reference'],
                brand: $data['base']['brand'],
                ean: $data['base']['ean'],
                description: null,
                family: $data['base']['family'],
                subfamily: $data['base']['subfamily'],
                unit: $data['base']['unit'],
                dimensions: $data['base']['dimensions'],
                priceTiers: $tiers,
                taxes: $data['base']['taxes'],
            );
        }
    }

    /**
     * Extract the product-level fields from any row of a REF group (all rows
     * of a group should agree on these).
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function extractBase(array $row, string $ref): array
    {
        [$family, $subfamily] = $this->splitCategory($this->cleanString($row[self::COLUMN_CATEGORY] ?? null));

        $weightRaw = $row[self::COLUMN_WEIGHT] ?? null;
        $weight = ($weightRaw === null || $weightRaw === '') ? null : (float) $weightRaw;

        return [
            'reference'  => $ref,
            'brand'      => $this->cleanString($row[self::COLUMN_BRAND] ?? null) ?? 'Unknown',
            'ean'        => $this->cleanString($row[self::COLUMN_EAN] ?? null),
            'family'     => $family,
            'subfamily'  => $subfamily,
            'unit'       => self::WEIGHT_UNIT,
            'dimensions' => $weight !== null
                ? ['weight' => $weight, 'weight_unit' => self::WEIGHT_UNIT]
                : [],
            'taxes'      => $this->extractTaxes($row),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{min_quantity: int, price: float, currency: string}|null
     */
    private function extractTier(array $row): ?array
    {
        $qtyRaw = $row[self::COLUMN_TIER_QTY] ?? null;
        $priceRaw = $row[self::COLUMN_TIER_PRICE] ?? null;
        if ($qtyRaw === null || $qtyRaw === '' || $priceRaw === null || $priceRaw === '') {
            return null;
        }

        return [
            'min_quantity' => (int) $qtyRaw,
            'price'        => (float) $priceRaw,
            'currency'     => self::CURRENCY,
        ];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function splitCategory(?string $raw): array
    {
        if ($raw === null) {
            return [null, null];
        }
        $parts = array_map('trim', explode('/', $raw, 2));

        return [$parts[0] ?? null, $parts[1] ?? null];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, array{country_code: string, rate: float, type: string}>
     */
    private function extractTaxes(array $row): array
    {
        $taxes = [];
        foreach (self::TAX_COLUMNS as $header => $country) {
            $rateRaw = $row[$header] ?? null;
            if ($rateRaw === null || $rateRaw === '') {
                continue;
            }
            $taxes[] = [
                'country_code' => $country,
                'rate'         => (float) $rateRaw,
                'type'         => 'vat',
            ];
        }

        return $taxes;
    }
}
