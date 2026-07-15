<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = [
        'cash_register_id',
        'created_by',
        'amount',
        'category',
        'description',
        'payment_method',
        'notes',
    ];

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getCategoryLabelAttribute(): string
    {
        return match($this->category) {
            'insumos'   => 'Insumos',
            'operativo' => 'Operativo',
            'personal'  => 'Personal',
            default     => 'Otro',
        };
    }
}
