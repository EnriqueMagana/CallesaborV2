<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderRefund extends Model
{
    protected $fillable = [
        'order_id', 'order_change_request_id', 'cash_register_id', 'processed_by',
        'cash_movement_id', 'type', 'amount', 'allocations', 'external_reference',
        'inventory_disposition', 'status', 'reason', 'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'allocations' => 'array',
        'processed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(OrderChangeRequest::class, 'order_change_request_id');
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function cashMovement(): BelongsTo
    {
        return $this->belongsTo(CashMovement::class);
    }
}
