<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceTier extends Model
{
    protected $table = 'product_price_tiers';

    protected $fillable = [
        'product_id',
        'min_quantity',
        'price',
        'currency',
    ];

    protected $casts = [
        'min_quantity' => 'integer',
        'price' => 'decimal:4',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
