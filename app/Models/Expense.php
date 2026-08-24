<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class Expense extends CashMovement
{
    protected static function booted(): void
    {
        static::addGlobalScope('expense', fn (Builder $query) => $query->expense());
        static::creating(function (Expense $expense): void {
            $expense->type = 'expense';
        });
    }
}
