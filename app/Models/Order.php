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
        'cash_register_id', 'kiosk_terminal_id', 'public_token', 'customer_id', 'discount_beneficiary_user_id', 'mesa_id',
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

    public function discountBeneficiary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discount_beneficiary_user_id');
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

    public function dataChangeAudits(): HasMany
    {
        return $this->hasMany(OrderDataChangeAudit::class);
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

    /**
     * Dinero que realmente entró por la orden, sin descontar devoluciones.
     * Excluye el cobro contra entrega que el repartidor todavía trae en la calle.
     */
    public function getPaidAmountAttribute(): float
    {
        return round((float) $this->payments->where('is_provisional', false)->sum('amount'), 2);
    }

    /**
     * Efectivo contra entrega que el repartidor cobrará y entregará en el corte.
     */
    public function getProvisionalAmountAttribute(): float
    {
        return round((float) $this->payments->where('is_provisional', true)->sum('amount'), 2);
    }

    public function getRefundedAmountAttribute(): float
    {
        return round((float) $this->refunds->sum('amount'), 2);
    }

    /**
     * Dinero real que la caja conserva por esta orden: pagos menos devoluciones.
     */
    public function getNetPaidAmountAttribute(): float
    {
        return round($this->paid_amount - $this->refunded_amount, 2);
    }

    /**
     * Lo que falta por cobrar de la orden, lo cobre quien lo cobre: el cajero
     * en el POS o el repartidor contra entrega.
     */
    public function getBalanceDueAttribute(): float
    {
        if ($this->status === 'cancelada') {
            return 0;
        }

        return max(0, round((float) $this->total - $this->net_paid_amount, 2));
    }

    /**
     * Saldo que nadie tiene asignado cobrar. Una orden sana siempre lo tiene
     * en cero; si no, es un descuadre real.
     */
    public function getUncoveredAmountAttribute(): float
    {
        return max(0, round($this->balance_due - $this->provisional_amount, 2));
    }

    public function getIsFullyPaidAttribute(): bool
    {
        return $this->balance_due <= 0.009;
    }

    /**
     * La orden ya recibió dinero real pero no cubre el total vigente: se
     * agregaron productos después de cobrar y el cajero debe cobrar el resto.
     */
    public function getHasPartialPaymentAttribute(): bool
    {
        return $this->net_paid_amount > 0.009 && $this->uncovered_amount > 0.009;
    }

    /**
     * El cobro de esta orden lo hace el repartidor, no la caja.
     */
    public function getIsCollectedOnDeliveryAttribute(): bool
    {
        return $this->type === 'delivery' && $this->delivery_method === 'contra_entrega';
    }
    public function getAmountToCollectAttribute(): float
    {
        if ($this->delivery_method !== 'contra_entrega') {
            return 0;
        }

        return $this->balance_due;
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

    /**
     * Condición SQL de "recibió algo y no cubre su total". Es la misma regla que
     * `balance_due`, expresada en SQL para filtrar y contar en la base sin
     * cargar órdenes. La usan el panel de pendientes y el contador del POS.
     */
    public static function pendingBalanceSql(string $ordersTable = 'orders'): string
    {
        $payments = (new OrderPayment)->getTable();
        $refunds = (new OrderRefund)->getTable();
        $realPaid = "coalesce((select sum(rp.amount) from {$payments} rp where rp.order_id = {$ordersTable}.id and rp.is_provisional = 0), 0)";
        $refunded = "coalesce((select sum(rf.amount) from {$refunds} rf where rf.order_id = {$ordersTable}.id), 0)";

        return "{$ordersTable}.status <> 'cancelada'"
            ." and exists (select 1 from {$payments} ap where ap.order_id = {$ordersTable}.id)"
            ." and {$ordersTable}.total - ({$realPaid} - {$refunded}) > 0.009";
    }

    public function scopeWithPendingBalance(Builder $query): Builder
    {
        return $query->whereRaw(static::pendingBalanceSql($this->getTable()));
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
