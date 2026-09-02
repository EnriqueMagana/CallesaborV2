<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $fillable = [
        'cash_register_id', 'kiosk_terminal_id', 'public_token', 'customer_id', 'mesa_id',
        'mesa_service_id',
        'folio',
        'customer_name', 'customer_phone', 'customer_address', 'customer_neighborhood', 'customer_references',
        'served_by', 'type', 'source', 'fulfillment', 'table_identifier', 'delivery_method',
        'delivery_flow_mode', 'accounted_at', 'status', 'subtotal', 'total', 'notes',
        'cancelled_by', 'cancellation_reason', 'cancelled_at', 'paid_at',
    ];

    protected $casts = [
        'folio' => 'integer',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
        'cancelled_at' => 'datetime',
        'paid_at' => 'datetime',
        'accounted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            $order->public_token ??= Str::random(64);

            if (! $order->cash_register_id) {
                return;
            }

            DB::transaction(function () use ($order): void {
                $register = CashRegister::query()->lockForUpdate()->findOrFail($order->cash_register_id);
                $next = max(
                    (int) ($register->next_order_folio ?: 1),
                    ((int) static::query()->where('cash_register_id', $register->id)->max('folio')) + 1,
                );

                if ($order->folio) {
                    $register->updateQuietly(['next_order_folio' => max($next, ((int) $order->folio) + 1)]);

                    return;
                }

                $order->folio = $next;
                $register->updateQuietly(['next_order_folio' => $next + 1]);
            });
        });
    }

    public function ensurePublicToken(): string
    {
        if (! $this->public_token) {
            $this->forceFill(['public_token' => Str::random(64)])->saveQuietly();
        }

        return $this->public_token;
    }

    public function isFinalizedForAccounting(): bool
    {
        if ($this->status === 'cancelada') {
            return false;
        }

        return $this->status === 'pagada'
            || ($this->type === 'delivery'
                && $this->delivery_flow_mode === 'manual'
                && $this->accounted_at !== null);
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

    public function changeRequests(): HasMany
    {
        return $this->hasMany(OrderChangeRequest::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(OrderRefund::class);
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
        return 'ORD-'.str_pad((string) ($this->folio ?: $this->id), 3, '0', STR_PAD_LEFT);
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
        return $query
            ->where('status', '!=', 'cancelada')
            ->where(fn (Builder $accounting) => $accounting
                ->where('status', 'pagada')
                ->orWhere(fn (Builder $manual) => $manual
                    ->where('type', 'delivery')
                    ->where('delivery_flow_mode', 'manual')
                    ->whereNotNull('accounted_at')));
    }
}
