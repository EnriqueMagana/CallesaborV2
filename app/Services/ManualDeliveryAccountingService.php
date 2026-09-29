<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManualDeliveryAccountingService
{
    public function account(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->type !== 'delivery' || $locked->status === 'cancelada') {
                throw ValidationException::withMessages([
                    'delivery' => 'Sólo se pueden contabilizar pedidos delivery activos.',
                ]);
            }

            $locked->forceFill(['delivery_flow_mode' => 'manual'])->save();

            if ($locked->accounted_at) {
                return $locked->load('payments');
            }

            // Contra entrega: el repartidor todavía no cobra. Se registra como
            // provisión y se mantiene al día con el total de la orden.
            if ($locked->delivery_method === 'contra_entrega') {
                $locked->forceFill(['accounted_at' => now()])->save();
                $this->syncProvision($locked);

                return $locked->load('payments');
            }

            $paid = (float) $locked->payments()->sum('amount');
            $remaining = max(0, round((float) $locked->total - $paid, 2));

            if ($remaining > 0) {
                $paymentMethod = match ($locked->delivery_method) {
                    'cash' => 'efectivo',
                    'tarjeta', 'card' => 'tarjeta',
                    'transferencia', 'transfer' => 'transferencia',
                    default => throw ValidationException::withMessages([
                        'delivery' => 'Define el método de pago antes de activar la gestión manual.',
                    ]),
                };

                OrderPayment::create([
                    'order_id' => $locked->id,
                    'method' => $paymentMethod,
                    'amount' => $remaining,
                    'received_amount' => $paymentMethod === 'efectivo' ? $remaining : null,
                    'change_amount' => $paymentMethod === 'efectivo' ? 0 : null,
                ]);
            }

            $locked->forceFill(['accounted_at' => now()])->save();

            return $locked->load('payments');
        });
    }

    /**
     * Mantiene una entrega manual sincronizada con su total vigente.
     * Contra entrega ajusta la provisión del repartidor; los demás métodos
     * registran automáticamente cualquier incremento con el método original.
     */
    public function syncProvision(Order $order): void
    {
        $order->refresh()->load(['payments', 'refunds']);

        if (! $this->tracksManualCollection($order)) {
            return;
        }

        if ($order->delivery_method !== 'contra_entrega') {
            if ($order->status === 'cancelada') {
                return;
            }

            $needed = max(0, round((float) $order->total - $order->net_paid_amount, 2));
            if ($needed <= 0.009) {
                return;
            }

            $paymentMethod = match ($order->delivery_method) {
                'cash' => 'efectivo',
                'tarjeta', 'card' => 'tarjeta',
                'transferencia', 'transfer' => 'transferencia',
                default => null,
            };

            if ($paymentMethod === null) {
                return;
            }

            OrderPayment::create([
                'order_id' => $order->id,
                'method' => $paymentMethod,
                'amount' => $needed,
                'received_amount' => $paymentMethod === 'efectivo' ? $needed : null,
                'change_amount' => $paymentMethod === 'efectivo' ? 0 : null,
            ]);
            $order->load('payments');

            return;
        }

        $needed = $order->status === 'cancelada'
            ? 0.0
            : max(0, round((float) $order->total - $order->net_paid_amount, 2));

        $order->payments()->where('is_provisional', true)->delete();

        if ($needed > 0.009) {
            OrderPayment::create([
                'order_id' => $order->id,
                'method' => 'efectivo',
                'amount' => $needed,
                'is_provisional' => true,
                'received_amount' => $needed,
                'change_amount' => 0,
            ]);
        }

        $order->load('payments');
    }

    /**
     * El repartidor entregó el efectivo: la provisión pasa a ser dinero real.
     */
    public function settle(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->status === 'cancelada') {
                throw ValidationException::withMessages(['delivery' => 'La orden está cancelada; no hay efectivo que liquidar.']);
            }

            $this->syncProvision($locked);
            $settled = $locked->payments()->where('is_provisional', true)->update(['is_provisional' => false]);

            if ($settled === 0) {
                throw ValidationException::withMessages(['delivery' => 'Este pedido no tiene efectivo pendiente del repartidor.']);
            }

            $locked->forceFill(['status' => 'pagada', 'paid_at' => $locked->paid_at ?? now()])->save();

            return $locked->refresh()->load(['payments', 'refunds']);
        });
    }

    private function tracksManualCollection(Order $order): bool
    {
        return $order->type === 'delivery'
            && $order->delivery_flow_mode === 'manual'
            && $order->accounted_at !== null;
    }
}
