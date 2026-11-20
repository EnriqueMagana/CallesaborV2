<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use Illuminate\Support\Collection;

/**
 * Applies one automatic discount to all active items in a table account.
 * Per-item values are only the persisted accounting allocation of that total.
 */
class MesaAccountDiscountService
{
    public function __construct(private readonly DiscountPricingService $discounts) {}

    /**
     * Reprices the active orders together, so a fixed discount is taken once
     * and percentage discounts use the account's combined eligible subtotal.
     * Call inside a transaction after locking the orders/service.
     *
     * @param  Collection<int, Order>  $orders
     * @return array{subtotal: float, discount_total: float, total: float}
     */
    public function reprice(Collection $orders, string $fulfillment = 'dine_in'): array
    {
        $orderIds = $orders->pluck('id')->all();
        if ($orderIds === []) {
            return ['subtotal' => 0.0, 'discount_total' => 0.0, 'total' => 0.0];
        }

        if (OrderPayment::query()->whereIn('order_id', $orderIds)->exists()) {
            $total = round((float) $orders->sum('total'), 2);

            return ['subtotal' => $total, 'discount_total' => 0.0, 'total' => $total];
        }

        $items = OrderItem::query()
            ->whereIn('order_id', $orderIds)
            ->where(fn ($query) => $query->whereNull('is_cancelled')->orWhere('is_cancelled', false))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $cart = $items->map(fn (OrderItem $item): array => [
            '_order_item_id' => $item->id,
            'product_id' => $item->product_id,
            'promotion_id' => $item->promotion_id,
            'promotion_discount' => (float) $item->promotion_discount,
            'promotion_selections' => $item->promotion_selections,
            'subtotal' => (float) $item->subtotal,
            'discount_id' => $item->discount_id,
            'discount_amount' => (float) $item->discount_amount,
            'discount_snapshot' => $item->discount_snapshot,
        ])->values()->all();

        $priced = $this->discounts->apply($cart, $fulfillment);
        if (! collect($priced)->contains(fn (array $line): bool => (float) ($line['discount_amount'] ?? 0) > 0)
            && ! $items->contains(fn (OrderItem $item): bool => $item->discount_id !== null || (float) $item->discount_amount > 0)) {
            return [
                'subtotal' => round((float) $orders->sum('total'), 2),
                'discount_total' => 0.0,
                'total' => round((float) $orders->sum('total'), 2),
            ];
        }

        $itemsById = $items->keyBy('id');

        foreach ($priced as $line) {
            $item = $itemsById->get($line['_order_item_id']);
            if (! $item) {
                continue;
            }

            $item->forceFill([
                'discount_id' => $line['discount_id'] ?? null,
                'discount_amount' => $line['discount_amount'] ?? 0,
                'discount_snapshot' => $line['discount_snapshot'] ?? null,
                'subtotal' => $line['subtotal'],
            ]);
            if ($item->isDirty()) {
                $item->save();
            }
        }

        $discountedItems = collect($priced)->keyBy('_order_item_id');
        $itemsByOrder = $items->groupBy('order_id');
        foreach ($orders as $order) {
            $orderTotal = $itemsByOrder->get($order->id, collect())
                ->sum(fn (OrderItem $item): float => (float) ($discountedItems->get($item->id)['subtotal'] ?? $item->subtotal));

            $order->forceFill([
                'subtotal' => round($orderTotal, 2),
                'total' => round($orderTotal, 2),
            ]);
            if ($order->isDirty()) {
                $order->save();
            }
            $order->unsetRelation('items');
        }

        $discountTotal = round((float) collect($priced)->sum('discount_amount'), 2);
        $total = round((float) collect($priced)->sum('subtotal'), 2);

        return [
            'subtotal' => round($total + $discountTotal, 2),
            'discount_total' => $discountTotal,
            'total' => $total,
        ];
    }
}
