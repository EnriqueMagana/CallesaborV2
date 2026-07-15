<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'cash_register_id', 'customer_id', 'mesa_id',
        'customer_name', 'customer_phone', 'customer_address', 'customer_references',
        'served_by', 'type', 'table_identifier', 'delivery_method',
        'status', 'subtotal', 'total', 'notes',
        'cancelled_by', 'cancellation_reason', 'cancelled_at', 'paid_at',
    ];

    protected $casts = [
        'subtotal'      => 'decimal:2',
        'total'         => 'decimal:2',
        'cancelled_at'  => 'datetime',
        'paid_at'       => 'datetime',
    ];

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function mesa(): BelongsTo
    {
        return $this->belongsTo(Mesa::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'served_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->customer_name
            ?? $this->customer?->name
            ?? 'Cliente general';
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pendiente'      => 'warning',
            'en_preparacion' => 'info',
            'pagada'         => 'success',
            'cancelada'      => 'danger',
            default          => 'secondary',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pendiente'      => 'Pendiente',
            'en_preparacion' => 'En preparación',
            'pagada'         => 'Pagada',
            'cancelada'      => 'Cancelada',
            default          => $this->status,
        };
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'mesa'       => 'Mesa',
            'ventanilla' => 'Ventanilla',
            'delivery'   => 'Delivery',
            'pick_up'    => 'Pick-up',
            default      => $this->type,
        };
    }

    public function getTypeIconAttribute(): string
    {
        return match ($this->type) {
            'mesa'       => 'bx-table',
            'ventanilla' => 'bx-store',
            'delivery'   => 'bx-cycling',
            'pick_up'    => 'bx-package',
            default      => 'bx-receipt',
        };
    }

    public function getDeliveryMethodLabelAttribute(): string
    {
        return match ($this->delivery_method) {
            'contra_entrega' => 'Contra entrega',
            'tarjeta'        => 'Tarjeta',
            'transferencia'  => 'Transferencia',
            default          => '—',
        };
    }
}
