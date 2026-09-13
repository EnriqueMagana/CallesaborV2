<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Collection;

class OrderFinancialSummaryService
{
    /**
     * @param  Collection<int, Order>  $orders
     * @return array{gross: array<string, float>, refunds: array<string, float>, net: array<string, float>}
     */
    public function forOrders(Collection $orders): array
    {
        $gross = collect();
        $refunds = collect();

        foreach ($orders as $order) {
            foreach ($order->payments as $payment) {
                $gross->put($payment->method, round((float) $gross->get($payment->method, 0) + (float) $payment->amount, 2));
            }

            foreach ($order->refunds as $refund) {
                foreach ($refund->allocations ?? [] as $method => $amount) {
                    $refunds->put($method, round((float) $refunds->get($method, 0) + (float) $amount, 2));
                }
            }
        }

        $methods = $gross->keys()->merge($refunds->keys())->unique();
        $net = $methods->mapWithKeys(fn (string $method) => [
            $method => max(0, round((float) $gross->get($method, 0) - (float) $refunds->get($method, 0), 2)),
        ]);

        return [
            'gross' => $gross->map(fn ($amount) => (float) $amount)->all(),
            'refunds' => $refunds->map(fn ($amount) => (float) $amount)->all(),
            'net' => $net->all(),
        ];
    }

    /**
     * @return array{gross: array<string, float>, refunds: array<string, float>, net: array<string, float>}
     */
    public function forOrder(Order $order): array
    {
        return $this->forOrders(collect([$order]));
    }
}
