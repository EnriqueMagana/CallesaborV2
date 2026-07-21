<?php

namespace App\Livewire\Mesas;

use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Mesa;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAddon;
use App\Models\OrderItemIngredient;
use App\Models\Product;
use Livewire\Attributes\Computed;
use Livewire\Component;

class MesaOrden extends Component
{
    public int    $mesaId;
    public string $search         = '';
    public ?int   $categoryFilter = null;
    public array  $cart           = [];
    public string $orderNotes     = '';

    // Customize modal
    public bool    $showCustomize       = false;
    public ?int    $customizingProduct  = null;
    public ?string $editingCartId       = null;
    public array   $selectedAddons      = [];
    public array   $selectedIngredients = [];
    public string  $itemNotes          = '';
    public int     $itemQty            = 1;

    // Cart drawer (mobile)
    public bool $showCartDrawer = false;

    public function mount(Mesa $mesa): void
    {
        $assignment = $mesa->currentAssignment;
        $user       = auth()->user();

        if (!$user->hasAnyRole(['admin', 'super-admin', 'gerente', 'cajero'])) {
            if (!$assignment || $assignment->user_id !== $user->id) {
                session()->flash('error', 'No tienes acceso a esta mesa.');
                $this->redirect(route('app.mesas'));
                return;
            }
        }

        if (!in_array($mesa->status, ['ocupada', 'en_cuenta'])) {
            session()->flash('error', 'Esta mesa no está activa.');
            $this->redirect(route('app.mesas'));
            return;
        }

        $this->mesaId = $mesa->id;
    }

    #[Computed]
    public function mesa(): Mesa
    {
        return Mesa::with([
            'area',
            'currentAssignment.waiter',
        ])->findOrFail($this->mesaId);
    }

