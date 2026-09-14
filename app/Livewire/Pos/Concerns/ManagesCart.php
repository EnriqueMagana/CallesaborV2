<?php

namespace App\Livewire\Pos\Concerns;

use App\Models\Product;
use App\Services\DiscountPricingService;
use App\Services\PromotionPricingService;
use App\Support\Pos\CartState;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;

/**
 * El carrito: agregar, personalizar, editar y vaciar.
 *
 * Contiene la personalizacion de un producto (complementos e ingredientes) porque
 * su unico destino es una linea del carrito. Los totales se derivan aqui; el
 * catalogo no participa: es un componente hijo que solo avisa que producto se
 * toco.
 */
trait ManagesCart
{
    #[Computed]
    public function cartTotal(): float
    {
        return $this->cartState()->total();
    }

    #[Computed]
    public function cartDiscountTotal(): float
    {
        return $this->cartState()->discountTotal();
    }

    #[Computed]
    public function employeeDiscountTotal(): float
    {
        return $this->cartState()->employeeDiscountTotal();
    }

    #[Computed]
    public function cartCount(): int
    {
        return $this->cartState()->count();
    }

    #[Computed]
    public function totalSelectedIngredients(): int
    {
        return array_sum($this->selectedIngredients);
    }

    #[Computed]
    public function customizationIsValid(): bool
    {
        $product = $this->customizingProduct;
        if (! $product) {
            return false;
        }

        foreach ($product->addonGroups as $group) {
            $available = $group->addons->count();
            $selected = $group->addons->filter(
                fn ($addon) => isset($this->selectedAddons[$addon->id])
            )->count();
            $minimum = $this->effectiveGroupMinimum($group);
            $maximum = $this->effectiveGroupMaximum($group, $available, $minimum);

            if ($available < $minimum || $selected < $minimum || $selected > $maximum) {
                return false;
            }
        }

        $ingredients = array_sum($this->selectedIngredients);

        $addonsAreValid = ! $product->max_addons
            || collect($this->selectedAddons)->filter()->count() <= (int) $product->max_addons;

        return $addonsAreValid
            && $ingredients >= (int) $product->min_ingredients
            && (! $product->max_ingredients || $ingredients <= (int) $product->max_ingredients);
    }

    #[Computed]
    public function customizingProduct(): ?Product
    {
        if (! $this->customizingProductId) {
            return null;
        }

        return Product::query()
            ->select([
                'id', 'name', 'description', 'image', 'price', 'is_customizable',
                'max_addons', 'min_ingredients', 'max_ingredients',
            ])
            ->with([
                'addonGroups' => fn ($q) => $q->where('is_active', true)
                    ->select([
                        'addon_groups.id', 'addon_groups.name', 'addon_groups.description',
                        'addon_groups.is_required', 'addon_groups.min_selections',
                        'addon_groups.max_selections', 'addon_groups.sort_order',
                    ])
                    ->with(['addons' => fn ($q) => $q->where('is_active', true)
                        ->select([
                            'id', 'addon_group_id', 'name', 'description', 'image',
                            'extra_price', 'sort_order',
                        ])
                        ->orderBy('sort_order')]),
                'ingredients' => fn ($q) => $q->where('is_active', true)
                    ->select([
                        'ingredients.id', 'ingredients.name', 'ingredients.description',
                        'ingredients.image', 'ingredients.extra_price', 'ingredients.sort_order',
                    ])
                    ->orderBy('ingredients.sort_order'),
            ])->find($this->customizingProductId);
    }

    /**
     * Unidades en el carrito por producto, para el distintivo del catalogo.
     *
     * @return array<int, int>
     */
    #[Computed]
    public function cartProductQuantities(): array
    {
        return $this->cartState()->quantitiesByProduct();
    }

