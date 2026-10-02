<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Validation\ValidationException;

class OnlineOrderCartService
{
    public function price(array $items, string $fulfillment): array
    {
        if (! in_array($fulfillment, ['takeaway', 'delivery'], true)) {
            throw ValidationException::withMessages(['fulfillment' => 'Selecciona domicilio o pasa a recoger.']);
        }

        $lines = collect($items)->take(50)->map(function (array $item) use ($fulfillment): array {
            if (! empty($item['promotion_id'])) {
                return $this->promotionLine($item, $fulfillment);
            }

            return $this->productLine($item);
        })->values()->all();

        $lines = app(PromotionPricingService::class)->apply($lines, 'digital_menu', $fulfillment);
        $total = round((float) collect($lines)->sum('subtotal'), 2);

        if ($total <= 0) {
            throw ValidationException::withMessages(['cart' => 'Agrega al menos un producto disponible.']);
        }

        return ['lines' => $lines, 'subtotal' => $total, 'total' => $total];
    }

    private function productLine(array $item): array
    {
        $product = Product::query()->where('is_active', true)
            ->with(['addonGroups' => fn ($q) => $q->where('is_active', true)->with(['addons' => fn ($a) => $a->where('is_active', true)]),
                'ingredients' => fn ($q) => $q->where('is_active', true)])
            ->find($item['product_id'] ?? null);

        if (! $product) {
            throw ValidationException::withMessages(['cart' => 'Uno de los productos ya no está disponible.']);
        }

        $addonQuantities = $this->quantities($item['addon_quantities'] ?? []);
        $ingredientQuantities = $this->quantities($item['ingredient_quantities'] ?? []);
        $this->validateSelections($product, $addonQuantities, $ingredientQuantities);

        $allowedAddons = $product->addonGroups->flatMap->addons->keyBy('id');
        $allowedIngredients = $product->ingredients->keyBy('id');
        $addons = collect($addonQuantities)->map(fn (int $quantity, int $id) => [
            'id' => $id, 'name' => $allowedAddons[$id]->name,
            'extra_price' => round((float) $allowedAddons[$id]->extra_price, 2), 'quantity' => $quantity,
        ])->values()->all();
        $ingredients = collect($ingredientQuantities)->map(fn (int $quantity, int $id) => [
            'id' => $id, 'name' => $allowedIngredients[$id]->name,
            'extra_price' => round((float) $allowedIngredients[$id]->extra_price, 2), 'quantity' => $quantity,
        ])->values()->all();
        $base = round((float) $product->price, 2);
        $unit = round($base + collect($addons)->sum(fn ($a) => $a['extra_price'] * $a['quantity'])
            + collect($ingredients)->sum(fn ($i) => $i['extra_price'] * $i['quantity']), 2);
        $quantity = max(1, min(99, (int) ($item['quantity'] ?? 1)));

        return [
            'product_id' => $product->id, 'promotion_id' => null, 'product_name' => $product->name,
            'product_price' => $base, 'base_price' => $base, 'unit_total' => $unit,
            'quantity' => $quantity, 'subtotal' => round($unit * $quantity, 2),
            'addons' => $addons, 'ingredients' => $ingredients,
            'notes' => mb_substr(trim((string) ($item['notes'] ?? '')), 0, 300),
            'promotion_selections' => null, 'promotion_discount' => 0,
            'promotion_rule_snapshot' => null,
        ];
    }

    private function promotionLine(array $item, string $fulfillment): array
    {
        $promotion = Promotion::query()->available('digital_menu', null, $fulfillment)
            ->with(['groups.products' => fn ($q) => $q->where('is_active', true)])
            ->find($item['promotion_id']);
        if (! $promotion || $promotion->groups->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Una promoción ya no está disponible para esta modalidad.']);
        }

        $selection = app(PromotionSelectionService::class);
        $snapshot = $selection->snapshot($promotion, $selection->selectionMap($item['promotion_selections'] ?? []));
        $quantity = max(1, min(99, (int) ($item['quantity'] ?? 1)));
        $price = round((float) $promotion->price, 2);

        return [
            'product_id' => null, 'promotion_id' => $promotion->id, 'product_name' => $promotion->name,
            'product_price' => $price, 'base_price' => $price, 'unit_total' => $price,
            'quantity' => $quantity, 'subtotal' => round($price * $quantity, 2),
            'addons' => [], 'ingredients' => [], 'notes' => '',
            'promotion_selections' => $snapshot, 'promotion_discount' => 0,
            'promotion_rule_snapshot' => null,
        ];
    }

    private function quantities(array $values): array
    {
        return collect($values)->mapWithKeys(fn ($quantity, $id) => [(int) $id => max(0, min(99, (int) $quantity))])
            ->filter()->all();
    }

    private function validateSelections(Product $product, array $addons, array $ingredients): void
    {
        $validAddonIds = $product->addonGroups->flatMap->addons->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (array_diff(array_keys($addons), $validAddonIds)) {
            throw ValidationException::withMessages(['cart' => 'Un complemento seleccionado ya no está disponible.']);
        }
        foreach ($product->addonGroups as $group) {
            $ids = $group->addons->pluck('id')->map(fn ($id) => (int) $id)->all();
            $count = collect($ids)->sum(fn ($id) => $addons[$id] ?? 0);
            $minimum = $group->is_required ? max(1, (int) $group->min_selections) : (int) $group->min_selections;
            if ($count < $minimum || $count > max(1, (int) $group->max_selections)) {
                throw ValidationException::withMessages(['cart' => "Revisa las selecciones de {$group->name} en {$product->name}."]);
            }
        }
        if ($product->max_addons && array_sum($addons) > $product->max_addons) {
            throw ValidationException::withMessages(['cart' => "{$product->name} acepta hasta {$product->max_addons} complementos."]);
        }
        $validIngredientIds = $product->ingredients->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (array_diff(array_keys($ingredients), $validIngredientIds)) {
            throw ValidationException::withMessages(['cart' => 'Un ingrediente seleccionado ya no está disponible.']);
        }
        $count = array_sum($ingredients);
        if ($count < (int) $product->min_ingredients || ($product->max_ingredients && $count > $product->max_ingredients)) {
            throw ValidationException::withMessages(['cart' => "Revisa la cantidad de ingredientes de {$product->name}."]);
        }
    }
}
