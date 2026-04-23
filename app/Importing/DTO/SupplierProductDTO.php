<?php

namespace App\Importing\DTO;

/**
 * One product as an importer sees it — supplier metadata + product fields +
 * a list of price tiers + a list of country/unit taxes.

 *
 * Expected shapes:
 * - `priceTiers[]`: `['min_quantity' => int, 'price' => float, 'currency' => string]`
 * - `taxes[]`:      `['country_code' => string, 'rate' => ?float, 'amount' => ?float, 'unit' => ?string, 'type' => string]`
 */
final class SupplierProductDTO
{
    /**
     * @param  array<int, array{min_quantity: int, price: float, currency: string}>  $priceTiers
     * @param  array<int, array{country_code: string, rate?: ?float, amount?: ?float, unit?: ?string, type?: string}>  $taxes
     * @param  array<string, mixed>  $dimensions
     */
    public function __construct(
        public readonly string $supplierCode,
        public readonly string $reference,
        public readonly string $brand,
        public readonly ?string $ean = null,
        public readonly ?string $description = null,
        public readonly ?string $family = null,
        public readonly ?string $subfamily = null,
        public readonly ?string $unit = null,
        public readonly array $dimensions = [],
        public readonly array $priceTiers = [],
        public readonly array $taxes = [],
    ) {}
}
