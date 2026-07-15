<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuotationItem extends Model
{
    protected $fillable = [
        'quotation_id', 'product_id', 'product_name',
        'product_price', 'quantity', 'subtotal', 'notes',
    ];

    protected $casts = [
        'product_price' => 'decimal:2',
        'subtotal'      => 'decimal:2',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
