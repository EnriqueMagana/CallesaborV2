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
use App\Services\MesaServiceManager;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

class MesaOrden extends Component
{
    private const MAX_ITEM_QUANTITY = 99;

    private const MAX_ITEM_NOTES_LENGTH = 500;

    public int $mesaId;

    public string $search = '';

    public ?int $categoryFilter = null;

    public array $cart = [];

    public string $orderNotes = '';

    // Customize modal
    public bool $showCustomize = false;

    public ?int $customizingProduct = null;

    public ?string $editingCartId = null;

    public array $selectedAddons = [];

    public array $selectedIngredients = [];

    public string $itemNotes = '';

    public int $itemQty = 1;

    // Cart drawer (mobile)
    public bool $showCartDrawer = false;

    public bool $showCloseModal = false;

    public function mount(Mesa $mesa): void
    {
        $assignment = $mesa->currentAssignment;
        $user = auth()->user();

        abort_unless($user?->can('ordenar mesas'), 403);

        if (! $user->hasAnyRole(['admin', 'super-admin', 'gerente', 'cajero'])) {
            if (! $assignment || $assignment->user_id !== $user->id) {
                session()->flash('error', 'No tienes acceso a esta mesa.');
                $this->redirect(route('app.mesas'));

                return;
            }
        }

        if ($mesa->status !== 'ocupada') {
            session()->flash('error', 'La cuenta está cerrada. Reabre la mesa antes de agregar pedidos.');
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

    #[Computed(persist: true, seconds: 60)]
    public function categories()
    {
        return Category::where('is_active', true)
            ->select(['id', 'name', 'icon', 'sort_order'])
            ->with(['products' => fn ($q) => $q
                ->where('is_active', true)
                ->select([
                    'id', 'category_id', 'name', 'description', 'image', 'price',
                    'is_customizable', 'max_addons', 'min_ingredients',
                    'max_ingredients', 'sort_order',
                ])
                ->withCount(['addonGroups', 'ingredients'])
                ->orderBy('sort_order')])
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
                    fn ($p) => str_contains(mb_strtolower($p->name), $s)
                )->values());

                return $cat;
            })->filter(fn ($cat) => $cat->products->isNotEmpty())->values();
        }

        return $cats;
    }

    #[Computed]
    public function customizingProductModel(): ?Product
    {
        if (! $this->customizingProduct) {
            return null;
        }

        return Product::with([
            'addonGroups' => fn ($q) => $q->where('is_active', true)
                ->select([
                    'addon_groups.id', 'addon_groups.name', 'addon_groups.description',
                    'addon_groups.is_required', 'addon_groups.min_selections',
                    'addon_groups.max_selections', 'addon_groups.sort_order',
                ])
                ->with(['addons' => fn ($q) => $q
                    ->where('is_active', true)
                    ->select([
                        'id', 'addon_group_id', 'name', 'description',
                        'image', 'extra_price', 'sort_order',
                    ])
                    ->orderBy('sort_order')]),
            'ingredients' => fn ($q) => $q
                ->where('is_active', true)
                ->select([
                    'ingredients.id', 'ingredients.name', 'ingredients.description',
                    'ingredients.image', 'ingredients.extra_price',
                    'ingredients.sort_order',
                ])
                ->orderBy('ingredients.sort_order'),
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
        $product = Product::query()
            ->where('is_active', true)
            ->select(['id', 'name', 'price', 'image', 'is_customizable'])
            ->withCount(['addonGroups', 'ingredients'])
            ->find($productId);

        if (! $product) {
            return;
        }

        // If product has no customizations, add directly
        if (! $product->is_customizable && $product->addon_groups_count === 0 && $product->ingredients_count === 0) {
            $this->addProductToCart($product);

            return;
        }

        $this->customizingProduct = $productId;
        $this->editingCartId = null;
        $this->selectedAddons = [];
        $this->selectedIngredients = [];
        $this->itemNotes = '';
        $this->itemQty = 1;
        $this->showCustomize = true;
        unset($this->customizingProductModel);
        $this->preselectRequiredSingletons();
    }

    public function closeCustomize(): void
    {
        $this->resetErrorBag();
        $this->resetCustomizationState();
    }

    private function resetCustomizationState(): void
    {
        $this->showCustomize = false;
        $this->customizingProduct = null;
        $this->editingCartId = null;
        $this->selectedAddons = [];
        $this->selectedIngredients = [];
        $this->itemNotes = '';
        $this->itemQty = 1;
        unset($this->customizingProductModel);
    }

    public function editCartItem(string $cartId): void
    {
        $item = collect($this->cart)->firstWhere('cart_id', $cartId);
        if (! $item) {
            return;
        }

        $this->customizingProduct = $item['product_id'];
        $this->editingCartId = $cartId;
        $this->selectedAddons = collect($item['addons'] ?? [])
            ->mapWithKeys(fn ($a) => [$a['addon_id'] => true])->toArray();
        $this->selectedIngredients = collect($item['ingredients'] ?? [])
            ->mapWithKeys(fn ($i) => [$i['ingredient_id'] => $i['quantity']])->toArray();
        $this->itemNotes = $item['notes'];
        $this->itemQty = $item['qty'];
        $this->showCustomize = true;
        unset($this->customizingProductModel);
    }

    public function addDirect(int $productId): void
    {
        $product = Product::query()
            ->where('is_active', true)
            ->select(['id', 'name', 'price', 'image'])
            ->find($productId);

        if ($product) {
            $this->addProductToCart($product);
        }
    }

    private function addProductToCart(Product $product): void
    {
        $cartId = $product->id.'_plain';
        $found = false;
        foreach ($this->cart as &$line) {
            if ($line['cart_id'] === $cartId) {
                if ($line['qty'] >= self::MAX_ITEM_QUANTITY) {
                    $this->addError('cart', 'La cantidad máxima por producto es 99.');
                    unset($line);

                    return;
                }
                $line['qty']++;
                $found = true;
                break;
            }
        }
        unset($line);

        if (! $found) {
            $this->cart[] = [
                'cart_id' => $cartId,
                'product_id' => $product->id,
                'name' => $product->name,
                'image' => $product->image,
                'price' => (float) $product->price,
                'unit_total' => (float) $product->price,
                'qty' => 1,
                'notes' => '',
                'addons' => [],
                'ingredients' => [],
            ];
        }

        unset($this->cartTotal, $this->cartCount);
        $this->resetErrorBag('cart');
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
        if (! $group) {
            return;
        }

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
            foreach ($group->addons as $addon) {
                unset($this->selectedAddons[$addon->id]);
            }
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
        if (! $product) {
            return;
        }

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
        if (! $product || ! $product->ingredients->contains('id', $ingredientId)) {
            return;
        }

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

    public function confirmCustomize(
        ?array $addonIds = null,
        ?array $ingredientQuantities = null,
        ?int $quantity = null,
        ?string $notes = null,
    ): void {
        $product = $this->customizingProductModel;
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
            $this->itemQty = $quantity;
        }
        if ($notes !== null) {
            $this->itemNotes = trim($notes);
        }

        if (mb_strlen($this->itemNotes) > self::MAX_ITEM_NOTES_LENGTH) {
            $this->addError('itemNotes', 'La nota no puede superar 500 caracteres.');

            return;
        }

        // Validate required addon groups
        foreach ($product->addonGroups as $group) {
            $selected = collect($group->addons)
                ->filter(fn ($a) => isset($this->selectedAddons[$a->id]))
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

        $maximumAddons = (int) ($product->max_addons ?? 0);
        $totalAddons = collect($this->selectedAddons)->filter()->count();
        if ($maximumAddons > 0 && $totalAddons > $maximumAddons) {
            $this->addError('addons_general', "Este producto permite máximo {$maximumAddons} complemento(s).");

            return;
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

        if ($this->itemQty < 1 || $this->itemQty > self::MAX_ITEM_QUANTITY) {
            $this->addError('itemQty', 'La cantidad debe estar entre 1 y 99.');

            return;
        }

        $addons = [];
        foreach ($product->addonGroups as $group) {
            foreach ($group->addons as $addon) {
                if (isset($this->selectedAddons[$addon->id])) {
                    $addons[] = [
                        'addon_id' => $addon->id,
                        'addon_name' => $addon->name,
                        'group_name' => $group->name,
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
                    'quantity' => $qty,
                    'extra_price' => (float) $ing->extra_price,
                ];
            }
        }

        $addonExtra = array_sum(array_column($addons, 'extra_price'));
        $ingExtra = array_sum(array_map(fn ($i) => $i['extra_price'] * $i['quantity'], $ingredients));
        $unitTotal = (float) $product->price + $addonExtra + $ingExtra;

        if ($this->editingCartId) {
            foreach ($this->cart as &$line) {
                if ($line['cart_id'] === $this->editingCartId) {
                    $line['addons'] = $addons;
                    $line['ingredients'] = $ingredients;
                    $line['unit_total'] = $unitTotal;
                    $line['qty'] = $this->itemQty;
                    $line['notes'] = $this->itemNotes;
                    break;
                }
            }
            unset($line);
        } else {
            $cartId = uniqid('item_');
            $this->cart[] = [
                'cart_id' => $cartId,
                'product_id' => $product->id,
                'name' => $product->name,
                'image' => $product->image,
                'price' => (float) $product->price,
                'unit_total' => $unitTotal,
                'qty' => $this->itemQty,
                'notes' => $this->itemNotes,
                'addons' => $addons,
                'ingredients' => $ingredients,
            ];
        }

        $this->resetCustomizationState();
        unset($this->cartTotal, $this->cartCount);
    }

    public function incrementQty(string $cartId): void
    {
        foreach ($this->cart as &$line) {
            if ($line['cart_id'] === $cartId) {
                if ($line['qty'] >= self::MAX_ITEM_QUANTITY) {
                    $this->addError('cart', 'La cantidad máxima por producto es 99.');
                    break;
                }
                $line['qty']++;
                $this->resetErrorBag('cart');
                break;
            }
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
        $this->cart = array_values(array_filter($this->cart, fn ($l) => $l['cart_id'] !== $cartId));
        unset($this->cartTotal, $this->cartCount);
    }

    // ── Place order ──

    public function placeOrder(): void
    {
        $this->requirePermission('ordenar mesas');

        if (empty($this->cart)) {
            $this->addError('cart', 'Agrega al menos un producto.');

            return;
        }

        if (mb_strlen($this->orderNotes) > self::MAX_ITEM_NOTES_LENGTH) {
            $this->addError('cart', 'La nota general no puede superar 500 caracteres.');

            return;
        }

        foreach ($this->cart as $line) {
            $quantity = (int) ($line['qty'] ?? 0);
            if ($quantity < 1 || $quantity > self::MAX_ITEM_QUANTITY) {
                $this->addError('cart', 'Cada producto debe tener una cantidad entre 1 y 99.');

                return;
            }
        }

        $register = CashRegister::where('is_open', true)->first();
        if (! $register) {
            $this->addError('cart', 'No hay caja abierta. Pide al cajero que abra la caja primero.');

            return;
        }

        $currentMesa = Mesa::find($this->mesaId);
        if (! $currentMesa || $currentMesa->status !== 'ocupada') {
            $this->addError('cart', 'La cuenta se cerró mientras ordenabas. Reabre la mesa antes de enviar productos.');

            return;
        }

        $currentService = app(MesaServiceManager::class)->findActiveForMesa($currentMesa, $register->id);
        if ($currentService?->status === 'en_cuenta'
            || $currentService?->splits()->whereIn('status', ['pendiente', 'parcial'])->exists()) {
            $this->addError('cart', 'Esta cuenta está bloqueada para cobro. Reabre la mesa antes de enviar productos.');

            return;
        }

        $total = $this->cartTotal;

        $order = DB::transaction(function () use ($register, $total) {
            $mesa = Mesa::lockForUpdate()->findOrFail($this->mesaId);
            abort_unless($mesa->status === 'ocupada', 409, 'La cuenta ya fue cerrada.');

            $service = app(MesaServiceManager::class)->resolveOrCreate(
                $mesa,
                $register,
                auth()->id()
            );
            abort_if(
                $service->status !== 'abierta'
                    || $service->splits()->whereIn('status', ['pendiente', 'parcial'])->exists(),
                409,
                'La cuenta está bloqueada para cobro.'
            );

            $order = Order::create([
                'cash_register_id' => $register->id,
                'mesa_id' => $this->mesaId,
                'mesa_service_id' => $service->id,
                'served_by' => auth()->id(),
                'type' => 'mesa',
                'status' => 'pendiente',
                'subtotal' => $total,
                'total' => $total,
                'notes' => $this->orderNotes ?: null,
            ]);

            $addonRows = [];
            $ingredientRows = [];
            $timestamp = now();

            foreach ($this->cart as $line) {
                $item = OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $line['product_id'],
                    'product_name' => $line['name'],
                    'product_price' => $line['price'],
                    'quantity' => $line['qty'],
                    'subtotal' => round($line['unit_total'] * $line['qty'], 2),
                    'notes' => $line['notes'] ?: null,
                ]);

                foreach ($line['addons'] ?? [] as $addon) {
                    $addonRows[] = [
                        'order_item_id' => $item->id,
                        'addon_id' => $addon['addon_id'],
                        'addon_name' => $addon['addon_name'],
                        'extra_price' => $addon['extra_price'],
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }

                foreach ($line['ingredients'] ?? [] as $ingredient) {
                    $ingredientRows[] = [
                        'order_item_id' => $item->id,
                        'ingredient_id' => $ingredient['ingredient_id'],
                        'ingredient_name' => $ingredient['ingredient_name'],
                        'quantity' => $ingredient['quantity'],
                        'extra_price' => $ingredient['extra_price'],
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }
            }

            if ($addonRows !== []) {
                OrderItemAddon::insert($addonRows);
            }
            if ($ingredientRows !== []) {
                OrderItemIngredient::insert($ingredientRows);
            }

            return $order;
        });

        $this->cart = [];
        $this->orderNotes = '';
        $this->showCartDrawer = false;
        unset($this->mesa, $this->cartTotal, $this->cartCount);

        session()->flash('orderSent', "Orden #{$order->id} enviada a cocina.");
    }

    // ── Close mesa ──

    public function openCloseMesa(): void
    {
        $this->requirePermission('cerrar mesas');

        if ($this->mesa->status !== 'ocupada') {
            session()->flash('error', 'La mesa ya no está disponible para cerrar.');
            $this->redirect(route('app.mesas'));

            return;
        }

        $this->showCloseModal = true;
    }

    public function closeCloseModal(): void
    {
        $this->showCloseModal = false;
    }

    public function confirmCloseMesa(string $mode): void
    {
        abort_unless(in_array($mode, ['full', 'split'], true), 422);
        if ($mode === 'split') {
            $this->requirePermission('dividir mesas');
        }

        $this->showCloseModal = false;
        $this->closeMesa($mode === 'split');
    }

    public function closeMesa(bool $divide = false): void
    {
        $this->requirePermission('cerrar mesas');
        if ($divide) {
            $this->requirePermission('dividir mesas');
        }

        $mesa = Mesa::find($this->mesaId);
        if (! $mesa || $mesa->status !== 'ocupada') {
            return;
        }

        $register = CashRegister::where('is_open', true)->latest('id')->first();
        if (! $register) {
            $this->addError('cart', 'No hay una caja abierta para cerrar la mesa.');

            return;
        }

        $manager = app(MesaServiceManager::class);
        $service = $manager->resolveOrCreate($mesa, $register, auth()->id());
        if ($service->splits()->whereIn('status', ['pendiente', 'parcial'])->exists()) {
            $this->addError('cart', 'Esta mesa ya tiene una cuenta dividida pendiente.');

            return;
        }

        $service = $manager->markInAccount($mesa, $register->id);
        $memberIds = $service?->mesas()->pluck('mesas.id')->all() ?: [$mesa->id];
        Mesa::whereIn('id', $memberIds)->update(['status' => 'en_cuenta']);
        session()->flash('success', $divide
            ? "Mesa {$mesa->number} cerrada. Divide la cuenta y envíala a caja."
            : "Mesa {$mesa->number} cerrada y enviada a caja para cobro conjunto.");
        $this->redirect($divide ? route('app.mesas.split', $mesa) : route('app.mesas'));
    }

    public function goBack(): void
    {
        $this->redirect(route('app.mesas'));
    }

    private function requirePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }

    public function render()
    {
        return view('livewire.mesas.mesa-orden')
            ->layout('layouts.app');
    }
}
