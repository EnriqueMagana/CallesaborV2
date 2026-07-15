<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItemAddon extends Model
{
    protected $fillable = ['quotation_item_id', 'addon_id', 'addon_name', 'extra_price', 'quantity'];

    protected $casts = ['extra_price' => 'decimal:2'];

    public function quotationItem(): BelongsTo
    {
        return $this->belongsTo(QuotationItem::class);
    }
}
