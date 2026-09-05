<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderDataChangeAudit;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderOperationalDataService
{
    public function update(Order $order, int $cashRegisterId, array $data, ?int $actorId): Order
    {
        return DB::transaction(function () use ($order, $cashRegisterId, $data, $actorId): Order {
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->where('cash_register_id', $cashRegisterId)
                ->where('status', '!=', 'cancelada')
                ->lockForUpdate()
                ->first();

            if (! $lockedOrder) {
                throw ValidationException::withMessages([
                    'orderDataOrderId' => 'La orden ya no pertenece a la caja abierta o fue cancelada.',
                ]);
            }

            $payments = $lockedOrder->payments()->lockForUpdate()->orderBy('id')->get();
            $submittedPayments = collect($data['payments'] ?? [])->keyBy(fn (array $payment) => (int) $payment['id']);

            if ($payments->pluck('id')->all() !== $submittedPayments->keys()->sort()->values()->all()) {
                throw ValidationException::withMessages([
                    'orderDataPayments' => 'Los pagos de la orden cambiaron. Vuelve a seleccionarla antes de guardar.',
                ]);
            }

            $before = $this->snapshot($lockedOrder, $payments->all());
            $lockedOrder->fill(Arr::only($data, [
                'customer_name',
                'customer_phone',
                'customer_address',
                'customer_neighborhood',
                'customer_references',
            ]));

            if ($lockedOrder->type === 'delivery' && $payments->isEmpty()) {
                $lockedOrder->delivery_method = $data['delivery_method'];
            }

            $lockedOrder->save();

            foreach ($payments as $payment) {
                $submitted = $submittedPayments->get($payment->id);
                $method = $submitted['method'];
                $amount = (float) $payment->amount;

                $payment->update([
                    'method' => $method,
                    'received_amount' => $method === 'efectivo'
                        ? max($amount, (float) ($submitted['received_amount'] ?? $amount))
                        : null,
                    'change_amount' => $method === 'efectivo'
                        ? max(0, round((float) ($submitted['received_amount'] ?? $amount) - $amount, 2))
                        : 0,
                    'card_last4' => $method === 'tarjeta' ? ($submitted['card_last4'] ?? null) : null,
                    'transfer_reference' => $method === 'transferencia' ? ($submitted['transfer_reference'] ?? null) : null,
                ]);
            }

            if ($lockedOrder->type === 'delivery' && $payments->count() === 1) {
                $lockedOrder->update([
                    'delivery_method' => match ($payments->first()->fresh()->method) {
                        'tarjeta' => 'tarjeta',
                        'transferencia' => 'transferencia',
                        default => 'contra_entrega',
                    },
                ]);
            }

            $lockedOrder->refresh()->load('payments');
            $after = $this->snapshot($lockedOrder, $lockedOrder->payments->all());

            if ($before !== $after) {
                OrderDataChangeAudit::create([
                    'order_id' => $lockedOrder->id,
                    'cash_register_id' => $lockedOrder->cash_register_id,
                    'changed_by' => $actorId,
                    'changes' => ['before' => $before, 'after' => $after],
                ]);
            }

            return $lockedOrder;
        });
    }

    private function snapshot(Order $order, array $payments): array
    {
        return [
            'customer' => Arr::only($order->getAttributes(), [
                'customer_name',
                'customer_phone',
                'customer_address',
                'customer_neighborhood',
                'customer_references',
                'delivery_method',
            ]),
            'payments' => collect($payments)->map(fn ($payment) => [
                'id' => $payment->id,
                'method' => $payment->method,
                'amount' => (string) $payment->amount,
                'received_amount' => $payment->received_amount === null ? null : (string) $payment->received_amount,
                'change_amount' => $payment->change_amount === null ? null : (string) $payment->change_amount,
                'card_last4' => $payment->card_last4,
                'transfer_reference' => $payment->transfer_reference,
            ])->values()->all(),
        ];
    }
}
