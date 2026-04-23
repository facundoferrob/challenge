<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'brand' => $this->brand?->name,
            'supplier' => $this->supplier?->code,
            'ean' => $this->ean,
            'description' => $this->description,
            'family' => $this->family?->parent?->name ?? $this->family?->name,
            'subfamily' => $this->family?->parent_id ? $this->family?->name : null,
            'unit' => $this->unit,
            'dimensions' => $this->dimensions,
            'price_tiers' => $this->priceTiers->map(fn ($t) => [
                'min_quantity' => $t->min_quantity,
                'price' => (float) $t->price,
                'currency' => $t->currency,
            ])->values(),
            'taxes' => $this->taxes->map(fn ($tax) => [
                'country' => $tax->country?->code,
                'unit' => $tax->unit,
                'type' => $tax->type,
                'rate' => $tax->rate !== null ? (float) $tax->rate : null,
                'amount' => $tax->amount !== null ? (float) $tax->amount : null,
            ])->values(),
        ];
    }
}
