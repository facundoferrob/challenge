<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = ['code', 'name', 'default_tax_rate'];

    protected $casts = [
        'default_tax_rate' => 'decimal:4',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