    private function saveCart(): void
    {
        $discounts = app(DiscountPricingService::class);
        $this->cart = $discounts->clear($this->cart);
        $this->cart = app(PromotionPricingService::class)->apply(
            $this->cart,
            'pos',
            $this->promotionFulfillmentForOrderType($this->orderType)
        );
        $this->cart = $discounts->apply(
            $this->cart,
            $this->promotionFulfillmentForOrderType($this->orderType),
            $this->customerId,
            $this->validDiscountEmployeeId(),
        );
        unset($this->cartTotal, $this->cartCount, $this->cartDiscountTotal, $this->employeeDiscountTotal, $this->promotionOpportunities, $this->cartProductQuantities);
        Session::put('pos_cart_'.auth()->id(), $this->cart);

        // El catalogo dibuja el distintivo "en el pedido" desde Alpine, no desde
        // Blade, para no depender del carrito en cada render. Este es el unico
        // punto por el que pasa toda mutacion del carrito.
        $this->dispatch('pos-cart-quantities', quantities: $this->cartProductQuantities);
    }

    public function openCustomizeModal(int $productId): void
    {
        // Ver `selectPromotionFromCatalog`: libera la tarjeta del catálogo.
        $this->dispatch('pos-catalog-settled');

        $product = Product::query()
            ->where('is_active', true)
            ->select(['id', 'name', 'price', 'image', 'is_customizable'])
            ->withCount(['addonGroups', 'ingredients'])
            ->find($productId);

        if (! $product) {
            return;
        }

        if (! $product->is_customizable && $product->addon_groups_count === 0 && $product->ingredients_count === 0) {
            $this->addSimpleProductToCart($product);

            return;
        }

        $this->customizingProductId = $productId;
        $this->editingCartId = null;
        $this->selectedAddons = [];
        $this->selectedIngredients = [];
        $this->itemNotes = '';
        $this->itemQuantity = 1;
        $this->showCustomizeModal = true;
        unset($this->customizingProduct, $this->totalSelectedIngredients);
        $this->preselectRequiredSingletons();
    }

    public function closeCustomizeModal(): void
    {
        $this->resetErrorBag();
        $this->resetCustomizationState();
    }

    private function resetCustomizationState(): void
    {
        $this->showCustomizeModal = false;
        $this->customizingProductId = null;
        $this->editingCartId = null;
        $this->selectedAddons = [];
        $this->selectedIngredients = [];
        $this->itemNotes = '';
        $this->itemQuantity = 1;
        unset($this->customizingProduct, $this->totalSelectedIngredients, $this->customizationIsValid);
    }

    private function addSimpleProductToCart(Product $product): void
    {
        $duplicate = $this->findDuplicateCartItem($product->id, [], []);

        if ($duplicate !== null) {
            if ($this->cart[$duplicate]['quantity'] >= self::MAX_ITEM_QUANTITY) {
                $this->dispatch('notify', type: 'warning', message: 'La cantidad máxima por producto es 99.');

                return;
            }
            $this->cart[$duplicate]['quantity']++;
            $this->cart[$duplicate]['subtotal'] = $this->cart[$duplicate]['unit_total'] * $this->cart[$duplicate]['quantity'];
        } else {
            $this->cart[] = [
                'cart_id' => Str::uuid()->toString(),
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_price' => (float) $product->price,
                'product_image' => $product->image,
                'quantity' => 1,
                'unit_extra' => 0,
                'unit_total' => (float) $product->price,
                'subtotal' => (float) $product->price,
                'notes' => '',
                'addons' => [],
                'ingredients' => [],
            ];
        }

        unset($this->cartTotal, $this->cartCount);
        $this->saveCart();
    }

    public function editCartItem(string $cartId): void
    {
        $item = collect($this->cart)->firstWhere('cart_id', $cartId);
        if (! $item) {
            return;
        }

        if (! empty($item['promotion_id'])) {
            $this->openPromotionModal((int) $item['promotion_id']);

            return;
        }

        $this->customizingProductId = $item['product_id'];
        $this->editingCartId = $cartId;
        $this->itemQuantity = $item['quantity'];
        $this->itemNotes = $item['notes'];

        $this->selectedAddons = collect($item['addons'])
            ->mapWithKeys(fn ($a) => [$a['addon_id'] => true])
            ->toArray();

        $this->selectedIngredients = collect($item['ingredients'])
            ->mapWithKeys(fn ($i) => [$i['ingredient_id'] => $i['quantity']])
            ->toArray();

        $this->showCustomizeModal = true;
        unset($this->customizingProduct, $this->totalSelectedIngredients);
        $this->preselectRequiredSingletons();
    }

