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

            $paid = (float) $locked->payments()->sum('amount');
            $remaining = max(0, round((float) $locked->total - $paid, 2));

            if ($remaining > 0) {
                $paymentMethod = match ($locked->delivery_method) {
                    'contra_entrega', 'cash' => 'efectivo',
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
}
