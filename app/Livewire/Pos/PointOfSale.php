<?php

namespace App\Livewire\Pos;

use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Mesa;
use App\Models\MesaAssignment;
use App\Models\MesaGroup;
use App\Models\MesaSplit;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAddon;
use App\Models\OrderItemIngredient;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\QuotationItemAddon;
use App\Models\QuotationItemIngredient;
use App\Services\ThermalTicketRenderer;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class PointOfSale extends Component
{
    // ─── Cart ─────────────────────────────────────────────────────────────────
    public array $cart = [];

    // ─── Order setup ──────────────────────────────────────────────────────────
    public string $orderType = 'ventanilla';

    public string $deliveryMethod = 'contra_entrega';

    public string $orderNotes = '';

    // ─── Customer ─────────────────────────────────────────────────────────────
    public ?int $customerId = null;

    public string $customerName = '';

    public string $customerPhone = '';

    public string $customerAddress = '';

    public string $customerReferences = '';

    public string $customerSearch = '';

    // ─── New customer inline ───────────────────────────────────────────────────
    public bool $showAddCustomerModal = false;

    public string $newCustomerName = '';

    public string $newCustomerPhone = '';

    public string $newCustomerEmail = '';

    public string $newCustomerAddress = '';

    public string $newCustomerReferences = '';

    // ─── Product browsing ─────────────────────────────────────────────────────
    public string $productSearch = '';

    public ?int $selectedCategoryId = null;

    // ─── Customize product modal ───────────────────────────────────────────────
    public bool $showCustomizeModal = false;

    public ?int $customizingProductId = null;

    public ?string $editingCartId = null;

    public array $selectedAddons = [];

    public array $selectedIngredients = [];

    public string $itemNotes = '';

    public int $itemQuantity = 1;

    // ─── Checkout modal ────────────────────────────────────────────────────────
    public bool $showCheckoutModal = false;

    public array $payments = [];  // [['method','amount','cash_received','card_last4','transfer_ref']]

    public string $payMethod = 'cash';

    public string $payAmount = '';

    public string $payCashReceived = '';

    public string $payCardLast4 = '';

    public string $payTransferRef = '';

    // ─── Quotation modals ─────────────────────────────────────────────────────
    public bool $showQuotationsModal = false;

    public bool $showSaveQuotationModal = false;

    public string $quotationName = '';

    public string $quotationNotes = '';

    public ?int $activeQuotationId = null;

    // ─── Mesa pay modal ───────────────────────────────────────────────────────
    public bool $showMesaPayModal = false;

    public ?int $mesaPayId = null;

    public ?int $mesaSplitId = null;

    public ?int $mesaSplitAccountIdx = null;

    public array $mesaPayments = [];

    public string $mesaPayMethod = 'cash';

    public string $mesaPayAmount = '';

    public string $mesaPayReceived = '';

    public string $mesaPayCard = '';

    public string $mesaPayRef = '';

    // ─── Pickup pay modal ─────────────────────────────────────────────────────
    public bool $showPickupPayModal = false;

    public bool $showConvertDeliveryModal = false;

    public ?int $convertDeliveryOrderId = null;

    public string $convertDeliveryName = '';

    public string $convertDeliveryPhone = '';

    public string $convertDeliveryAddress = '';

    public string $convertDeliveryReferences = '';

    public string $convertDeliveryMethod = 'contra_entrega';

    public ?int $pickupPayOrderId = null;  // usado solo por el modal, no activa el panel lateral

    // ─── Open cash register modal ─────────────────────────────────────────────
    public bool $showCashModal = false;

    public string $cashName = 'Caja 1';

    public string $cashInitialAmount = '500.00';

    // ─── Post-order success ────────────────────────────────────────────────────
    public bool $showOrderSuccess = false;

    public ?int $lastOrderId = null;

    public ?int $lastOrderFolio = null;

    public ?string $lastOrderType = null;

    // ─── Gastos ────────────────────────────────────────────────────────────────
    public bool $showExpenseModal = false;

    public string $expenseAmount = '';

    public string $expenseCategory = 'otro';

    public string $expenseDescription = '';

    public string $expensePaymentMethod = 'cash';

    public string $expenseNotes = '';

    // ─── Pickup panel ──────────────────────────────────────────────────────────
    public string $pickupSearch = '';

    public string $deliverySearch = '';

    public ?int $pickupOrderId = null;

    public array $pickupPayments = [];

    public string $pickupPayMethod = 'cash';

    public string $pickupPayAmount = '';

    public string $pickupPayReceived = '';

    public string $pickupPayCard = '';

    public string $pickupPayRef = '';

    // ─── Kitchen panel ────────────────────────────────────────────────────────
    public string $kitchenSearch = '';

    // ─── Reprint / orders search ───────────────────────────────────────────────
    public string $reprintSearch = '';

    public string $reprintType = 'ventanilla'; // ventanilla | mesas | delivery

    // ──────────────────────────────────────────────────────────────────────────

    #[Computed]
    public function activeCashRegister(): ?CashRegister
    {
        return CashRegister::where('is_open', true)->latest('opened_at')->first();
    }

    #[Computed]
    public function categoriesWithProducts()
    {
        $search = $this->productSearch;
        $catId = $this->selectedCategoryId;

        return Category::with(['products' => function ($q) use ($search) {
            $q->where('is_active', true)
                ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
                ->orderBy('sort_order')->orderBy('name');
        }])
            ->where('is_active', true)
            ->when($catId, fn ($q) => $q->where('id', $catId))
            ->orderBy('sort_order')->orderBy('name')
            ->get()
            ->filter(fn ($cat) => $cat->products->isNotEmpty());
    }

    #[Computed]
    public function productsWithoutCategory()
    {
        if ($this->selectedCategoryId) {
            return collect();
        }

        return Product::whereNull('category_id')
            ->where('is_active', true)
            ->when($this->productSearch, fn ($q) => $q->where('name', 'like', "%{$this->productSearch}%"))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function allCategories()
    {
        return Category::where('is_active', true)
            ->whereHas('products', fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')->orderBy('name')
            ->get();
    }

    #[Computed]
    public function customizingProduct(): ?Product
    {
        if (! $this->customizingProductId) {
            return null;
        }

        return Product::with([
            'addonGroups' => fn ($q) => $q->where('is_active', true)
                ->with(['addons' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')]),
            'ingredients' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
        ])->find($this->customizingProductId);
    }

    #[Computed]
    public function cartTotal(): float
    {
        return collect($this->cart)->sum('subtotal');
    }

    #[Computed]
    public function cartCount(): int
    {
        return collect($this->cart)->sum('quantity');
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
            $maximum = max($minimum, min((int) $group->max_selections, $available));

            if ($available < $minimum || $selected < $minimum || $selected > $maximum) {
                return false;
            }
        }

        $ingredients = array_sum($this->selectedIngredients);

        return $ingredients >= (int) $product->min_ingredients
            && (! $product->max_ingredients || $ingredients <= (int) $product->max_ingredients);
    }

    #[Computed]
    public function paidTotal(): float
    {
        return collect($this->payments)->sum(fn ($p) => (float) ($p['amount'] ?? 0));
    }

    #[Computed]
    public function paymentRemaining(): float
    {
        return max(0, round($this->cartTotal - $this->paidTotal, 2));
    }

    #[Computed]
    public function customerSearchResults()
    {
        if (strlen($this->customerSearch) < 2) {
            return collect();
        }

        return Customer::where('name', 'like', "%{$this->customerSearch}%")
            ->orWhere('phone', 'like', "%{$this->customerSearch}%")
            ->limit(8)->get();
    }

    #[Computed]
    public function quotations()
    {
        return Quotation::where('status', 'active')
            ->where('created_by', auth()->id())
            ->orderByDesc('created_at')
            ->get();
    }

    #[Computed]
    public function recentOrders()
    {
        $search = $this->reprintSearch;
        $cashRegisterId = $this->activeCashRegister?->id;

        if (! $cashRegisterId) {
            return collect();
        }

        return Order::with(['items', 'payments', 'mesa.area'])
            ->where('cash_register_id', $cashRegisterId)
            ->where(function ($query) {
                match ($this->reprintType) {
                    'mesas' => $query->where(function ($area) {
                        $area->where('type', 'mesa')
                            ->orWhere(fn ($kiosk) => $kiosk->where('source', 'kiosk')->where('fulfillment', 'dine_in'));
                    }),
                    'delivery' => $query->where(function ($area) {
                        $area->where('type', 'delivery')
                            ->orWhere(fn ($kiosk) => $kiosk->where('source', 'kiosk')->where('fulfillment', 'delivery'));
                    }),
                    default => $query->whereIn('type', ['ventanilla', 'pick_up'])
                        ->where(fn ($source) => $source->where('source', '!=', 'kiosk')
                            ->orWhere(fn ($kiosk) => $kiosk->where('source', 'kiosk')->where('fulfillment', 'takeaway'))),
                };
            })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();
    }

    #[Computed]
    public function reprintMesaGroups()
    {
        return $this->recentOrders
            ->groupBy(fn (Order $order) => $order->mesa_id ?: 'sin-mesa')
            ->map(function ($orders) {
                $first = $orders->first();

                return (object) [
                    'mesa' => $first->mesa,
                    'orders' => $orders,
                    'total' => (float) $orders->sum('total'),
                ];
            });
    }

    #[Computed]
    public function pickupOrders()
    {
        $search = $this->pickupSearch;
        $cashRegisterId = $this->activeCashRegister?->id;

        if (! $cashRegisterId) {
            return collect();
        }

        return Order::with(['items', 'payments'])
            ->where('cash_register_id', $cashRegisterId)
            ->whereDoesntHave('payments')
            ->where(function ($query) {
                $query->where(function ($kiosk) {
                    $kiosk->where('source', 'kiosk')
                        ->where('fulfillment', 'takeaway')
                        ->whereIn('status', ['pendiente', 'en_preparacion', 'lista']);
                })->orWhere(function ($pickup) {
                    $pickup->whereIn('type', ['pick_up', 'ventanilla'])
                        ->where(fn ($source) => $source->whereNull('source')->orWhere('source', '!=', 'kiosk'))
                        ->whereIn('status', ['pendiente', 'en_preparacion', 'lista']);
                });
            })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%")
                        ->orWhere('customer_address', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->get();
    }

    #[Computed]
    public function deliveryOrders()
    {
        $search = $this->deliverySearch;
        $cashRegisterId = $this->activeCashRegister?->id;

        if (! $cashRegisterId) {
            return collect();
        }

        return Order::with(['items', 'payments'])
            ->where('cash_register_id', $cashRegisterId)
            ->where('type', 'delivery')
            ->where('delivery_method', 'contra_entrega')
            ->whereDoesntHave('payments')
            ->whereIn('status', ['pendiente', 'en_preparacion', 'lista'])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('id', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%")
                        ->orWhere('customer_address', 'like', "%{$search}%");
                });
            })
            ->oldest()
            ->get();
    }

    #[Computed]
    public function kioskDineInOrders()
    {
        $cashRegisterId = $this->activeCashRegister?->id;

        if (! $cashRegisterId) {
            return collect();
        }

        return Order::with(['items.addons', 'items.ingredients', 'payments', 'mesa'])
            ->where('cash_register_id', $cashRegisterId)
            ->where('source', 'kiosk')
            ->where('fulfillment', 'dine_in')
            // Comer aquí siempre debe estar ligado a una mesa elegida en el kiosco.
            ->whereNotNull('mesa_id')
            ->whereIn('status', ['pendiente', 'en_preparacion', 'lista'])
            ->whereDoesntHave('payments')
            ->oldest()
            ->get();
    }

    #[Computed]
    public function mesasPendientes()
    {
        $cashRegisterId = $this->activeCashRegister?->id;

        if (! $cashRegisterId) {
            return collect();
        }

        return Mesa::with([
            'area',
            'currentAssignment.waiter',
            'orders' => fn ($q) => $q->where('cash_register_id', $cashRegisterId)
                ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada'])
                ->with('items'),
            // Tomamos únicamente el split vigente; los completados ya no deben
            // aparecer como cuentas pendientes en caja.
            'splits' => fn ($q) => $q->whereIn('status', ['pendiente', 'parcial'])->latest('id'),
        ])
            // El estado de la mesa y sus órdenes activas son la fuente de
            // verdad: una mesa liberada no pertenece a este panel aunque
            // exista un split antiguo pendiente.
            ->where('status', 'en_cuenta')
            ->whereHas('orders', fn ($q) => $q->where('cash_register_id', $cashRegisterId)
                ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada']))
            ->orderBy('number')
            ->get()
            ->map(function ($mesa) {
                $split = $mesa->splits->first();
                $mesa->active_split = $split;
                $mesa->mesa_total = $split
                    ? collect($split->split_data)
                        ->reject(fn ($account) => (bool) ($account['paid'] ?? false))
                        ->sum('total')
                    : $mesa->orders->sum('total');

                return $mesa;
            });
    }

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->cart = Session::get('pos_cart_'.auth()->id(), []);
    }

    private function saveCart(): void
    {
        Session::put('pos_cart_'.auth()->id(), $this->cart);
    }

    // ─── Cash Register ─────────────────────────────────────────────────────────

    public function openCashRegister(): void
    {
        $this->validate([
            'cashName' => 'required|string|max:60',
            'cashInitialAmount' => 'required|numeric|min:0',
        ]);

        CashRegister::create([
            'name' => $this->cashName,
            'opened_by' => auth()->id(),
            'initial_amount' => $this->cashInitialAmount,
            'opened_at' => now(),
            'is_open' => true,
        ]);

        $this->showCashModal = false;
        unset($this->activeCashRegister);
        $this->dispatch('notify', type: 'success', message: "Caja \"{$this->cashName}\" abierta.");
    }

    // ─── Product browsing ─────────────────────────────────────────────────────

    public function selectCategory(?int $id): void
    {
        $this->selectedCategoryId = $id;
        unset($this->categoriesWithProducts, $this->productsWithoutCategory);
    }

    // ─── Customize product modal ───────────────────────────────────────────────

    public function openCustomizeModal(int $productId): void
    {
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

    public function editCartItem(string $cartId): void
    {
        $item = collect($this->cart)->firstWhere('cart_id', $cartId);
        if (! $item) {
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
        $maximum = max($minimum, min((int) $group->max_selections, $group->addons->count()));

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

    public function incrementIngredient(int $ingredientId): void
    {
        $product = $this->customizingProduct;
        $total = array_sum($this->selectedIngredients);

        if ($product && $product->max_ingredients && $total >= $product->max_ingredients) {
            return;
        }

        $this->selectedIngredients[$ingredientId] = ($this->selectedIngredients[$ingredientId] ?? 0) + 1;
        unset($this->totalSelectedIngredients);
    }

    public function decrementIngredient(int $ingredientId): void
    {
        $current = $this->selectedIngredients[$ingredientId] ?? 0;
        if ($current <= 1) {
            unset($this->selectedIngredients[$ingredientId]);
        } else {
            $this->selectedIngredients[$ingredientId] = $current - 1;
        }
        unset($this->totalSelectedIngredients);
    }

    public function addToCart(): void
    {
        $product = $this->customizingProduct;
        if (! $product) {
            return;
        }

        foreach ($product->addonGroups as $group) {
            $selected = collect($group->addons)
                ->filter(fn ($a) => isset($this->selectedAddons[$a->id]))
                ->count();

            $minimum = $this->effectiveGroupMinimum($group);
            $available = $group->addons->count();
            $maximum = max($minimum, min((int) $group->max_selections, $available));

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

        $this->showCustomizeModal = false;
        $this->customizingProductId = null;
        $this->editingCartId = null;
        unset($this->cartTotal, $this->cartCount);
        $this->saveCart();
    }

    // ─── Cart operations ───────────────────────────────────────────────────────

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

    #[On('modal-confirmed')]
    public function handleConfirmed(string $action, array $params = []): void
    {
        match ($action) {
            'clearCart' => $this->clearCart(),
            'discardEmptyMesaAccount' => $this->discardEmptyMesaAccount((int) ($params['mesaId'] ?? 0)),
            default => null,
        };
    }

    // ─── Customer (checkout inline) ───────────────────────────────────────────

    public function selectCustomer(int $id): void
    {
        $customer = Customer::findOrFail($id);
        $this->customerId = $customer->id;
        $this->customerName = $customer->name;
        $this->customerPhone = $customer->phone ?? '';
        $this->customerAddress = $customer->address ?? '';
        $this->customerReferences = $customer->references ?? '';
        $this->customerSearch = '';
        unset($this->customerSearchResults);
    }

    public function clearCustomer(): void
    {
        $this->customerId = null;
        $this->customerName = '';
        $this->customerPhone = '';
        $this->customerAddress = '';
        $this->customerReferences = '';
    }

    public function updatedCustomerSearch(): void
    {
        unset($this->customerSearchResults);
    }

    public function openAddCustomerModal(): void
    {
        $this->newCustomerName = '';
        $this->newCustomerPhone = '';
        $this->newCustomerEmail = '';
        $this->newCustomerAddress = '';
        $this->newCustomerReferences = '';
        $this->showAddCustomerModal = true;
        $this->resetErrorBag();
    }

    public function saveNewCustomer(): void
    {
        $this->validate([
            'newCustomerName' => 'required|string|max:120',
            'newCustomerPhone' => 'required|string|max:30',
        ]);

        $customer = Customer::create([
            'name' => $this->newCustomerName,
            'phone' => $this->newCustomerPhone,
            'email' => $this->newCustomerEmail ?: null,
            'address' => $this->newCustomerAddress ?: null,
            'references' => $this->newCustomerReferences ?: null,
        ]);

        $this->selectCustomer($customer->id);
        $this->showAddCustomerModal = false;
        $this->dispatch('notify', type: 'success', message: 'Cliente registrado.');
    }

    // ─── Checkout modal ────────────────────────────────────────────────────────

    public function openCheckoutModal(): void
    {
        if (empty($this->cart)) {
            $this->dispatch('notify', type: 'warning', message: 'El carrito está vacío.');

            return;
        }

        $this->payments = [];
        $this->payMethod = 'cash';
        $this->payAmount = number_format($this->cartTotal, 2, '.', '');
        $this->payCashReceived = '';
        $this->payCardLast4 = '';
        $this->payTransferRef = '';
        $this->orderType = 'ventanilla';
        $this->deliveryMethod = 'contra_entrega';
        $this->resetErrorBag();
        $this->showCheckoutModal = true;
    }

    public function updatedOrderType(): void
    {
        $this->payments = [];
        $this->payAmount = number_format($this->cartTotal, 2, '.', '');
        unset($this->paidTotal, $this->paymentRemaining);
    }

    public function addPayment(): void
    {
        $amount = (float) $this->payAmount;
        if ($amount <= 0) {
            $this->dispatch('notify', type: 'warning', message: 'Ingresa un monto válido.');

            return;
        }

        $payment = [
            'method' => $this->payMethod,
            'amount' => $amount,
        ];

        if ($this->payMethod === 'cash') {
            $received = (float) $this->payCashReceived;
            $payment['cash_received'] = $received > 0 ? $received : $amount;
            $payment['cash_change'] = max(0, ($received > 0 ? $received : $amount) - $amount);
        } elseif ($this->payMethod === 'card') {
            $payment['card_last4'] = $this->payCardLast4;
        } elseif ($this->payMethod === 'transfer') {
            $payment['transfer_ref'] = $this->payTransferRef;
        }

        $this->payments[] = $payment;

        $remaining = max(0, $this->cartTotal - collect($this->payments)->sum('amount'));
        $this->payAmount = $remaining > 0 ? number_format($remaining, 2, '.', '') : '';
        $this->payCashReceived = '';
        $this->payCardLast4 = '';
        $this->payTransferRef = '';
        unset($this->paidTotal, $this->paymentRemaining);
    }

    public function removePayment(int $index): void
    {
        array_splice($this->payments, $index, 1);
        unset($this->paidTotal, $this->paymentRemaining);
    }

    private function mapPaymentMethod(string $method): string
    {
        return match ($method) {
            'cash' => 'efectivo',
            'card' => 'tarjeta',
            'transfer' => 'transferencia',
            'contra_entrega',
            'delivery' => 'contra_entrega',
            default => $method,
        };
    }

    public function submitOrder(): void
    {
        if (empty($this->cart)) {
            return;
        }

        $isContraEntrega = $this->orderType === 'delivery' && $this->deliveryMethod === 'contra_entrega';

        if (! $isContraEntrega) {
            $paid = collect($this->payments)->sum('amount');
            if ($paid < $this->cartTotal - 0.01) {
                $this->dispatch('notify', type: 'warning', message: 'El monto pagado es insuficiente.');

                return;
            }
        }

        if ($this->orderType === 'delivery' && empty($this->customerAddress)) {
            $this->addError('customerAddress', 'La dirección es requerida para delivery.');

            return;
        }

        $order = $this->persistOrder($this->orderType, $isContraEntrega ? 'pendiente' : 'pagada');

        if (! $isContraEntrega) {
            foreach ($this->payments as $p) {
                $payment = OrderPayment::create([
                    'order_id' => $order->id,
                    'method' => $this->mapPaymentMethod($p['method']),
                    'amount' => $p['amount'],
                ]);

                if ($p['method'] === 'cash') {
                    $payment->update([
                        'received_amount' => $p['cash_received'] ?? $p['amount'],
                        'change_amount' => $p['cash_change'] ?? 0,
                    ]);
                }
            }
        }

        $this->showCheckoutModal = false;
        $this->finishSale($order);
    }

    public function submitPickupLater(): void
    {
        if (empty($this->cart)) {
            return;
        }

        if (empty($this->customerName) && ! $this->customerId) {
            $this->dispatch('notify', type: 'warning', message: 'Nombre del cliente requerido para Para recoger.');

            return;
        }

        $order = $this->persistOrder('pick_up', 'en_preparacion');
        $this->showCheckoutModal = false;

        // Auto-imprimir ticket de cocina
        $orderForPrint = Order::with([
            'items.addons',
            'items.ingredients',
            'items.product.category.printArea',
            'payments',
        ])->find($order->id);

        if ($orderForPrint) {
            $this->dispatch('pos-reprint-show-cocina',
                html_cliente: $this->buildTicketHtml($orderForPrint),
                html_cocina: $this->buildKitchenTicketHtml($orderForPrint),
            );
        }

        $this->finishSale($order);
    }

    public function submitOrderLater(): void
    {
        if (empty($this->cart)) {
            return;
        }

        if (empty($this->customerName) && ! $this->customerId) {
            $this->dispatch('notify', type: 'warning', message: 'Nombre del cliente requerido para pagar después.');

            return;
        }

        $order = $this->persistOrder($this->orderType === 'ventanilla' ? 'ventanilla' : 'pick_up', 'en_preparacion');
        $this->showCheckoutModal = false;

        $orderForPrint = Order::with(['items.addons', 'items.ingredients', 'items.product.category.printArea', 'payments'])->find($order->id);
        if ($orderForPrint) {
            $this->dispatch('pos-reprint-show-cocina',
                html_cliente: $this->buildTicketHtml($orderForPrint),
                html_cocina: $this->buildKitchenTicketHtml($orderForPrint),
            );
        }

        $this->finishSale($order);
    }

    // ─── Quotations ────────────────────────────────────────────────────────────

    public function saveQuotation(): void
    {
        if (empty($this->cart)) {
            $this->dispatch('notify', type: 'warning', message: 'El carrito está vacío.');

            return;
        }

        $quotation = Quotation::create([
            'created_by' => auth()->id(),
            'customer_id' => $this->customerId,
            'name' => $this->quotationName ?: null,
            'notes' => $this->quotationNotes ?: null,
            'customer_name' => $this->customerName ?: null,
            'customer_phone' => $this->customerPhone ?: null,
            'total' => $this->cartTotal,
            'status' => 'active',
        ]);

        foreach ($this->cart as $item) {
            $qi = QuotationItem::create([
                'quotation_id' => $quotation->id,
                'product_id' => $item['product_id'],
                'product_name' => $item['product_name'],
                'product_price' => $item['product_price'],
                'quantity' => $item['quantity'],
                'subtotal' => $item['subtotal'],
                'notes' => $item['notes'] ?: null,
            ]);

            foreach ($item['addons'] as $a) {
                QuotationItemAddon::create([
                    'quotation_item_id' => $qi->id,
                    'addon_id' => $a['addon_id'],
                    'addon_name' => $a['addon_name'],
                    'extra_price' => $a['extra_price'],
                    'quantity' => 1,
                ]);
            }

            foreach ($item['ingredients'] as $i) {
                QuotationItemIngredient::create([
                    'quotation_item_id' => $qi->id,
                    'ingredient_id' => $i['ingredient_id'],
                    'ingredient_name' => $i['ingredient_name'],
                    'extra_price' => $i['extra_price'],
                    'quantity' => $i['quantity'],
                ]);
            }
        }

        $this->showSaveQuotationModal = false;
        $this->quotationName = '';
        $this->quotationNotes = '';
        unset($this->quotations);
        $this->dispatch('notify', type: 'success', message: 'Cotización guardada.');
    }

    public function loadQuotation(int $id): void
    {
        $quotation = Quotation::with(['items.addons', 'items.ingredients', 'customer'])
            ->findOrFail($id);

        $newCart = [];
        foreach ($quotation->items as $item) {
            $addons = $item->addons->map(fn ($a) => [
                'addon_id' => $a->addon_id,
                'addon_name' => $a->addon_name,
                'extra_price' => (float) $a->extra_price,
            ])->toArray();

            $ingredients = $item->ingredients->map(fn ($i) => [
                'ingredient_id' => $i->ingredient_id,
                'ingredient_name' => $i->ingredient_name,
                'extra_price' => (float) $i->extra_price,
                'quantity' => $i->quantity,
            ])->toArray();

            $addonExtra = array_sum(array_column($addons, 'extra_price'));
            $ingExtra = array_sum(array_map(fn ($i) => $i['extra_price'] * $i['quantity'], $ingredients));
            $unitTotal = (float) $item->product_price + $addonExtra + $ingExtra;

            $newCart[] = [
                'cart_id' => Str::uuid()->toString(),
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'product_price' => (float) $item->product_price,
                'product_image' => Product::find($item->product_id)?->image,
                'quantity' => $item->quantity,
                'unit_extra' => $addonExtra + $ingExtra,
                'unit_total' => $unitTotal,
                'subtotal' => $unitTotal * $item->quantity,
                'notes' => $item->notes ?? '',
                'addons' => $addons,
                'ingredients' => $ingredients,
            ];
        }

        $this->cart = $newCart;

        if ($quotation->customer_id) {
            $this->customerId = $quotation->customer_id;
            $this->customerName = $quotation->customer?->name ?? '';
            $this->customerPhone = $quotation->customer?->phone ?? '';
            $this->customerAddress = $quotation->customer?->address ?? '';
            $this->customerReferences = $quotation->customer?->references ?? '';
        } elseif ($quotation->customer_name) {
            $this->customerName = $quotation->customer_name;
            $this->customerPhone = $quotation->customer_phone ?? '';
        }

        $this->activeQuotationId = $quotation->id;
        $this->showQuotationsModal = false;
        unset($this->cartTotal, $this->cartCount, $this->quotations);
        $this->dispatch('notify', type: 'info', message: "Cotización \"{$quotation->display_name}\" cargada.");
    }

    public function deleteQuotation(int $id): void
    {
        Quotation::findOrFail($id)->delete();
        unset($this->quotations);
        $this->dispatch('notify', type: 'warning', message: 'Cotización eliminada.');
    }

    public function updatedDeliveryMethod(): void
    {
        // Sincroniza el método de pago cuando se selecciona tarjeta/transfer en delivery
        if ($this->deliveryMethod === 'card') {
            $this->payMethod = 'card';
        } elseif ($this->deliveryMethod === 'transfer') {
            $this->payMethod = 'transfer';
        } elseif ($this->deliveryMethod === 'cash') {
            $this->payMethod = 'cash';
        }
        // Limpia pagos previos al cambiar método de entrega
        $this->payments = [];
        $this->payAmount = '';
    }

    public function updatedReprintType(): void
    {
        $this->reprintSearch = '';
        unset($this->recentOrders);
    }

    public function updatedReprintSearch(): void
    {
        unset($this->recentOrders);
    }

    // ─── Gastos ────────────────────────────────────────────────────────────────

    public function openExpenseModal(): void
    {
        $this->expenseAmount = '';
        $this->expenseCategory = 'otro';
        $this->expenseDescription = '';
        $this->expensePaymentMethod = 'cash';
        $this->expenseNotes = '';
        $this->resetErrorBag();
        $this->showExpenseModal = true;
    }

    public function saveExpense(): void
    {
        $this->validate([
            'expenseAmount' => 'required|numeric|min:0.01',
            'expenseDescription' => 'required|string|max:255',
        ]);

        Expense::create([
            'cash_register_id' => $this->activeCashRegister?->id,
            'created_by' => auth()->id(),
            'amount' => $this->expenseAmount,
            'category' => $this->expenseCategory,
            'description' => $this->expenseDescription,
            'payment_method' => $this->expensePaymentMethod,
            'notes' => $this->expenseNotes ?: null,
        ]);

        $this->showExpenseModal = false;
        $this->dispatch('notify', type: 'success', message: 'Gasto registrado.');
    }

    // ─── Pickup panel ──────────────────────────────────────────────────────────

    public function selectPickupOrder(int $orderId): void
    {
        $this->pickupOrderId = $orderId;
        $this->pickupPayments = [];
        $this->pickupPayMethod = 'cash';
        $this->pickupPayAmount = '';
        $this->pickupPayReceived = '';
        $this->pickupPayCard = '';
        $this->pickupPayRef = '';
        unset($this->pickupOrders);
    }

    public function openConvertDeliveryModal(int $orderId): void
    {
        $order = Order::with('customer')
            ->where('cash_register_id', $this->activeCashRegister?->id)
            ->findOrFail($orderId);
        if ($order->type === 'delivery' || $order->status === 'pagada') {
            return;
        }

        $this->convertDeliveryOrderId = $order->id;
        $this->convertDeliveryName = $order->customer_name ?: ($order->customer?->name ?? '');
        $this->convertDeliveryPhone = $order->customer_phone ?: ($order->customer?->phone ?? '');
        $this->convertDeliveryAddress = $order->customer_address ?: ($order->customer?->address ?? '');
        $this->convertDeliveryReferences = $order->customer_references ?: ($order->customer?->references ?? '');
        $this->convertDeliveryMethod = 'contra_entrega';
        $this->resetErrorBag();
        $this->showConvertDeliveryModal = true;
    }

    public function closeConvertDeliveryModal(): void
    {
        $this->showConvertDeliveryModal = false;
        $this->convertDeliveryOrderId = null;
    }

    public function convertOrderToDelivery(): void
    {
        $this->validate([
            'convertDeliveryName' => 'required|string|max:120',
            'convertDeliveryPhone' => ['required', 'regex:/^[0-9]{10}$/'],
            'convertDeliveryAddress' => 'required|string|max:180',
            'convertDeliveryReferences' => 'nullable|string|max:255',
            'convertDeliveryMethod' => 'required|in:contra_entrega,transferencia',
        ], [
            'convertDeliveryPhone.regex' => 'El teléfono debe tener 10 dígitos.',
        ]);

        $order = Order::where('cash_register_id', $this->activeCashRegister?->id)
            ->find($this->convertDeliveryOrderId);
        if (! $order || $order->type === 'delivery' || $order->status === 'pagada') {
            $this->closeConvertDeliveryModal();

            return;
        }

        $order->update([
            'type' => 'delivery',
            'customer_name' => trim($this->convertDeliveryName),
            'customer_phone' => trim($this->convertDeliveryPhone),
            'customer_address' => trim($this->convertDeliveryAddress),
            'customer_references' => trim($this->convertDeliveryReferences) ?: null,
            'delivery_method' => $this->convertDeliveryMethod,
        ]);

        $this->closeConvertDeliveryModal();
        unset($this->pickupOrders, $this->deliveryOrders, $this->recentOrders);
        $this->dispatch('notify', type: 'success', message: "Pedido #{$order->id} enviado a Delivery.");
    }

    public function openPickupPayModal(int $orderId): void
    {
        $order = Order::where('cash_register_id', $this->activeCashRegister?->id)
            ->findOrFail($orderId);

        $isPayableArea = $order->source === 'kiosk'
            || in_array($order->type, ['pick_up', 'ventanilla', 'delivery', 'mesa'], true);

        if (! $isPayableArea || $order->status !== 'lista') {
            $this->dispatch('notify', type: 'warning', message: 'Marca el pedido como listo antes de cobrarlo.');

            return;
        }

        $this->pickupPayOrderId = $orderId;
        $this->pickupPayments = [];
        $this->pickupPayMethod = ($order->type === 'delivery' && $order->delivery_method === 'contra_entrega')
            ? 'contra_entrega'
            : 'cash';
        $this->pickupPayAmount = '';
        $this->pickupPayReceived = '';
        $this->pickupPayCard = '';
        $this->pickupPayRef = '';
        $this->showPickupPayModal = true;
    }

    public function closePickupPayModal(): void
    {
        $this->showPickupPayModal = false;
        $this->pickupPayOrderId = null;
        $this->pickupPayments = [];
    }

    public function clearPickupOrder(): void
    {
        $this->pickupOrderId = null;
        $this->pickupPayments = [];
    }

    // ─── Mesa pay modal ───────────────────────────────────────────────────────

    public function openMesaPayModal(int $mesaId): void
    {
        $this->mesaPayId = $mesaId;
        $this->mesaSplitId = null;
        $this->mesaSplitAccountIdx = null;
        $this->mesaPayments = [];
        $this->mesaPayMethod = 'cash';
        $this->mesaPayAmount = '';
        $this->mesaPayReceived = '';
        $this->mesaPayCard = '';
        $this->mesaPayRef = '';
        $this->showMesaPayModal = true;
    }

    public function reopenMesa(int $mesaId): void
    {
        $mesa = Mesa::with('orders')->find($mesaId);
        if (! $mesa || $mesa->status !== 'en_cuenta') {
            return;
        }

        $mesa->update(['status' => 'ocupada']);
        unset($this->mesasPendientes);
        $this->dispatch('notify', type: 'success', message: "Mesa {$mesa->display_name} reabierta.");
    }

    public function discardEmptyMesaAccount(int $mesaId): void
    {
        $mesa = Mesa::with(['orders' => fn ($q) => $q->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada'])])->find($mesaId);
        if (! $mesa || $mesa->status !== 'en_cuenta' || $mesa->orders->sum('total') > 0.009) {
            $this->dispatch('notify', type: 'warning', message: 'Solo se pueden descartar cuentas en cero y sin órdenes activas.');

            return;
        }

        $this->releaseMesa($mesa);
        unset($this->mesasPendientes);
        $this->dispatch('notify', type: 'success', message: "Cuenta vacía de {$mesa->display_name} eliminada; mesa disponible.");
    }

    public function requestDiscardEmptyMesaAccount(int $mesaId): void
    {
        $mesa = Mesa::find($mesaId);
        if (! $mesa || $mesa->status !== 'en_cuenta') {
            return;
        }

        $this->dispatch('open-confirm',
            type: 'danger',
            title: 'Eliminar cuenta vacía',
            message: "La cuenta de <strong>{$mesa->display_name}</strong> está en cero. Se eliminará el cierre y la mesa quedará disponible. No se generará ningún movimiento de caja.",
            action: 'discardEmptyMesaAccount',
            params: ['mesaId' => $mesaId],
            confirmText: 'Eliminar cuenta',
            cancelText: 'Conservar',
        );
    }

    public function openMesaSplitPayModal(int $splitId, int $accountIdx): void
    {
        $cashRegisterId = $this->activeCashRegister?->id;
        $split = MesaSplit::whereIn('status', ['pendiente', 'parcial'])
            ->whereHas('mesa.orders', fn ($q) => $q->where('cash_register_id', $cashRegisterId)
                ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada']))
            ->findOrFail($splitId);
        $this->mesaPayId = $split->mesa_id;
        $this->mesaSplitId = $splitId;
        $this->mesaSplitAccountIdx = $accountIdx;
        $this->mesaPayments = [];
        $this->mesaPayMethod = 'cash';
        $this->mesaPayAmount = '';
        $this->mesaPayReceived = '';
        $this->mesaPayCard = '';
        $this->mesaPayRef = '';
        $this->showMesaPayModal = true;
    }

    public function closeMesaPayModal(): void
    {
        $this->showMesaPayModal = false;
        $this->mesaPayId = null;
        $this->mesaSplitId = null;
        $this->mesaSplitAccountIdx = null;
        $this->mesaPayments = [];
    }

    public function addMesaPayment(): void
    {
        $cashRegisterId = $this->activeCashRegister?->id;
        if ($this->mesaSplitId !== null) {
            $split = MesaSplit::whereHas('mesa.orders', fn ($q) => $q->where('cash_register_id', $cashRegisterId)
                ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada']))
                ->find($this->mesaSplitId);
            $total = $split ? (float) ($split->split_data[$this->mesaSplitAccountIdx]['total'] ?? 0) : 0;
        } else {
            $mesa = Mesa::with(['orders' => fn ($q) => $q->where('cash_register_id', $cashRegisterId)
                ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada'])])
                ->find($this->mesaPayId);
            $total = $mesa ? (float) $mesa->orders->sum('total') : 0;
        }

        $paid = collect($this->mesaPayments)->sum('amount');
        $rem = max(0, $total - $paid);
        $amount = (float) $this->mesaPayAmount ?: $rem;
        if ($amount <= 0) {
            return;
        }

        $payment = ['method' => $this->mesaPayMethod, 'amount' => $amount];

        if ($this->mesaPayMethod === 'cash') {
            $received = (float) $this->mesaPayReceived;
            $payment['cash_received'] = $received > 0 ? $received : $amount;
            $payment['cash_change'] = max(0, ($received > 0 ? $received : $amount) - $amount);
        } elseif ($this->mesaPayMethod === 'card') {
            $payment['card_last4'] = $this->mesaPayCard;
        } elseif ($this->mesaPayMethod === 'transfer') {
            $payment['transfer_ref'] = $this->mesaPayRef;
        }

        $this->mesaPayments[] = $payment;
        $this->mesaPayAmount = '';
        $this->mesaPayReceived = '';
        $this->mesaPayCard = '';
        $this->mesaPayRef = '';
    }

    public function removeMesaPayment(int $index): void
    {
        array_splice($this->mesaPayments, $index, 1);
    }

    public function confirmMesaPayment(): void
    {
        // Para un pago único, el cajero puede confirmar directamente. Si no
        // agregó una parcialidad manualmente, usamos el saldo pendiente con
        // el método seleccionado en el modal.
        if (empty($this->mesaPayments)) {
            $this->addMesaPayment();
        }

        if (empty($this->mesaPayments)) {
            $this->dispatch('notify', type: 'warning', message: 'Agrega un monto válido para continuar.');

            return;
        }

        if ($this->mesaSplitId !== null) {
            $this->confirmSplitAccountPayment();
        } else {
            $this->confirmFullMesaPayment();
        }
    }

    private function confirmFullMesaPayment(): void
    {
        $cashRegisterId = $this->activeCashRegister?->id;
        $mesa = Mesa::with([
            'area',
            'currentAssignment.waiter',
            'orders' => fn ($q) => $q->where('cash_register_id', $cashRegisterId)
                ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada'])
                ->with('items'),
        ])->whereHas('orders', fn ($q) => $q->where('cash_register_id', $cashRegisterId)
            ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada']))
            ->find($this->mesaPayId);
        if (! $mesa) {
            return;
        }

        $orders = $mesa->orders;
        $mesaTotal = (float) $orders->sum('total');
        $paid = collect($this->mesaPayments)->sum('amount');

        if ($paid < $mesaTotal - 0.01) {
            $this->dispatch('notify', type: 'warning', message: 'El monto es insuficiente.');

            return;
        }

        foreach ($orders as $order) {
            $share = $mesaTotal > 0 ? $order->total / $mesaTotal : (count($orders) > 0 ? 1.0 / count($orders) : 1.0);
            foreach ($this->mesaPayments as $p) {
                $amt = round((float) $p['amount'] * $share, 2);
                if ($amt <= 0) {
                    continue;
                }
                $pay = OrderPayment::create([
                    'order_id' => $order->id,
                    'method' => $this->mapPaymentMethod($p['method']),
                    'amount' => $amt,
                ]);
                if ($p['method'] === 'cash') {
                    $pay->update([
                        'received_amount' => round(($p['cash_received'] ?? $p['amount']) * $share, 2),
                        'change_amount' => round(($p['cash_change'] ?? 0) * $share, 2),
                    ]);
                }
            }
            $order->update(['status' => 'pagada', 'paid_at' => now()]);
        }

        $assignment = $mesa->currentAssignment;
        $this->releaseMesa($mesa);

        // Print ticket
        $ticketItems = $orders->flatMap(fn ($o) => $o->items->map(fn ($i) => [
            'qty' => $i->quantity,
            'name' => $i->product_name,
            'subtotal' => (float) $i->subtotal,
        ]))->toArray();

        $this->dispatch('pos-reprint-show',
            html_cliente: $this->buildMesaTicketHtml(
                mesa: $mesa,
                accountLabel: $mesa->display_name,
                items: $ticketItems,
                total: $mesaTotal,
                payments: $this->mesaPayments,
                assignment: $assignment,
                cashierName: auth()->user()->name,
            ),
            html_cocina: '',
        );

        $this->showMesaPayModal = false;
        $this->mesaPayId = null;
        $this->mesaPayments = [];
        unset($this->mesasPendientes);
        $this->dispatch('notify', type: 'success', message: "Mesa {$mesa->display_name} cobrada y liberada.");
    }

    private function confirmSplitAccountPayment(): void
    {
        $cashRegisterId = $this->activeCashRegister?->id;
        $split = MesaSplit::with([
            'mesa.area',
            'mesa.currentAssignment.waiter',
        ])->whereHas('mesa.orders', fn ($q) => $q->where('cash_register_id', $cashRegisterId)
            ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada']))
            ->findOrFail($this->mesaSplitId);

        $mesa = $split->mesa;
        $splitData = $split->split_data;
        $account = $splitData[$this->mesaSplitAccountIdx] ?? null;
        if (! $account || ($account['paid'] ?? false)) {
            $this->dispatch('notify', type: 'warning', message: 'Esta subcuenta ya no está disponible para cobro.');
            $this->closeMesaPayModal();

            return;
        }
        $accountTotal = (float) $account['total'];
        $assignment = $mesa->currentAssignment;

        $paid = collect($this->mesaPayments)->sum('amount');
        if ($paid < $accountTotal - 0.01) {
            $this->dispatch('notify', type: 'warning', message: 'El monto es insuficiente.');

            return;
        }

        // Map split items to orders for payment records
        $accountItems = $account['items'] ?? [];
        $orderAmounts = [];

        if (! empty($accountItems)) {
            foreach ($accountItems as $item) {
                $oi = OrderItem::find($item['id'] ?? null);
                if ($oi) {
                    $orderAmounts[$oi->order_id] = ($orderAmounts[$oi->order_id] ?? 0) + (float) $item['subtotal'];
                }
            }
        }

        // Fallback: distribute across active orders
        if (empty($orderAmounts)) {
            $activeOrders = Order::where('mesa_id', $mesa->id)
                ->where('cash_register_id', $cashRegisterId)
                ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada'])
                ->get();
            $share = $accountTotal / max(1, $activeOrders->count());
            foreach ($activeOrders as $o) {
                $orderAmounts[$o->id] = $share;
            }
        }

        foreach ($this->mesaPayments as $p) {
            foreach ($orderAmounts as $orderId => $orderAmt) {
                $ratio = $accountTotal > 0 ? $orderAmt / $accountTotal : 1.0 / count($orderAmounts);
                $amt = round((float) $p['amount'] * $ratio, 2);
                if ($amt <= 0) {
                    continue;
                }
                $pay = OrderPayment::create([
                    'order_id' => $orderId,
                    'method' => $this->mapPaymentMethod($p['method']),
                    'amount' => $amt,
                ]);
                if ($p['method'] === 'cash') {
                    $pay->update([
                        'received_amount' => round(($p['cash_received'] ?? $p['amount']) * $ratio, 2),
                        'change_amount' => round(($p['cash_change'] ?? 0) * $ratio, 2),
                    ]);
                }
            }
        }

        // Mark account paid
        $splitData[$this->mesaSplitAccountIdx]['paid'] = true;
        $allPaid = collect($splitData)->every(fn ($a) => (bool) ($a['paid'] ?? false));
        $split->update([
            'split_data' => $splitData,
            'status' => $allPaid ? 'completado' : 'parcial',
        ]);

        if ($allPaid) {
            // Cierra cualquier split activo duplicado que pudiera existir por
            // el flujo anterior. Ningún split pendiente debe mantener la mesa
            // visible después de liquidar su última subcuenta.
            MesaSplit::where('mesa_id', $mesa->id)
                ->where('id', '!=', $split->id)
                ->whereIn('status', ['pendiente', 'parcial'])
                ->update(['status' => 'completado']);

            Order::where('mesa_id', $mesa->id)
                ->where('cash_register_id', $cashRegisterId)
                ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada'])
                ->update(['status' => 'pagada', 'paid_at' => now()]);

            $this->releaseMesa($mesa);
        }

        // Build ticket items from split_data snapshot
        $ticketItems = collect($accountItems)->map(fn ($i) => [
            'qty' => $i['qty'],
            'name' => $i['name'],
            'subtotal' => (float) $i['subtotal'],
        ])->toArray();

        $this->dispatch('pos-reprint-show',
            html_cliente: $this->buildMesaTicketHtml(
                mesa: $mesa,
                accountLabel: $account['label'],
                items: $ticketItems,
                total: $accountTotal,
                payments: $this->mesaPayments,
                assignment: $assignment,
                cashierName: auth()->user()->name,
            ),
            html_cocina: '',
        );

        $this->showMesaPayModal = false;
        $this->mesaSplitId = null;
        $this->mesaSplitAccountIdx = null;
        $this->mesaPayId = null;
        $this->mesaPayments = [];
        unset($this->mesasPendientes);
        $this->dispatch('mesa-payment-completed', mesaId: $mesa->id, released: $allPaid);

        $msg = $allPaid
            ? "Mesa {$mesa->display_name} cobrada y liberada."
            : "Cuenta \"{$account['label']}\" cobrada.";
        $this->dispatch('notify', type: 'success', message: $msg);
    }

    private function releaseMesa(Mesa $mesa): void
    {
        MesaAssignment::where('mesa_id', $mesa->id)
            ->whereNull('released_at')
            ->update([
                'released_by' => auth()->id(),
                'released_at' => now(),
                'release_reason' => 'Cobrado desde POS',
            ]);

        $groupId = $mesa->mesa_group_id;
        $mesa->update(['status' => 'disponible', 'mesa_group_id' => null]);

        if ($groupId) {
            $remaining = Mesa::where('mesa_group_id', $groupId)->where('id', '!=', $mesa->id)->count();
            if ($remaining <= 1) {
                Mesa::where('mesa_group_id', $groupId)->update(['mesa_group_id' => null]);
                MesaGroup::destroy($groupId);
            }
        }
    }

    private function releaseMesaIfSettled(int $mesaId): bool
    {
        $hasActiveOrders = Order::where('mesa_id', $mesaId)
            ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada'])
            ->exists();

        if ($hasActiveOrders) {
            return false;
        }

        $mesa = Mesa::find($mesaId);
        if (! $mesa) {
            return false;
        }

        $this->releaseMesa($mesa);

        return true;
    }

    private function buildMesaTicketHtml(
        Mesa $mesa,
        string $accountLabel,
        array $items,
        float $total,
        array $payments,
        ?MesaAssignment $assignment,
        string $cashierName,
    ): string {
        return app(ThermalTicketRenderer::class)->renderMesaAccount(
            $mesa,
            $accountLabel,
            $items,
            $total,
            $payments,
            $assignment,
            $cashierName,
        );

        $appName = config('app.name');
        $now = now()->format('d/m/Y H:i');
        $waiterName = $assignment?->waiter?->name ?? '—';
        $openedAt = $assignment ? $assignment->assigned_at->format('d/m/Y H:i') : '—';

        $itemsHtml = '';
        foreach ($items as $item) {
            $sub = number_format((float) $item['subtotal'], 2);
            $itemsHtml .= '<tr>'
                ."<td>{$item['qty']}x ".htmlspecialchars($item['name']).'</td>'
                ."<td class='r'>\${$sub}</td>"
                .'</tr>';
        }

        $paymentsHtml = '';
        $totalChange = 0;
        foreach ($payments as $p) {
            $label = match ($p['method']) {
                'cash' => 'Efectivo',
                'card' => 'Tarjeta',
                'transfer' => 'Transferencia',
                default => ucfirst($p['method']),
            };
            $paymentsHtml .= "<tr><td>{$label}</td><td class='r'>\$".number_format((float) $p['amount'], 2).'</td></tr>';
            if (isset($p['cash_change']) && $p['cash_change'] > 0) {
                $totalChange += $p['cash_change'];
                $paymentsHtml .= "<tr><td class='addon'>Cambio</td><td class='r addon'>\$".number_format($p['cash_change'], 2).'</td></tr>';
            }
            if (! empty($p['card_last4'])) {
                $paymentsHtml .= "<tr><td colspan='2' class='addon'>Tarjeta: •••• {$p['card_last4']}</td></tr>";
            }
            if (! empty($p['transfer_ref'])) {
                $paymentsHtml .= "<tr><td colspan='2' class='addon'>Ref: {$p['transfer_ref']}</td></tr>";
            }
        }

        $mesaName = htmlspecialchars($mesa->display_name);
        $areaName = htmlspecialchars($mesa->area?->name ?? '');
        $label = htmlspecialchars($accountLabel);
        $totalFmt = number_format($total, 2);

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  @page { size: 80mm auto; margin: 4mm; }
  * { box-sizing: border-box; }
  html { background: #f0f0f0; }
  body {
    font-family: 'Courier New', monospace;
    font-size: 12px;
    margin: 8px auto;
    padding: 10px 12px;
    width: 72mm;
    background: #fff;
    color: #000;
    box-shadow: 0 2px 8px rgba(0,0,0,.12);
  }
  h2 { text-align: center; font-size: 15px; margin: 0 0 2px; letter-spacing: .04em; }
  .center { text-align: center; }
  .small  { font-size: 10px; color: #444; margin: 1px 0; }
  .bold   { font-weight: bold; }
  hr { border: none; border-top: 1px dashed #999; margin: 5px 0; }
  table { width: 100%; border-collapse: collapse; }
  td { padding: 2px 0; vertical-align: top; font-size: 12px; }
  .r { text-align: right; white-space: nowrap; padding-left: 6px; }
  .addon { font-size: 10px; color: #555; padding-left: 10px; }
  .total td { font-weight: bold; font-size: 14px; border-top: 1px dashed #000; padding-top: 4px; }
  .meta td { font-size: 10px; color: #444; padding: 1px 0; }
  .footer { text-align: center; font-size: 10px; color: #666; margin-top: 8px; }
  @media print { body { margin: 0; } }
</style>
</head>
<body>
  <h2>{$appName}</h2>
  <div class="center small">TICKET DE MESA</div>
  <div class="center small">{$now}</div>
  <hr>
  <table class="meta">
    <tr><td>Mesa</td><td class="r bold">{$mesaName}</td></tr>
    <tr><td>Área</td><td class="r">{$areaName}</td></tr>
    <tr><td>Apertura</td><td class="r">{$openedAt}</td></tr>
    <tr><td>Mesero</td><td class="r">{$waiterName}</td></tr>
    <tr><td>Cajero</td><td class="r">{$cashierName}</td></tr>
  </table>
  <hr>
  <div class="center bold" style="font-size:11px;letter-spacing:.04em">{$label}</div>
  <hr>
  <table>{$itemsHtml}</table>
  <hr>
  <table>
    <tr class="total"><td>TOTAL</td><td class="r">\${$totalFmt}</td></tr>
  </table>
  <hr>
  <table>{$paymentsHtml}</table>
  <hr>
  <div class="footer">¡Gracias por su visita!</div>
  <script>window.onload=function(){ window.print(); }<\/script>
</body>
</html>
HTML;
    }

    public function addPickupPayment(): void
    {
        // Si no ingresaron monto, usar el restante exacto
        $order = Order::where('cash_register_id', $this->activeCashRegister?->id)
            ->find($this->pickupPayOrderId);
        $paid = collect($this->pickupPayments)->sum('amount');
        $rem = $order ? max(0, $order->total - $paid) : 0;
        $amount = (float) $this->pickupPayAmount ?: $rem;
        if ($amount <= 0) {
            return;
        }

        $payment = ['method' => $this->pickupPayMethod, 'amount' => $amount];

        if ($this->pickupPayMethod === 'cash') {
            $received = (float) $this->pickupPayReceived;
            // Si no ingresaron recibido, asumen pago exacto
            $payment['cash_received'] = $received > 0 ? $received : $amount;
            $payment['cash_change'] = max(0, ($received > 0 ? $received : $amount) - $amount);
        } elseif ($this->pickupPayMethod === 'card') {
            $payment['card_last4'] = $this->pickupPayCard;
        } elseif ($this->pickupPayMethod === 'transfer') {
            $payment['transfer_ref'] = $this->pickupPayRef;
        }

        $this->pickupPayments[] = $payment;
        $this->pickupPayAmount = '';
        $this->pickupPayReceived = '';
        $this->pickupPayCard = '';
        $this->pickupPayRef = '';
    }

    public function removePickupPayment(int $index): void
    {
        array_splice($this->pickupPayments, $index, 1);
    }

    public function confirmPickupPayment(): void
    {
        $order = Order::with(['items'])
            ->where('cash_register_id', $this->activeCashRegister?->id)
            ->find($this->pickupPayOrderId);
        if (! $order) {
            return;
        }

        $isPayableArea = $order->source === 'kiosk'
            || in_array($order->type, ['pick_up', 'ventanilla', 'delivery', 'mesa'], true);

        if (! $isPayableArea || $order->status !== 'lista') {
            $this->dispatch('notify', type: 'warning', message: 'El pedido debe estar listo antes de cobrarlo.');

            return;
        }

        $paid = collect($this->pickupPayments)->sum('amount');
        if ($paid < $order->total - 0.01) {
            $this->dispatch('notify', type: 'warning', message: 'El monto es insuficiente.');

            return;
        }

        foreach ($this->pickupPayments as $p) {
            $payment = OrderPayment::create([
                'order_id' => $order->id,
                'method' => $this->mapPaymentMethod($p['method']),
                'amount' => $p['amount'],
            ]);

            if ($p['method'] === 'cash') {
                $payment->update([
                    'received_amount' => $p['cash_received'] ?? $p['amount'],
                    'change_amount' => $p['cash_change'] ?? 0,
                ]);
            }
        }

        $order->update(['status' => 'pagada', 'paid_at' => now()]);

        $mesaWasReleased = false;
        if ($order->mesa_id) {
            $mesaWasReleased = $this->releaseMesaIfSettled((int) $order->mesa_id);
        }

        $this->showPickupPayModal = false;
        $this->pickupPayOrderId = null;
        $this->pickupPayments = [];
        unset(
            $this->pickupOrders,
            $this->deliveryOrders,
            $this->kioskDineInOrders,
            $this->mesasPendientes,
            $this->recentOrders,
            $this->reprintMesaGroups,
        );
        $message = $mesaWasReleased
            ? "Orden #{$order->id} cobrada. Era la última nota y la mesa quedó disponible."
            : "Orden #{$order->id} cobrada.";
        $this->dispatch('notify', type: 'success', message: $message);

        // Dispatch print event for the order
        $this->openReprintModal($order->id);
    }

    // ─── Kitchen panel ────────────────────────────────────────────────────────

    #[Computed]
    public function kitchenOrders()
    {
        $cashRegisterId = $this->activeCashRegister?->id;

        if (! $cashRegisterId) {
            return collect();
        }

        return Order::with([
            'items.addons',
            'items.ingredients',
            'items.product.category.printArea',
            'mesa.area',
        ])
            ->where('cash_register_id', $cashRegisterId)
            ->whereIn('status', ['pendiente', 'en_preparacion'])
            ->where(fn ($query) => $query->where('type', 'mesa')->orWhere('source', 'kiosk'))
            ->when($this->kitchenSearch, function ($q) {
                $search = $this->kitchenSearch;
                $q->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhereHas('mesa', fn ($q) => $q->where('number', 'like', "%{$search}%"));
                });
            })
            ->orderBy('created_at')
            ->get();
    }

    #[Computed]
    public function kitchenPendingCount(): int
    {
        $cashRegisterId = $this->activeCashRegister?->id;

        if (! $cashRegisterId) {
            return 0;
        }

        return Order::where('cash_register_id', $cashRegisterId)
            ->whereIn('status', ['pendiente', 'en_preparacion'])
            ->where(fn ($query) => $query->where('type', 'mesa')->orWhere('source', 'kiosk'))
            ->count();
    }

    public function updatedKitchenSearch(): void
    {
        unset($this->kitchenOrders);
    }

    public function updatedPickupSearch(): void
    {
        unset($this->pickupOrders);
    }

    public function updatedDeliverySearch(): void
    {
        unset($this->deliveryOrders);
    }

    public function markKitchenReady(int $orderId): void
    {
        $order = Order::with([
            'items.addons',
            'items.ingredients',
            'items.product.category.printArea',
        ])->where('cash_register_id', $this->activeCashRegister?->id)
            ->find($orderId);

        $isOperationalOrder = $order
            && ($order->source === 'kiosk' || in_array($order->type, ['mesa', 'pick_up', 'ventanilla', 'delivery'], true));

        if (! $isOperationalOrder || ! in_array($order->status, ['pendiente', 'en_preparacion'], true)) {
            return;
        }

        $nextStatus = $order->status === 'pendiente' ? 'en_preparacion' : 'lista';
        $order->update(['status' => $nextStatus]);
        unset(
            $this->kitchenOrders,
            $this->kitchenPendingCount,
            $this->pickupOrders,
            $this->deliveryOrders,
            $this->kioskDineInOrders,
            $this->mesasPendientes,
            $this->recentOrders,
            $this->reprintMesaGroups,
        );

        if ($nextStatus === 'en_preparacion') {
            $this->dispatch('pos-reprint-show-cocina',
                html_cliente: '',
                html_cocina: $this->buildKitchenTicketHtml($order),
            );
        }

        $message = $nextStatus === 'en_preparacion'
            ? "Orden #{$order->id} enviada a cocina."
            : "Orden #{$order->id} marcada como lista.";
        $this->dispatch('notify', type: 'success', message: $message);
    }

    public function reprintKitchenOrder(int $orderId): void
    {
        $order = Order::with([
            'items.addons',
            'items.ingredients',
            'items.product.category.printArea',
        ])->where('cash_register_id', $this->activeCashRegister?->id)
            ->find($orderId);

        if (! $order) {
            return;
        }

        if ($order->status === 'pendiente') {
            $order->update(['status' => 'en_preparacion']);
            unset(
                $this->kitchenOrders,
                $this->kitchenPendingCount,
                $this->pickupOrders,
                $this->deliveryOrders,
                $this->kioskDineInOrders,
                $this->mesasPendientes,
                $this->recentOrders,
                $this->reprintMesaGroups,
            );
            $this->dispatch('notify', type: 'success', message: "Orden #{$order->id} impresa y enviada a preparación.");
        }

        $this->dispatch('pos-reprint-show-cocina',
            html_cliente: '',
            html_cocina: $this->buildKitchenTicketHtml($order),
        );
    }

    // ─── Print via iframe ─────────────────────────────────────────────────────

    public function openReprintModal(int $orderId): void
    {
        $order = Order::with([
            'items.addons',
            'items.ingredients',
            'items.product.category.printArea',
            'payments',
        ])->where('cash_register_id', $this->activeCashRegister?->id)
            ->findOrFail($orderId);

        $this->dispatch('pos-reprint-show',
            html_cliente: $this->buildTicketHtml($order),
            html_cocina: $this->buildKitchenTicketHtml($order),
        );
    }

    private function buildTicketHtml(Order $order): string
    {
        return app(ThermalTicketRenderer::class)->renderOrder(
            $order,
            $order->type === 'delivery' ? 'delivery' : 'customer',
        );

        $appName = config('app.name');
        $now = now()->format('d/m/Y H:i');
        $typeLabel = $order->source === 'kiosk'
            ? match ($order->fulfillment) {
                'dine_in' => 'Kiosco - Comer Aqui',
                'delivery' => 'Kiosco - Domicilio',
                default => 'Kiosco - Para Recoger',
            }
        : match ($order->type) {
            'mesa' => 'Mesa',
            'pick_up' => 'Para Recoger',
            'delivery' => 'Delivery',
            default => 'Ventanilla',
        };

        // Items
        $itemsHtml = '';
        foreach ($order->items as $item) {
            $itemsHtml .= '<tr>'
                ."<td>{$item->quantity}x {$item->product_name}</td>"
                ."<td class='r'>\${$item->subtotal}</td>"
                .'</tr>';
            foreach ($item->addons as $a) {
                $price = $a->extra_price > 0 ? "+\${$a->extra_price}" : '';
                $itemsHtml .= '<tr>'
                    ."<td class='addon'>+ {$a->addon_name}</td>"
                    ."<td class='r addon'>{$price}</td>"
                    .'</tr>';
            }
            foreach ($item->ingredients as $i) {
                $price = $i->extra_price > 0 ? "+\${$i->extra_price}" : '';
                $qty = $i->quantity > 1 ? " x{$i->quantity}" : '';
                $itemsHtml .= '<tr>'
                    ."<td class='addon'>&#x2022; {$i->ingredient_name}{$qty}</td>"
                    ."<td class='r addon'>{$price}</td>"
                    .'</tr>';
            }
            if ($item->notes) {
                $itemsHtml .= "<tr><td colspan='2' class='note'>\"{$item->notes}\"</td></tr>";
            }
        }

        // Payments
        $paymentsHtml = '';
        foreach ($order->payments as $p) {
            $method = match ($p->method) {
                'efectivo' => 'Efectivo',
                'tarjeta' => 'Tarjeta',
                'transferencia' => 'Transferencia',
                'contra_entrega' => 'Contraentrega',
                default => ucfirst($p->method),
            };
            $paymentsHtml .= "<tr><td>{$method}</td><td class='r'>\${$p->amount}</td></tr>";
            if ($p->change_amount > 0) {
                $paymentsHtml .= "<tr><td class='addon'>Cambio</td><td class='r addon'>\${$p->change_amount}</td></tr>";
            }
        }

        // Customer / delivery block
        $isDelivery = $order->type === 'delivery';

        if ($isDelivery) {
            $name = htmlspecialchars($order->customer_name ?? 'Sin nombre');
            $phone = htmlspecialchars($order->customer_phone ?? '');
            $addr = htmlspecialchars($order->customer_address ?? '');
            $refs = htmlspecialchars($order->customer_references ?? '');
            $method = match ($order->delivery_method ?? '') {
                'contra_entrega' => 'Contraentrega',
                'card' => 'Tarjeta online',
                'transfer' => 'Transferencia',
                default => 'Delivery',
            };

            $addrVal = $addr ?: '—';
            $refsVal = $refs ?: '—';
            $phoneVal = $phone ?: '—';

            $customerHtml = "
  <hr>
  <div class='delivery-box'>
    <div class='dlv-title'>DELIVERY</div>
    <table class='dlv-table'>
      <tr><td class='dlv-lbl'>Cliente</td><td class='dlv-val'>{$name}</td></tr>
      <tr><td class='dlv-lbl'>Tel&eacute;fono</td><td class='dlv-val'>{$phoneVal}</td></tr>
      <tr><td class='dlv-lbl'>Direcci&oacute;n</td><td class='dlv-val'>{$addrVal}</td></tr>
      <tr><td class='dlv-lbl'>Referencias</td><td class='dlv-val'>{$refsVal}</td></tr>
      <tr><td class='dlv-lbl'>Cobro</td><td class='dlv-val'>{$method}</td></tr>
    </table>
  </div>";
        } else {
            $customerHtml = $order->customer_name
                ? "<div class='center small'>Cliente: ".htmlspecialchars($order->customer_name).'</div>'
                : '';
        }

        $paymentsBlock = $paymentsHtml
            ? "<hr><table>{$paymentsHtml}</table>"
            : '';

        $deliveryCss = $isDelivery ? '
  .delivery-box { border: 2px solid #000; border-radius: 3px; padding: 6px 8px; margin: 5px 0; }
  .dlv-title { font-weight: bold; font-size: 13px; text-align: center; margin-bottom: 4px; letter-spacing: .05em; }
  .dlv-table { width: 100%; }
  .dlv-lbl { font-size: 10px; color: #555; white-space: nowrap; padding-right: 6px; vertical-align: top; }
  .dlv-val { font-size: 11px; font-weight: 600; color: #000; }' : '';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  @page { size: 80mm auto; margin: 4mm; }
  * { box-sizing: border-box; }
  html { background: #f0f0f0; }
  body {
    font-family: 'Courier New', monospace;
    font-size: 12px;
    margin: 8px auto;
    padding: 10px 12px;
    width: 72mm;
    background: #fff;
    color: #000;
    box-shadow: 0 2px 8px rgba(0,0,0,.12);
  }
  h2 { text-align: center; font-size: 15px; margin: 0 0 2px; letter-spacing: .04em; }
  .center { text-align: center; }
  .small { font-size: 10px; color: #444; margin: 1px 0; }
  hr { border: none; border-top: 1px dashed #999; margin: 5px 0; }
  table { width: 100%; border-collapse: collapse; }
  td { padding: 2px 0; vertical-align: top; font-size: 12px; }
  .r { text-align: right; white-space: nowrap; padding-left: 6px; }
  .addon { font-size: 10px; color: #555; padding-left: 10px; }
  .note { font-size: 10px; color: #777; font-style: italic; padding-left: 10px; }
  .total td { font-weight: bold; font-size: 14px; border-top: 1px dashed #000; padding-top: 4px; }
  .footer { text-align: center; font-size: 10px; color: #666; margin-top: 8px; }
  {$deliveryCss}
  @media print { body { margin: 0; } }
</style>
</head>
<body>
  <h2>{$appName}</h2>
  <div class="center small">Pedido #{$order->id} &mdash; {$typeLabel}</div>
  <div class="center small">{$now}</div>
  {$customerHtml}
  <hr>
  <table>{$itemsHtml}</table>
  <hr>
  <table>
    <tr class="total"><td>TOTAL</td><td class="r">\${$order->total}</td></tr>
  </table>
  {$paymentsBlock}
  <hr>
  <div class="footer">¡Gracias por su visita!</div>
  <script>window.onload=function(){ window.print(); }<\/script>
</body>
</html>
HTML;
    }

    private function buildKitchenTicketHtml(Order $order): string
    {
        return app(ThermalTicketRenderer::class)->renderOrder($order, 'kitchen_area');

        $appName = config('app.name');
        $now = now()->format('d/m/Y H:i');
        $typeLabel = $order->source === 'kiosk'
            ? match ($order->fulfillment) {
                'dine_in' => 'Kiosco - Mesa '.($order->mesa?->number ?? 'sin asignar'),
                'delivery' => 'Kiosco - Delivery',
                default => 'Kiosco - Ventanilla',
            }
        : match ($order->type) {
            'mesa' => 'Mesa '.($order->mesa?->number ?? 'sin asignar'),
            'pick_up' => 'Ventanilla - Para Recoger',
            'delivery' => 'Delivery',
            default => 'Ventanilla',
        };
        $customerLine = $order->customer_name
            ? "<div class='center small'>Cliente: ".htmlspecialchars($order->customer_name).'</div>'
            : '';

        // Group items by print area (null = Sin área)
        $areas = [];
        foreach ($order->items as $item) {
            $area = $item->product?->category?->printArea;
            $areaId = $area?->id ?? 0;
            $areaName = $area?->name ?? 'General';

            if (! isset($areas[$areaId])) {
                $areas[$areaId] = ['name' => $areaName, 'items' => []];
            }
            $areas[$areaId]['items'][] = $item;
        }

        if (empty($areas)) {
            return '<html><body style="font-family:monospace;text-align:center;padding:20px">Sin productos</body></html>';
        }

        $sections = '';
        $areaValues = array_values($areas);
        $lastIndex = count($areaValues) - 1;

        foreach ($areaValues as $idx => $area) {
            $isLast = $idx === $lastIndex;
            $itemsHtml = '';

            foreach ($area['items'] as $item) {
                $itemsHtml .= '<tr>'
                    ."<td class='qty'>{$item->quantity}x</td>"
                    ."<td>{$item->product_name}</td>"
                    .'</tr>';
                foreach ($item->addons as $a) {
                    $itemsHtml .= "<tr><td></td><td class='mod'>+ {$a->addon_name}</td></tr>";
                }
                foreach ($item->ingredients as $i) {
                    $qty = $i->quantity > 1 ? " x{$i->quantity}" : '';
                    $itemsHtml .= "<tr><td></td><td class='mod'>&#x2022; {$i->ingredient_name}{$qty}</td></tr>";
                }
                if ($item->notes) {
                    $itemsHtml .= "<tr><td></td><td class='note'>\"{$item->notes}\"</td></tr>";
                }
            }

            $pageBreak = $isLast ? '' : "<div class='pb'></div>";

            $sections .= <<<SECTION
<div class="ticket">
  <h2>{$appName}</h2>
  <div class="center small">Pedido #{$order->id} &mdash; {$typeLabel}</div>
  <div class="center small">{$now}</div>
  {$customerLine}
  <hr>
  <div class="area-name">{$area['name']}</div>
  <hr>
  <table>{$itemsHtml}</table>
  <hr>
  <div class="footer">&#x2702; Cortar aqu&iacute;</div>
</div>
{$pageBreak}
SECTION;
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  @page { size: 80mm auto; margin: 4mm; }
  * { box-sizing: border-box; }
  html { background: #f0f0f0; }
  body { font-family: 'Courier New', monospace; font-size: 12px; margin: 0; padding: 0; color: #000; }
  .ticket {
    width: 72mm;
    background: #fff;
    margin: 8px auto;
    padding: 10px 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,.12);
  }
  h2 { text-align: center; font-size: 15px; margin: 0 0 2px; letter-spacing: .04em; }
  .center { text-align: center; }
  .small { font-size: 10px; color: #444; margin: 1px 0; }
  .area-name { font-size: 14px; font-weight: bold; text-align: center; padding: 4px 0; letter-spacing: .05em; text-transform: uppercase; }
  hr { border: none; border-top: 1px dashed #999; margin: 5px 0; }
  table { width: 100%; border-collapse: collapse; }
  td { padding: 3px 0; vertical-align: top; font-size: 12px; }
  .qty { width: 26px; font-weight: bold; }
  .mod { font-size: 10px; color: #555; padding-left: 4px; }
  .note { font-size: 10px; color: #777; font-style: italic; padding-left: 4px; }
  .footer { text-align: center; font-size: 10px; color: #888; margin-top: 4px; }
  .pb { page-break-after: always; }
  @media print {
    html { background: none; }
    .ticket { margin: 0; padding: 6px 8px; box-shadow: none; width: 100%; }
    .pb { page-break-after: always; height: 0; }
  }
</style>
</head>
<body>
{$sections}
<script>window.onload=function(){ window.print(); }<\/script>
</body>
</html>
HTML;
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    private function persistOrder(string $type, string $status): Order
    {
        $cash = $this->activeCashRegister;

        $order = Order::create([
            'cash_register_id' => $cash?->id,
            'customer_id' => $this->customerId,
            'customer_name' => $this->customerName ?: null,
            'customer_phone' => $this->customerPhone ?: null,
            'customer_address' => $type === 'delivery' ? ($this->customerAddress ?: null) : null,
            'customer_references' => $type === 'delivery' ? ($this->customerReferences ?: null) : null,
            'served_by' => auth()->id(),
            'type' => $type,
            'delivery_method' => $type === 'delivery' ? $this->deliveryMethod : null,
            'status' => $status,
            'subtotal' => $this->cartTotal,
            'total' => $this->cartTotal,
            'notes' => $this->orderNotes ?: null,
            'paid_at' => $status === 'pagada' ? now() : null,
        ]);

        foreach ($this->cart as $item) {
            $orderItem = OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'product_name' => $item['product_name'],
                'product_price' => $item['product_price'],
                'quantity' => $item['quantity'],
                'subtotal' => $item['subtotal'],
                'notes' => $item['notes'] ?: null,
            ]);

            foreach ($item['addons'] as $a) {
                OrderItemAddon::create([
                    'order_item_id' => $orderItem->id,
                    'addon_id' => $a['addon_id'],
                    'addon_name' => $a['addon_name'],
                    'extra_price' => $a['extra_price'],
                    'quantity' => 1,
                ]);
            }

            foreach ($item['ingredients'] as $i) {
                OrderItemIngredient::create([
                    'order_item_id' => $orderItem->id,
                    'ingredient_id' => $i['ingredient_id'],
                    'ingredient_name' => $i['ingredient_name'],
                    'extra_price' => $i['extra_price'],
                    'quantity' => $i['quantity'],
                ]);
            }
        }

        return $order;
    }

    private function finishSale(Order $order): void
    {
        if ($this->activeQuotationId) {
            Quotation::find($this->activeQuotationId)?->delete();
            $this->activeQuotationId = null;
            unset($this->quotations);
        }

        $this->lastOrderId = $order->id;
        $this->lastOrderFolio = $order->folio;
        $this->lastOrderType = $order->type;
        $this->showOrderSuccess = true;
        $this->cart = [];
        $this->saveCart();
        $this->resetOrderForm();
        unset($this->cartTotal, $this->cartCount, $this->activeCashRegister, $this->recentOrders);
        $this->dispatch('notify', type: 'success', message: "Orden #{$order->id} creada.");
    }

    private function resetOrderForm(): void
    {
        $this->orderType = 'ventanilla';
        $this->deliveryMethod = 'contra_entrega';
        $this->orderNotes = '';
        $this->customerId = null;
        $this->customerName = '';
        $this->customerPhone = '';
        $this->customerAddress = '';
        $this->customerReferences = '';
        $this->customerSearch = '';
        $this->payments = [];
    }

    public function startNewSale(): void
    {
        $this->showOrderSuccess = false;
        $this->lastOrderId = null;
        $this->lastOrderFolio = null;
        $this->lastOrderType = null;
    }

    private function findDuplicateCartItem(int $productId, array $addons, array $ingredients): ?int
    {
        foreach ($this->cart as $index => $item) {
            if ($item['product_id'] !== $productId) {
                continue;
            }

            $itemAddonIds = collect($item['addons'])->pluck('addon_id')->sort()->values()->toArray();
            $newAddonIds = collect($addons)->pluck('addon_id')->sort()->values()->toArray();
            if ($itemAddonIds !== $newAddonIds) {
                continue;
            }

            $itemIngs = collect($item['ingredients'])->sortBy('ingredient_id')
                ->map(fn ($i) => [$i['ingredient_id'], $i['quantity']])->values()->toArray();
            $newIngs = collect($ingredients)->sortBy('ingredient_id')
                ->map(fn ($i) => [$i['ingredient_id'], $i['quantity']])->values()->toArray();
            if ($itemIngs !== $newIngs) {
                continue;
            }

            return $index;
        }

        return null;
    }

    // ──────────────────────────────────────────────────────────────────────────

    public function render()
    {
        return view('livewire.pos.point-of-sale')
            ->layout('layouts.pos');
    }
}
