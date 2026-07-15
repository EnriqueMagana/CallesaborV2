<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'name', 'description', 'image',
        'price', 'is_customizable', 'max_addons',
        'min_ingredients', 'max_ingredients',
        'sort_order', 'is_active',
    ];

    protected $casts = [
        'price'           => 'decimal:2',
        'is_customizable' => 'boolean',
        'is_active'       => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function addonGroups(): BelongsToMany
    {
        return $this->belongsToMany(AddonGroup::class, 'product_addon_group')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'product_ingredient')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }
}
