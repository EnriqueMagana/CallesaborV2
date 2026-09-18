<?php

namespace App\Services;

use App\Models\AddonGroup;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de personalización de un producto: grupos de extras con mínimos y
 * máximos, ingredientes con cantidad y notas. El precio final lo calcula
 * siempre el servidor a partir de los ids elegidos, nunca a partir de lo que
 * manda el navegador.
 *
 * Replica las reglas del carrito del POS (`ManagesCart::confirmCustomize`)
 * para que una orden modificada quede igual que si se hubiera vendido en caja.
 */
class ProductCustomizationService
{
    public const MAX_QUANTITY = 99;

    public const MAX_NOTES_LENGTH = 500;

    public function find(int $productId): ?Product
    {
        return Product::query()
            ->where('is_active', true)
            ->with([
                'addonGroups' => fn ($query) => $query->where('is_active', true)
                    ->with(['addons' => fn ($addons) => $addons->where('is_active', true)->orderBy('sort_order')]),
                'ingredients' => fn ($query) => $query->where('is_active', true)->orderBy('ingredients.sort_order'),
            ])
            ->find($productId);
    }

    public function requiresCustomization(Product $product): bool
    {
        return $product->is_customizable
            || $product->addonGroups->isNotEmpty()
            || $product->ingredients->isNotEmpty();
    }

    public function groupMinimum(AddonGroup $group): int
    {
        return $group->is_required ? max(1, (int) $group->min_selections) : (int) $group->min_selections;
    }

    public function groupMaximum(AddonGroup $group): int
    {
        $minimum = $this->groupMinimum($group);
        $available = $group->addons->count();
        $configured = (int) $group->max_selections;

        return $configured > 0 ? max($minimum, min($configured, $available)) : $available;
    }

    /**
     * Extras que deben venir marcados de inicio: grupos obligatorios con una
     * sola opción, igual que en el POS.
     *
     * @return array<int, int>
     */
    public function preselectedAddonIds(Product $product): array
    {
        return $product->addonGroups
            ->filter(fn (AddonGroup $group) => $this->groupMinimum($group) > 0 && $group->addons->count() === 1)
            ->map(fn (AddonGroup $group) => (int) $group->addons->first()->id)
            ->values()
            ->all();
    }

    /**
     * Valida la selección y devuelve la línea lista para guardar.
     *
     * @param  array<int, int|string>  $addonIds
     * @param  array<int|string, int|string>  $ingredientQuantities  id => cantidad
     * @return array{addons: array<int, array>, ingredients: array<int, array>, notes: ?string, base_price: float, unit_extra: float, unit_total: float, subtotal: float, quantity: int, summary: string}
     */
    public function build(Product $product, array $addonIds, array $ingredientQuantities, int $quantity, ?string $notes = null): array
    {
        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw ValidationException::withMessages(['customQuantity' => 'La cantidad debe estar entre 1 y 99.']);
        }

        $notes = trim((string) $notes);
        if (mb_strlen($notes) > self::MAX_NOTES_LENGTH) {
            throw ValidationException::withMessages(['customNotes' => 'La nota no puede superar 500 caracteres.']);
        }

        $selectedAddonIds = collect($addonIds)->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->unique();
        $allowedAddonIds = $product->addonGroups->flatMap->addons->pluck('id')->map(fn ($id) => (int) $id);
        if ($selectedAddonIds->diff($allowedAddonIds)->isNotEmpty()) {
            throw ValidationException::withMessages(['customAddons' => 'La selección contiene complementos que ya no están disponibles.']);
        }

        $selectedIngredients = collect($ingredientQuantities)
            ->filter(fn ($qty, $id) => is_numeric($id) && is_numeric($qty) && (int) $qty > 0)
            ->mapWithKeys(fn ($qty, $id) => [(int) $id => min(self::MAX_QUANTITY, (int) $qty)]);
        $allowedIngredientIds = $product->ingredients->pluck('id')->map(fn ($id) => (int) $id);
        if ($selectedIngredients->keys()->diff($allowedIngredientIds)->isNotEmpty()) {
            throw ValidationException::withMessages(['customIngredients' => 'La selección contiene ingredientes que ya no están disponibles.']);
        }