    public function toggleAddon(int $addonId): void
    {
        $product = $this->customizingProduct;
        $group = $product?->addonGroups->first(
            fn ($candidate) => $candidate->addons->contains('id', $addonId)
        );

        if (! $group) {
            return;
        }

        $minimum = $this->effectiveGroupMinimum($group);
        $maximum = $this->effectiveGroupMaximum($group, $group->addons->count(), $minimum);

        if (isset($this->selectedAddons[$addonId])) {
            $selectedInGroup = $group->addons->filter(
                fn ($addon) => isset($this->selectedAddons[$addon->id])
            )->count();
            if ($selectedInGroup <= $minimum) {
                return;
            }

            unset($this->selectedAddons[$addonId]);
        } else {
            if ($maximum === 1) {
                foreach ($group->addons as $addon) {
                    unset($this->selectedAddons[$addon->id]);
                }
            } else {
                $selectedInGroup = $group->addons->filter(
                    fn ($addon) => isset($this->selectedAddons[$addon->id])
                )->count();
                if ($selectedInGroup >= $maximum) {
                    return;
                }
            }

            $this->selectedAddons[$addonId] = true;
        }

        $this->resetErrorBag('addons_'.$group->id);
        unset($this->customizationIsValid);
    }

    private function effectiveGroupMinimum($group): int
    {
        return $group->is_required
            ? max(1, (int) $group->min_selections)
            : (int) $group->min_selections;
    }

    private function effectiveGroupMaximum($group, int $available, ?int $minimum = null): int
    {
        $minimum ??= $this->effectiveGroupMinimum($group);
        $configured = (int) $group->max_selections;

        return $configured > 0
            ? max($minimum, min($configured, $available))
            : $available;
    }

    private function preselectRequiredSingletons(): void
    {
        $product = $this->customizingProduct;
        if (! $product) {
            return;
        }

        foreach ($product->addonGroups as $group) {
            if ($this->effectiveGroupMinimum($group) > 0 && $group->addons->count() === 1) {
                $this->selectedAddons[$group->addons->first()->id] = true;
            }
        }

        unset($this->customizationIsValid);
    }

    public function addToCart(): void
    {
        $this->confirmCustomize();
    }

