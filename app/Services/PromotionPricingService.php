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
            ->where(function (Builder $eligible) use ($productIds): void {
                $eligible->whereIn('primary_product_id', $productIds)
                    ->orWhereHas('groups.products', fn (Builder $products) => $products->whereIn('products.id', $productIds));
            })
            ->with('groups.products:id,name,image,is_active,is_customizable')
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get();

        $candidates = $rules->map(function (Promotion $promotion) use ($cart) {
            $eligibleProductIds = $this->eligibleProductIds($promotion);
            $indexes = $this->eligibleIndexes($cart, $eligibleProductIds);

            return [
                'promotion' => $promotion,
                'eligible_product_ids' => $eligibleProductIds,
                'indexes' => $indexes,
                'discount' => $this->potentialDiscount($promotion, $cart, $indexes),
            ];
        })->filter(fn (array $candidate) => $candidate['discount'] > 0.0)
            ->sortByDesc('discount');

        $claimedProductIds = [];
        foreach ($candidates as $candidate) {
            if (array_intersect($claimedProductIds, $candidate['eligible_product_ids']) !== []) {
                continue;
            }

            $cart = $this->applyRule($cart, $candidate['indexes'], $candidate['promotion']);
            $claimedProductIds = array_values(array_unique(array_merge($claimedProductIds, $candidate['eligible_product_ids'])));
        }

        return array_values($cart);
    }

    /**
     * Returns incomplete buy-X/get-Y benefits that are one or more units away
     * from activating. Prices are still calculated exclusively by apply().
     */
    public function opportunities(array $cart, string $channel, string $fulfillment): array
    {
        if (! config('promotions.automatic_pricing_enabled', true) || $cart === []) {
            return [];
        }

        $productIds = collect($cart)
            ->filter(fn (array $line) => ! empty($line['product_id']) && empty($line['promotion_selections']))
            ->pluck('product_id')->map(fn ($id) => (int) $id)->unique()->values();

        if ($productIds->isEmpty()) {
            return [];
        }

        $rules = Promotion::query()
            ->automaticPricingAvailable($channel, null, $fulfillment)
            ->where('pricing_rule_type', Promotion::PRICING_RULE_BUY_X_GET_Y_DISCOUNT)
            ->where(function (Builder $eligible) use ($productIds): void {
                $eligible->whereIn('primary_product_id', $productIds)
                    ->orWhereHas('groups.products', fn (Builder $products) => $products->whereIn('products.id', $productIds));
            })
            ->with(['primaryProduct:id,name,image,is_active,is_customizable', 'groups.products:id,name,image,is_active,is_customizable'])
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get();

        return $rules->map(function (Promotion $promotion) use ($cart) {
                    $eligibleProductIds = $this->eligibleProductIds($promotion);
                    $eligibleProducts = $promotion->groups->flatMap->products
                        ->filter(fn ($product) => $product->is_active)
                        ->unique('id')
                        ->values();
                    $product = $eligibleProducts->first() ?: $promotion->primaryProduct;
                    $quantity = collect($this->eligibleIndexes($cart, $eligibleProductIds))
                        ->sum(fn (int $index) => $this->quantity($cart[$index]));
                    $config = $promotion->normalizedPricingRule();
                    $cycle = $config['buy_quantity'] + $config['reward_quantity'];
                    $completed = intdiv($quantity, $cycle);
                    $remainder = $quantity % $cycle;

                    if (! $product?->is_active
                        || $remainder < $config['buy_quantity']
                        || ($config['max_applications_per_order'] && $completed >= $config['max_applications_per_order'])) {
                        return null;
                    }

                    $missing = $cycle - $remainder;

                    return [
                        'promotion_id' => $promotion->id,
                        'promotion_name' => $promotion->name,
                        'promotion_label' => $promotion->pricingRuleLabel(),
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'product_image' => $product->image,
                        'eligible_product_ids' => $eligibleProductIds,
                        'eligible_product_names' => $eligibleProducts->pluck('name')->all(),
                        'missing_quantity' => $missing,
                        'message' => count($eligibleProductIds) === 1
                            ? ($missing === 1
                                ? "Agrega 1 {$product->name} y activa {$promotion->pricingRuleLabel()}."
                                : "Agrega {$missing} unidades de {$product->name} y activa {$promotion->pricingRuleLabel()}.")
                            : ($missing === 1
                                ? 'Agrega 1 producto elegible y activa '.$promotion->pricingRuleLabel().'.'
                                : "Agrega {$missing} productos elegibles y activa {$promotion->pricingRuleLabel()}."),
                    ];
        })->filter()->values()->all();
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
        if ($promotion->pricing_rule_type === Promotion::PRICING_RULE_PERCENTAGE_DISCOUNT) {
            return round((float) collect($indexes)->sum(
                fn (int $index) => $this->quantity($cart[$index]) * $this->basePrice($cart[$index])
            ) * ($config['discount_percentage'] / 100), 2);
        }

        if ($promotion->pricing_rule_type === Promotion::PRICING_RULE_FIXED_PRODUCT_PRICE) {
            return round((float) collect($indexes)->sum(
                fn (int $index) => max(0, $this->basePrice($cart[$index]) - $config['fixed_price']) * $this->quantity($cart[$index])
            ), 2);
        }

        $rewardUnits = $this->rewardUnitCounts($cart, $indexes, $config);

        return round((float) collect($rewardUnits)->map(
            fn (int $units, int $index) => $units * $this->basePrice($cart[$index]) * ($config['reward_discount_percentage'] / 100)
        )->sum(), 2);
    }

    private function applyRule(array $cart, array $indexes, Promotion $promotion): array
    {
        $config = $promotion->normalizedPricingRule();
        if ($promotion->pricing_rule_type === Promotion::PRICING_RULE_PERCENTAGE_DISCOUNT) {
            return $this->applyPercentageRule($cart, $indexes, $promotion, $config);
        }

        if ($promotion->pricing_rule_type === Promotion::PRICING_RULE_FIXED_PRODUCT_PRICE) {
            return $this->applyFixedProductPriceRule($cart, $indexes, $promotion, $config);
        }

        $rewardUnitsByIndex = $this->rewardUnitCounts($cart, $indexes, $config);

        foreach ($indexes as $index) {
            $quantity = $this->quantity($cart[$index]);
            $rewardUnits = (int) ($rewardUnitsByIndex[$index] ?? 0);
            $discount = round($rewardUnits * $this->basePrice($cart[$index]) * ($config['reward_discount_percentage'] / 100), 2);

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

    private function applyPercentageRule(array $cart, array $indexes, Promotion $promotion, array $config): array
    {
        foreach ($indexes as $index) {
            $quantity = $this->quantity($cart[$index]);
            $regularSubtotal = round($this->unitTotal($cart[$index]) * $quantity, 2);
            $discount = round($this->basePrice($cart[$index]) * $quantity * ($config['discount_percentage'] / 100), 2);
            if ($discount <= 0) {
                continue;
            }

            $cart[$index]['promotion_id'] = $promotion->id;
            $cart[$index]['promotion_discount'] = $discount;
            $cart[$index]['promotion_rule_snapshot'] = [
                'version' => 1,
                'promotion_id' => $promotion->id,
                'promotion_name' => $promotion->name,
                'rule_type' => $promotion->pricing_rule_type,
                'label' => $promotion->pricingRuleLabel(),
                'config' => $config,
                'rewarded_units' => $quantity,
                'discount_amount' => $discount,
                'regular_subtotal' => $regularSubtotal,
            ];
            $cart[$index]['auto_promotion_applied'] = true;
            $cart[$index]['subtotal'] = round($regularSubtotal - $discount, 2);
        }

        return $cart;
    }

    private function applyFixedProductPriceRule(array $cart, array $indexes, Promotion $promotion, array $config): array
    {
        foreach ($indexes as $index) {
            $quantity = $this->quantity($cart[$index]);
            $regularSubtotal = round($this->unitTotal($cart[$index]) * $quantity, 2);
            $discount = round(max(0, $this->basePrice($cart[$index]) - $config['fixed_price']) * $quantity, 2);
            if ($discount <= 0) {
                continue;
            }

            $cart[$index]['promotion_id'] = $promotion->id;
            $cart[$index]['promotion_discount'] = $discount;
            $cart[$index]['promotion_rule_snapshot'] = [
                'version' => 1,
                'promotion_id' => $promotion->id,
                'promotion_name' => $promotion->name,
                'rule_type' => $promotion->pricing_rule_type,
                'label' => $promotion->pricingRuleLabel(),
                'config' => $config,
                'rewarded_units' => $quantity,
                'discount_amount' => $discount,
                'regular_subtotal' => $regularSubtotal,
            ];
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

    private function eligibleProductIds(Promotion $promotion): array
    {
        if ($promotion->pricing_rule_type !== Promotion::PRICING_RULE_BUY_X_GET_Y_DISCOUNT) {
            return $promotion->primary_product_id ? [(int) $promotion->primary_product_id] : [];
        }

        $promotion->loadMissing('groups.products:id');
        $ids = $promotion->groups->flatMap->products->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $ids !== [] ? $ids : ($promotion->primary_product_id ? [(int) $promotion->primary_product_id] : []);
    }

    private function eligibleIndexes(array $cart, array $productIds): array
    {
        return collect($cart)->keys()->filter(fn (int $index) =>
            in_array((int) ($cart[$index]['product_id'] ?? 0), $productIds, true)
            && empty($cart[$index]['promotion_selections'])
        )->values()->all();
    }

    /**
     * Sorts eligible units from highest to lowest, forms complete cycles, and
     * rewards the cheapest unit(s) inside each cycle. Add-ons never participate.
     */
    private function rewardUnitCounts(array $cart, array $indexes, array $config): array
    {
        $units = [];
        foreach ($indexes as $index) {
            for ($unit = 0; $unit < $this->quantity($cart[$index]); $unit++) {
                $units[] = ['index' => $index, 'price' => $this->basePrice($cart[$index])];
            }
        }
        usort($units, fn (array $left, array $right) => $right['price'] <=> $left['price'] ?: $left['index'] <=> $right['index']);

        $cycle = $config['buy_quantity'] + $config['reward_quantity'];
        $applications = intdiv(count($units), $cycle);
        if ($config['max_applications_per_order']) {
            $applications = min($applications, $config['max_applications_per_order']);
        }

        $counts = [];
        for ($application = 0; $application < $applications; $application++) {
            $pair = array_slice($units, $application * $cycle, $cycle);
            $rewards = array_slice($pair, $config['buy_quantity'], $config['reward_quantity']);
            foreach ($rewards as $reward) {
                $counts[$reward['index']] = ($counts[$reward['index']] ?? 0) + 1;
            }
        }

        return $counts;
    }
}
