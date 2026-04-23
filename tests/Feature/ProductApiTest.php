<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Country;
use App\Models\Family;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Tax;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    private function seedProduct(string $brand, string $ref, string $countryCode = 'ES'): Product
    {
        $supplier = Supplier::firstOrCreate(['code' => 'test'], ['name' => 'Test']);
        $brandModel = Brand::firstOrCreate(['name' => $brand]);
        $family = Family::firstOrCreate(['name' => 'Fam', 'parent_id' => null]);
        $country = Country::firstOrCreate(['code' => $countryCode], ['name' => $countryCode]);

        $product = Product::create([
            'supplier_id' => $supplier->id,
            'brand_id' => $brandModel->id,
            'family_id' => $family->id,
            'reference' => $ref,
            'ean' => null,
            'description' => null,
            'unit' => 'unit',
            'dimensions' => null,
        ]);

        PriceTier::create([
            'product_id' => $product->id,
            'min_quantity' => 1,
            'price' => 1.00,
            'currency' => 'EUR',
        ]);
        Tax::create([
            'product_id' => $product->id,
            'country_id' => $country->id,
            'type' => 'vat',
            'rate' => 0.21,
        ]);

        return $product;
    }

    public function test_index_filters_by_brand_and_paginates(): void
    {
        $this->seedProduct('Acme', 'R-1');
        $this->seedProduct('Acme', 'R-2');
        $this->seedProduct('Other', 'R-3');

        $res = $this->getJson('/api/products?brand=Acme&per_page=1');

        $res->assertOk();
        $res->assertJsonCount(1, 'data');
        $res->assertJsonPath('meta.total', 2);
        $res->assertJsonPath('data.0.brand', 'Acme');
    }

    public function test_show_by_brand_and_reference_returns_tiers_and_taxes(): void
    {
        $this->seedProduct('Acme', 'R-1');

        $res = $this->getJson('/api/products/Acme/R-1');

        $res->assertOk();
        $res->assertJsonPath('data.reference', 'R-1');
        $res->assertJsonPath('data.brand', 'Acme');
        $res->assertJsonPath('data.price_tiers.0.min_quantity', 1);
        $res->assertJsonPath('data.price_tiers.0.price', 1);
        $res->assertJsonPath('data.taxes.0.country', 'ES');
        $res->assertJsonPath('data.taxes.0.rate', 0.21);
    }

    public function test_show_returns_404_for_unknown_reference(): void
    {
        $this->seedProduct('Acme', 'R-1');

        $this->getJson('/api/products/Acme/NOPE')->assertNotFound();
        $this->getJson('/api/products/UnknownBrand/R-1')->assertNotFound();
    }
}
