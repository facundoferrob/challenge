<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListProductsRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;

class ProductController extends Controller
{
    public function index(ListProductsRequest $request)
    {
        $perPage = $request->integer('per_page', 50);

        $query = Product::query()
            ->with(['supplier', 'brand', 'family.parent', 'priceTiers', 'taxes.country']);

        if ($brand = $request->validated('brand')) {
            $query->whereHas('brand', fn ($q) => $q->where('name', $brand));
        }
        if ($reference = $request->validated('reference')) {
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
            ->first();

        if (! $product) {
            abort(404, 'Product not found');
        }

        return new ProductResource($product);
    }
}
