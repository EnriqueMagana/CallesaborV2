<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    /**
     * The legacy table name is kept to avoid breaking existing expense records.
     */
    protected $table = 'expenses';

    protected $fillable = [
        'cash_register_id',
        'created_by',
        'type',
        'amount',
        'category',
        'description',
        'payment_method',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeIncome(Builder $query): Builder
    {
        return $query->where('type', 'income');
    }

    public function scopeExpense(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where('type', 'expense')->orWhereNull('type');
        });
    }

    public function getCategoryLabelAttribute(): string
    {
        if ($this->type === 'income') {
            return match ($this->category) {
                'fondo' => 'Fondo adicional',
                'devolucion' => 'Devolución recibida',
                'otro_ingreso' => 'Otro ingreso',
                default => 'Ingreso de caja',
            };
        }

        return match ($this->category) {
            'insumos' => 'Insumos',
            'operativo' => 'Operativo',
            'personal' => 'Personal',
            default => 'Otro',
        };
    }
}
