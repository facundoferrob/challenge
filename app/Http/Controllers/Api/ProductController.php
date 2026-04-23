<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min((int) $request->query('per_page', 50), 200);
        $perPage = max($perPage, 1);

        $query = Product::query()
            ->with(['supplier', 'brand', 'family.parent', 'priceTiers', 'taxes.country']);

        if ($brand = $request->query('brand')) {
            $query->whereHas('brand', fn ($q) => $q->where('name', $brand));
        }
        if ($reference = $request->query('reference')) {
            $query->where('reference', $reference);
        }

        return ProductResource::collection($query->orderBy('id')->paginate($perPage));
    }

    public function showByBrandAndReference(string $brand, string $reference)
    {
        $product = Product::query()
            ->with(['supplier', 'brand', 'family.parent', 'priceTiers', 'taxes.country'])
            ->whereHas('brand', fn ($q) => $q->where('name', $brand))
            ->where('reference', $reference)
            ->firstOrFail();

        return new ProductResource($product);
    }
}
