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

    public function test_show_returns_clean_404_for_unknown_product(): void
    {
        $this->seedProduct('Acme', 'R-1');

        $this->getJson('/api/products/Acme/NOPE')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Product not found']);

        $this->getJson('/api/products/UnknownBrand/R-1')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Product not found']);
    }

    public function test_unknown_api_route_returns_clean_404(): void
    {
        $this->getJson('/api/no-existe-esta-ruta')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Resource not found']);
    }

    public function test_errors_render_as_json_for_api_routes(): void
    {
        // Sin pasar Accept: application/json (cliente "estilo browser").
        $res = $this->get('/api/products/NoExiste/X');

        $res->assertNotFound();
        // Lo crítico: que sea JSON, no HTML.
        $res->assertHeader('Content-Type', 'application/json');
        $this->assertJson($res->getContent());
    }

    public function test_validation_rejects_non_integer_per_page(): void
    {
        $this->getJson('/api/products?per_page=abc')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_validation_rejects_per_page_out_of_range(): void
    {
        $this->getJson('/api/products?per_page=0')->assertStatus(422);
        $this->getJson('/api/products?per_page=-5')->assertStatus(422);
        $this->getJson('/api/products?per_page=999')->assertStatus(422);
    }

    public function test_validation_rejects_array_brand(): void
    {
        $this->getJson('/api/products?brand[]=Acme')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['brand']);
    }

    public function test_validation_rejects_invalid_page(): void
    {
        $this->getJson('/api/products?page=banana')->assertStatus(422);
        $this->getJson('/api/products?page=-1')->assertStatus(422);
        $this->getJson('/api/products?page=0')->assertStatus(422);
    }
}
