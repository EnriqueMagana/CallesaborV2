<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItemIngredient extends Model
{
    protected $fillable = ['quotation_item_id', 'ingredient_id', 'ingredient_name', 'extra_price', 'quantity'];

    protected $casts = ['extra_price' => 'decimal:2'];

    public function quotationItem(): BelongsTo
    {
        return $this->belongsTo(QuotationItem::class);
    }
}
