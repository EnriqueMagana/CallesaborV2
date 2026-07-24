<?php

namespace App\Livewire\Kiosk;

use App\Models\CashRegister;
use App\Models\Category;
use App\Models\KioskProductPromotion;
use App\Models\KioskTerminal;
use App\Models\Mesa;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAddon;
use App\Models\OrderItemIngredient;
use App\Models\Product;
use App\Services\KioskQrCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.kiosk')]
class OrderWizard extends Component
{
    public ?int $terminalId = null;

    public string $terminalToken;

    public string $accessState = 'ready';

    public ?string $unavailableTerminalName = null;

    public ?string $recommendationName = null;

    public ?int $featuredProductIntent = null;

    public int $step = 1;

    public string $fulfillment = '';

    public ?int $selectedMesaId = null;

    public string $search = '';

    public ?int $categoryFilter = null;

    public array $cart = [];

    public ?int $customizingProduct = null;

    public array $selectedAddons = [];

    public array $addonQuantities = [];

    public array $selectedIngredients = [];

    public string $itemNotes = '';

    public int $itemQuantity = 1;

    public string $customerName = '';

    public string $customerPhone = '';

    public string $deliveryStreet = '';

    public string $deliveryNeighborhood = '';

    public string $deliveryReferences = '';

    public string $orderNotes = '';

    public ?int $completedOrderId = null;

    public ?string $publicToken = null;

    public bool $submitting = false;

    public function mount(string $token): void
    {
        $this->terminalToken = $token;
        $terminal = KioskTerminal::findByPlainToken($token, false);

        if (! $terminal) {
            $this->accessState = 'invalid';

            return;
        }

        $this->terminalId = $terminal->id;
        $this->unavailableTerminalName = $terminal->name;

        if (! $terminal->is_active) {
            $this->accessState = 'paused';

            return;
        }

        $terminal->update(['last_used_at' => now()]);
    }

    #[Computed]
    public function terminal(): KioskTerminal
    {
        $terminal = KioskTerminal::findByPlainToken($this->terminalToken, false);
        abort_unless($terminal && $terminal->id === $this->terminalId, 404);

        return $terminal;
    }