    #[Computed]
    public function categories()
    {
        return Category::where('is_active', true)
            ->with(['products' => fn($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();
    }

    #[Computed]
    public function filteredProducts()
    {
        $cats = $this->categories;

        if ($this->categoryFilter) {
            $cats = $cats->where('id', $this->categoryFilter)->values();
        }

        if (trim($this->search)) {
            $s = mb_strtolower(trim($this->search));
            return $cats->map(function ($cat) use ($s) {
                $cat->setRelation('products', $cat->products->filter(
                    fn($p) => str_contains(mb_strtolower($p->name), $s)
                )->values());
                return $cat;
            })->filter(fn($cat) => $cat->products->isNotEmpty())->values();
        }

        return $cats;
    }

    #[Computed]
    public function customizingProductModel(): ?Product
    {
        if (!$this->customizingProduct) return null;
        return Product::with([
            'addonGroups' => fn($q) => $q->where('is_active', true)
                ->with(['addons' => fn($q) => $q->where('is_active', true)->orderBy('sort_order')]),
            'ingredients' => fn($q) => $q->where('is_active', true)->orderBy('sort_order'),
        ])->find($this->customizingProduct);
    }

    #[Computed]
    public function cartTotal(): float
    {
        $total = 0;
        foreach ($this->cart as $line) {
            $total += ($line['unit_total'] ?? $line['price'] ?? 0) * $line['qty'];
        }
        return round($total, 2);
    }

    #[Computed]
    public function cartCount(): int
    {
        return collect($this->cart)->sum('qty');
    }

    // ── Open customize modal ──

    public function openCustomize(int $productId): void
    {
        $product = Product::with([
            'addonGroups.addons',
            'ingredients',
        ])->find($productId);

        if (!$product) return;

        // If product has no customizations, add directly
        if (!$product->is_customizable && $product->addonGroups->isEmpty() && $product->ingredients->isEmpty()) {
            $this->addDirect($productId, $product->name, (float) $product->price);
            return;
        }

        $this->customizingProduct  = $productId;
        $this->editingCartId       = null;
        $this->selectedAddons      = [];
        $this->selectedIngredients = [];
        $this->itemNotes           = '';
        $this->itemQty             = 1;
        $this->showCustomize       = true;
        unset($this->customizingProductModel);
        $this->preselectRequiredSingletons();
    }

    public function editCartItem(string $cartId): void
    {
        $item = collect($this->cart)->firstWhere('cart_id', $cartId);
        if (!$item) return;

        $this->customizingProduct  = $item['product_id'];
        $this->editingCartId       = $cartId;
        $this->selectedAddons      = collect($item['addons'] ?? [])
            ->mapWithKeys(fn($a) => [$a['addon_id'] => true])->toArray();
        $this->selectedIngredients = collect($item['ingredients'] ?? [])
            ->mapWithKeys(fn($i) => [$i['ingredient_id'] => $i['quantity']])->toArray();
        $this->itemNotes           = $item['notes'];
        $this->itemQty             = $item['qty'];
        $this->showCustomize       = true;
        unset($this->customizingProductModel);
    }

    public function addDirect(int $productId, string $name, float $price): void
    {
        $cartId = $productId . '_plain';
        $found  = false;
        foreach ($this->cart as &$line) {
            if ($line['cart_id'] === $cartId) {
                $line['qty']++;
                $found = true;
                break;
            }
        }
        unset($line);

        if (!$found) {
            $this->cart[] = [
                'cart_id'    => $cartId,
                'product_id' => $productId,
                'name'       => $name,
                'price'      => $price,
                'unit_total' => $price,
                'qty'        => 1,
                'notes'      => '',
                'addons'     => [],
                'ingredients'=> [],
            ];
        }

        unset($this->cartTotal, $this->cartCount);
    }

    public function toggleAddon(int $addonId): void
    {
        if (isset($this->selectedAddons[$addonId])) {
            $product = $this->customizingProductModel;
            $group = $product?->addonGroups->first(fn ($candidate) => $candidate->addons->contains('id', $addonId));
            if ($group) {
                $minimum = $this->effectiveGroupMinimum($group);
                $selected = collect($group->addons)->filter(fn ($addon) => isset($this->selectedAddons[$addon->id]))->count();
                if ($selected <= $minimum) {
                    $this->addError('addons_'.$group->id, "Debes mantener al menos {$minimum} opcion(es).");
                    return;
                }
            }
            unset($this->selectedAddons[$addonId]);
            return;
        }

        $product = $this->customizingProductModel;
        $group = $product?->addonGroups->first(fn ($candidate) => $candidate->addons->contains('id', $addonId));
        if (! $group) return;

        $minimum = $this->effectiveGroupMinimum($group);
        $configuredMax = (int) $group->max_selections;
        $maximum = $configuredMax > 0 ? max($minimum, min($configuredMax, $group->addons->count())) : $group->addons->count();
        $selected = collect($group->addons)->filter(fn ($addon) => isset($this->selectedAddons[$addon->id]))->count();
        $productMaxAddons = (int) ($product->max_addons ?? 0);
        $totalSelectedAddons = collect($this->selectedAddons)->filter(fn ($selected) => $selected)->count();
        if ($productMaxAddons > 0 && ($totalSelectedAddons - $selected) >= $productMaxAddons) {
            $this->addError('addons_'.$group->id, "Este producto permite máximo {$productMaxAddons} complemento(s).");
            return;
        }
        if ($maximum === 1) {
            foreach ($group->addons as $addon) unset($this->selectedAddons[$addon->id]);
        } elseif ($selected >= $maximum) {
            $this->addError('addons_'.$group->id, "Maximo {$maximum} opcion(es) permitido.");
            return;
        }

        $this->selectedAddons[$addonId] = true;
        $this->resetErrorBag('addons_'.$group->id);
    }

    private function effectiveGroupMinimum($group): int
    {
        return $group->is_required ? max(1, (int) $group->min_selections) : (int) $group->min_selections;
    }

    private function preselectRequiredSingletons(): void
    {
        $product = $this->customizingProductModel;
        if (! $product) return;

        foreach ($product->addonGroups as $group) {
            if ($this->effectiveGroupMinimum($group) > 0 && $group->addons->count() === 1) {
                $this->selectedAddons[$group->addons->first()->id] = true;
            }
        }

        $maxAddons = (int) ($product->max_addons ?? 0);
        $totalAddons = collect($this->selectedAddons)->filter(fn ($selected) => $selected)->count();
        if ($maxAddons > 0 && $totalAddons > $maxAddons) {
            $this->addError('addons_general', "Este producto permite máximo {$maxAddons} complemento(s).");
            return;
        }
    }

    public function setIngredientQty(int $ingredientId, int $qty): void
    {
        $product = $this->customizingProductModel;
        if (! $product || ! $product->ingredients->contains('id', $ingredientId)) return;

        $current = (int) ($this->selectedIngredients[$ingredientId] ?? 0);
        if ($qty <= 0) {
            unset($this->selectedIngredients[$ingredientId]);
            return;
        }

        $max = (int) ($product->max_ingredients ?? 0);
        $totalWithoutCurrent = array_sum($this->selectedIngredients) - $current;
        if ($max > 0 && $totalWithoutCurrent + $qty > $max) {
            $this->addError('ingredients', "Maximo {$max} ingrediente(s) permitido.");
            return;
        }

        $this->selectedIngredients[$ingredientId] = $qty;
        $this->resetErrorBag('ingredients');
    }

    public function confirmCustomize(): void
    {
        $product = $this->customizingProductModel;
        if (!$product) return;

        // Validate required addon groups
        foreach ($product->addonGroups as $group) {
            $selected = collect($group->addons)
                ->filter(fn($a) => isset($this->selectedAddons[$a->id]))
                ->count();
            $minimum = $this->effectiveGroupMinimum($group);
            $configuredMax = (int) $group->max_selections;
            $maximum = $configuredMax > 0 ? max($minimum, min($configuredMax, $group->addons->count())) : $group->addons->count();
            if ($selected < $minimum) {
                $this->addError('addons_'.$group->id, "Selecciona al menos {$minimum} opcion(es) en {$group->name}.");
                return;
            }
            if ($maximum !== null && $selected > $maximum) {
                $this->addError('addons_'.$group->id, "Selecciona maximo {$maximum} opcion(es) en {$group->name}.");
                return;
            }
        }

        $totalIngredients = array_sum($this->selectedIngredients);
        $minimumIngredients = (int) ($product->min_ingredients ?? 0);
        $maximumIngredients = (int) ($product->max_ingredients ?? 0);
        if ($totalIngredients < $minimumIngredients) {
            $this->addError('ingredients', "Selecciona al menos {$minimumIngredients} ingrediente(s).");
            return;
        }
        if ($maximumIngredients > 0 && $totalIngredients > $maximumIngredients) {
            $this->addError('ingredients', "Selecciona maximo {$maximumIngredients} ingrediente(s).");
            return;
        }

        if ($this->itemQty < 1 || $this->itemQty > 99) {
            $this->addError('itemQty', 'La cantidad debe estar entre 1 y 99.');
            return;
        }

        $addons = [];
        foreach ($product->addonGroups as $group) {
            foreach ($group->addons as $addon) {
                if (isset($this->selectedAddons[$addon->id])) {
                    $addons[] = [
                        'addon_id'    => $addon->id,
                        'addon_name'  => $addon->name,
                        'group_name'  => $group->name,
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
                    'ingredient_id'   => $ing->id,
                    'ingredient_name' => $ing->name,
                    'quantity'        => $qty,
                    'extra_price'     => (float) $ing->extra_price,
                ];
            }
        }

        $addonExtra = array_sum(array_column($addons, 'extra_price'));
        $ingExtra   = array_sum(array_map(fn($i) => $i['extra_price'] * $i['quantity'], $ingredients));
        $unitTotal  = (float) $product->price + $addonExtra + $ingExtra;

        if ($this->editingCartId) {
            foreach ($this->cart as &$line) {
                if ($line['cart_id'] === $this->editingCartId) {
                    $line['addons']      = $addons;
                    $line['ingredients'] = $ingredients;
                    $line['unit_total']  = $unitTotal;
                    $line['qty']         = $this->itemQty;
                    $line['notes']       = $this->itemNotes;
                    break;
                }
            }
            unset($line);
        } else {
            $cartId = uniqid('item_');
            $this->cart[] = [
                'cart_id'    => $cartId,
                'product_id' => $product->id,
                'name'       => $product->name,
                'price'      => (float) $product->price,
                'unit_total' => $unitTotal,
                'qty'        => $this->itemQty,
                'notes'      => $this->itemNotes,
                'addons'     => $addons,
                'ingredients'=> $ingredients,
            ];
        }

        $this->showCustomize = false;
        unset($this->cartTotal, $this->cartCount);
    }

    public function incrementQty(string $cartId): void
    {
        foreach ($this->cart as &$line) {
            if ($line['cart_id'] === $cartId) { $line['qty']++; break; }
        }
        unset($line);
        unset($this->cartTotal, $this->cartCount);
    }

    public function decrementQty(string $cartId): void
    {
        foreach ($this->cart as $i => $line) {
            if ($line['cart_id'] === $cartId) {
                if ($line['qty'] <= 1) {
                    array_splice($this->cart, $i, 1);
                } else {
                    $this->cart[$i]['qty']--;
                }
                break;
            }
        }
        unset($this->cartTotal, $this->cartCount);
    }

    public function removeFromCart(string $cartId): void
    {
        $this->cart = array_values(array_filter($this->cart, fn($l) => $l['cart_id'] !== $cartId));
        unset($this->cartTotal, $this->cartCount);
    }

    // ── Place order ──

    public function placeOrder(): void
    {
        if (empty($this->cart)) {
            $this->addError('cart', 'Agrega al menos un producto.');
            return;
        }

        $register = CashRegister::where('is_open', true)->first();
        if (!$register) {
            $this->addError('cart', 'No hay caja abierta. Pide al cajero que abra la caja primero.');
            return;
        }

        $total = $this->cartTotal;

        $order = Order::create([
            'cash_register_id' => $register->id,
            'mesa_id'          => $this->mesaId,
            'served_by'        => auth()->id(),
            'type'             => 'mesa',
            'status'           => 'pendiente',
            'subtotal'         => $total,
            'total'            => $total,
            'notes'            => $this->orderNotes ?: null,
        ]);

        foreach ($this->cart as $line) {
            $item = OrderItem::create([
                'order_id'      => $order->id,
                'product_id'    => $line['product_id'],
                'product_name'  => $line['name'],
                'product_price' => $line['price'],
                'quantity'      => $line['qty'],
                'subtotal'      => round($line['unit_total'] * $line['qty'], 2),
                'notes'         => $line['notes'] ?: null,
            ]);

            foreach ($line['addons'] ?? [] as $addon) {
                OrderItemAddon::create([
                    'order_item_id' => $item->id,
                    'addon_id'      => $addon['addon_id'],
                    'addon_name'    => $addon['addon_name'],
                    'extra_price'   => $addon['extra_price'],
                ]);
            }

            foreach ($line['ingredients'] ?? [] as $ing) {
                OrderItemIngredient::create([
                    'order_item_id'   => $item->id,
                    'ingredient_id'   => $ing['ingredient_id'],
                    'ingredient_name' => $ing['ingredient_name'],
                    'quantity'        => $ing['quantity'],
                    'extra_price'     => $ing['extra_price'],
                ]);
            }
        }

        $this->cart       = [];
        $this->orderNotes = '';
        $this->showCartDrawer = false;
        unset($this->mesa, $this->cartTotal, $this->cartCount);

        session()->flash('orderSent', "Orden #{$order->id} enviada a cocina.");
    }

    // ── Close mesa ──

    public function closeMesa(): void
    {
        $mesa = Mesa::find($this->mesaId);
        if (!$mesa || $mesa->status !== 'ocupada') return;

        $mesa->update(['status' => 'en_cuenta']);
        session()->flash('success', "Mesa {$mesa->number} cerrada. Procede al cobro.");
        $this->redirect(route('app.mesas'));
    }

    public function goBack(): void
    {
        $this->redirect(route('app.mesas'));
    }

    public function render()
    {
        return view('livewire.mesas.mesa-orden')
            ->layout('layouts.app');
    }
}
