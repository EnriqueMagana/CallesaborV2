<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashRegisterCut extends Model
{
    protected $fillable = [
        'cash_register_id', 'generated_by', 'folio',
        'v_efectivo', 'm_efectivo', 'd_efectivo',
        'v_tarjeta', 'v_transfer',
        'm_tarjeta', 'm_transfer',
        'd_tarjeta', 'd_transfer',
        'initial_amount', 'total_cash_in', 'total_cash_income', 'total_expenses_cash',
        'expected_cash', 'declared_cash', 'difference',
        'cut_data', 'generated_at',
    ];

    protected $casts = [
        'v_efectivo' => 'decimal:2', 'm_efectivo' => 'decimal:2', 'd_efectivo' => 'decimal:2',
        'v_tarjeta' => 'decimal:2', 'v_transfer' => 'decimal:2',
        'm_tarjeta' => 'decimal:2', 'm_transfer' => 'decimal:2',
        'd_tarjeta' => 'decimal:2', 'd_transfer' => 'decimal:2',
        'initial_amount' => 'decimal:2',
        'total_cash_in' => 'decimal:2',
        'total_cash_income' => 'decimal:2',
        'total_expenses_cash' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'declared_cash' => 'decimal:2',
        'difference' => 'decimal:2',
        'cut_data' => 'array',
        'generated_at' => 'datetime',
    ];

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
