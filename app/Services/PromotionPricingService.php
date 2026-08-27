<?php

namespace App\Services;

use App\Models\Promotion;
use Illuminate\Database\Eloquent\Builder;

class PromotionPricingService
{
    /**
     * Recalculates automatic commercial rules from trusted catalog prices.
     * Explicit fixed-price bundle rows are intentionally left untouched.
     */
    public function apply(array $cart, string $channel, string $fulfillment): array
    {
        $cart = $this->resetAutomaticDiscounts($cart);

        if (! config('promotions.automatic_pricing_enabled', true) || $cart === []) {
            return $cart;
        }

        $productIds = collect($cart)
            ->filter(fn (array $line) => ! empty($line['product_id']) && empty($line['promotion_selections']))
            ->pluck('product_id')->map(fn ($id) => (int) $id)->unique()->values();

        if ($productIds->isEmpty()) {
            return $cart;
        }

        $rules = Promotion::query()
            ->automaticPricingAvailable($channel, null, $fulfillment)
            ->whereIn('primary_product_id', $productIds)
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get()
            ->groupBy('primary_product_id');

        foreach ($productIds as $productId) {
            $indexes = collect($cart)->keys()->filter(fn (int $index) =>
                (int) ($cart[$index]['product_id'] ?? 0) === $productId
                && empty($cart[$index]['promotion_selections'])
            )->values()->all();

            $best = collect($rules->get($productId, collect()))
                ->map(fn (Promotion $promotion) => [
                    'promotion' => $promotion,
                    'discount' => $this->potentialDiscount($promotion, $cart, $indexes),
                ])
                ->sortByDesc('discount')
                ->first(fn (array $candidate) => $candidate['discount'] > 0.0);

            if ($best) {
                $cart = $this->applyRule($cart, $indexes, $best['promotion']);
            }
        }

        return array_values($cart);
    }

    private function resetAutomaticDiscounts(array $cart): array
    {
        foreach ($cart as $index => $line) {
            if (empty($line['auto_promotion_applied'])) {
                continue;
            }

            $quantity = $this->quantity($line);
            $regularSubtotal = round($this->unitTotal($line) * $quantity, 2);
            $cart[$index]['promotion_id'] = null;
            $cart[$index]['promotion_discount'] = 0.0;
            $cart[$index]['promotion_rule_snapshot'] = null;
            $cart[$index]['auto_promotion_applied'] = false;
            $cart[$index]['subtotal'] = $regularSubtotal;
        }

        return $cart;
    }

    private function potentialDiscount(Promotion $promotion, array $cart, array $indexes): float
    {
        $config = $promotion->normalizedPricingRule();
        $cycle = $config['buy_quantity'] + $config['reward_quantity'];
        $quantity = collect($indexes)->sum(fn (int $index) => $this->quantity($cart[$index]));
        $applications = intdiv($quantity, $cycle);
        if ($config['max_applications_per_order']) {
            $applications = min($applications, $config['max_applications_per_order']);
        }

        $discountedUnits = $applications * $config['reward_quantity'];
        $basePrices = collect($indexes)->flatMap(function (int $index) use ($cart) {
            return array_fill(0, $this->quantity($cart[$index]), $this->basePrice($cart[$index]));
        })->sort()->take($discountedUnits);

        return round((float) $basePrices->sum() * ($config['reward_discount_percentage'] / 100), 2);
    }

    private function applyRule(array $cart, array $indexes, Promotion $promotion): array
    {
        $config = $promotion->normalizedPricingRule();
        $totalQuantity = collect($indexes)->sum(fn (int $index) => $this->quantity($cart[$index]));
        $applications = intdiv($totalQuantity, $config['buy_quantity'] + $config['reward_quantity']);
        if ($config['max_applications_per_order']) {
            $applications = min($applications, $config['max_applications_per_order']);
        }
        $remainingRewards = $applications * $config['reward_quantity'];

        // Reward the cheapest eligible base units first. Add-ons are never discounted.
        usort($indexes, fn (int $left, int $right) => $this->basePrice($cart[$left]) <=> $this->basePrice($cart[$right]));
        foreach ($indexes as $index) {
            $quantity = $this->quantity($cart[$index]);
            $rewardUnits = min($quantity, $remainingRewards);
            $discount = round($rewardUnits * $this->basePrice($cart[$index]) * ($config['reward_discount_percentage'] / 100), 2);
            $remainingRewards -= $rewardUnits;

            if ($discount <= 0) {
                continue;
            }

            $regularSubtotal = round($this->unitTotal($cart[$index]) * $quantity, 2);
            $snapshot = [
                'version' => 1,
                'promotion_id' => $promotion->id,
                'promotion_name' => $promotion->name,
                'rule_type' => $promotion->pricing_rule_type,
                'label' => $promotion->pricingRuleLabel(),
                'config' => $config,
                'rewarded_units' => $rewardUnits,
                'discount_amount' => $discount,
                'regular_subtotal' => $regularSubtotal,
            ];

            $cart[$index]['promotion_id'] = $promotion->id;
            $cart[$index]['promotion_discount'] = $discount;
            $cart[$index]['promotion_rule_snapshot'] = $snapshot;
            $cart[$index]['auto_promotion_applied'] = true;
            $cart[$index]['subtotal'] = round($regularSubtotal - $discount, 2);
        }

        return $cart;
    }

    private function quantity(array $line): int
    {
        return max(1, (int) ($line['quantity'] ?? $line['qty'] ?? 1));
    }

    private function basePrice(array $line): float
    {
        return round((float) ($line['product_price'] ?? $line['price'] ?? $line['base_price'] ?? $line['unit_total'] ?? 0), 2);
    }

    private function unitTotal(array $line): float
    {
        return round((float) ($line['unit_total'] ?? $line['price'] ?? $line['product_price'] ?? 0), 2);
    }
}