    public function confirmCustomize(
        ?array $addonIds = null,
        ?array $ingredientQuantities = null,
        ?int $quantity = null,
        ?string $notes = null,
    ): void {
        $product = $this->customizingProduct;
        if (! $product) {
            return;
        }

        $this->resetErrorBag();

        if ($addonIds !== null) {
            $requestedAddonIds = collect($addonIds)
                ->filter(fn ($id) => is_numeric($id))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();
            $allowedAddonIds = $product->addonGroups->flatMap->addons->pluck('id');

            if ($requestedAddonIds->diff($allowedAddonIds)->isNotEmpty()) {
                $this->addError('addons_general', 'La selección contiene complementos no disponibles.');

                return;
            }

            $this->selectedAddons = $requestedAddonIds
                ->mapWithKeys(fn ($id) => [$id => true])
                ->all();
        }

        if ($ingredientQuantities !== null) {
            $allowedIngredientIds = $product->ingredients->pluck('id')->map(fn ($id) => (int) $id);
            $requestedIngredientIds = collect(array_keys($ingredientQuantities))
                ->filter(fn ($id) => is_numeric($id))
                ->map(fn ($id) => (int) $id);

            if ($requestedIngredientIds->diff($allowedIngredientIds)->isNotEmpty()) {
                $this->addError('ingredients', 'La selección contiene ingredientes no disponibles.');

                return;
            }

            $this->selectedIngredients = collect($ingredientQuantities)
                ->filter(fn ($qty, $id) => is_numeric($id) && is_numeric($qty) && (int) $qty > 0)
                ->mapWithKeys(fn ($qty, $id) => [(int) $id => (int) $qty])
                ->all();
        }

        if ($quantity !== null) {
            $this->itemQuantity = $quantity;
        }
        if ($notes !== null) {
            $this->itemNotes = trim($notes);
        }

        if ($this->itemQuantity < 1 || $this->itemQuantity > self::MAX_ITEM_QUANTITY) {
            $this->addError('itemQuantity', 'La cantidad debe estar entre 1 y 99.');

            return;
        }
        if (mb_strlen($this->itemNotes) > self::MAX_ITEM_NOTES_LENGTH) {
            $this->addError('itemNotes', 'La nota no puede superar 500 caracteres.');

            return;
        }

        foreach ($product->addonGroups as $group) {
            $selected = collect($group->addons)
                ->filter(fn ($a) => isset($this->selectedAddons[$a->id]))
                ->count();

            $minimum = $this->effectiveGroupMinimum($group);
            $available = $group->addons->count();
            $maximum = $this->effectiveGroupMaximum($group, $available, $minimum);

            if ($available < $minimum) {
                $this->addError('addons_'.$group->id, "«{$group->name}» no tiene suficientes opciones disponibles.");

                return;
            }
            if ($selected < $minimum) {
                $this->addError('addons_'.$group->id, "«{$group->name}» requiere al menos {$minimum} opción(es).");

                return;
            }
            if ($selected > $maximum) {
                $this->addError('addons_'.$group->id, "«{$group->name}» permite máximo {$maximum} opción(es).");

                return;
            }
        }

        $maximumAddons = (int) ($product->max_addons ?? 0);
        $totalAddons = collect($this->selectedAddons)->filter()->count();
        if ($maximumAddons > 0 && $totalAddons > $maximumAddons) {
            $this->addError('addons_general', "Este producto permite máximo {$maximumAddons} complemento(s).");

            return;
        }

        $totalIng = array_sum($this->selectedIngredients);
        if ($product->min_ingredients > 0 && $totalIng < $product->min_ingredients) {
            $this->addError('ingredients', "Mínimo {$product->min_ingredients} ingrediente(s) requerido(s).");

            return;
        }
        if ($product->max_ingredients && $totalIng > $product->max_ingredients) {
            $this->addError('ingredients', "Máximo {$product->max_ingredients} ingredientes.");

            return;
        }

        $addons = [];
        foreach ($product->addonGroups as $group) {
            foreach ($group->addons as $addon) {
                if (isset($this->selectedAddons[$addon->id])) {
                    $addons[] = [
                        'addon_id' => $addon->id,
                        'addon_name' => $addon->name,
                        'extra_price' => (float) $addon->extra_price,
                    ];
                }
            }
        }

        $ingredients = [];
        foreach ($product->ingredients as $ing) {
            $qty = $this->selectedIngredients[$ing->id] ?? 0;
            if ($qty > 0) {
                $ingredients[] = [
                    'ingredient_id' => $ing->id,
                    'ingredient_name' => $ing->name,
                    'extra_price' => (float) $ing->extra_price,
                    'quantity' => $qty,
                ];
            }
        }

        $addonExtra = array_sum(array_column($addons, 'extra_price'));
        $ingExtra = array_sum(array_map(fn ($i) => $i['extra_price'] * $i['quantity'], $ingredients));
        $unitTotal = (float) $product->price + $addonExtra + $ingExtra;

        if ($this->editingCartId) {
            foreach ($this->cart as &$item) {
                if ($item['cart_id'] === $this->editingCartId) {
                    $item['addons'] = $addons;
                    $item['ingredients'] = $ingredients;
                    $item['quantity'] = $this->itemQuantity;
                    $item['notes'] = $this->itemNotes;
                    $item['unit_extra'] = $addonExtra + $ingExtra;
                    $item['unit_total'] = $unitTotal;
                    $item['subtotal'] = $unitTotal * $this->itemQuantity;
                    break;
                }
            }
            unset($item);
        } else {
            $dupIndex = $this->findDuplicateCartItem($product->id, $addons, $ingredients);

            if ($dupIndex !== null && $this->itemNotes === '') {
                if ($this->cart[$dupIndex]['quantity'] + $this->itemQuantity > self::MAX_ITEM_QUANTITY) {
                    $this->addError('itemQuantity', 'La cantidad acumulada del producto no puede superar 99.');

                    return;
                }
                $this->cart[$dupIndex]['quantity'] += $this->itemQuantity;
                $this->cart[$dupIndex]['subtotal'] = $this->cart[$dupIndex]['unit_total'] * $this->cart[$dupIndex]['quantity'];
            } else {
                $this->cart[] = [
                    'cart_id' => Str::uuid()->toString(),
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_price' => (float) $product->price,
                    'product_image' => $product->image,
                    'quantity' => $this->itemQuantity,
                    'unit_extra' => $addonExtra + $ingExtra,
                    'unit_total' => $unitTotal,
                    'subtotal' => $unitTotal * $this->itemQuantity,
                    'notes' => $this->itemNotes,
                    'addons' => $addons,
                    'ingredients' => $ingredients,
                ];
            }
        }

        $this->resetCustomizationState();
        unset($this->cartTotal, $this->cartCount);
        $this->saveCart();
    }

