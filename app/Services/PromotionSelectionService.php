<?php

namespace App\Services;

use App\Models\Promotion;
use Illuminate\Validation\ValidationException;

class PromotionSelectionService
{
    public function snapshot(Promotion $promotion, array $selections): array
    {
        $snapshot = [];

        foreach ($promotion->groups as $group) {
            $requested = collect($selections[$group->id] ?? [])
                ->mapWithKeys(fn ($quantity, $productId) => [(int) $productId => max(0, (int) $quantity)])
                ->filter();
            $allowed = $group->products->keyBy('id');

            if ($requested->keys()->diff($allowed->keys())->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'promotion' => "«{$group->name}» contiene productos no disponibles.",
                ]);
            }

            $total = (int) $requested->sum();
            if ($total < $group->min_selections || $total > $group->max_selections) {
                throw ValidationException::withMessages([
                    'promotion' => "«{$group->name}» requiere entre {$group->min_selections} y {$group->max_selections} selección(es).",
                ]);
            }

            $snapshot[] = [
                'group_id' => $group->id,
                'group_name' => $group->name,
                'min_selections' => $group->min_selections,
                'max_selections' => $group->max_selections,
                'items' => $requested->map(fn (int $quantity, int $productId) => [
                    'product_id' => $productId,
                    'product_name' => $allowed[$productId]->name,
                    'quantity' => $quantity,
                ])->values()->all(),
            ];
        }

        return $snapshot;
    }

    public function selectionMap(array $snapshot): array
    {
        return collect($snapshot)->mapWithKeys(fn (array $group) => [
            (int) ($group['group_id'] ?? 0) => collect($group['items'] ?? [])->mapWithKeys(
                fn (array $item) => [(int) ($item['product_id'] ?? 0) => (int) ($item['quantity'] ?? 0)]
            )->all(),
        ])->all();
    }
}
