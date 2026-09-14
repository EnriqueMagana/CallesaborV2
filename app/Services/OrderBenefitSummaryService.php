<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;

class OrderBenefitSummaryService
{
    /**
     * Return the commercial benefits recorded on a persisted order line.
     * Snapshots take priority so a later promotion/discount edit cannot alter
     * what appears on a historical ticket.
     */
    public function forItem(OrderItem $item): array
    {
        $benefits = [];
        $promotionAmount = round((float) ($item->promotion_discount ?? 0), 2);
        $promotionLabel = data_get($item->promotion_rule_snapshot, 'label')
            ?: $item->promotion?->name
            ?: ($item->promotion_id ? $item->product_name : null);

        if ($item->promotion_id || $promotionAmount > 0 || filled($item->promotion_rule_snapshot)) {
            $benefits[] = [
                'type' => 'promotion',
                'type_label' => 'Promoción',
                'name' => $promotionLabel ?: 'Promoción aplicada',
                'amount' => $promotionAmount,
            ];
        }

        $discountAmount = round((float) ($item->discount_amount ?? 0), 2);
        if ($discountAmount > 0) {
            $benefits[] = [
                'type' => 'discount',
                'type_label' => 'Descuento',
                'name' => data_get($item->discount_snapshot, 'name')
                    ?: $item->discount?->name
                    ?: 'Descuento aplicado',
                'amount' => $discountAmount,
            ];
        }

        return $benefits;
    }

    /**
     * Create the immutable, informational audit stored with a cash cut.
     * Cancelled lines are excluded because their benefits did not form part of
     * the finalized sale used by the cut.
     */
    public function forOrders(iterable $orders): array
    {
        return collect($orders)->map(function (Order $order): array {
            $benefits = $order->items
                ->reject(fn (OrderItem $item) => (bool) $item->is_cancelled)
                ->flatMap(function (OrderItem $item): array {
                    return collect($this->forItem($item))->map(fn (array $benefit): array => array_merge($benefit, [
                        'product' => $item->product_name,
                    ]))->all();
                })
                ->values();

            return [
                'folio' => $order->display_folio,
                'customer' => $order->display_name,
                'benefits' => $benefits->all(),
                'total_discount' => round((float) $benefits->sum('amount'), 2),
            ];
        })->filter(fn (array $order): bool => ! empty($order['benefits']))
            ->values()
            ->all();
    }
}
