<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPayment extends Model
{
    protected $fillable = [
        'order_id', 'method', 'amount',
        'received_amount', 'change_amount', 'card_last4', 'transfer_reference',
    ];

    protected $casts = [
        'amount'          => 'decimal:2',
        'received_amount' => 'decimal:2',
        'change_amount'   => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function getMethodLabelAttribute(): string
    {
        return match ($this->method) {
            'efectivo'       => 'Efectivo',
            'tarjeta'        => 'Tarjeta',
            'transferencia'  => 'Transferencia',
            'contra_entrega' => 'Contra entrega',
            default          => $this->method,
        };
    }

    public function getMethodIconAttribute(): string
    {
        return match ($this->method) {
            'efectivo'       => 'bx-money',
            'tarjeta'        => 'bx-credit-card',
            'transferencia'  => 'bx-transfer',
            'contra_entrega' => 'bx-cycling',
            default          => 'bx-credit-card',
        };
    }
}
