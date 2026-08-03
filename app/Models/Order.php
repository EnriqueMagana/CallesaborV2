<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'cash_register_id', 'kiosk_terminal_id', 'public_token', 'customer_id', 'mesa_id',
        'mesa_service_id',
        'folio',
        'customer_name', 'customer_phone', 'customer_address', 'customer_references',
        'served_by', 'type', 'source', 'fulfillment', 'table_identifier', 'delivery_method',
        'status', 'subtotal', 'total', 'notes',
        'cancelled_by', 'cancellation_reason', 'cancelled_at', 'paid_at',
    ];

    protected $casts = [
        'folio' => 'integer',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
        'cancelled_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            if ($order->folio || ! $order->cash_register_id) {
                return;
            }

            $order->folio = ((int) static::query()
                ->where('cash_register_id', $order->cash_register_id)
                ->max('folio')) + 1;
        });
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function kioskTerminal(): BelongsTo
    {
        return $this->belongsTo(KioskTerminal::class);
    }

    public function mesa(): BelongsTo
    {
        return $this->belongsTo(Mesa::class);
    }

    public function mesaService(): BelongsTo
    {
        return $this->belongsTo(MesaService::class);
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

    public function deliveryAssignment(): HasOne
    {
        return $this->hasOne(DeliveryAssignment::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->customer_name
            ?? $this->customer?->name
            ?? 'Cliente general';
    }

    public function getDisplayFolioAttribute(): string
    {
        return (string) ($this->folio ?: $this->id);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pendiente' => 'warning',
            'en_preparacion' => 'info',
            'lista' => 'success',
            'en_reparto' => 'primary',
            'entregada' => 'success',
            'pagada' => 'success',
            'cancelada' => 'danger',
            default => 'secondary',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pendiente' => 'Pendiente',
            'en_preparacion' => 'En preparación',
            'lista' => 'Listo',
            'en_reparto' => 'Recogido para entrega',
            'entregada' => 'Entregado',
            'pagada' => 'Pagada',
            'cancelada' => 'Cancelada',
            default => $this->status,
        };
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'mesa' => 'Mesa',
            'ventanilla' => 'Ventanilla',
            'delivery' => 'Delivery',
            'pick_up' => 'Pick-up',
            default => $this->type,
        };
    }

    public function getTypeIconAttribute(): string
    {
        return match ($this->type) {
            'mesa' => 'bx-table',
            'ventanilla' => 'bx-store',
            'delivery' => 'bx-cycling',
            'pick_up' => 'bx-package',
            default => 'bx-receipt',
        };
    }

    public function getDeliveryMethodLabelAttribute(): string
    {
        return match ($this->delivery_method) {
            'contra_entrega' => 'Contra entrega',
            'tarjeta' => 'Tarjeta',
            'transferencia' => 'Transferencia',
            default => '—',
        };
    }

    public function getOriginLabelAttribute(): string
    {
        if ($this->source === 'kiosk') {
            return $this->kioskTerminal?->name
                ? 'Kiosco · '.$this->kioskTerminal->name
                : 'Kiosco';
        }

        return 'Ventanilla · POS';
    }

    public function getAmountToCollectAttribute(): float
    {
        if ($this->delivery_method !== 'contra_entrega') {
            return 0;
        }

        return max(0, round((float) $this->total - (float) $this->payments->sum('amount'), 2));
    }

    public function getDeliveryPaymentLabelAttribute(): string
    {
        if ($this->delivery_method === 'contra_entrega') {
            return $this->amount_to_collect > 0
                ? 'Cobrar $'.number_format($this->amount_to_collect, 2).' en efectivo'
                : 'Efectivo cobrado';
        }

        if ($this->payments->contains('method', 'transferencia') || $this->delivery_method === 'transferencia') {
            return 'Transferencia confirmada';
        }

        if ($this->payments->contains('method', 'tarjeta') || $this->delivery_method === 'tarjeta') {
            return 'Pagado con tarjeta';
        }

        return $this->payments->isNotEmpty() ? 'Pagado en sucursal' : 'Pago por confirmar';
    }

    public function scopeFinalizedForAccounting(Builder $query): Builder
    {
        return $query->where('status', 'pagada');
    }
}
