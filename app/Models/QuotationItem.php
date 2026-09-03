<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuotationItem extends Model
{
    protected $fillable = [
        'quotation_id', 'product_id', 'promotion_id', 'discount_id', 'product_name',
        'product_price', 'quantity', 'subtotal', 'promotion_discount', 'discount_amount', 'notes',
        'promotion_selections', 'promotion_rule_snapshot', 'discount_snapshot',
    ];

    protected $casts = [
        'product_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'promotion_discount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'promotion_selections' => 'array',
        'promotion_rule_snapshot' => 'array',
        'discount_snapshot' => 'array',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(QuotationItemAddon::class);
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(QuotationItemIngredient::class);
    }
}
