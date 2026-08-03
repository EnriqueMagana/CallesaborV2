<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliverySettlement extends Model
{
    protected $fillable = [
        'cash_register_id',
        'driver_id',
        'completed_by',
        'orders_count',
        'sales_total',
        'expected_cash',
        'declared_cash',
        'difference',
        'transfer_total',
        'card_total',
        'notes',
        'completed_at',
    ];

    protected $casts = [
        'sales_total' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'declared_cash' => 'decimal:2',
        'difference' => 'decimal:2',
        'transfer_total' => 'decimal:2',
        'card_total' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id')->withTrashed();
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by')->withTrashed();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DeliveryAssignment::class);
    }
}
