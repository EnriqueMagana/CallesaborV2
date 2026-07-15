<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Ingredient extends Model
{
    protected $fillable = [
        'name',
        'description',
        'image',
        'extra_price',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'extra_price' => 'decimal:2',
        'is_active'   => 'boolean',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_ingredient')
            ->withPivot('sort_order');
    }
}
