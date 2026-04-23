<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'supplier_id',
        'brand_id',
        'family_id',
        'reference',
        'ean',
        'description',
        'unit',
        'dimensions',
    ];

    protected $casts = [
        'dimensions' => 'array',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function priceTiers(): HasMany
    {
        return $this->hasMany(PriceTier::class)->orderBy('min_quantity');
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(Tax::class);
    }
}
