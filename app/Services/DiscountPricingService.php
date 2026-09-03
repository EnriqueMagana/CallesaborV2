<?php

namespace App\Services;

use App\Models\Discount;

class DiscountPricingService
{
    /**
     * Applies only the best eligible automatic discount. Promotion pricing must
     * run first so this service can honor the configured combination policy.
     */
    public function apply(
        array $cart,
        string $fulfillment,
        ?int $customerId = null,
        ?int $employeeId = null,
    ): array {
        $cart = $this->clear($cart);
        if ($cart === []) {
            return $cart;
        }

        $gross = round((float) collect($cart)->sum('subtotal'), 2);
        $discounts = Discount::query()
            ->available($fulfillment)
            ->with(['products:id', 'customers:id', 'employees:id'])
            ->orderBy('priority')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (Discount $discount) => $this->audienceMatches($discount, $customerId, $employeeId));

        $candidate = $discounts->map(function (Discount $discount) use ($cart, $gross): array {
            $indexes = $this->eligibleIndexes($discount, $cart);
            $base = round((float) collect($indexes)->sum(fn (int $index) => (float) $cart[$index]['subtotal']), 2);
            $amount = $gross + 0.001 < (float) $discount->minimum_purchase
                ? 0.0
                : $this->amount($discount, $base);

            return compact('discount', 'indexes', 'base', 'amount');
        })->filter(fn (array $item) => $item['amount'] > 0 && $item['indexes'] !== [])
            ->sort(fn (array $left, array $right) => $right['amount'] <=> $left['amount']
                ?: $left['discount']->priority <=> $right['discount']->priority)
            ->first();

        if (! $candidate) {
            return array_values($cart);
        }

        return $this->allocate($cart, $candidate);
    }

    public function clear(array $cart): array
    {
        foreach ($cart as $index => $line) {
            if (empty($line['discount_id']) && empty($line['discount_amount'])) {
                continue;
            }

            $base = (float) data_get($line, 'discount_snapshot.base_subtotal',
                (float) ($line['subtotal'] ?? 0) + (float) ($line['discount_amount'] ?? 0));
            $cart[$index]['subtotal'] = round($base, 2);
            $cart[$index]['discount_id'] = null;
            $cart[$index]['discount_amount'] = 0.0;
            $cart[$index]['discount_snapshot'] = null;
        }

        return $cart;
    }

    private function audienceMatches(Discount $discount, ?int $customerId, ?int $employeeId): bool
    {
        return match ($discount->audience) {
            'customers' => $customerId !== null,
            'selected_customers' => $customerId !== null && $discount->customers->contains('id', $customerId),
            'employees' => $employeeId !== null,
            'selected_employees' => $employeeId !== null && $discount->employees->contains('id', $employeeId),
            default => true,
        };
    }

    private function eligibleIndexes(Discount $discount, array $cart): array
    {
        $productIds = $discount->scope === 'products'
            ? $discount->products->pluck('id')->map(fn ($id) => (int) $id)->all()
            : null;

        return collect($cart)->keys()->filter(function (int $index) use ($cart, $discount, $productIds): bool {
            $line = $cart[$index];
            if (empty($line['product_id']) || ! empty($line['promotion_selections'])) {
                return false;
            }
            if (! $discount->combine_with_promotions && (float) ($line['promotion_discount'] ?? 0) > 0) {
                return false;
            }

            return $productIds === null || in_array((int) $line['product_id'], $productIds, true);
        })->values()->all();
    }

    private function amount(Discount $discount, float $base): float
    {
        if ($base <= 0) {
            return 0.0;
        }

        $amount = $discount->value_type === 'percentage'
            ? $base * ((float) $discount->value / 100)
            : min($base, (float) $discount->value);
        if ($discount->maximum_discount !== null) {
            $amount = min($amount, (float) $discount->maximum_discount);
        }

        return round(min($base, $amount), 2);
    }

    private function allocate(array $cart, array $candidate): array
    {
        /** @var Discount $discount */
        $discount = $candidate['discount'];
        $remaining = (int) round($candidate['amount'] * 100);
        $baseCents = max(1, (int) round($candidate['base'] * 100));
        $indexes = $candidate['indexes'];

        foreach ($indexes as $position => $index) {
            $lineBase = round((float) $cart[$index]['subtotal'], 2);
            $isLast = $position === array_key_last($indexes);
            $lineDiscountCents = $isLast
                ? $remaining
                : min($remaining, (int) round(($lineBase * 100) / $baseCents * ($candidate['amount'] * 100)));
            $lineDiscount = round($lineDiscountCents / 100, 2);
            $remaining -= $lineDiscountCents;

            $cart[$index]['discount_id'] = $discount->id;
            $cart[$index]['discount_amount'] = $lineDiscount;
            $cart[$index]['discount_snapshot'] = [
                'version' => 1,
                'discount_id' => $discount->id,
                'name' => $discount->name,
                'label' => $discount->benefitLabel(),
                'category' => $discount->category,
                'audience' => $discount->audience,
                'scope' => $discount->scope,
                'base_subtotal' => $lineBase,
                'discount_amount' => $lineDiscount,
            ];
            $cart[$index]['subtotal'] = round(max(0, $lineBase - $lineDiscount), 2);
        }

        return array_values($cart);
    }
}