        foreach ($product->addonGroups as $group) {
            $selected = $group->addons->filter(fn ($addon) => $selectedAddonIds->contains((int) $addon->id))->count();
            $minimum = $this->groupMinimum($group);
            $maximum = $this->groupMaximum($group);

            if ($group->addons->count() < $minimum) {
                throw ValidationException::withMessages(["customGroup{$group->id}" => "«{$group->name}» no tiene suficientes opciones disponibles."]);
            }
            if ($selected < $minimum) {
                throw ValidationException::withMessages(["customGroup{$group->id}" => "«{$group->name}» requiere al menos {$minimum} opción(es)."]);
            }
            if ($selected > $maximum) {
                throw ValidationException::withMessages(["customGroup{$group->id}" => "«{$group->name}» permite máximo {$maximum} opción(es)."]);
            }
        }

        $maximumAddons = (int) ($product->max_addons ?? 0);
        if ($maximumAddons > 0 && $selectedAddonIds->count() > $maximumAddons) {
            throw ValidationException::withMessages(['customAddons' => "Este producto permite máximo {$maximumAddons} complemento(s)."]);
        }

        $totalIngredients = $selectedIngredients->sum();
        if ((int) $product->min_ingredients > 0 && $totalIngredients < (int) $product->min_ingredients) {
            throw ValidationException::withMessages(['customIngredients' => "Elige al menos {$product->min_ingredients} ingrediente(s)."]);
        }
        if ((int) $product->max_ingredients > 0 && $totalIngredients > (int) $product->max_ingredients) {
            throw ValidationException::withMessages(['customIngredients' => "Máximo {$product->max_ingredients} ingrediente(s)."]);
        }

        $addons = [];
        foreach ($product->addonGroups as $group) {
            foreach ($group->addons as $addon) {
                if ($selectedAddonIds->contains((int) $addon->id)) {
                    $addons[] = [
                        'addon_id' => (int) $addon->id,
                        'addon_name' => $addon->name,
                        'extra_price' => round((float) $addon->extra_price, 2),
                    ];
                }
            }
        }

        $ingredients = [];
        foreach ($product->ingredients as $ingredient) {
            $qty = (int) ($selectedIngredients[(int) $ingredient->id] ?? 0);
            if ($qty > 0) {
                $ingredients[] = [
                    'ingredient_id' => (int) $ingredient->id,
                    'ingredient_name' => $ingredient->name,
                    'extra_price' => round((float) $ingredient->extra_price, 2),
                    'quantity' => $qty,
                ];
            }
        }

        $unitExtra = round(
            array_sum(array_column($addons, 'extra_price'))
            + array_sum(array_map(fn (array $item) => $item['extra_price'] * $item['quantity'], $ingredients)),
            2
        );
        $unitTotal = round((float) $product->price + $unitExtra, 2);

        return [
            'addons' => $addons,
            'ingredients' => $ingredients,
            'notes' => $notes !== '' ? $notes : null,
            'base_price' => round((float) $product->price, 2),
            'unit_extra' => $unitExtra,
            'unit_total' => $unitTotal,
            'subtotal' => round($unitTotal * $quantity, 2),
            'quantity' => $quantity,
            'summary' => $this->summary($addons, $ingredients, $notes),
        ];
    }

    /**
     * Texto corto para listas y tickets: "Salsa alfredo, + Queso x2 · sin cebolla".
     */
    public function summary(array $addons, array $ingredients, ?string $notes = null): string
    {
        $parts = collect($addons)->pluck('addon_name')
            ->merge(collect($ingredients)->map(fn (array $item) => $item['ingredient_name'].((int) $item['quantity'] > 1 ? ' x'.(int) $item['quantity'] : '')))
            ->filter()
            ->implode(', ');

        $notes = trim((string) $notes);

        return trim($parts.($notes !== '' ? ($parts !== '' ? ' · ' : '').$notes : ''));
    }
}
