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

    public function clear(array $cart, bool $recalculateFromQuantity = false): array
    {
        foreach ($cart as $index => $line) {
            if (empty($line['discount_id']) && empty($line['discount_amount'])) {
                continue;
            }

            // Live carts derive their base from the current quantity. Persisted
            // items have no unit_total, so recover their base from the net amount.
            $hasUnitAndQuantity = isset($line['unit_total'])
                && (isset($line['quantity']) || isset($line['qty']));
            $base = $hasUnitAndQuantity
                ? max(0, (float) $line['unit_total'] * (int) ($line['quantity'] ?? $line['qty'])
                    - ($recalculateFromQuantity ? 0 : (float) ($line['promotion_discount'] ?? 0)))
                : (float) ($line['subtotal'] ?? 0) + (float) ($line['discount_amount'] ?? 0);
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
            if ($productIds !== null && empty($line['product_id'])) {
                return false;
            }
            if (! $discount->combine_with_promotions
                && (! empty($line['promotion_id'])
                    || ! empty($line['promotion_selections'])
                    || (float) ($line['promotion_discount'] ?? 0) > 0)) {
                return false;
            }

            if ($productIds === null) {
                return true;
            }

            return ! empty($line['product_id']) && in_array((int) $line['product_id'], $productIds, true);
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
        $discountCents = (int) round($candidate['amount'] * 100);
        $baseCents = max(1, (int) round($candidate['base'] * 100));
        $indexes = $candidate['indexes'];
        $allocations = [];
        $remainders = [];
        $allocatedCents = 0;

        foreach ($indexes as $index) {
            $lineBaseCents = max(0, (int) round((float) $cart[$index]['subtotal'] * 100));
            if ($lineBaseCents === 0) {
                $allocations[$index] = 0;

                continue;
            }

            $numerator = $discountCents * $lineBaseCents;
            $lineDiscountCents = min($lineBaseCents, intdiv($numerator, $baseCents));
            $allocations[$index] = $lineDiscountCents;
            $allocatedCents += $lineDiscountCents;
            $remainders[] = ['index' => $index, 'fraction' => $numerator % $baseCents];
        }

        usort($remainders, fn (array $left, array $right) => $right['fraction'] <=> $left['fraction']);
        $remainingCents = max(0, $discountCents - $allocatedCents);
        foreach ($remainders as $remainder) {
            if ($remainingCents === 0) {
                break;
            }

            $index = $remainder['index'];
            $lineBaseCents = max(0, (int) round((float) $cart[$index]['subtotal'] * 100));
            if ($allocations[$index] >= $lineBaseCents) {
                continue;
            }

            $allocations[$index]++;
            $remainingCents--;
        }

        foreach ($indexes as $index) {
            $lineBase = round((float) $cart[$index]['subtotal'], 2);
            $lineDiscount = round(($allocations[$index] ?? 0) / 100, 2);

            $cart[$index]['discount_id'] = $discount->id;
            $cart[$index]['discount_amount'] = $lineDiscount;
            $cart[$index]['discount_snapshot'] = [
                'version' => 2,
                'discount_id' => $discount->id,
                'name' => $discount->name,
                'label' => $discount->benefitLabel(),
                'value_type' => $discount->value_type,
                'value' => (float) $discount->value,
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
