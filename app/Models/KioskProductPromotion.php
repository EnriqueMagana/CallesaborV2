<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KioskProductPromotion extends Model
{
    protected $fillable = [
        'kiosk_terminal_id',
        'product_id',
        'promotional_price',
        'label',
        'sort_order',
    ];

    protected $casts = [
        'promotional_price' => 'decimal:2',
    ];

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(KioskTerminal::class, 'kiosk_terminal_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
