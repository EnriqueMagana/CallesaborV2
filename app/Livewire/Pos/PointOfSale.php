<?php

namespace App\Livewire\Pos;

use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Mesa;
use App\Models\MesaAssignment;
use App\Models\MesaGroup;
use App\Models\MesaService;
use App\Models\MesaSplit;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAddon;
use App\Models\OrderItemIngredient;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\QuotationItemAddon;
use App\Models\QuotationItemIngredient;
use App\Services\DeliveryModulePolicy;
use App\Services\InventoryService;
use App\Services\ManualDeliveryAccountingService;
use App\Services\MesaServiceManager;
use App\Services\PromotionPricingService;
use App\Services\ThermalTicketRenderer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class PointOfSale extends Component
{
    private const DRAFT_STATE_VERSION = 1;

    private const MAX_ITEM_QUANTITY = 99;

    private const MAX_ITEM_NOTES_LENGTH = 500;

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

    public string $customerNeighborhood = '';

    public string $customerReferences = '';

    public string $customerSearch = '';

    // ─── New customer inline ───────────────────────────────────────────────────
    public bool $showAddCustomerModal = false;

    public string $newCustomerName = '';

    public string $newCustomerPhone = '';

    public string $newCustomerEmail = '';

    public string $newCustomerAddress = '';

    public string $newCustomerNeighborhood = '';

    public string $newCustomerReferences = '';

    // ─── Product browsing ─────────────────────────────────────────────────────
    public string $productSearch = '';

    public ?int $selectedCategoryId = null;

    public string $catalogMode = 'products';

    public bool $showPromotionModal = false;

    public ?int $customizingPromotionId = null;

    public array $promotionSelections = [];

    public int $promotionQuantity = 1;

    public ?int $automaticPromotionPickerId = null;

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

    // ─── Movimientos operativos ───────────────────────────────────────────────
    public bool $showExpenseModal = false;

    public string $operationType = 'expense';

    public string $expenseAmount = '';

    public string $expenseCategory = 'otro';

    public string $expenseDescription = '';

    public string $expensePaymentMethod = 'cash';

    public string $expenseNotes = '';

    public ?int $inventoryItemId = null;

    public string $adjustQuantity = '';

    public string $inventoryReason = '';

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

    public bool $tableTrackingLoaded = false;

    public ?string $tableTrackingRefreshedAt = null;

    public bool $tablesBillingLoaded = false;

    public bool $tableWorkspaceLoaded = false;

    public string $tableWorkspaceFilter = 'all';

    public bool $deliveryPanelLoaded = false;

    // ──────────────────────────────────────────────────────────────────────────

    #[Computed]
    public function activeCashRegister(): ?CashRegister
    {
        return CashRegister::where('is_open', true)->latest('opened_at')->first();
    }

    #[Computed]
    public function operationInventoryItems()
    {
        return InventoryItem::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'unit', 'current_stock', 'minimum_stock']);
    }

    #[Computed(persist: true, seconds: 60)]
    public function categoriesWithProducts()
    {
        return Category::query()
            ->select(['id', 'name', 'icon', 'sort_order'])
            ->with(['products' => function ($q) {
                $q->where('is_active', true)
                    ->select([
                        'id', 'category_id', 'name', 'description', 'image', 'price',
                        'is_customizable', 'max_addons', 'min_ingredients',
                        'max_ingredients', 'sort_order',
                    ])
                    ->withCount(['addonGroups', 'ingredients'])
                    ->orderBy('sort_order')->orderBy('name');
            }])
            ->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')
            ->get();
    }

    #[Computed(persist: true, seconds: 60)]
    public function productsWithoutCategory()
    {
        return Product::whereNull('category_id')
            ->where('is_active', true)
            ->select([
                'id', 'category_id', 'name', 'description', 'image', 'price',
                'is_customizable', 'max_addons', 'min_ingredients',
                'max_ingredients', 'sort_order',
            ])
            ->withCount(['addonGroups', 'ingredients'])
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function allCategories()
    {
        return $this->categoriesWithProducts
            ->filter(fn (Category $category) => $category->products->isNotEmpty())
            ->values();
    }

    #[Computed]
    public function activePromotions()
    {
        $fulfillment = $this->promotionFulfillmentForOrderType($this->orderType);
        $relations = [
            'primaryProduct' => fn ($query) => $query
                ->select(['id', 'name', 'image', 'price', 'is_active', 'is_customizable'])
                ->withCount(['addonGroups', 'ingredients']),
            'groups.products' => fn ($query) => $query
                ->where('is_active', true)
                ->select(['products.id', 'products.category_id', 'products.name', 'products.image']),
            'groups.products.category.printArea',
        ];

        $manual = Promotion::query()
            ->available('pos')
            ->forAnyFulfillment(Promotion::POS_FULFILLMENT_MODES)
            ->with($relations)
            ->get();
        $automatic = Promotion::query()
            ->automaticPricingAvailable('pos', null, $fulfillment)
            ->with($relations)
            ->get();

        return $manual->concat($automatic)->unique('id')->sortBy('name')->values();
    }

    #[Computed]
    public function promotionOpportunities(): array
    {
        return app(PromotionPricingService::class)->opportunities(
            $this->cart,
            'pos',
            $this->promotionFulfillmentForOrderType($this->orderType)
        );
    }

    #[Computed]
    public function customizingPromotion(): ?Promotion
    {
        if (! $this->customizingPromotionId) {
            return null;
        }

        return Promotion::query()
            ->available('pos')
            ->forAnyFulfillment(Promotion::POS_FULFILLMENT_MODES)
            ->with(['groups.products' => fn ($query) => $query
                ->where('is_active', true)
                ->select(['products.id', 'products.category_id', 'products.name', 'products.image']),
                'groups.products.category.printArea'])
            ->find($this->customizingPromotionId);
    }

    #[Computed]
    public function automaticPromotionPicker(): ?Promotion
    {
        if (! $this->automaticPromotionPickerId) {
            return null;
        }

        return Promotion::query()
            ->automaticPricingAvailable('pos', null, $this->promotionFulfillmentForOrderType($this->orderType))
            ->with([
                'primaryProduct:id,name,image,price,is_active,is_customizable',
                'groups.products' => fn ($query) => $query->where('is_active', true)
                    ->select(['products.id', 'products.name', 'products.image', 'products.price', 'products.is_customizable']),
            ])
            ->find($this->automaticPromotionPickerId);
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
    public function tableTrackingServices()
    {
        return $this->tableWorkspaceAllServices;
    }

    #[Computed]
    public function tableWorkspaceAllServices()
    {
        $cashRegisterId = $this->activeCashRegister?->id;

        if (! $cashRegisterId || ! $this->tableWorkspaceLoaded) {
            return collect();
        }

        return MesaService::query()
            ->where('cash_register_id', $cashRegisterId)
            ->active()
            ->with([
                'mesas.area',
                'primaryMesa.area',
                'primaryMesa.currentAssignment.waiter',
                'orders' => fn ($query) => $query
                    ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada'])
                    ->with(['items.addons', 'items.ingredients', 'payments'])
                    ->oldest('created_at'),
                'splits' => fn ($query) => $query
                    ->whereIn('status', ['pendiente', 'parcial'])
                    ->latest('id'),
            ])
            ->oldest('opened_at')
            ->get();
    }

    #[Computed]
    public function tableWorkspaceServices()
    {
        return $this->tableWorkspaceAllServices
            ->filter(function (MesaService $service): bool {
                $orders = $service->orders;

                return match ($this->tableWorkspaceFilter) {
                    'service' => $service->status === 'abierta',
                    'kitchen' => $orders->contains(fn (Order $order) => in_array($order->status, ['pendiente', 'en_preparacion'], true)),
                    'ready' => $orders->isNotEmpty()
                        && $orders->every(fn (Order $order) => in_array($order->status, ['lista', 'entregada'], true)),
                    'billing' => $service->status === 'en_cuenta',
                    default => true,
                };
            })
            ->values();
    }

    #[Computed]
    public function tableWorkspaceCounts(): array
    {
        $services = $this->tableWorkspaceAllServices;

        return [
            'all' => $services->count(),
            'service' => $services->where('status', 'abierta')->count(),
            'kitchen' => $services->filter(fn (MesaService $service) => $service->orders
                ->contains(fn (Order $order) => in_array($order->status, ['pendiente', 'en_preparacion'], true)))->count(),
            'ready' => $services->filter(fn (MesaService $service) => $service->orders->isNotEmpty()
                && $service->orders->every(fn (Order $order) => in_array($order->status, ['lista', 'entregada'], true)))->count(),
            'billing' => $services->where('status', 'en_cuenta')->count(),
        ];
    }

    #[Computed]
    public function toolbarPendingCounts(): array
    {
        $cashRegisterId = $this->activeCashRegister?->id;

        if (! $cashRegisterId) {
            return ['pickup' => 0, 'tables' => 0, 'delivery' => 0];
        }

        $activeStatuses = ['pendiente', 'en_preparacion', 'lista'];
        $pickup = Order::query()
            ->where('cash_register_id', $cashRegisterId)
            ->whereDoesntHave('payments')
            ->where(function ($query) use ($activeStatuses) {
                $query->where(function ($kiosk) use ($activeStatuses) {
                    $kiosk->where('source', 'kiosk')
                        ->where('fulfillment', 'takeaway')
                        ->whereIn('status', $activeStatuses);
                })->orWhere(function ($counter) use ($activeStatuses) {
                    $counter->whereIn('type', ['pick_up', 'ventanilla'])
                        ->where(fn ($source) => $source->whereNull('source')->orWhere('source', '!=', 'kiosk'))
                        ->whereIn('status', $activeStatuses);
                });
            })
            ->count();

        $delivery = Order::query()
            ->where('cash_register_id', $cashRegisterId)
            ->where('type', 'delivery')
            ->where('status', 'pendiente')
            ->count();

        $tables = MesaService::query()
            ->where('cash_register_id', $cashRegisterId)
            ->active()
            ->count();
        $legacyTables = Order::query()
            ->where('cash_register_id', $cashRegisterId)
            ->whereNull('mesa_service_id')
            ->whereNotNull('mesa_id')
            ->whereIn('status', $activeStatuses)
            ->distinct()
            ->count('mesa_id');

        return [
            'pickup' => $pickup,
            'tables' => $tables + $legacyTables,
            'delivery' => $delivery,
        ];
    }

    #[Computed]
    public function mesaServiceHistory()
    {
        $cashRegisterId = $this->activeCashRegister?->id;

        if (! $cashRegisterId) {
            return collect();
        }

        $search = trim($this->reprintSearch);

        return MesaService::query()
            ->where('cash_register_id', $cashRegisterId)
            ->whereIn('status', ['pagada', 'liberada'])
            ->with([
                'mesas.area',
                'primaryMesa.area',
                'closer',
                'orders.items',
                'orders.payments',
                'splits',
            ])
            ->when($search, fn ($query) => $query->where(function ($inner) use ($search) {
                $inner->where('service_label', 'like', "%{$search}%")
                    ->orWhere('opener_name_snapshot', 'like', "%{$search}%")
                    ->orWhereHas('orders', fn ($orders) => $orders->where('id', 'like', "%{$search}%"));
            }))
            ->latest('closed_at')
            ->limit(40)
            ->get();
    }

    public function openTableTracking(): void
    {
        $this->openTableWorkspace('all');
    }

    public function closeTableTracking(): void
    {
        $this->closeTableWorkspace();
    }

    public function openTablesBilling(): void
    {
        abort_unless(auth()->user()?->can('cobrar mesas'), 403);
        $this->openTableWorkspace('billing');
    }

    public function closeTablesBilling(): void
    {
        $this->closeTableWorkspace();
    }

    public function openTableWorkspace(string $filter = 'all'): void
    {
        abort_unless(auth()->user()?->canAny(['cobrar mesas', 'editar ordenes', 'reimprimir tickets']), 403);
        $this->resetOperationalPanelState();
        $this->tableWorkspaceLoaded = true;
        $this->tableTrackingLoaded = true;
        $this->tablesBillingLoaded = auth()->user()?->can('cobrar mesas') ?? false;
        $this->setTableWorkspaceFilter($filter);
        $this->refreshTableWorkspace();
    }

    public function closeTableWorkspace(): void
    {
        $this->closeOperationalPanels();
    }

    public function setTableWorkspaceFilter(string $filter): void
    {
        abort_unless(in_array($filter, ['all', 'service', 'kitchen', 'ready', 'billing'], true), 422);
        if ($filter === 'billing') {
            abort_unless(auth()->user()?->can('cobrar mesas'), 403);
        }

        $this->tableWorkspaceFilter = $filter;
        unset($this->tableWorkspaceServices);
    }

    public function refreshTableWorkspace(): void
    {
        $this->tableTrackingRefreshedAt = now()->format('g:i:s A');
        unset(
            $this->tableWorkspaceAllServices,
            $this->tableWorkspaceServices,
            $this->tableWorkspaceCounts,
            $this->tableTrackingServices,
            $this->mesasPendientes,
        );
    }

    public function openDeliveryPanel(): void
    {
        abort_unless(auth()->user()?->can('cerrar ordenes'), 403);
        $this->resetOperationalPanelState();
        $this->deliveryPanelLoaded = true;
        unset($this->deliveryOrders);
    }

    public function closeDeliveryPanel(): void
    {
        $this->closeOperationalPanels();
    }

    public function openPickupPanel(): void
    {
        abort_unless(auth()->user()?->can('cerrar ordenes'), 403);
        $this->resetOperationalPanelState();
    }

    public function openReprintPanel(): void
    {
        abort_unless(auth()->user()?->can('reimprimir tickets'), 403);
        $this->resetOperationalPanelState();
    }

    public function openSavedOrdersModal(): void
    {
        abort_unless(auth()->user()?->canAny(['crear ordenes', 'editar ordenes']), 403);
        $this->resetOperationalPanelState();
        $this->showQuotationsModal = true;
    }

    public function closeOperationalPanels(): void
    {
        $this->resetOperationalPanelState();
    }

    private function resetOperationalPanelState(): void
    {
        $this->tableWorkspaceLoaded = false;
        $this->tableTrackingLoaded = false;
        $this->tablesBillingLoaded = false;
        $this->deliveryPanelLoaded = false;

        unset(
            $this->tableWorkspaceAllServices,
            $this->tableWorkspaceServices,
            $this->tableWorkspaceCounts,
            $this->tableTrackingServices,
            $this->mesasPendientes,
            $this->deliveryOrders,
            $this->pickupOrders,
            $this->recentOrders,
            $this->mesaServiceHistory,
        );
    }

    public function refreshTableTracking(): void
    {
        $this->tableWorkspaceLoaded = true;
        $this->tableTrackingLoaded = true;
        $this->refreshTableWorkspace();
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
        if (! $this->deliveryPanelLoaded) {
            return collect();
        }

        $search = $this->deliverySearch;
        $cashRegisterId = $this->activeCashRegister?->id;

        if (! $cashRegisterId) {
            return collect();
        }

        return Order::with(['items', 'payments'])
            ->where('cash_register_id', $cashRegisterId)
            ->where('type', 'delivery')
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
        if (! $this->tablesBillingLoaded) {
            return collect();
        }

        $cashRegisterId = $this->activeCashRegister?->id;

        if (! $cashRegisterId) {
            return collect();
        }

        $serviceMesas = MesaService::query()
            ->where('cash_register_id', $cashRegisterId)
            ->where('status', 'en_cuenta')
            ->with([
                'primaryMesa.area',
                'primaryMesa.currentAssignment.waiter',
                'mesas',
                'orders' => fn ($query) => $query
                    ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada'])
                    ->with('items'),
                'splits' => fn ($query) => $query
                    ->whereIn('status', ['pendiente', 'parcial'])
                    ->latest('id'),
            ])
            ->oldest('opened_at')
            ->get()
            ->map(function (MesaService $service) {
                $mesa = $service->primaryMesa ?: $service->mesas->first();
                if (! $mesa) {
                    return null;
                }

                $mesa->setRelation('orders', $service->orders);
                $mesa->setRelation('splits', $service->splits);
                $mesa->active_service = $service;
                $mesa->operational_label = $service->service_label;
                $split = $service->splits->first();
                $mesa->active_split = $split;
                $mesa->mesa_total = $split
                    ? collect($split->split_data)
                        ->reject(fn ($account) => (bool) ($account['paid'] ?? false))
                        ->sum('total')
                    : $mesa->orders->sum('total');

                return $mesa;
            })
            ->filter()
            ->values();

        $activeServiceMemberIds = DB::table('mesa_service_mesa')
            ->join('mesa_services', 'mesa_services.id', '=', 'mesa_service_mesa.mesa_service_id')
            ->whereIn('mesa_services.status', MesaService::ACTIVE_STATUSES)
            ->pluck('mesa_service_mesa.mesa_id')
            ->unique()
            ->all();

        // Compatibilidad con órdenes creadas antes de la migración o desde
        // integraciones que aún no envían mesa_service_id.
        $legacyMesas = Mesa::with([
            'area',
            'currentAssignment.waiter',
            'orders' => fn ($query) => $query
                ->where('cash_register_id', $cashRegisterId)
                ->whereNull('mesa_service_id')
                ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada'])
                ->with('items'),
            'splits' => fn ($query) => $query
                ->whereNull('mesa_service_id')
                ->whereIn('status', ['pendiente', 'parcial'])
                ->latest('id'),
        ])
            ->where('status', 'en_cuenta')
            ->when($activeServiceMemberIds, fn ($query) => $query->whereNotIn('id', $activeServiceMemberIds))
            ->where(function ($query) use ($cashRegisterId) {
                $query->whereHas('orders', fn ($orders) => $orders
                    ->where('cash_register_id', $cashRegisterId)
                    ->whereNull('mesa_service_id')
                    ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada']))
                    ->orWhereDoesntHave('orders', fn ($orders) => $orders
                        ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada']));
            })
            ->orderBy('number')
            ->get()
            ->map(function (Mesa $mesa) {
                $split = $mesa->splits->first();
                $mesa->active_split = $split;
                $mesa->mesa_total = $split
                    ? collect($split->split_data)
                        ->reject(fn ($account) => (bool) ($account['paid'] ?? false))
                        ->sum('total')
                    : $mesa->orders->sum('total');

                return $mesa;
            });

        return $serviceMesas->concat($legacyMesas)->values();
    }

    #[Computed]
    public function mesaPaymentContext(): array
    {
        $empty = [
            'mesa' => null,
            'service' => null,
            'split' => null,
            'account' => null,
            'accountLabel' => '',
            'orders' => collect(),
            'items' => collect(),
            'total' => 0.0,
            'isSplit' => false,
        ];

        if (! $this->showMesaPayModal || ! $this->mesaPayId) {
            return $empty;
        }

        $cashRegisterId = $this->activeCashRegister?->id;
        $mesa = Mesa::with(['area', 'currentAssignment.waiter'])->find($this->mesaPayId);
        if (! $mesa || ! $cashRegisterId) {
            return $empty;
        }

        $service = app(MesaServiceManager::class)->findActiveForMesa($mesa, $cashRegisterId);

        if ($this->mesaSplitId !== null) {
            $split = MesaSplit::whereIn('status', ['pendiente', 'parcial'])
                ->where(function ($query) use ($cashRegisterId) {
                    $query->whereHas('mesaService', fn ($serviceQuery) => $serviceQuery
                        ->where('cash_register_id', $cashRegisterId)
                        ->active())
                        ->orWhere(function ($legacy) {
                            $legacy->whereNull('mesa_service_id')
                                ->where('mesa_id', $this->mesaPayId);
                        });
                })
                ->find($this->mesaSplitId);
            $account = $split?->split_data[$this->mesaSplitAccountIdx] ?? null;

            return [
                ...$empty,
                'mesa' => $mesa,
                'service' => $service,
                'split' => $split,
                'account' => $account,
                'accountLabel' => (string) ($account['label'] ?? ''),
                'items' => collect($account['items'] ?? []),
                'total' => (float) ($account['total'] ?? 0),
                'isSplit' => true,
            ];
        }

        $orders = $service
            ? $service->orders()
                ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada'])
                ->with('items')
                ->get()
            : $mesa->orders()
                ->where('cash_register_id', $cashRegisterId)
                ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada'])
                ->with('items')
                ->get();

        return [
            ...$empty,
            'mesa' => $mesa,
            'service' => $service,
            'accountLabel' => $service?->service_label ?? $mesa->display_name,
            'orders' => $orders,
            'total' => (float) $orders->sum('total'),
        ];
    }

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->cart = Session::get('pos_cart_'.auth()->id(), []);
        $this->saveCart();
    }

    private function saveCart(): void
    {
        $this->cart = app(PromotionPricingService::class)->apply(
            $this->cart,
            'pos',
            $this->promotionFulfillmentForOrderType($this->orderType)
        );
        unset($this->cartTotal, $this->cartCount, $this->promotionOpportunities);
        Session::put('pos_cart_'.auth()->id(), $this->cart);
    }

    // ─── Cash Register ─────────────────────────────────────────────────────────

    public function openCashRegister(): void
    {
        abort_unless(auth()->user()?->can('abrir caja'), 403);
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
    }

    public function setCatalogMode(string $mode): void
    {
        abort_unless(in_array($mode, ['products', 'promotions'], true), 422);
        $this->catalogMode = $mode;
    }

    public function selectPromotionFromCatalog(int $promotionId): void
    {
        $fulfillment = $this->promotionFulfillmentForOrderType($this->orderType);
        $promotion = Promotion::query()->with(['primaryProduct', 'groups.products' => fn ($query) => $query->where('is_active', true)])->find($promotionId);
        if (! $promotion) {
            $this->dispatch('notify', type: 'warning', message: 'Esta promoción ya no está disponible.');

            return;
        }

        if (! $promotion->hasAutomaticPricingRule()) {
            $this->openPromotionModal($promotionId);

            return;
        }

        $available = Promotion::query()
            ->automaticPricingAvailable('pos', null, $fulfillment)
            ->whereKey($promotionId)
            ->exists();
        $eligibleProducts = $promotion->groups->flatMap->products->unique('id')->values();
        if ($eligibleProducts->isEmpty() && $promotion->primaryProduct?->is_active) {
            $eligibleProducts = collect([$promotion->primaryProduct]);
        }
        if (! $available || $eligibleProducts->isEmpty()) {
            $this->dispatch('notify', type: 'warning', message: 'Esta oferta no aplica a la modalidad actual.');

            return;
        }

        if ($eligibleProducts->count() > 1) {
            $this->automaticPromotionPickerId = $promotion->id;
            unset($this->automaticPromotionPicker);

            return;
        }

        $this->openCustomizeModal((int) $eligibleProducts->first()->id);
    }

    public function closeAutomaticPromotionPicker(): void
    {
        $this->automaticPromotionPickerId = null;
        unset($this->automaticPromotionPicker);
    }

    public function addEligiblePromotionProduct(int $promotionId, int $productId): void
    {
        abort_unless($this->automaticPromotionPickerId === $promotionId, 422);
        $promotion = $this->automaticPromotionPicker;
        $eligibleIds = $promotion?->groups->flatMap->products->pluck('id')->map(fn ($id) => (int) $id)->unique()->all() ?? [];
        if ($eligibleIds === [] && $promotion?->primary_product_id) {
            $eligibleIds = [(int) $promotion->primary_product_id];
        }
        abort_unless(in_array($productId, $eligibleIds, true), 422);

        $this->closeAutomaticPromotionPicker();
        $this->openCustomizeModal($productId);
    }

    public function completePromotionOpportunity(int $promotionId): void
    {
        $opportunity = collect($this->promotionOpportunities)->firstWhere('promotion_id', $promotionId);
        if (! $opportunity) {
            $this->dispatch('notify', type: 'info', message: 'La oferta ya fue aplicada o dejó de estar disponible.');

            return;
        }

        if (count($opportunity['eligible_product_ids'] ?? []) > 1) {
            $this->selectPromotionFromCatalog($promotionId);

            return;
        }

        $product = Product::query()
            ->where('is_active', true)
            ->withCount(['addonGroups', 'ingredients'])
            ->find($opportunity['product_id']);
        if (! $product) {
            $this->dispatch('notify', type: 'warning', message: 'El producto de la oferta ya no está disponible.');

            return;
        }

        $missing = max(1, min(self::MAX_ITEM_QUANTITY, (int) $opportunity['missing_quantity']));
        if ($product->is_customizable || $product->addon_groups_count > 0 || $product->ingredients_count > 0) {
            $this->openCustomizeModal($product->id);
            $this->itemQuantity = $missing;

            return;
        }

        for ($unit = 0; $unit < $missing; $unit++) {
            $this->addSimpleProductToCart($product);
        }
        $this->dispatch('notify', type: 'success', message: 'Producto agregado; la promoción se aplicó automáticamente.');
    }

    // ─── Customize product modal ───────────────────────────────────────────────

    public function openCustomizeModal(int $productId): void
    {
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

    public function openPromotionModal(int $promotionId): void
    {
        $promotion = Promotion::query()
            ->available('pos')
            ->forAnyFulfillment(Promotion::POS_FULFILLMENT_MODES)
            ->with([
                'groups.products' => fn ($query) => $query
                    ->where('is_active', true)
                    ->select(['products.id', 'products.category_id', 'products.name', 'products.image']),
                'groups.products.category.printArea',
            ])
            ->find($promotionId);

        if (! $promotion || $promotion->groups->isEmpty()) {
            $this->dispatch('notify', type: 'warning', message: 'Esta promoción ya no está disponible.');

            return;
        }

        $this->customizingPromotionId = $promotion->id;
        $this->promotionSelections = [];
        $this->promotionQuantity = 1;
        $this->showPromotionModal = true;
        $this->resetErrorBag('promotion');
        unset($this->customizingPromotion);
    }

    public function closePromotionModal(): void
    {
        $this->showPromotionModal = false;
        $this->customizingPromotionId = null;
        $this->promotionSelections = [];
        $this->promotionQuantity = 1;
        $this->resetErrorBag('promotion');
        unset($this->customizingPromotion);
    }

    public function changePromotionSelection(int $groupId, int $productId, int $delta): void
    {
        $promotion = $this->customizingPromotion;
        $group = $promotion?->groups->firstWhere('id', $groupId);
        if (! $group || ! $group->products->contains('id', $productId)) {
            return;
        }

        $current = (int) ($this->promotionSelections[$groupId][$productId] ?? 0);
        $groupTotal = (int) collect($this->promotionSelections[$groupId] ?? [])->sum();
        if ($delta > 0 && $groupTotal >= $group->max_selections) {
            return;
        }

        $next = max(0, $current + ($delta > 0 ? 1 : -1));
        if ($next === 0) {
            unset($this->promotionSelections[$groupId][$productId]);
        } else {
            $this->promotionSelections[$groupId][$productId] = $next;
        }
        $this->resetErrorBag('promotion');
    }

    public function addPromotionToCart(): void
    {
        $promotion = $this->customizingPromotion;
        if (! $promotion) {
            $this->addError('promotion', 'Esta promoción ya no está disponible.');

            return;
        }
        if ($this->promotionQuantity < 1 || $this->promotionQuantity > self::MAX_ITEM_QUANTITY) {
            $this->addError('promotion', 'La cantidad debe estar entre 1 y 99.');

            return;
        }

        $snapshot = $this->validatedPromotionSnapshot($promotion, $this->promotionSelections);
        $this->cart[] = [
            'cart_id' => Str::uuid()->toString(),
            'product_id' => null,
            'promotion_id' => $promotion->id,
            'product_name' => $promotion->name,
            'product_price' => (float) $promotion->price,
            'product_image' => $promotion->image,
            'quantity' => $this->promotionQuantity,
            'unit_extra' => 0,
            'unit_total' => (float) $promotion->price,
            'subtotal' => (float) $promotion->price * $this->promotionQuantity,
            'notes' => '',
            'addons' => [],
            'ingredients' => [],
            'promotion_selections' => $snapshot,
        ];

        $this->closePromotionModal();
        unset($this->cartTotal, $this->cartCount, $this->activePromotions);
        $this->saveCart();
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
        $this->customerNeighborhood = $customer->neighborhood ?? '';
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
        $this->customerNeighborhood = '';
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
        $this->newCustomerNeighborhood = '';
        $this->newCustomerReferences = '';
        $this->showAddCustomerModal = true;
        $this->resetErrorBag();
    }

    public function saveNewCustomer(): void
    {
        abort_unless(auth()->user()?->can('crear ordenes'), 403);
        $this->validate([
            'newCustomerName' => 'required|string|max:120',
            'newCustomerPhone' => 'required|string|max:30',
            'newCustomerEmail' => 'nullable|email:rfc|max:160',
            'newCustomerNeighborhood' => 'required|string|max:120',
        ], [
            'newCustomerNeighborhood.required' => 'Escribe la colonia o zona del cliente.',
            'newCustomerNeighborhood.max' => 'La colonia o zona no puede superar 120 caracteres.',
        ]);

        $customer = Customer::create([
            'name' => $this->newCustomerName,
            'phone' => $this->newCustomerPhone,
            'email' => $this->newCustomerEmail ?: null,
            'address' => $this->newCustomerAddress ?: null,
            'neighborhood' => trim($this->newCustomerNeighborhood),
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

        $this->refreshPromotionCart();

        if ($this->payments === [] && blank($this->payAmount)) {
            $this->payAmount = number_format($this->cartTotal, 2, '.', '');
        }
        $this->resetErrorBag();
        $this->showCheckoutModal = true;
    }

    public function updatedOrderType(): void
    {
        $this->catalogMode = 'products';
        unset($this->activePromotions, $this->promotionOpportunities);
        $this->saveCart();
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

    public function submitOrder(
        DeliveryModulePolicy $deliveryPolicy,
        ManualDeliveryAccountingService $manualAccounting,
    ): void {
        abort_unless(auth()->user()?->can('crear ordenes'), 403);
        if (empty($this->cart)) {
            return;
        }
        if (! $this->cartContentsAreValid()) {
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

        if ($this->orderType === 'delivery' && blank($this->customerNeighborhood)) {
            $this->addError('customerNeighborhood', 'La colonia o zona es requerida para delivery.');

            return;
        }

        $order = DB::transaction(function () use ($isContraEntrega, $deliveryPolicy, $manualAccounting) {
            if ($this->orderType === 'delivery') {
                $this->rememberMissingCustomerDeliveryData();
            }

            $isManualDelivery = $this->orderType === 'delivery' && ! $deliveryPolicy->enabledForUpdate();
            $status = $isManualDelivery ? 'pendiente' : ($isContraEntrega ? 'pendiente' : 'pagada');
            $order = $this->persistOrder(
                $this->orderType,
                $status,
                $isManualDelivery ? 'manual' : null,
            );

            if (! $isContraEntrega && $this->payments !== []) {
                $timestamp = now();
                OrderPayment::insert(collect($this->payments)->map(fn ($payment) => [
                    'order_id' => $order->id,
                    'method' => $this->mapPaymentMethod($payment['method']),
                    'amount' => $payment['amount'],
                    'received_amount' => $payment['method'] === 'cash'
                        ? ($payment['cash_received'] ?? $payment['amount'])
                        : null,
                    'change_amount' => $payment['method'] === 'cash'
                        ? ($payment['cash_change'] ?? 0)
                        : 0,
                    'card_last4' => $payment['method'] === 'card'
                        ? ($payment['card_last4'] ?? null)
                        : null,
                    'transfer_reference' => $payment['method'] === 'transfer'
                        ? ($payment['transfer_ref'] ?? null)
                        : null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ])->all());
            }

            if ($isManualDelivery) {
                $order = $manualAccounting->account($order);
            }

            return $order;
        });

        $this->showCheckoutModal = false;
        $this->finishSale($order, openTicket: true);
    }

    public function submitPickupLater(): void
    {
        abort_unless(auth()->user()?->can('crear ordenes'), 403);
        if (empty($this->cart)) {
            return;
        }
        if (! $this->cartContentsAreValid()) {
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

        $this->finishSale($order, openTicket: false);
    }

    public function submitOrderLater(): void
    {
        abort_unless(auth()->user()?->can('crear ordenes'), 403);
        if (empty($this->cart)) {
            return;
        }
        if (! $this->cartContentsAreValid()) {
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

        $this->finishSale($order, openTicket: false);
    }

    // ─── Quotations ────────────────────────────────────────────────────────────

    public function saveQuotation(): void
    {
        abort_unless(auth()->user()?->can('crear ordenes'), 403);
        if (empty($this->cart)) {
            $this->dispatch('notify', type: 'warning', message: 'El carrito está vacío.');

            return;
        }

        $this->refreshPromotionCart();

        DB::transaction(function (): void {
            $quotation = $this->activeQuotationId
                ? Quotation::query()
                    ->whereKey($this->activeQuotationId)
                    ->where('created_by', auth()->id())
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first()
                : null;

            $attributes = [
                'created_by' => auth()->id(),
                'customer_id' => $this->customerId,
                'name' => $this->quotationName ?: null,
                'notes' => $this->quotationNotes ?: null,
                'customer_name' => $this->customerName ?: null,
                'customer_phone' => $this->customerPhone ?: null,
                'order_type' => $this->orderType,
                'draft_version' => self::DRAFT_STATE_VERSION,
                'checkout_state' => $this->draftCheckoutState(),
                'total' => $this->cartTotal,
                'status' => 'active',
            ];

            if ($quotation) {
                $quotation->update($attributes);
                $quotation->items()->delete();
            } else {
                $quotation = Quotation::create($attributes);
            }

            foreach ($this->cart as $item) {
                $qi = QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'product_id' => $item['product_id'],
                    'promotion_id' => $item['promotion_id'] ?? null,
                    'product_name' => $item['product_name'],
                    'product_price' => $item['product_price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $item['subtotal'],
                    'promotion_discount' => $item['promotion_discount'] ?? 0,
                    'notes' => $item['notes'] ?: null,
                    'promotion_selections' => $item['promotion_selections'] ?? null,
                    'promotion_rule_snapshot' => $item['promotion_rule_snapshot'] ?? null,
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
        });

        $this->showSaveQuotationModal = false;
        $this->showCheckoutModal = false;
        $this->quotationName = '';
        $this->quotationNotes = '';
        $this->clearCart();
        unset($this->quotations);
        $this->dispatch('notify', type: 'success', message: 'Borrador guardado con todos los datos capturados.');
    }

    public function loadQuotation(int $id): void
    {
        $quotation = Quotation::query()
            ->where('created_by', auth()->id())
            ->with(['items.addons', 'items.ingredients', 'customer'])
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
                'promotion_id' => $item->promotion_id,
                'product_name' => $item->product_name,
                'product_price' => (float) $item->product_price,
                'product_image' => $item->promotion_id
                    ? Promotion::find($item->promotion_id)?->image
                    : Product::find($item->product_id)?->image,
                'quantity' => $item->quantity,
                'unit_extra' => $addonExtra + $ingExtra,
                'unit_total' => $unitTotal,
                'subtotal' => $unitTotal * $item->quantity,
                'promotion_discount' => (float) ($item->promotion_discount ?? 0),
                'notes' => $item->notes ?? '',
                'addons' => $addons,
                'ingredients' => $ingredients,
                'promotion_selections' => $item->promotion_selections ?? [],
                'promotion_rule_snapshot' => $item->promotion_rule_snapshot,
                'auto_promotion_applied' => filled($item->promotion_rule_snapshot),
            ];
        }

        $draftState = is_array($quotation->checkout_state) ? $quotation->checkout_state : [];
        if ($draftState === []) {
            $this->resetOrderForm();
        } else {
            $this->restoreDraftCheckoutState($draftState);
        }
        $savedCart = $draftState['cart'] ?? null;
        $this->cart = is_array($savedCart) && $savedCart !== []
            ? $this->restoreDraftCart($savedCart)
            : $newCart;
        $this->saveCart();

        if ($draftState === [] && $quotation->customer_id) {
            $this->customerId = $quotation->customer_id;
            $this->customerName = $quotation->customer?->name ?? '';
            $this->customerPhone = $quotation->customer?->phone ?? '';
            $this->customerAddress = $quotation->customer?->address ?? '';
            $this->customerNeighborhood = $quotation->customer?->neighborhood ?? '';
            $this->customerReferences = $quotation->customer?->references ?? '';
        } elseif ($draftState === [] && $quotation->customer_name) {
            $this->customerName = $quotation->customer_name;
            $this->customerPhone = $quotation->customer_phone ?? '';
        }
        $this->quotationName = $quotation->name ?? '';
        $this->quotationNotes = $quotation->notes ?? '';

        $this->activeQuotationId = $quotation->id;
        $this->showQuotationsModal = false;
        unset($this->cartTotal, $this->cartCount, $this->quotations);
        $this->dispatch('notify', type: 'info', message: "Borrador \"{$quotation->display_name}\" cargado.");
    }

    public function deleteQuotation(int $id): void
    {
        abort_unless(auth()->user()?->can('editar ordenes'), 403);
        Quotation::query()
            ->where('created_by', auth()->id())
            ->findOrFail($id)
            ->delete();
        unset($this->quotations);
        $this->dispatch('notify', type: 'warning', message: 'Borrador eliminado.');
    }

    /**
     * Snapshot versionado de todos los datos editables del pedido.
     * Las partidas tambiÃ©n se guardan en tablas relacionales para conservar
     * compatibilidad con los borradores creados antes de esta versiÃ³n.
     */
    private function draftCheckoutState(): array
    {
        return [
            'version' => self::DRAFT_STATE_VERSION,
            'cart' => $this->cart,
            'order' => [
                'type' => $this->orderType,
                'delivery_method' => $this->deliveryMethod,
                'notes' => $this->orderNotes,
            ],
            'customer' => [
                'id' => $this->customerId,
                'name' => $this->customerName,
                'phone' => $this->customerPhone,
                'address' => $this->customerAddress,
                'neighborhood' => $this->customerNeighborhood,
                'references' => $this->customerReferences,
                'search' => $this->customerSearch,
            ],
            'payment' => [
                'payments' => $this->payments,
                'method' => $this->payMethod,
                'amount' => $this->payAmount,
                'cash_received' => $this->payCashReceived,
                'card_last4' => $this->payCardLast4,
                'transfer_reference' => $this->payTransferRef,
            ],
        ];
    }

    private function restoreDraftCheckoutState(array $state): void
    {
        if ($state === []) {
            return;
        }

        $order = is_array($state['order'] ?? null) ? $state['order'] : [];
        $customer = is_array($state['customer'] ?? null) ? $state['customer'] : [];
        $payment = is_array($state['payment'] ?? null) ? $state['payment'] : [];

        $orderType = (string) ($order['type'] ?? 'ventanilla');
        $this->orderType = in_array($orderType, ['ventanilla', 'pick_up', 'delivery'], true)
            ? $orderType
            : 'ventanilla';

        $deliveryMethod = (string) ($order['delivery_method'] ?? 'contra_entrega');
        $this->deliveryMethod = in_array($deliveryMethod, ['contra_entrega', 'cash', 'card', 'transfer'], true)
            ? $deliveryMethod
            : 'contra_entrega';
        $this->orderNotes = (string) ($order['notes'] ?? '');

        $savedCustomerId = filter_var($customer['id'] ?? null, FILTER_VALIDATE_INT);
        $this->customerId = $savedCustomerId && Customer::query()->whereKey($savedCustomerId)->exists()
            ? (int) $savedCustomerId
            : null;
        $this->customerName = (string) ($customer['name'] ?? '');
        $this->customerPhone = (string) ($customer['phone'] ?? '');
        $this->customerAddress = (string) ($customer['address'] ?? '');
        $this->customerNeighborhood = (string) ($customer['neighborhood'] ?? '');
        $this->customerReferences = (string) ($customer['references'] ?? '');
        $this->customerSearch = (string) ($customer['search'] ?? '');

        $this->payments = collect(is_array($payment['payments'] ?? null) ? $payment['payments'] : [])
            ->filter(fn ($item) => is_array($item)
                && in_array($item['method'] ?? null, ['cash', 'card', 'transfer'], true)
                && is_numeric($item['amount'] ?? null)
                && (float) $item['amount'] > 0)
            ->values()
            ->all();

        $payMethod = (string) ($payment['method'] ?? 'cash');
        $this->payMethod = in_array($payMethod, ['cash', 'card', 'transfer'], true) ? $payMethod : 'cash';
        $this->payAmount = (string) ($payment['amount'] ?? '');
        $this->payCashReceived = (string) ($payment['cash_received'] ?? '');
        $this->payCardLast4 = (string) ($payment['card_last4'] ?? '');
        $this->payTransferRef = (string) ($payment['transfer_reference'] ?? '');

        unset($this->customerSearchResults, $this->paidTotal, $this->paymentRemaining);
    }

    private function restoreDraftCart(array $savedCart): array
    {
        return collect($savedCart)
            ->filter(fn ($item) => is_array($item) && array_key_exists('product_name', $item))
            ->map(function (array $item): array {
                $item['cart_id'] = Str::uuid()->toString();
                $item['addons'] = is_array($item['addons'] ?? null) ? $item['addons'] : [];
                $item['ingredients'] = is_array($item['ingredients'] ?? null) ? $item['ingredients'] : [];
                $item['promotion_selections'] = is_array($item['promotion_selections'] ?? null)
                    ? $item['promotion_selections']
                    : [];

                return $item;
            })
            ->values()
            ->all();
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
        unset($this->recentOrders, $this->mesaServiceHistory);
    }

    public function updatedReprintSearch(): void
    {
        unset($this->recentOrders, $this->mesaServiceHistory);
    }

    // ─── Movimientos operativos ───────────────────────────────────────────────

    public function openOperationsModal(string $type = 'expense'): void
    {
        abort_unless(in_array($type, ['expense', 'income', 'inventory_out'], true), 404);

        $this->authorizeOperationType($type);

        $this->resetOperationalPanelState();
        $this->resetOperationForm();
        $this->operationType = $type;
        $this->showExpenseModal = true;
    }

    /** Backwards-compatible entry point used by existing integrations. */
    public function openExpenseModal(): void
    {
        $this->openOperationsModal('expense');
    }

    public function updatedOperationType(string $type): void
    {
        abort_unless(in_array($type, ['expense', 'income', 'inventory_out'], true), 404);

        $this->authorizeOperationType($type);

        $this->resetErrorBag();
        $this->expenseCategory = $type === 'income' ? 'fondo' : 'otro';
        $this->expensePaymentMethod = 'cash';
    }

    public function saveOperation(InventoryService $inventoryService): void
    {
        if ($this->operationType === 'inventory_out') {
            $this->saveInventoryOutflow($inventoryService);

            return;
        }

        abort_unless(in_array($this->operationType, ['expense', 'income'], true), 422);
        $this->saveCashMovement($this->operationType);
    }

    /** Backwards-compatible action used by existing tests and callers. */
    public function saveExpense(): void
    {
        $this->operationType = 'expense';
        $this->saveCashMovement('expense');
    }

    private function saveCashMovement(string $type): void
    {
        abort_unless(in_array($type, ['expense', 'income'], true), 422);
        $this->authorizeOperationType($type);
        $register = $this->activeCashRegister;
        abort_unless($register, 409, 'Abre una caja antes de registrar movimientos.');

        $categories = $type === 'income'
            ? 'fondo,devolucion,otro_ingreso'
            : 'insumos,operativo,personal,otro';

        $validated = $this->validate([
            'expenseAmount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'expenseCategory' => ['required', 'in:'.$categories],
            'expenseDescription' => ['required', 'string', 'max:255'],
            'expensePaymentMethod' => ['required', 'in:cash,card,transfer'],
            'expenseNotes' => ['nullable', 'string', 'max:1000'],
        ], [
            'expenseAmount.required' => 'Ingresa el monto del movimiento.',
            'expenseAmount.min' => 'El monto debe ser mayor a cero.',
            'expenseDescription.required' => 'Describe el motivo del movimiento.',
        ]);

        $paymentMethod = $type === 'income' ? 'cash' : $validated['expensePaymentMethod'];

        DB::transaction(function () use ($register, $type, $validated, $paymentMethod): void {
            CashRegister::query()
                ->whereKey($register->id)
                ->where('is_open', true)
                ->lockForUpdate()
                ->firstOrFail();

            CashMovement::create([
                'cash_register_id' => $register->id,
                'created_by' => auth()->id(),
                'type' => $type,
                'amount' => round((float) $validated['expenseAmount'], 2),
                'category' => $validated['expenseCategory'],
                'description' => trim($validated['expenseDescription']),
                'payment_method' => $paymentMethod,
                'notes' => filled($validated['expenseNotes']) ? trim($validated['expenseNotes']) : null,
            ]);
        });

        $this->showExpenseModal = false;
        $label = $type === 'income' ? 'Ingreso de caja' : 'Gasto de caja';
        $this->dispatch('notify', type: 'success', message: $label.' registrado.');
    }

    private function saveInventoryOutflow(InventoryService $inventoryService): void
    {
        $this->authorizeInventoryOutflow();

        $validated = $this->validate([
            'inventoryItemId' => ['required', 'integer', 'exists:inventory_items,id'],
            'adjustQuantity' => ['required', 'numeric', 'min:0.001', 'max:999999999'],
            'inventoryReason' => ['required', 'string', 'max:255'],
        ], [
            'inventoryItemId.required' => 'Selecciona el insumo que saldrá del inventario.',
            'adjustQuantity.required' => 'Ingresa la cantidad que saldrá.',
            'adjustQuantity.min' => 'La cantidad debe ser mayor a cero.',
            'inventoryReason.required' => 'Explica el motivo de la salida.',
        ]);

        $item = InventoryItem::query()
            ->where('is_active', true)
            ->findOrFail($validated['inventoryItemId']);

        $inventoryService->adjust(
            $item,
            'out',
            (float) $validated['adjustQuantity'],
            trim($validated['inventoryReason']),
            auth()->user(),
            'pos_supply_outflow',
        );

        unset($this->operationInventoryItems);
        $this->showExpenseModal = false;
        $this->dispatch('notify', type: 'success', message: 'Salida de insumo registrada y existencia actualizada.');
    }

    private function authorizeOperationType(string $type): void
    {
        match ($type) {
            'expense' => abort_unless(
                auth()->user()?->canAny(['registrar gastos', 'registrar movimientos de caja']),
                403,
            ),
            'income' => abort_unless(
                auth()->user()?->can('registrar movimientos de caja'),
                403,
            ),
            'inventory_out' => $this->authorizeInventoryOutflow(),
            default => abort(404),
        };
    }

    private function authorizeInventoryOutflow(): void
    {
        abort_unless(
            auth()->user()?->can('registrar salida de insumos')
                || auth()->user()?->can('ajustar inventario'),
            403,
        );
    }

    private function resetOperationForm(): void
    {
        $this->reset(
            'expenseAmount',
            'expenseDescription',
            'expenseNotes',
            'inventoryItemId',
            'adjustQuantity',
            'inventoryReason',
        );
        $this->expenseCategory = 'otro';
        $this->expensePaymentMethod = 'cash';
        $this->resetErrorBag();
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
        abort_unless(auth()->user()?->can('editar ordenes'), 403);
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

    public function convertOrderToDelivery(
        DeliveryModulePolicy $deliveryPolicy,
        ManualDeliveryAccountingService $manualAccounting,
    ): void {
        abort_unless(auth()->user()?->can('editar ordenes'), 403);
        $this->validate([
            'convertDeliveryName' => 'required|string|max:120',
            'convertDeliveryPhone' => ['required', 'regex:/^[0-9]{10}$/'],
            'convertDeliveryAddress' => 'required|string|max:180',
            'convertDeliveryReferences' => 'nullable|string|max:255',
            'convertDeliveryMethod' => 'required|in:contra_entrega,transferencia',
        ], [
            'convertDeliveryPhone.regex' => 'El teléfono debe tener 10 dígitos.',
        ]);

        $registerId = $this->activeCashRegister?->id;
        $order = DB::transaction(function () use ($registerId, $deliveryPolicy, $manualAccounting): ?Order {
            $manualMode = ! $deliveryPolicy->enabledForUpdate();
            $lockedOrder = Order::query()
                ->where('cash_register_id', $registerId)
                ->lockForUpdate()
                ->find($this->convertDeliveryOrderId);

            if (! $lockedOrder || $lockedOrder->type === 'delivery' || $lockedOrder->status === 'pagada') {
                return null;
            }

            $lockedOrder->update([
                'type' => 'delivery',
                'customer_name' => trim($this->convertDeliveryName),
                'customer_phone' => trim($this->convertDeliveryPhone),
                'customer_address' => trim($this->convertDeliveryAddress),
                'customer_references' => trim($this->convertDeliveryReferences) ?: null,
                'delivery_method' => $this->convertDeliveryMethod,
                'delivery_flow_mode' => $manualMode ? 'manual' : 'managed',
            ]);

            return $manualMode
                ? $manualAccounting->account($lockedOrder)
                : $lockedOrder;
        });

        if (! $order) {
            $this->closeConvertDeliveryModal();

            return;
        }

        $this->closeConvertDeliveryModal();
        unset($this->pickupOrders, $this->deliveryOrders, $this->recentOrders);
        $this->dispatch('notify', type: 'success', message: "Pedido #{$order->id} enviado a Delivery.");
    }

    public function openPickupPayModal(int $orderId): void
    {
        abort_unless(auth()->user()?->can('cerrar ordenes'), 403);
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
        abort_unless(auth()->user()?->can('cobrar mesas'), 403);
        $this->resetErrorBag();
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
        unset($this->mesaPaymentContext);
    }

    public function sendTableServiceToBilling(int $serviceId): void
    {
        abort_unless(auth()->user()?->can('cerrar mesas'), 403);

        $cashRegisterId = $this->activeCashRegister?->id;
        if (! $cashRegisterId) {
            $this->dispatch('notify', type: 'warning', message: 'No hay una caja abierta para solicitar la cuenta.');

            return;
        }

        $service = DB::transaction(function () use ($serviceId, $cashRegisterId): ?MesaService {
            $service = MesaService::query()
                ->where('cash_register_id', $cashRegisterId)
                ->where('status', 'abierta')
                ->lockForUpdate()
                ->find($serviceId);

            if (! $service) {
                return null;
            }

            if ($service->splits()->whereIn('status', ['pendiente', 'parcial'])->exists()) {
                return null;
            }

            $service->update([
                'status' => 'en_cuenta',
                'in_account_at' => $service->in_account_at ?? now(),
            ]);

            $memberIds = $service->mesas()->pluck('mesas.id')->all();
            Mesa::whereIn('id', $memberIds)->update(['status' => 'en_cuenta']);

            return $service;
        }, 3);

        if (! $service) {
            $this->dispatch('notify', type: 'warning', message: 'El servicio cambió o ya tiene una división activa. Actualiza el panel.');
            $this->refreshTableWorkspace();

            return;
        }

        $this->tableWorkspaceFilter = 'billing';
        $this->refreshTableWorkspace();
        $this->dispatch('notify', type: 'success', message: "{$service->service_label} quedó lista para cobro.");
    }

    public function reopenMesa(int $mesaId): void
    {
        abort_unless(auth()->user()?->can('cobrar mesas'), 403);

        $mesa = Mesa::with('orders')->find($mesaId);
        if (! $mesa || $mesa->status !== 'en_cuenta') {
            return;
        }

        $cashRegisterId = $this->activeCashRegister?->id;
        if (! $cashRegisterId) {
            $this->dispatch('notify', type: 'warning', message: 'No hay una caja abierta para reabrir la mesa.');

            return;
        }

        $manager = app(MesaServiceManager::class);
        $service = $manager->findActiveForMesa($mesa, $cashRegisterId);

        $split = MesaSplit::query()
            ->where('mesa_id', $mesa->id)
            ->whereIn('status', ['pendiente', 'parcial'])
            ->when(
                $service,
                fn ($query) => $query->where('mesa_service_id', $service->id),
                fn ($query) => $query->whereNull('mesa_service_id')
            )
            ->latest('id')
            ->first();

        if ($split && collect($split->split_data ?? [])->contains(fn ($account) => (bool) ($account['paid'] ?? false))) {
            $this->dispatch('notify', type: 'warning', message: 'No puedes reabrir una cuenta dividida que ya tiene pagos. Cobra las subcuentas pendientes para liberar la mesa.');

            return;
        }

        DB::transaction(function () use ($split, $manager, $mesa, $cashRegisterId): void {
            $split?->delete();
            if ($cashRegisterId) {
                $manager->reopen($mesa, $cashRegisterId);
            }
        });

        $memberIds = $service?->mesas()->pluck('mesas.id')->all() ?: [$mesa->id];
        Mesa::whereIn('id', $memberIds)->update(['status' => 'ocupada']);
        unset($this->mesasPendientes, $this->tableTrackingServices, $this->tableWorkspaceAllServices, $this->tableWorkspaceServices, $this->tableWorkspaceCounts, $this->mesaServiceHistory);
        $this->dispatch(
            'notify',
            type: 'success',
            message: $split
                ? "Mesa {$mesa->display_name} reabierta; la división sin pagos fue cancelada y los pedidos siguen en la cuenta."
                : "Mesa {$mesa->display_name} reabierta."
        );
    }

    public function discardEmptyMesaAccount(int $mesaId): void
    {
        abort_unless(auth()->user()?->can('cobrar mesas'), 403);

        $cashRegisterId = $this->activeCashRegister?->id;
        $mesa = Mesa::find($mesaId);
        if (! $mesa || $mesa->status !== 'en_cuenta' || ! $cashRegisterId) {
            $this->dispatch('notify', type: 'warning', message: 'La cuenta ya no está disponible.');

            return;
        }

        $manager = app(MesaServiceManager::class);
        $service = $manager->findActiveForMesa($mesa, $cashRegisterId);
        $split = MesaSplit::query()
            ->where('mesa_id', $mesa->id)
            ->whereIn('status', ['pendiente', 'parcial'])
            ->when(
                $service,
                fn ($query) => $query->where('mesa_service_id', $service->id),
                fn ($query) => $query->whereNull('mesa_service_id')
            )
            ->latest('id')
            ->first();

        if ($split) {
            $splitData = $split->split_data ?? [];
            $pendingIndexes = collect($splitData)
                ->keys()
                ->filter(fn ($index) => ! (bool) ($splitData[$index]['paid'] ?? false));
            $hasPendingBalance = $pendingIndexes->contains(
                fn ($index) => (float) ($splitData[$index]['total'] ?? 0) > 0.009
            );

            if ($pendingIndexes->isEmpty() || $hasPendingBalance) {
                $this->dispatch('notify', type: 'warning', message: 'Solo se pueden eliminar subcuentas pendientes con saldo de $0.00.');

                return;
            }

            DB::transaction(function () use ($split, $splitData, $pendingIndexes, $mesa, $service, $cashRegisterId): void {
                $discardedAt = now()->toIso8601String();
                foreach ($pendingIndexes as $index) {
                    $splitData[$index]['paid'] = true;
                    $splitData[$index]['discarded'] = true;
                    $splitData[$index]['discarded_at'] = $discardedAt;
                    $splitData[$index]['discarded_by'] = auth()->id();
                }

                $split->update([
                    'split_data' => $splitData,
                    'status' => 'completado',
                ]);

                $this->completeMesaSplit($split, $mesa, $service, $cashRegisterId);
            });

            unset($this->mesasPendientes, $this->tableTrackingServices, $this->tableWorkspaceAllServices, $this->tableWorkspaceServices, $this->tableWorkspaceCounts, $this->mesaServiceHistory);
            $this->dispatch('mesa-payment-completed', mesaId: $mesa->id, released: true);
            $this->dispatch('notify', type: 'success', message: "Subcuenta sin consumo eliminada; {$mesa->display_name} quedó disponible.");

            return;
        }

        $result = DB::transaction(function () use ($mesa, $service, $manager, $cashRegisterId): string {
            $lockedMesa = Mesa::query()->lockForUpdate()->find($mesa->id);
            if (! $lockedMesa || $lockedMesa->status !== 'en_cuenta') {
                return 'unavailable';
            }

            $lockedService = $service
                ? MesaService::query()
                    ->whereKey($service->id)
                    ->where('cash_register_id', $cashRegisterId)
                    ->where('status', 'en_cuenta')
                    ->lockForUpdate()
                    ->first()
                : null;

            if ($service && ! $lockedService) {
                return 'unavailable';
            }

            $memberIds = $lockedService?->mesas()->pluck('mesas.id')->all()
                ?: ($lockedMesa->mesa_group_id
                    ? Mesa::where('mesa_group_id', $lockedMesa->mesa_group_id)->pluck('id')->all()
                    : [$lockedMesa->id]);
            $hasActiveOrders = Order::query()
                ->where('cash_register_id', $cashRegisterId)
                ->when(
                    $lockedService,
                    fn ($query) => $query->where('mesa_service_id', $lockedService->id),
                    fn ($query) => $query->whereNull('mesa_service_id')->whereIn('mesa_id', $memberIds)
                )
                ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada'])
                ->exists();

            if ($hasActiveOrders) {
                return 'orders';
            }

            $reason = 'Servicio sin consumo cancelado desde POS';
            if ($lockedService) {
                $manager->releaseWithoutPayment($lockedService, auth()->id(), $reason);
            }
            $this->releaseMesa($lockedMesa, $lockedService, $reason);

            return 'discarded';
        });

        if ($result !== 'discarded') {
            $this->dispatch('notify', type: 'warning', message: 'Solo se pueden descartar cuentas en cero y sin órdenes activas.');

            return;
        }

        unset($this->mesasPendientes, $this->tableTrackingServices, $this->tableWorkspaceAllServices, $this->tableWorkspaceServices, $this->tableWorkspaceCounts, $this->mesaServiceHistory);
        $this->dispatch('notify', type: 'success', message: "Servicio sin consumo de {$mesa->display_name} cancelado; mesa disponible.");
    }

    public function requestDiscardEmptyMesaAccount(int $mesaId): void
    {
        abort_unless(auth()->user()?->can('cobrar mesas'), 403);

        $mesa = Mesa::find($mesaId);
        if (! $mesa || $mesa->status !== 'en_cuenta') {
            return;
        }

        $this->dispatch('open-confirm',
            type: 'danger',
            title: 'Cancelar servicio sin consumo',
            message: "La cuenta pendiente de <strong>{$mesa->display_name}</strong> está en $0.00. Se conservará el historial del servicio y la mesa quedará disponible sin generar una venta ni un movimiento de caja.",
            action: 'discardEmptyMesaAccount',
            params: ['mesaId' => $mesaId],
            confirmText: 'Cancelar servicio',
            cancelText: 'Conservar',
        );
    }

    public function openMesaSplitPayModal(int $splitId, int $accountIdx): void
    {
        abort_unless(auth()->user()?->can('cobrar mesas'), 403);
        $this->resetErrorBag();
        $cashRegisterId = $this->activeCashRegister?->id;
        $split = MesaSplit::whereIn('status', ['pendiente', 'parcial'])
            ->whereHas('mesaService', fn ($query) => $query
                ->where('cash_register_id', $cashRegisterId)
                ->active())
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
        unset($this->mesaPaymentContext);
    }

    public function closeMesaPayModal(): void
    {
        $this->resetErrorBag();
        $this->showMesaPayModal = false;
        $this->mesaPayId = null;
        $this->mesaSplitId = null;
        $this->mesaSplitAccountIdx = null;
        $this->mesaPayments = [];
        unset($this->mesaPaymentContext);
    }

    public function addMesaPayment(): void
    {
        $total = (float) ($this->mesaPaymentContext['total'] ?? 0);

        $paid = collect($this->mesaPayments)->sum('amount');
        $rem = max(0, $total - $paid);
        $amount = (float) $this->mesaPayAmount;
        $this->resetErrorBag(['mesaPayAmount', 'mesaPayReceived', 'mesaPayments']);

        if ($amount <= 0) {
            $this->addError('mesaPayAmount', 'Captura el monto que se aplicará a este pago.');

            return;
        }

        if ($amount > $rem + 0.01) {
            $this->addError('mesaPayAmount', 'El monto no puede superar el saldo pendiente.');

            return;
        }

        if (! in_array($this->mesaPayMethod, ['cash', 'card', 'transfer'], true)) {
            $this->addError('mesaPayAmount', 'Selecciona un método de pago válido.');

            return;
        }

        $payment = ['method' => $this->mesaPayMethod, 'amount' => $amount];

        if ($this->mesaPayMethod === 'cash') {
            $received = (float) $this->mesaPayReceived;
            if ($received <= 0) {
                $this->addError('mesaPayReceived', 'Captura cuánto efectivo entregó el cliente.');

                return;
            }

            if ($received < $amount - 0.01) {
                $this->addError('mesaPayReceived', 'El efectivo recibido no cubre el monto a aplicar.');

                return;
            }

            $payment['cash_received'] = $received;
            $payment['cash_change'] = max(0, $received - $amount);
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

    private function hasValidMesaPayments(): bool
    {
        if (empty($this->mesaPayments)) {
            return false;
        }

        $total = (float) ($this->mesaPaymentContext['total'] ?? 0);
        $paid = collect($this->mesaPayments)->sum(fn ($payment) => (float) ($payment['amount'] ?? 0));
        if ($paid <= 0 || $paid > $total + 0.01) {
            return false;
        }

        return collect($this->mesaPayments)->every(function ($payment): bool {
            $method = $payment['method'] ?? null;
            $amount = (float) ($payment['amount'] ?? 0);
            if (! in_array($method, ['cash', 'card', 'transfer'], true) || $amount <= 0) {
                return false;
            }

            return $method !== 'cash'
                || (float) ($payment['cash_received'] ?? 0) >= $amount - 0.01;
        });
    }

    public function confirmMesaPayment(): void
    {
        abort_unless(auth()->user()?->can('cobrar mesas'), 403);

        if (! $this->hasValidMesaPayments()) {
            $this->addError('mesaPayments', 'Agrega al menos un pago antes de cobrar la cuenta.');
            $this->dispatch('notify', type: 'warning', message: 'Primero agrega el pago y confirma el monto recibido.');

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
        DB::transaction(function (): void {
            $cashRegisterId = $this->activeCashRegister?->id;
            $mesa = Mesa::query()->lockForUpdate()->find($this->mesaPayId);
            if (! $mesa || ! $cashRegisterId) {
                return;
            }

            $service = app(MesaServiceManager::class)->findActiveForMesa($mesa, $cashRegisterId);
            if ($service) {
                MesaService::query()->whereKey($service->id)->lockForUpdate()->first();
                Order::query()
                    ->where('mesa_service_id', $service->id)
                    ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada'])
                    ->lockForUpdate()
                    ->get();
            } else {
                Order::query()
                    ->where('mesa_id', $mesa->id)
                    ->where('cash_register_id', $cashRegisterId)
                    ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada'])
                    ->lockForUpdate()
                    ->get();
            }

            $this->performFullMesaPayment();
        }, 3);
    }

    private function performFullMesaPayment(): void
    {
        $cashRegisterId = $this->activeCashRegister?->id;
        $mesa = Mesa::with(['area', 'currentAssignment.waiter'])->find($this->mesaPayId);
        if (! $mesa) {
            return;
        }

        $service = app(MesaServiceManager::class)->findActiveForMesa($mesa, $cashRegisterId);
        $orders = $service
            ? $service->orders()
                ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada'])
                ->with('items')
                ->get()
            : $mesa->orders()
                ->where('cash_register_id', $cashRegisterId)
                ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada'])
                ->with('items')
                ->get();
        if ($orders->isEmpty()) {
            return;
        }
        if ($orders->contains(fn (Order $order) => ! in_array($order->status, ['lista', 'entregada'], true))) {
            $this->dispatch('notify', type: 'warning', message: 'Todas las comandas deben estar listas antes de cobrar.');

            return;
        }
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
        if ($service) {
            app(MesaServiceManager::class)->completePaid($service, auth()->id());
        }
        $this->releaseMesa($mesa, $service);

        // Print ticket
        $ticketItems = $orders->flatMap(fn ($o) => $o->items->map(fn ($i) => [
            'qty' => $i->quantity,
            'name' => $i->product_name,
            'subtotal' => (float) $i->subtotal,
        ]))->toArray();

        $this->dispatch('pos-reprint-show',
            html_cliente: $this->buildMesaTicketHtml(
                mesa: $mesa,
                accountLabel: $service?->service_label ?? $mesa->display_name,
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
        unset($this->mesasPendientes, $this->tableTrackingServices, $this->tableWorkspaceAllServices, $this->tableWorkspaceServices, $this->tableWorkspaceCounts, $this->mesaServiceHistory);
        $label = $service?->service_label ?? $mesa->display_name;
        $this->dispatch('notify', type: 'success', message: "{$label} cobrado y liberado.");
    }

    private function confirmSplitAccountPayment(): void
    {
        DB::transaction(function (): void {
            $cashRegisterId = $this->activeCashRegister?->id;
            $split = MesaSplit::query()
                ->whereHas('mesaService', fn ($query) => $query
                    ->where('cash_register_id', $cashRegisterId)
                    ->active())
                ->lockForUpdate()
                ->find($this->mesaSplitId);

            if (! $split) {
                return;
            }

            Mesa::query()->whereKey($split->mesa_id)->lockForUpdate()->first();
            Order::query()
                ->where('mesa_service_id', $split->mesa_service_id)
                ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada'])
                ->lockForUpdate()
                ->get();

            $this->performSplitAccountPayment();
        }, 3);
    }

    private function performSplitAccountPayment(): void
    {
        $cashRegisterId = $this->activeCashRegister?->id;
        $split = MesaSplit::with([
            'mesa.area',
            'mesa.currentAssignment.waiter',
        ])->whereHas('mesaService', fn ($query) => $query
            ->where('cash_register_id', $cashRegisterId)
            ->active())
            ->findOrFail($this->mesaSplitId);

        $mesa = $split->mesa;
        $service = $split->mesa_service_id
            ? MesaService::find($split->mesa_service_id)
            : app(MesaServiceManager::class)->findActiveForMesa($mesa, $cashRegisterId);
        $ordersReady = Order::query()
            ->when(
                $service,
                fn ($query) => $query->where('mesa_service_id', $service->id),
                fn ($query) => $query->where('mesa_id', $mesa->id)->where('cash_register_id', $cashRegisterId)
            )
            ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada'])
            ->get()
            ->every(fn (Order $order) => in_array($order->status, ['lista', 'entregada'], true));
        if (! $ordersReady) {
            $this->dispatch('notify', type: 'warning', message: 'Todas las comandas deben estar listas antes de cobrar.');

            return;
        }
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
            $activeOrders = Order::query()
                ->when(
                    $service,
                    fn ($query) => $query->where('mesa_service_id', $service->id),
                    fn ($query) => $query->where('mesa_id', $mesa->id)->where('cash_register_id', $cashRegisterId)
                )
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
            $this->completeMesaSplit($split, $mesa, $service, $cashRegisterId);
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
        unset($this->mesasPendientes, $this->tableTrackingServices, $this->tableWorkspaceAllServices, $this->tableWorkspaceServices, $this->tableWorkspaceCounts, $this->mesaServiceHistory);
        $this->dispatch('mesa-payment-completed', mesaId: $mesa->id, released: $allPaid);

        $msg = $allPaid
            ? "Mesa {$mesa->display_name} cobrada y liberada."
            : "Cuenta \"{$account['label']}\" cobrada.";
        $this->dispatch('notify', type: 'success', message: $msg);
    }

    private function completeMesaSplit(
        MesaSplit $split,
        Mesa $mesa,
        ?MesaService $service,
        int $cashRegisterId
    ): void {
        // Ningún split pendiente del mismo servicio debe mantener visible una
        // mesa después de liquidar o descartar su última subcuenta.
        MesaSplit::query()
            ->where('mesa_id', $mesa->id)
            ->where('id', '!=', $split->id)
            ->whereIn('status', ['pendiente', 'parcial'])
            ->when(
                $service,
                fn ($query) => $query->where('mesa_service_id', $service->id),
                fn ($query) => $query->whereNull('mesa_service_id')
            )
            ->update(['status' => 'completado']);

        Order::query()
            ->when(
                $service,
                fn ($query) => $query->where('mesa_service_id', $service->id),
                fn ($query) => $query->where('mesa_id', $mesa->id)->where('cash_register_id', $cashRegisterId)
            )
            ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada'])
            ->update(['status' => 'pagada', 'paid_at' => now()]);

        if ($service) {
            app(MesaServiceManager::class)->completePaid($service, auth()->id());
        }

        $this->releaseMesa($mesa, $service);
    }

    private function releaseMesa(
        Mesa $mesa,
        ?MesaService $service = null,
        string $releaseReason = 'Cobrado desde POS'
    ): void {
        $groupId = $mesa->mesa_group_id ?: $service?->mesa_group_id;
        $memberIds = $service?->mesas()->pluck('mesas.id')->all()
            ?: ($groupId
                ? Mesa::where('mesa_group_id', $groupId)->pluck('id')->all()
                : [$mesa->id]);

        MesaAssignment::whereIn('mesa_id', $memberIds)
            ->whereNull('released_at')
            ->update([
                'released_by' => auth()->id(),
                'released_at' => now(),
                'release_reason' => $releaseReason,
            ]);

        Mesa::whereIn('id', $memberIds)
            ->update(['status' => 'disponible', 'mesa_group_id' => null]);

        if ($groupId) {
            MesaGroup::destroy($groupId);
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

        $service = $this->activeCashRegister
            ? app(MesaServiceManager::class)->findActiveForMesa($mesa, $this->activeCashRegister->id)
            : null;
        if ($service) {
            app(MesaServiceManager::class)->completePaid($service, auth()->id());
        }
        $this->releaseMesa($mesa, $service);
        unset($this->tableTrackingServices, $this->tableWorkspaceAllServices, $this->tableWorkspaceServices, $this->tableWorkspaceCounts, $this->mesaServiceHistory);

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
        abort_unless(auth()->user()?->can('cerrar ordenes'), 403);
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
            $this->tableTrackingServices,
            $this->tableWorkspaceAllServices,
            $this->tableWorkspaceServices,
            $this->tableWorkspaceCounts,
        );
        $message = $mesaWasReleased
            ? "Orden #{$order->id} cobrada. Era la última nota y la mesa quedó disponible."
            : "Orden #{$order->id} cobrada.";
        $this->dispatch('notify', type: 'success', message: $message);

        // La primera impresión forma parte del cobro y no es una reimpresión.
        $this->dispatchOrderTicketPreview($order);
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
            ->where(fn ($query) => $query
                ->whereIn('type', ['mesa', 'delivery'])
                ->orWhere('source', 'kiosk'))
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
            ->where(fn ($query) => $query
                ->whereIn('type', ['mesa', 'delivery'])
                ->orWhere('source', 'kiosk'))
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
        abort_unless(auth()->user()?->can('editar ordenes'), 403);
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
            $this->tableTrackingServices,
            $this->tableWorkspaceAllServices,
            $this->tableWorkspaceServices,
            $this->tableWorkspaceCounts,
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
        abort_unless(auth()->user()?->can('reimprimir tickets'), 403);
        $order = Order::with([
            'items.addons',
            'items.ingredients',
            'items.product.category.printArea',
        ])->where('cash_register_id', $this->activeCashRegister?->id)
            ->find($orderId);

        if (! $order) {
            return;
        }

        $this->dispatch('pos-reprint-show-cocina',
            html_cliente: '',
            html_cocina: $this->buildKitchenTicketHtml($order),
        );
    }

    // ─── Print via iframe ─────────────────────────────────────────────────────

    public function openReprintModal(int $orderId): void
    {
        abort_unless(auth()->user()?->can('reimprimir tickets'), 403);
        $order = Order::with([
            'items.addons',
            'items.ingredients',
            'items.product.category.printArea',
            'payments',
        ])->where('cash_register_id', $this->activeCashRegister?->id)
            ->findOrFail($orderId);

        $this->dispatchOrderTicketPreview($order);
    }

    public function openMesaServiceHistoryTicket(int $serviceId): void
    {
        abort_unless(auth()->user()?->can('reimprimir tickets'), 403);

        $service = MesaService::query()
            ->where('cash_register_id', $this->activeCashRegister?->id)
            ->whereIn('status', ['pagada', 'liberada'])
            ->findOrFail($serviceId);

        $this->dispatch('pos-reprint-show',
            html_cliente: app(ThermalTicketRenderer::class)->renderMesaService($service),
            html_cocina: '',
        );
    }

    private function dispatchOrderTicketPreview(Order $order): void
    {
        $this->dispatch('pos-reprint-show',
            html_cliente: $this->buildTicketHtml($order),
            html_cocina: $this->buildKitchenTicketHtml($order),
        );
    }

    private function buildTicketHtml(Order $order): string
    {
        return app(ThermalTicketRenderer::class)->renderOrder(
            $order,
            $this->ticketTemplateTypeForOrder($order),
            autoPrint: false,
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
        return app(ThermalTicketRenderer::class)->renderOrder($order, 'kitchen_area', autoPrint: false);

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

    private function ticketTemplateTypeForOrder(Order $order): string
    {
        if ($order->type === 'delivery' || $order->fulfillment === 'delivery') {
            return 'delivery';
        }

        if (in_array($order->type, ['ventanilla', 'pick_up'], true) || $order->fulfillment === 'pickup') {
            return 'counter';
        }

        return 'customer';
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

    private function formattedCustomerDeliveryAddress(): ?string
    {
        $parts = array_filter([
            trim($this->customerAddress),
            trim($this->customerNeighborhood),
        ], fn (string $part) => $part !== '');

        return $parts === [] ? null : implode(', ', $parts);
    }

    private function rememberMissingCustomerDeliveryData(): void
    {
        if (! $this->customerId) {
            return;
        }

        $customer = Customer::query()->lockForUpdate()->find($this->customerId);
        if (! $customer) {
            return;
        }

        $updates = [];
        if (blank($customer->address) && filled($this->customerAddress)) {
            $updates['address'] = trim($this->customerAddress);
        }
        if (blank($customer->neighborhood) && filled($this->customerNeighborhood)) {
            $updates['neighborhood'] = trim($this->customerNeighborhood);
        }
        if (blank($customer->references) && filled($this->customerReferences)) {
            $updates['references'] = trim($this->customerReferences);
        }

        if ($updates !== []) {
            $customer->update($updates);
        }
    }

    private function persistOrder(string $type, string $status, ?string $deliveryFlowMode = null): Order
    {
        $this->refreshPromotionCart($this->promotionFulfillmentForOrderType($type));
        $cash = $this->activeCashRegister;
        $total = $this->cartTotal;

        return DB::transaction(function () use ($cash, $total, $type, $status, $deliveryFlowMode) {
            $order = Order::create([
                'cash_register_id' => $cash?->id,
                'customer_id' => $this->customerId,
                'customer_name' => $this->customerName ?: null,
                'customer_phone' => $this->customerPhone ?: null,
                'customer_address' => $type === 'delivery' ? $this->formattedCustomerDeliveryAddress() : null,
                'customer_neighborhood' => $type === 'delivery' ? ($this->customerNeighborhood ?: null) : null,
                'customer_references' => $type === 'delivery' ? ($this->customerReferences ?: null) : null,
                'served_by' => auth()->id(),
                'type' => $type,
                'delivery_method' => $type === 'delivery' ? $this->deliveryMethod : null,
                'delivery_flow_mode' => $type === 'delivery'
                    ? ($deliveryFlowMode ?? 'managed')
                    : 'managed',
                'status' => $status,
                'subtotal' => $total,
                'total' => $total,
                'notes' => $this->orderNotes ?: null,
                'paid_at' => $status === 'pagada' ? now() : null,
            ]);

            $addonRows = [];
            $ingredientRows = [];
            $timestamp = now();

            foreach ($this->cart as $item) {
                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'promotion_id' => $item['promotion_id'] ?? null,
                    'product_name' => $item['product_name'],
                    'product_price' => $item['product_price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $item['subtotal'],
                    'promotion_discount' => $item['promotion_discount'] ?? 0,
                    'notes' => $item['notes'] ?: null,
                    'promotion_selections' => $item['promotion_selections'] ?? null,
                    'promotion_rule_snapshot' => $item['promotion_rule_snapshot'] ?? null,
                ]);

                foreach ($item['addons'] as $addon) {
                    $addonRows[] = [
                        'order_item_id' => $orderItem->id,
                        'addon_id' => $addon['addon_id'],
                        'addon_name' => $addon['addon_name'],
                        'extra_price' => $addon['extra_price'],
                        'quantity' => 1,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }

                foreach ($item['ingredients'] as $ingredient) {
                    $ingredientRows[] = [
                        'order_item_id' => $orderItem->id,
                        'ingredient_id' => $ingredient['ingredient_id'],
                        'ingredient_name' => $ingredient['ingredient_name'],
                        'extra_price' => $ingredient['extra_price'],
                        'quantity' => $ingredient['quantity'],
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
    }

    private function refreshPromotionCart(?string $fulfillment = null): void
    {
        foreach ($this->cart as $index => $item) {
            if (empty($item['promotion_id']) || ! empty($item['auto_promotion_applied'])) {
                continue;
            }

            $promotion = Promotion::query()
                ->available('pos', null, $fulfillment)
                ->with([
                    'groups.products' => fn ($query) => $query
                        ->where('is_active', true)
                        ->select(['products.id', 'products.category_id', 'products.name', 'products.image']),
                    'groups.products.category.printArea',
                ])
                ->find($item['promotion_id']);
            if (! $promotion) {
                throw ValidationException::withMessages([
                    'cart' => "La promoción «{$item['product_name']}» venció o fue pausada. Retírala del pedido.",
                ]);
            }

            $selectionMap = collect($item['promotion_selections'] ?? [])->mapWithKeys(function ($group) {
                return [(int) ($group['group_id'] ?? 0) => collect($group['items'] ?? [])->mapWithKeys(
                    fn ($selected) => [(int) ($selected['product_id'] ?? 0) => (int) ($selected['quantity'] ?? 0)]
                )->all()];
            })->all();
            $snapshot = $this->validatedPromotionSnapshot($promotion, $selectionMap);
            $quantity = max(1, (int) $item['quantity']);
            $this->cart[$index]['product_name'] = $promotion->name;
            $this->cart[$index]['product_price'] = (float) $promotion->price;
            $this->cart[$index]['unit_total'] = (float) $promotion->price;
            $this->cart[$index]['subtotal'] = (float) $promotion->price * $quantity;
            $this->cart[$index]['promotion_selections'] = $snapshot;
        }

        unset($this->cartTotal, $this->cartCount);
        $this->saveCart();
    }

    private function promotionFulfillmentForOrderType(string $type): string
    {
        return match ($type) {
            'delivery' => 'delivery',
            'pick_up' => 'pickup',
            default => 'takeaway',
        };
    }

    private function validatedPromotionSnapshot(Promotion $promotion, array $selections): array
    {
        $snapshot = [];
        foreach ($promotion->groups as $group) {
            $requested = collect($selections[$group->id] ?? [])
                ->mapWithKeys(fn ($quantity, $productId) => [(int) $productId => max(0, (int) $quantity)])
                ->filter();
            $allowed = $group->products->keyBy('id');
            if ($requested->keys()->diff($allowed->keys())->isNotEmpty()) {
                throw ValidationException::withMessages(['promotion' => "«{$group->name}» contiene productos no disponibles."]);
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
                'items' => $requested->map(function ($quantity, $productId) use ($allowed, $group) {
                    $product = $allowed->get((int) $productId);
                    $category = $product?->category;
                    $printArea = $category?->printArea;

                    return [
                        'product_id' => (int) $productId,
                        'product_name' => $product?->name ?? 'Producto',
                        'quantity' => (int) $quantity,
                        'category_name' => $category?->name,
                        'print_area_id' => $printArea?->id,
                        'print_area_name' => $printArea?->name ?? $category?->name ?? $group->name ?? 'General',
                    ];
                })->values()->all(),
            ];
        }

        return $snapshot;
    }

    private function finishSale(Order $order, bool $openTicket): void
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

        if ($openTicket) {
            $order->loadMissing([
                'items.addons',
                'items.ingredients',
                'items.product.category.printArea',
                'payments',
            ]);
            $this->dispatchOrderTicketPreview($order);
        }
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
        $this->customerNeighborhood = '';
        $this->customerReferences = '';
        $this->customerSearch = '';
        $this->payments = [];
        $this->payMethod = 'cash';
        $this->payAmount = '';
        $this->payCashReceived = '';
        $this->payCardLast4 = '';
        $this->payTransferRef = '';
        unset($this->paidTotal, $this->paymentRemaining, $this->customerSearchResults);
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
