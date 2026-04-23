<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tax extends Model
{
    protected $table = 'product_taxes';

    protected $fillable = [
        'product_id',
        'country_id',
        'unit',
        'type',
        'rate',
        'amount',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'amount' => 'decimal:4',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
