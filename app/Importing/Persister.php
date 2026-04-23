<?php

namespace App\Importing;

use App\Importing\DTO\SupplierProductDTO;
use App\Models\Brand;
use App\Models\Country;
use App\Models\Family;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

/**
 * Persists a {@see SupplierProductDTO} into the relational model in an
 * idempotent way
 *
 * Stateless by design — the caller aggregates run-level stats via
 * {@see ImportReport}. This keeps persistence and reporting as separate
 * responsibilities (SRP).
 */
class Persister
{
    public function persist(SupplierProductDTO $dto, string $supplierName): PersistResult
    {
        return DB::transaction(function () use ($dto, $supplierName): PersistResult {
            $supplier = Supplier::firstOrCreate(
                ['code' => $dto->supplierCode],
                ['name' => $supplierName],
            );
            $brand = Brand::firstOrCreate(['name' => trim($dto->brand)]);
            $family = $this->resolveFamily($dto->family, $dto->subfamily);

            $existed = Product::where('supplier_id', $supplier->id)
                ->where('reference', $dto->reference)
                ->exists();

            /** @var Product $product */
            $product = Product::updateOrCreate(
                ['supplier_id' => $supplier->id, 'reference' => $dto->reference],
                [
                    'brand_id'    => $brand->id,
                    'family_id'   => $family?->id,
                    'ean'         => $dto->ean,
                    'description' => $dto->description,
                    'unit'        => $dto->unit,
                    'dimensions'  => $dto->dimensions ?: null,
                ],
            );

            $this->syncPriceTiers($product, $dto);
            $this->syncTaxes($product, $dto);

            return $existed ? PersistResult::Updated : PersistResult::Created;
        });
    }

    private function syncPriceTiers(Product $product, SupplierProductDTO $dto): void
    {
        $product->priceTiers()->delete();
        $product->priceTiers()->createMany($dto->priceTiers);
    }

    private function syncTaxes(Product $product, SupplierProductDTO $dto): void
    {
        $product->taxes()->delete();
        foreach ($dto->taxes as $tax) {
            $country = Country::firstOrCreate(
                ['code' => strtoupper($tax['country_code'])],
                ['name' => strtoupper($tax['country_code'])],
            );
            $product->taxes()->create([
                'country_id' => $country->id,
                'unit'       => $tax['unit'] ?? null,
                'type'       => $tax['type'] ?? 'vat',
                'rate'       => $tax['rate'] ?? null,
                'amount'     => $tax['amount'] ?? null,
            ]);
        }
    }

    private function resolveFamily(?string $family, ?string $subfamily): ?Family
    {
        if ($family === null) {
            return null;
        }

        $parent = Family::firstOrCreate(['name' => trim($family), 'parent_id' => null]);
        if ($subfamily === null) {
            return $parent;
        }

        return Family::firstOrCreate([
            'name'      => trim($subfamily),
            'parent_id' => $parent->id,
        ]);
    }
}