    public function removeCartItem(string $cartId): void
    {
        $this->cart = collect($this->cart)->reject(fn ($i) => $i['cart_id'] === $cartId)->values()->toArray();
        unset($this->cartTotal, $this->cartCount);
        $this->saveCart();
    }

    public function incrementCartItem(string $cartId): void
    {
        foreach ($this->cart as &$item) {
            if ($item['cart_id'] === $cartId) {
                if ($item['quantity'] >= self::MAX_ITEM_QUANTITY) {
                    $this->dispatch('notify', type: 'warning', message: 'La cantidad máxima por producto es 99.');
                    break;
                }
                $item['quantity']++;
                $item['subtotal'] = $item['unit_total'] * $item['quantity'];
                break;
            }
        }
        unset($item);
        unset($this->cartTotal, $this->cartCount);
        $this->saveCart();
    }

    public function decrementCartItem(string $cartId): void
    {
        foreach ($this->cart as $index => $item) {
            if ($item['cart_id'] === $cartId) {
                if ($item['quantity'] <= 1) {
                    array_splice($this->cart, $index, 1);
                } else {
                    $this->cart[$index]['quantity']--;
                    $this->cart[$index]['subtotal'] = $this->cart[$index]['unit_total'] * $this->cart[$index]['quantity'];
                }
                break;
            }
        }
        unset($this->cartTotal, $this->cartCount);
        $this->saveCart();
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->activeQuotationId = null;
        $this->resetOrderForm();
        unset($this->cartTotal, $this->cartCount);
        $this->saveCart();
    }

    public function confirmClearCart(): void
    {
        $this->dispatch('open-confirm',
            type: 'warning',
            title: 'Vaciar carrito',
            message: '¿Eliminar todos los productos del pedido actual?',
            action: 'clearCart',
            confirmText: 'Vaciar',
            cancelText: 'Cancelar',
        );
    }

    private function findDuplicateCartItem(int $productId, array $addons, array $ingredients): ?int
    {
        return $this->cartState()->findMatchingLine($productId, $addons, $ingredients);
    }

    /**
     * El carrito como objeto de valor. Ver `App\Support\Pos\CartState`.
     */
    private function cartState(): CartState
    {
        return CartState::fromArray($this->cart);
    }

    private function cartContentsAreValid(): bool
    {
        if (mb_strlen($this->orderNotes) > self::MAX_ITEM_NOTES_LENGTH) {
            $this->addError('orderNotes', 'La nota general no puede superar 500 caracteres.');

            return false;
        }

        foreach ($this->cart as $item) {
            $quantity = (int) ($item['quantity'] ?? 0);
            if ($quantity < 1 || $quantity > self::MAX_ITEM_QUANTITY) {
                $this->dispatch('notify', type: 'warning', message: 'Cada producto debe tener una cantidad entre 1 y 99.');

                return false;
            }
        }

        return true;
    }
}