    #[Computed]
    public function categories()
    {
        return Category::query()
            ->where('is_active', true)
            ->whereHas('products', fn ($query) => $query->where('is_active', true))
            ->with(['products' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();
    }

    #[Computed]
    public function products()
    {
        return Product::query()
            ->withCount(['addonGroups', 'ingredients'])
            ->where('is_active', true)
            ->when($this->categoryFilter, fn ($query) => $query->where('category_id', $this->categoryFilter))
            ->when(trim($this->search), function ($query) {
                $term = '%'.trim($this->search).'%';
                $query->where(fn ($inner) => $inner->where('name', 'like', $term)->orWhere('description', 'like', $term));
            })
            ->orderBy('sort_order')
            ->get();
    }

    #[Computed]
    public function promotions()
    {
        if (! $this->terminal->promotion_enabled) {
            return collect();
        }

        return KioskProductPromotion::query()
            ->where('kiosk_terminal_id', $this->terminalId)
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->with(['product' => fn ($query) => $query
                ->withCount(['addonGroups', 'ingredients'])
                ->with('category:id,name')])
            ->orderBy('sort_order')
            ->get()
            ->keyBy('product_id');
    }

    public function selectFeaturedProduct(int $productId): void
    {
        abort_unless($this->promotions->has($productId), 422);
        $this->featuredProductIntent = $productId;
    }

    public function applySearch(): void
    {
        $this->search = trim($this->search);
        unset($this->products);
    }

    #[Computed]
    public function product(): ?Product
    {
        if (! $this->customizingProduct) {
            return null;
        }

        return Product::with([
            'addonGroups' => fn ($query) => $query->where('is_active', true)
                ->with(['addons' => fn ($addons) => $addons->where('is_active', true)->orderBy('sort_order')]),
            'ingredients' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order'),
        ])->where('is_active', true)->find($this->customizingProduct);
    }

    #[Computed]
    public function cartCount(): int
    {
        return (int) collect($this->cart)->sum('quantity');
    }

    #[Computed]
    public function cartTotal(): float
    {
        return round((float) collect($this->cart)->sum('subtotal'), 2);
    }

    #[Computed]
    public function orderNotesWordCount(): int
    {
        $value = trim($this->orderNotes);

        return $value === '' ? 0 : count(preg_split('/\s+/u', $value) ?: []);
    }

    #[Computed]
    public function qrDataUri(): ?string
    {
        if (! $this->publicToken) {
            return null;
        }

        return app(KioskQrCode::class)->dataUri(route('kiosk.track', $this->publicToken));
    }

    #[Computed]
    public function kioskTables()
    {
        return Mesa::query()
            ->with('area')
            ->where('status', 'disponible')
            ->whereDoesntHave('orders', fn ($query) => $query
                ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada']))
            ->orderBy('number')
            ->get();
    }

    #[Computed]
    public function kioskTableGroups()
    {
        return $this->kioskTables
            ->groupBy(fn (Mesa $mesa) => $mesa->area?->name ?: 'Área general')
            ->sortKeys();
    }

    public function chooseFulfillment(string $fulfillment): void
    {
        abort_unless(in_array($fulfillment, ['dine_in', 'takeaway', 'delivery'], true), 422);
        abort_if($fulfillment === 'dine_in' && ! $this->terminal->allow_dine_in, 422);
        abort_if($fulfillment === 'takeaway' && ! $this->terminal->allow_takeaway, 422);
        abort_if($fulfillment === 'delivery' && ! $this->terminal->allow_delivery, 422);
        $this->fulfillment = $fulfillment;
        $this->selectedMesaId = null;
        $this->step = 2;

        if ($this->featuredProductIntent) {
            $productId = $this->featuredProductIntent;
            $this->featuredProductIntent = null;
            $this->recommendationName = 'Productos destacados';
            $this->step = 3;
            $this->openProduct($productId);
        }
    }

    public function chooseRecommendation(?int $categoryId = null): void
    {
        if ($categoryId) {
            $category = $this->categories->firstWhere('id', $categoryId);
            abort_unless($category, 422);
            $this->categoryFilter = $category->id;
            $this->recommendationName = $category->name;
        } else {
            $this->categoryFilter = null;
            $this->recommendationName = 'Todo el menú';
        }

        $this->step = 3;
    }

    public function openProduct(int $productId): void
    {
        $product = Product::with(['addonGroups.addons', 'ingredients'])
            ->where('is_active', true)->findOrFail($productId);

        if (! $product->is_customizable && $product->addonGroups->isEmpty() && $product->ingredients->isEmpty()) {
            $this->addLine($product, [], [], '', 1);

            return;
        }

        $this->customizingProduct = $product->id;
        $this->selectedAddons = [];
        $this->addonQuantities = [];
        $this->selectedIngredients = [];
        $this->itemNotes = '';
        $this->itemQuantity = 1;

        foreach ($product->addonGroups as $group) {
            $active = $group->addons->where('is_active', true);
            if ($group->is_required && $active->count() === 1) {
                $addonId = (int) $active->first()->id;
                $this->selectedAddons[] = $addonId;
                $this->addonQuantities[$addonId] = 1;
            }
        }

        $this->step = 4;
        unset($this->product);
    }

    public function toggleAddon(int $groupId, int $addonId): void
    {
        $current = (int) ($this->addonQuantities[$addonId] ?? 0);
        $this->changeAddonQuantity($groupId, $addonId, $current > 0 ? -$current : 1);
    }

    public function changeAddonQuantity(int $groupId, int $addonId, int $delta): void
    {
        $product = $this->product;
        $group = $product?->addonGroups->firstWhere('id', $groupId);
        abort_unless($group && $group->addons->where('is_active', true)->contains('id', $addonId), 422);

        $current = (int) ($this->addonQuantities[$addonId] ?? 0);
        $next = max(0, $current + $delta);
        $groupIds = $group->addons->where('is_active', true)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $groupTotal = collect($groupIds)->sum(fn ($id) => (int) ($this->addonQuantities[$id] ?? 0)) - $current + $next;
        $groupMaximum = max(1, (int) $group->max_selections);

        if ($groupMaximum === 1 && $next > 0) {
            foreach ($groupIds as $id) {
                unset($this->addonQuantities[$id]);
            }
            $next = 1;
            $groupTotal = 1;
        }

        if ($groupTotal > $groupMaximum) {
            $this->addError('customization', "En {$group->name} puedes elegir máximo {$groupMaximum} en total.");

            return;
        }

        $allTotal = array_sum(array_map('intval', $this->addonQuantities)) - $current + $next;
        if ($product->max_addons && $allTotal > $product->max_addons) {
            $this->addError('customization', "Este producto permite máximo {$product->max_addons} complementos en total.");

            return;
        }

        $isLockedRequiredOption = $group->is_required
            && $group->addons->where('is_active', true)->count() === 1
            && $next === 0;
        if ($isLockedRequiredOption) {
            $this->addError('customization', "{$group->name} es obligatorio. Debes conservar una opción.");

            return;
        }

        if ($next === 0) {
            unset($this->addonQuantities[$addonId]);
        } else {
            $this->addonQuantities[$addonId] = $next;
        }
        $this->selectedAddons = array_map('intval', array_keys(array_filter($this->addonQuantities)));
        $this->resetErrorBag('customization');
    }

    public function changeIngredient(int $ingredientId, int $delta): void
    {
        abort_unless($this->product?->ingredients->contains('id', $ingredientId), 422);
        $current = (int) ($this->selectedIngredients[$ingredientId] ?? 0);
        $next = max(0, $current + ($delta > 0 ? 1 : -1));
        $total = array_sum($this->selectedIngredients) - $current + $next;

        if ($this->product->max_ingredients && $total > $this->product->max_ingredients) {
            $this->addError('customization', "Puedes agregar hasta {$this->product->max_ingredients} ingredientes.");

            return;
        }

        if ($next === 0) {
            unset($this->selectedIngredients[$ingredientId]);
        } else {
            $this->selectedIngredients[$ingredientId] = $next;
        }
        $this->resetErrorBag('customization');
    }

    public function addCustomizedProduct(): void
    {
        $product = $this->product;
        if (! $product) {
            return;
        }

        try {
            $this->validateSelections($product, $this->addonQuantities, $this->selectedIngredients);
        } catch (ValidationException $exception) {
            $this->addError('customization', $exception->validator->errors()->first());

            return;
        }

        $this->resetErrorBag('customization');
        $this->addLine($product, $this->addonQuantities, $this->selectedIngredients, $this->itemNotes, max(1, $this->itemQuantity));
        $this->customizingProduct = null;
        $this->step = 3;
        unset($this->product);
    }

    public function cancelCustomization(): void
    {
        $this->customizingProduct = null;
        $this->step = 3;
        $this->resetErrorBag('customization');
        unset($this->product);
    }

    public function changeCartQuantity(string $lineId, int $delta): void
    {
        foreach ($this->cart as $index => $line) {
            if ($line['id'] !== $lineId) {
                continue;
            }

            $quantity = max(0, (int) $line['quantity'] + ($delta > 0 ? 1 : -1));
            if ($quantity === 0) {
                unset($this->cart[$index]);
                $this->cart = array_values($this->cart);
            } else {
                $this->cart[$index]['quantity'] = $quantity;
                $this->cart[$index]['subtotal'] = round($line['unit_total'] * $quantity, 2);
            }
            break;
        }
        unset($this->cartCount, $this->cartTotal);
    }

    public function reviewOrder(): void
    {
        if (empty($this->cart)) {
            $this->addError('cart', 'Agrega al menos un producto para continuar.');

            return;
        }
        $this->step = 5;
    }

    public function placeOrder(): void
    {
        if ($this->submitting || $this->completedOrderId) {
            return;
        }

        $this->validate([
            'customerName' => ['required', 'string', 'min:2', 'max:120', 'not_regex:/[0-9]/'],
            'customerPhone' => [Rule::requiredIf($this->terminal->require_customer_phone || $this->fulfillment === 'delivery'), 'nullable', 'regex:/^[0-9]{10}$/'],
            'deliveryStreet' => [Rule::requiredIf($this->fulfillment === 'delivery'), 'nullable', 'string', 'max:180'],
            'deliveryNeighborhood' => [Rule::requiredIf($this->fulfillment === 'delivery'), 'nullable', 'string', 'max:120'],
            'deliveryReferences' => ['nullable', 'string', 'max:240'],
            'fulfillment' => ['required', 'in:dine_in,takeaway,delivery'],
            'selectedMesaId' => [Rule::requiredIf($this->fulfillment === 'dine_in'), 'nullable', 'integer', 'exists:mesas,id'],
            'orderNotes' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $wordCount = trim((string) $value) === ''
                        ? 0
                        : count(preg_split('/\s+/u', trim((string) $value)) ?: []);

                    if ($wordCount > 50) {
                        $fail('La nota general puede contener máximo 50 palabras.');
                    }
                },
            ],
        ], [
            'customerName.required' => 'Escribe el nombre para llamar al cliente.',
            'customerName.min' => 'El nombre debe tener al menos 2 caracteres.',
            'customerName.not_regex' => 'El nombre no puede contener números.',
            'customerPhone.required' => 'Escribe un teléfono para poder contactar al cliente.',
            'customerPhone.regex' => 'El teléfono debe contener exactamente 10 dígitos.',
            'deliveryStreet.required' => 'Escribe la calle y el número del domicilio.',
            'deliveryNeighborhood.required' => 'Escribe la colonia o zona de entrega.',
            'selectedMesaId.required' => 'Selecciona la mesa en la que estás sentado.',
        ]);

        if (empty($this->cart)) {
            $this->addError('cart', 'El pedido está vacío.');

            return;
        }

        $rateKey = 'kiosk-order:'.$this->terminalId.':'.request()->ip();
        if (RateLimiter::tooManyAttempts($rateKey, $this->terminal->orders_per_minute)) {
            $this->addError('order', 'Se alcanzó el límite temporal de pedidos. Espera un minuto.');

            return;
        }
        RateLimiter::hit($rateKey, 60);
        $this->submitting = true;

        try {
            $order = DB::transaction(function () {
                $terminal = KioskTerminal::query()
                    ->whereKey($this->terminalId)
                    ->where('token_hash', hash('sha256', $this->terminalToken))
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->firstOrFail();
                $fulfillmentIsAllowed = match ($this->fulfillment) {
                    'dine_in' => $terminal->allow_dine_in,
                    'takeaway' => $terminal->allow_takeaway,
                    'delivery' => $terminal->allow_delivery,
                    default => false,
                };

                if (! $fulfillmentIsAllowed) {
                    throw ValidationException::withMessages([
                        'order' => 'Esta modalidad ya no está disponible. Vuelve al inicio y elige otra opción.',
                    ]);
                }
                $cash = CashRegister::where('is_open', true)->latest('opened_at')->first();

                if (! $cash) {
                    throw ValidationException::withMessages(['order' => 'El kiosco está pausado porque no hay una caja abierta.']);
                }

                $responsibleUserId = $terminal->user_id ?: $cash->opened_by;
                if (! $responsibleUserId) {
                    throw ValidationException::withMessages(['order' => 'El terminal no tiene un responsable configurado.']);
                }

                $lines = collect($this->cart)->map(fn ($line) => $this->resolveLine($line));
                $total = round((float) $lines->sum('subtotal'), 2);
                $publicToken = Str::random(64);

                $isDelivery = $this->fulfillment === 'delivery';
                $mesa = null;
                if ($this->fulfillment === 'dine_in') {
                    $mesa = Mesa::query()
                        ->whereKey($this->selectedMesaId)
                        ->where('status', 'disponible')
                        ->lockForUpdate()
                        ->first();

                    if (! $mesa) {
                        throw ValidationException::withMessages([
                            'selectedMesaId' => 'La mesa ya no está disponible. Selecciona otra mesa.',
                        ]);
                    }
                }
                $fulfillmentLabel = match ($this->fulfillment) {
                    'dine_in' => 'Comer aquí',
                    'delivery' => 'Para domicilio',
                    default => 'Para llevar',
                };
                $address = $isDelivery
                    ? trim($this->deliveryStreet).', '.trim($this->deliveryNeighborhood)
                    : null;

                $notes = collect([
                    'Pedido realizado en kiosco',
                    $fulfillmentLabel,
                    $mesa ? "Mesa {$mesa->number}" : null,
                    trim($this->orderNotes) ?: null,
                ])->filter()->implode(' · ');

                $order = Order::create([
                    'cash_register_id' => $cash->id,
                    'kiosk_terminal_id' => $terminal->id,
                    'public_token' => $publicToken,
                    'customer_name' => trim($this->customerName),
                    'customer_phone' => trim($this->customerPhone) ?: null,
                    'customer_address' => $address,
                    'customer_references' => $isDelivery ? (trim($this->deliveryReferences) ?: null) : null,
                    'served_by' => $responsibleUserId,
                    'type' => $mesa ? 'mesa' : ($isDelivery ? 'delivery' : 'ventanilla'),
                    'mesa_id' => $mesa?->id,
                    'delivery_method' => $isDelivery ? 'contra_entrega' : null,
                    'source' => 'kiosk',
                    'fulfillment' => $this->fulfillment,
                    'status' => 'pendiente',
                    'subtotal' => $total,
                    'total' => $total,
                    'notes' => $notes,
                ]);

                if ($mesa) {
                    // El kiosco ocupa la mesa inmediatamente; el mesero puede
                    // tomarla o reasignarla desde Gestión de mesas.
                    $mesa->update(['status' => 'ocupada']);
                }

                foreach ($lines as $line) {
                    $orderItem = OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $line['product']->id,
                        'product_name' => $line['product']->name,
                        'product_price' => $line['base_price'],
                        'quantity' => $line['quantity'],
                        'subtotal' => $line['subtotal'],
                        'notes' => $line['notes'] ?: null,
                    ]);

                    foreach ($line['addons'] as $addon) {
                        OrderItemAddon::create([
                            'order_item_id' => $orderItem->id,
                            'addon_id' => $addon['model']->id,
                            'addon_name' => $addon['model']->name,
                            'extra_price' => $addon['model']->extra_price,
                            'quantity' => $addon['quantity'],
                        ]);
                    }

                    foreach ($line['ingredients'] as $ingredient) {
                        OrderItemIngredient::create([
                            'order_item_id' => $orderItem->id,
                            'ingredient_id' => $ingredient['model']->id,
                            'ingredient_name' => $ingredient['model']->name,
                            'extra_price' => $ingredient['model']->extra_price,
                            'quantity' => $ingredient['quantity'],
                        ]);
                    }
                }

                return $order;
            });

            $this->completedOrderId = $order->id;
            $this->publicToken = $order->public_token;
            $this->cart = [];
            $this->step = 6;
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                $this->addError($key, $messages[0]);
            }
        } finally {
            $this->submitting = false;
        }
    }

    public function startAgain(): void
    {
        $this->reset([
            'fulfillment', 'selectedMesaId', 'search', 'categoryFilter', 'recommendationName', 'featuredProductIntent', 'cart', 'customizingProduct',
            'selectedAddons', 'addonQuantities', 'selectedIngredients', 'itemNotes', 'customerName',
            'customerPhone', 'deliveryStreet', 'deliveryNeighborhood', 'deliveryReferences',
            'orderNotes', 'completedOrderId', 'publicToken', 'submitting',
        ]);
        $this->step = 1;
    }

    public function refreshAvailability(): void
    {
        $terminal = KioskTerminal::findByPlainToken($this->terminalToken, false);

        if ($terminal?->is_active) {
            $this->redirect(route('kiosk.order', $this->terminalToken), navigate: true);

            return;
        }

        $this->accessState = $terminal ? 'paused' : 'invalid';
        $this->unavailableTerminalName = $terminal?->name;
    }

    private function addLine(Product $product, array $addonQuantities, array $ingredients, string $notes, int $quantity): void
    {
        $line = $this->resolveLine([
            'product_id' => $product->id,
            'addon_quantities' => $addonQuantities,
            'ingredients' => $ingredients,
            'quantity' => $quantity,
            'notes' => trim($notes),
        ]);

        $this->cart[] = [
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'product_name' => $product->name,
            'image' => $product->image,
            'addon_quantities' => $addonQuantities,
            'addon_names' => $line['addons']->map(fn ($item) => $item['model']->name.' ×'.$item['quantity'])->all(),
            'ingredients' => $ingredients,
            'ingredient_names' => $line['ingredients']->map(fn ($item) => $item['model']->name.' ×'.$item['quantity'])->all(),
            'notes' => trim($notes),
            'quantity' => $quantity,
            'unit_total' => $line['unit_total'],
            'subtotal' => $line['subtotal'],
        ];
        unset($this->cartCount, $this->cartTotal);
    }

    private function resolveLine(array $line): array
    {
        $product = Product::with(['addonGroups.addons', 'ingredients'])
            ->where('is_active', true)->findOrFail((int) $line['product_id']);
        $addonQuantities = collect($line['addon_quantities'] ?? [])
            ->mapWithKeys(fn ($quantity, $id) => [(int) $id => max(0, (int) $quantity)])
            ->filter()->all();
        if (empty($addonQuantities) && ! empty($line['addon_ids'])) {
            $addonQuantities = array_fill_keys(array_map('intval', $line['addon_ids']), 1);
        }
        $ingredients = collect($line['ingredients'] ?? [])
            ->mapWithKeys(fn ($quantity, $id) => [(int) $id => max(0, (int) $quantity)])
            ->filter()->all();

        $this->validateSelections($product, $addonQuantities, $ingredients);

        $allowedAddons = $product->addonGroups->flatMap->addons->where('is_active', true);
        $addons = collect($addonQuantities)->map(function ($quantity, $id) use ($allowedAddons) {
            return ['model' => $allowedAddons->firstWhere('id', (int) $id), 'quantity' => $quantity];
        })->values();
        $allowedIngredients = $product->ingredients->where('is_active', true)->keyBy('id');
        $ingredientModels = collect($ingredients)->map(function ($quantity, $id) use ($allowedIngredients) {
            return ['model' => $allowedIngredients->get((int) $id), 'quantity' => $quantity];
        })->values();

        $promotion = $this->terminal->promotion_enabled
            ? KioskProductPromotion::query()
                ->where('kiosk_terminal_id', $this->terminalId)
                ->where('product_id', $product->id)
                ->first()
            : null;
        $basePrice = $promotion?->promotional_price !== null
            ? (float) $promotion->promotional_price
            : (float) $product->price;

        $unitTotal = $basePrice
            + (float) $addons->sum(fn ($item) => (float) $item['model']->extra_price * $item['quantity'])
            + (float) $ingredientModels->sum(fn ($item) => (float) $item['model']->extra_price * $item['quantity']);
        $quantity = max(1, min(99, (int) ($line['quantity'] ?? 1)));

        return [
            'product' => $product,
            'addons' => $addons,
            'ingredients' => $ingredientModels,
            'quantity' => $quantity,
            'notes' => trim((string) ($line['notes'] ?? '')),
            'base_price' => round($basePrice, 2),
            'unit_total' => round($unitTotal, 2),
            'subtotal' => round($unitTotal * $quantity, 2),
        ];
    }

    private function validateSelections(Product $product, array $addonQuantities, array $ingredients): void
    {
        $addonQuantities = collect($addonQuantities)
            ->mapWithKeys(fn ($quantity, $id) => [(int) $id => max(0, (int) $quantity)])
            ->filter()->all();
        $addonIds = array_map('intval', array_keys($addonQuantities));
        $validAddonIds = $product->addonGroups->flatMap->addons->where('is_active', true)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (array_diff($addonIds, $validAddonIds)) {
            throw ValidationException::withMessages(['customization' => 'Una opción seleccionada ya no está disponible.']);
        }

        foreach ($product->addonGroups->where('is_active', true) as $group) {
            $groupIds = $group->addons->where('is_active', true)->pluck('id')->map(fn ($id) => (int) $id)->all();
            $count = collect($groupIds)->sum(fn ($id) => (int) ($addonQuantities[$id] ?? 0));
            $minimum = $group->is_required ? max(1, (int) $group->min_selections) : (int) $group->min_selections;
            $maximum = max(1, (int) $group->max_selections);
            if ($count < $minimum) {
                throw ValidationException::withMessages(['customization' => "Elige al menos {$minimum} opción(es) en {$group->name}."]);
            }
            if ($count > $maximum) {
                throw ValidationException::withMessages(['customization' => "Elige máximo {$maximum} opción(es) en {$group->name}."]);
            }
        }

        if ($product->max_addons && array_sum($addonQuantities) > $product->max_addons) {
            throw ValidationException::withMessages(['customization' => "Este producto acepta hasta {$product->max_addons} complementos."]);
        }

        $ingredientIds = array_map('intval', array_keys($ingredients));
        $validIngredientIds = $product->ingredients->where('is_active', true)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (array_diff($ingredientIds, $validIngredientIds)) {
            throw ValidationException::withMessages(['customization' => 'Un ingrediente seleccionado ya no está disponible.']);
        }
        $ingredientCount = array_sum(array_map('intval', $ingredients));
        if ($ingredientCount < (int) $product->min_ingredients) {
            throw ValidationException::withMessages(['customization' => "Agrega al menos {$product->min_ingredients} ingrediente(s)."]);
        }
        if ($product->max_ingredients && $ingredientCount > $product->max_ingredients) {
            throw ValidationException::withMessages(['customization' => "Agrega máximo {$product->max_ingredients} ingrediente(s)."]);
        }
    }

    public function render()
    {
        if ($this->accessState !== 'ready' || ! $this->terminalId || ! $this->terminal->is_active) {
            if ($this->terminalId && ! $this->terminal->is_active) {
                $this->accessState = 'paused';
                $this->unavailableTerminalName = $this->terminal->name;
            }

            return view('livewire.kiosk.terminal-unavailable');
        }

        return view('livewire.kiosk.order-wizard');
    }
}
