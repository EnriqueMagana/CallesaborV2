<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegister extends Model
{
    protected $fillable = [
        'name', 'opened_by', 'closed_by',
        'initial_amount', 'final_amount',
        'declared_amount', 'difference_amount',
        'opened_at', 'closed_at', 'is_open', 'notes', 'closing_notes',
    ];

    protected $casts = [
        'initial_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'declared_amount' => 'decimal:2',
        'difference_amount' => 'decimal:2',
        'is_open' => 'boolean',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function cuts(): HasMany
    {
        return $this->hasMany(CashRegisterCut::class);
    }

    public function deliverySettlements(): HasMany
    {
        return $this->hasMany(DeliverySettlement::class);
    }
}
