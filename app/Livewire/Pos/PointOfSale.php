<?php

namespace App\Livewire\Pos;

use App\Livewire\Pos\Concerns\ManagesCart;
use App\Livewire\Pos\Concerns\ManagesCheckout;
use App\Livewire\Pos\Concerns\ManagesCustomers;
use App\Livewire\Pos\Concerns\ManagesPromotions;
use App\Livewire\Pos\Concerns\ManagesQuotations;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Mesa;
use App\Models\MesaAssignment;
use App\Models\MesaGroup;
use App\Models\MesaService;
use App\Models\MesaSplit;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use App\Services\DeliveryModulePolicy;
use App\Services\DeliveryWorkflow;
use App\Services\InventoryService;
use App\Services\CashRegisterOpenService;
use App\Services\ManualDeliveryAccountingService;
use App\Services\MesaServiceManager;
use App\Services\OrderOperationalDataService;
use App\Services\ThermalTicketRenderer;
use App\Support\BusinessTime;
use App\Support\PaymentAllocator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class PointOfSale extends Component
{
    use ManagesCart;
    use ManagesCheckout;
    use ManagesCustomers;
    use ManagesPromotions;
    use ManagesQuotations;

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

    public ?int $discountEmployeeId = null;

    public string $checkoutIdentityType = 'customer';

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

    public array $pickupPayments = [];

    public string $pickupPayMethod = 'cash';

    public string $pickupPayAmount = '';

    public string $pickupPayReceived = '';

    public string $pickupPayCard = '';

    public string $pickupPayRef = '';

    // ─── Reprint / orders search ───────────────────────────────────────────────
    public string $reprintSearch = '';

    public string $reprintType = 'ventanilla'; // ventanilla | mesas | delivery

    public bool $reprintHistoryLoaded = false;

    /**
     * Guardas de carga diferida de los paneles.
     *
     * Los paneles se muestran y ocultan desde Alpine, asi que el servidor
     * construye su HTML en cada render aunque esten cerrados. Estos flags
     * cortan la consulta en seco: el panel cerrado no toca la base.
     */
    public bool $pickupPanelLoaded = false;

    // Corrección directa de datos operativos (nunca productos, importes ni estado).
    public bool $showOrderDataModal = false;

    public string $orderDataSearch = '';

    public ?int $orderDataOrderId = null;

    public string $orderDataCustomerName = '';

    public string $orderDataCustomerPhone = '';

    public string $orderDataCustomerAddress = '';

    public string $orderDataCustomerNeighborhood = '';

    public string $orderDataCustomerReferences = '';

    public string $orderDataDeliveryMethod = 'contra_entrega';

    public array $orderDataPayments = [];

    public bool $tableTrackingLoaded = false;

    public ?string $tableTrackingRefreshedAt = null;

    public bool $tablesBillingLoaded = false;

    public bool $tableWorkspaceLoaded = false;

    public string $tableWorkspaceFilter = 'all';

    public bool $deliveryPanelLoaded = false;

    // Despacho de repartidores dentro del POS.
    public bool $showDeliveryDispatchModal = false;

    public ?int $deliveryDispatchOrderId = null;

    public ?int $deliveryDispatchDriverId = null;

    public string $deliveryDispatchReason = '';

    public string $deliveryDispatchAction = '';

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Las vistas preguntaban esto directamente al servicio, dos veces por
     * render. Como propiedad computada se resuelve una sola vez.
     */
    #[Computed]
    public function deliveryModuleEnabled(): bool
    {
        return app(DeliveryModulePolicy::class)->enabled();
    }

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

    #[Computed]
    public function recentOrders()
    {
        // El flag solo acotaba el WHERE, asi que la consulta corria igual con el
        // panel cerrado. Ahora corta antes de tocar la base.
        if (! $this->reprintHistoryLoaded) {
            return collect();
        }

        $cashRegisterId = $this->activeCashRegister?->id;
        if (! $cashRegisterId) {
            return collect();
        }

        $search = $this->reprintSearch;

        return Order::with(['items', 'payments', 'refunds', 'mesa.area'])
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
    public function editableOrderDataOrders()
    {
        $cashRegisterId = $this->activeCashRegister?->id;

        if (! $cashRegisterId || ! $this->showOrderDataModal) {
            return collect();
        }

        $search = trim($this->orderDataSearch);

        return Order::query()
            ->with(['payments', 'customer'])
            ->where('cash_register_id', $cashRegisterId)
            ->where('status', '!=', 'cancelada')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($scope) use ($search): void {
                    $scope->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%");

                    if (ctype_digit($search)) {
                        $scope->orWhere('id', (int) $search)
                            ->orWhere('folio', (int) $search);
                    }
                });
            })
            ->latest('created_at')
            ->limit(30)
            ->get();
    }

    #[Computed]
    public function deliveryDispatchDrivers()
    {
        if (! $this->showDeliveryDispatchModal || ! auth()->user()?->canAny([
            'reasignar pedidos delivery',
            'editar datos de ordenes en punto de venta',
        ])) {
            return collect();
        }

        return User::query()
            ->whereNull('banned_at')
            ->with(['roles.permissions', 'permissions'])
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user): bool => $user->hasAnyPermission(['entregar delivery', 'gestionar delivery']))
            ->values();
    }

    #[Computed]
    public function deliveryDispatchOrders()
    {
        $cashRegisterId = $this->activeCashRegister?->id;

        if (! $cashRegisterId || ! $this->showDeliveryDispatchModal || ! auth()->user()?->canAny([
            'reasignar pedidos delivery',
            'editar datos de ordenes en punto de venta',
        ])) {
            return collect();
        }

        return Order::query()
            ->with(['deliveryAssignment.driver', 'items.addons', 'items.ingredients', 'payments'])
            ->where('cash_register_id', $cashRegisterId)
            ->where('type', 'delivery')
            ->where('delivery_flow_mode', 'managed')
            ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'pagada', 'en_reparto'])
            ->whereHas('deliveryAssignment', fn ($query) => $query->where('status', 'asignado'))
            ->oldest('created_at')
            ->get();
    }

    #[Computed]
    public function selectedDeliveryDispatchOrder(): ?Order
    {
        if (! $this->deliveryDispatchOrderId) {
            return null;
        }

        return $this->deliveryDispatchOrders->firstWhere('id', $this->deliveryDispatchOrderId);
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
            return ['pickup' => 0, 'tables' => 0, 'delivery' => 0, 'balances' => 0];
        }

        $activeStatuses = ['pendiente', 'en_preparacion', 'lista'];

        // Los tres contadores que viven en `orders` se resuelven con agregacion
        // condicional en una sola pasada, en vez de tres COUNT separados. Las
        // condiciones son las mismas que tenian las consultas individuales.
        $ordersTable = (new Order)->getTable();
        $paymentsTable = (new OrderPayment)->getTable();

        $statusList = $this->sqlList($activeStatuses);
        $pickupTypes = $this->sqlList(['pick_up', 'ventanilla']);
        $unpaid = "not exists (select 1 from {$paymentsTable} where {$paymentsTable}.order_id = {$ordersTable}.id)";

        $pickupCondition = "{$unpaid} and {$ordersTable}.status in ({$statusList}) and ("
            ."({$ordersTable}.source = 'kiosk' and {$ordersTable}.fulfillment = 'takeaway')"
            ." or ({$ordersTable}.type in ({$pickupTypes}) and ({$ordersTable}.source is null or {$ordersTable}.source <> 'kiosk'))"
            .')';

        $deliveryCondition = "{$ordersTable}.type = 'delivery' and {$ordersTable}.status = 'pendiente'";

        $balanceCondition = Order::pendingBalanceSql($ordersTable);

        $legacyTableCondition = "{$ordersTable}.mesa_service_id is null"
            ." and {$ordersTable}.mesa_id is not null"
            ." and {$ordersTable}.status in ({$statusList})";

        $counts = Order::query()
            ->where('cash_register_id', $cashRegisterId)
            ->selectRaw("sum(case when {$pickupCondition} then 1 else 0 end) as pickup_count")
            ->selectRaw("sum(case when {$deliveryCondition} then 1 else 0 end) as delivery_count")
            ->selectRaw("sum(case when {$balanceCondition} then 1 else 0 end) as balances_count")
            ->selectRaw("count(distinct case when {$legacyTableCondition} then {$ordersTable}.mesa_id end) as legacy_table_count")
            ->first();

        $tables = MesaService::query()
            ->where('cash_register_id', $cashRegisterId)
            ->active()
            ->count();

        return [
            'pickup' => (int) ($counts->pickup_count ?? 0),
            'tables' => $tables + (int) ($counts->legacy_table_count ?? 0),
            'delivery' => (int) ($counts->delivery_count ?? 0),
            'balances' => (int) ($counts->balances_count ?? 0),
        ];
    }

    /**
     * Lista de literales para un `IN (...)` construido a mano.
     *
     * Los valores son constantes del propio codigo, nunca entrada del usuario,
     * pero se escapan igual para que el metodo no se pueda usar mal mas tarde.
     *
     * @param  array<int, string>  $values
     */
    private function sqlList(array $values): string
    {
        return collect($values)
            ->map(fn (string $value) => "'".str_replace("'", "''", $value)."'")
            ->implode(', ');
    }

    #[Computed]
    public function mesaServiceHistory()
    {
        // Solo la consulta el panel de reimpresion, y carga siete relaciones
        // anidadas: con el panel cerrado no vale la pena ni abrirla.
        if (! $this->reprintHistoryLoaded) {
            return collect();
        }

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
        $this->tableTrackingRefreshedAt = BusinessTime::format(BusinessTime::now(), 'g:i:s A');
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
        abort_unless(auth()->user()?->can('ver pedidos en punto de venta'), 403);
        $this->resetOperationalPanelState();
        $this->deliveryPanelLoaded = true;
        unset($this->deliveryOrders);
    }

    public function closeDeliveryPanel(): void
    {
        $this->closeOperationalPanels();
    }

    public function openDeliveryDispatchModal(): void
    {
        abort_unless(auth()->user()?->canAny([
            'reasignar pedidos delivery',
            'editar datos de ordenes en punto de venta',
        ]), 403);
        app(DeliveryModulePolicy::class)->assertEnabled();

        if (! $this->activeCashRegister) {
            $this->dispatch('notify', type: 'warning', message: 'Abre una caja para consultar el reparto activo.');

            return;
        }

        $this->resetOperationalPanelState();
        $this->resetDeliveryDispatchState();
        $this->showDeliveryDispatchModal = true;
        unset($this->deliveryDispatchDrivers, $this->deliveryDispatchOrders, $this->selectedDeliveryDispatchOrder);
    }

    public function closeDeliveryDispatchModal(): void
    {
        $this->showDeliveryDispatchModal = false;
        $this->resetDeliveryDispatchState();
        unset($this->deliveryDispatchDrivers, $this->deliveryDispatchOrders, $this->selectedDeliveryDispatchOrder);
    }

    public function selectDeliveryDispatchOrder(int $orderId): void
    {
        abort_unless(auth()->user()?->canAny([
            'reasignar pedidos delivery',
            'editar datos de ordenes en punto de venta',
        ]), 403);

        $order = $this->deliveryDispatchOrders->firstWhere('id', $orderId);
        abort_unless($order, 404);

        $this->resetDeliveryDispatchAction();
        $this->deliveryDispatchOrderId = $order->id;
        unset($this->selectedDeliveryDispatchOrder);
    }

    public function selectDeliveryDispatchAction(string $action): void
    {
        abort_unless(in_array($action, ['edit_order', 'reassign'], true), 422);

        $permission = $action === 'edit_order'
            ? 'editar datos de ordenes en punto de venta'
            : 'reasignar pedidos delivery';
        abort_unless(auth()->user()?->can($permission), 403);

        $order = $this->selectedDeliveryDispatchOrder;
        abort_unless($order, 404);

        $this->resetDeliveryDispatchAction();
        $this->deliveryDispatchAction = $action;

        if ($action === 'edit_order') {
            $this->fillOrderDataEditor($order);
        }
    }

    public function resetDeliveryDispatchAction(): void
    {
        $this->deliveryDispatchAction = '';
        $this->deliveryDispatchDriverId = null;
        $this->deliveryDispatchReason = '';
        $this->resetOrderDataEditor();
        $this->resetValidation(['deliveryDispatchDriverId', 'deliveryDispatchReason']);
    }

    public function saveDeliveryDispatchOrderData(OrderOperationalDataService $service): void
    {
        abort_unless(auth()->user()?->can('editar datos de ordenes en punto de venta'), 403);
        abort_unless($this->deliveryDispatchAction === 'edit_order', 422);
        abort_unless($this->deliveryDispatchOrderId === $this->orderDataOrderId, 422);

        $this->saveOrderData($service);
        $this->deliveryDispatchAction = '';
        unset($this->deliveryDispatchOrders, $this->selectedDeliveryDispatchOrder);
    }

    public function reassignDeliveryFromPos(DeliveryWorkflow $workflow): void
    {
        abort_unless(auth()->user()?->can('reasignar pedidos delivery'), 403);
        app(DeliveryModulePolicy::class)->assertEnabled();
        abort_unless($this->deliveryDispatchOrderId && $this->activeCashRegister, 422);
        abort_unless($this->deliveryDispatchAction === 'reassign', 422);

        $validated = $this->validate([
            'deliveryDispatchDriverId' => ['required', 'integer', 'exists:users,id'],
            'deliveryDispatchReason' => ['required', 'string', 'min:8', 'max:500'],
        ], [
            'deliveryDispatchDriverId.required' => 'Selecciona al repartidor que recibirá el pedido.',
            'deliveryDispatchReason.required' => 'Indica por qué se reasigna el pedido.',
            'deliveryDispatchReason.min' => 'Describe el motivo con al menos 8 caracteres.',
        ]);

        $order = Order::query()
            ->whereKey($this->deliveryDispatchOrderId)
            ->where('cash_register_id', $this->activeCashRegister->id)
            ->where('type', 'delivery')
            ->where('delivery_flow_mode', 'managed')
            ->firstOrFail();

        try {
            $workflow->reassign(
                $order,
                User::query()->findOrFail($validated['deliveryDispatchDriverId']),
                auth()->user(),
                $validated['deliveryDispatchReason'],
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $mappedField = $field === 'reassignDriverId' ? 'deliveryDispatchDriverId' : $field;
                $this->addError($mappedField, $messages[0]);
            }

            return;
        } catch (AuthorizationException) {
            abort(403);
        }

        $this->resetDeliveryDispatchAction();
        unset($this->deliveryDispatchDrivers, $this->deliveryDispatchOrders, $this->selectedDeliveryDispatchOrder);
        $this->dispatch('notify', type: 'success', message: 'Pedido reasignado sin salir del punto de venta.');
    }

    private function resetDeliveryDispatchState(): void
    {
        $this->resetDeliveryDispatchAction();
        $this->deliveryDispatchOrderId = null;
    }

    public function openPickupPanel(): void
    {
        abort_unless(auth()->user()?->can('ver pedidos en punto de venta'), 403);
        $this->resetOperationalPanelState();
        $this->pickupPanelLoaded = true;
        unset($this->pickupOrders);
    }


    /**
     * El panel vive en su propio componente. El padre cierra los demás paneles
     * primero y luego lo abre: ambos eventos salen en este orden, así el cierre
     * general no apaga al panel recién abierto.
     */
    public function openBalancesPanel(): void
    {
        abort_unless(auth()->user()?->can('ver pedidos en punto de venta'), 403);
        $this->resetOperationalPanelState();
        $this->dispatch('pos-open-balances');
    }

    public function openReprintPanel(): void
    {
        abort_unless(auth()->user()?->can('reimprimir tickets'), 403);
        $this->resetOperationalPanelState();
        $this->reprintHistoryLoaded = true;
        unset($this->recentOrders);
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
        $this->reprintHistoryLoaded = false;
        $this->pickupPanelLoaded = false;

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

        // Los paneles extraídos a componentes hijos tienen su propio flag de
        // carga; el padre no puede apagarlo por ellos.
        $this->dispatch('pos-panels-closed');
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
        if (! $this->pickupPanelLoaded) {
            return collect();
        }

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

    // ─── Cash Register ─────────────────────────────────────────────────────────

    public function openCashRegister(): void
    {
        abort_unless(auth()->user()?->can('abrir caja'), 403);
        $this->validate([
            'cashName' => 'required|string|max:60',
            'cashInitialAmount' => 'required|numeric|min:0',
        ]);

        try {
            app(CashRegisterOpenService::class)->open(auth()->user(), $this->cashName, (float) $this->cashInitialAmount);
        } catch (ValidationException $exception) {
            $this->addError('cashName', collect($exception->errors())->flatten()->first());
            unset($this->activeCashRegister);

            return;
        }

        $this->showCashModal = false;
        unset($this->activeCashRegister);
        $this->dispatch('notify', type: 'success', message: "Caja \"{$this->cashName}\" abierta.");
    }

    // ─── Product browsing ─────────────────────────────────────────────────────

    // ─── Customize product modal ───────────────────────────────────────────────

    // ─── Cart operations ───────────────────────────────────────────────────────

    #[On('pos-balances-changed')]
    public function refreshBalanceCounters(): void
    {
        unset($this->toolbarPendingCounts, $this->deliveryOrders);
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

    // ─── Checkout modal ────────────────────────────────────────────────────────

    // ─── Quotations ────────────────────────────────────────────────────────────

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

    public function openOrderDataModal(): void
    {
        abort_unless(auth()->user()?->can('editar datos de ordenes en punto de venta'), 403);

        if (! $this->activeCashRegister) {
            $this->dispatch('notify', type: 'warning', message: 'Abre una caja antes de corregir una orden.');

            return;
        }

        $this->resetOrderDataEditor();
        $this->showOrderDataModal = true;
        unset($this->editableOrderDataOrders);
    }

    public function closeOrderDataModal(): void
    {
        $this->showOrderDataModal = false;
        $this->resetOrderDataEditor();
    }

    public function updatedOrderDataSearch(): void
    {
        unset($this->editableOrderDataOrders);
    }

    public function selectOrderForDataEdit(int $orderId): void
    {
        abort_unless(auth()->user()?->can('editar datos de ordenes en punto de venta'), 403);

        $order = Order::query()
            ->with('payments')
            ->whereKey($orderId)
            ->where('cash_register_id', $this->activeCashRegister?->id)
            ->where('status', '!=', 'cancelada')
            ->firstOrFail();

        $this->fillOrderDataEditor($order);
    }

    private function fillOrderDataEditor(Order $order): void
    {
        $this->resetErrorBag();
        $this->orderDataOrderId = $order->id;
        $this->orderDataCustomerName = (string) $order->customer_name;
        $this->orderDataCustomerPhone = (string) $order->customer_phone;
        $this->orderDataCustomerAddress = (string) $order->customer_address;
        $this->orderDataCustomerNeighborhood = (string) $order->customer_neighborhood;
        $this->orderDataCustomerReferences = (string) $order->customer_references;
        $this->orderDataDeliveryMethod = match ($order->delivery_method) {
            'tarjeta', 'card' => 'tarjeta',
            'transferencia', 'transfer' => 'transferencia',
            default => 'contra_entrega',
        };
        $this->orderDataPayments = $order->payments->map(fn (OrderPayment $payment) => [
            'id' => $payment->id,
            'method' => $payment->method,
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'received_amount' => number_format((float) ($payment->received_amount ?? $payment->amount), 2, '.', ''),
            'card_last4' => (string) $payment->card_last4,
            'transfer_reference' => (string) $payment->transfer_reference,
        ])->values()->all();
    }

    public function saveOrderData(OrderOperationalDataService $service): void
    {
        abort_unless(auth()->user()?->can('editar datos de ordenes en punto de venta'), 403);

        $cashRegisterId = $this->activeCashRegister?->id;
        abort_unless($cashRegisterId && $this->orderDataOrderId, 404);

        $order = Order::query()
            ->whereKey($this->orderDataOrderId)
            ->where('cash_register_id', $cashRegisterId)
            ->where('status', '!=', 'cancelada')
            ->firstOrFail();

        $this->validate([
            'orderDataCustomerName' => ['nullable', 'string', 'max:150'],
            'orderDataCustomerPhone' => ['nullable', 'string', 'max:30'],
            'orderDataCustomerAddress' => ['nullable', 'string', 'max:255'],
            'orderDataCustomerNeighborhood' => ['nullable', 'string', 'max:120'],
            'orderDataCustomerReferences' => ['nullable', 'string', 'max:255'],
            'orderDataDeliveryMethod' => ['required', 'in:contra_entrega,tarjeta,transferencia'],
            'orderDataPayments' => ['array'],
            'orderDataPayments.*.id' => ['required', 'integer'],
            'orderDataPayments.*.method' => ['required', 'in:efectivo,tarjeta,transferencia,contra_entrega'],
        ]);

        $errors = [];
        if ($order->type === 'delivery') {
            if (blank($this->orderDataCustomerAddress)) {
                $errors['orderDataCustomerAddress'] = 'La dirección es obligatoria para delivery.';
            }
            if (blank($this->orderDataCustomerNeighborhood)) {
                $errors['orderDataCustomerNeighborhood'] = 'La colonia o zona es obligatoria para delivery.';
            }
        }

        foreach ($this->orderDataPayments as $index => $payment) {
            $method = $payment['method'] ?? null;
            $amount = (float) ($payment['amount'] ?? 0);

            if ($method === 'efectivo' && (! is_numeric($payment['received_amount'] ?? null) || (float) $payment['received_amount'] < $amount)) {
                $errors["orderDataPayments.{$index}.received_amount"] = 'El efectivo recibido debe cubrir este importe.';
            }
            if ($method === 'tarjeta' && ! preg_match('/^\d{4}$/', (string) ($payment['card_last4'] ?? ''))) {
                $errors["orderDataPayments.{$index}.card_last4"] = 'Captura los últimos 4 dígitos.';
            }
            if ($method === 'transferencia' && mb_strlen(trim((string) ($payment['transfer_reference'] ?? ''))) < 4) {
                $errors["orderDataPayments.{$index}.transfer_reference"] = 'Captura una referencia de al menos 4 caracteres.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $service->update($order, $cashRegisterId, [
            'customer_name' => trim($this->orderDataCustomerName) ?: null,
            'customer_phone' => trim($this->orderDataCustomerPhone) ?: null,
            'customer_address' => trim($this->orderDataCustomerAddress) ?: null,
            'customer_neighborhood' => trim($this->orderDataCustomerNeighborhood) ?: null,
            'customer_references' => trim($this->orderDataCustomerReferences) ?: null,
            'delivery_method' => $this->orderDataDeliveryMethod,
            'payments' => $this->orderDataPayments,
        ], auth()->id());

        $this->closeOrderDataModal();
        unset($this->recentOrders, $this->pickupOrders, $this->deliveryOrders);
        $this->dispatch('notify', type: 'success', message: 'Datos de la orden actualizados sin modificar productos ni total.');
    }

    private function resetOrderDataEditor(): void
    {
        $this->resetErrorBag();
        $this->orderDataSearch = '';
        $this->orderDataOrderId = null;
        $this->orderDataCustomerName = '';
        $this->orderDataCustomerPhone = '';
        $this->orderDataCustomerAddress = '';
        $this->orderDataCustomerNeighborhood = '';
        $this->orderDataCustomerReferences = '';
        $this->orderDataDeliveryMethod = 'contra_entrega';
        $this->orderDataPayments = [];
        unset($this->editableOrderDataOrders);
    }

    public function openOperationsModal(string $type = 'expense'): void
    {
        abort_unless(in_array($type, ['expense', 'income', 'inventory_out'], true), 404);

        $this->authorizeOperationType($type);

        $this->resetOperationalPanelState();
        $this->resetOperationForm();
        $this->operationType = $type;
        $this->showExpenseModal = true;
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

    public function openConvertDeliveryModal(int $orderId): void
    {
        abort_unless(auth()->user()?->can('convertir pedidos a delivery en punto de venta'), 403);
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
        abort_unless(auth()->user()?->can('convertir pedidos a delivery en punto de venta'), 403);
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
        $this->dispatch('notify', type: 'success', message: "Pedido {$order->display_folio} enviado a Delivery.");
    }

    public function openPickupPayModal(int $orderId): void
    {
        abort_unless(auth()->user()?->can('cobrar pedidos en punto de venta'), 403);
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

        if ($this->moneyInCents($amount) > $this->moneyInCents($rem)) {
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
        $paidInCents = $this->paymentSumInCents($this->mesaPayments);
        if ($paidInCents <= 0 || $paidInCents > $this->moneyInCents($total)) {
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

    /**
     * Registra los pagos repartidos entre órdenes en centavos exactos (ver
     * PaymentAllocator). El cambio entregado queda en el primer tramo de cada
     * pago en efectivo para que recibido y cambio también sumen exacto.
     *
     * @param  array<int, int>  $orderCents  order_id => centavos a cubrir
     * @param  array<int, array<string, mixed>>  $payments
     */
    private function recordAllocatedPayments(array $orderCents, array $payments): void
    {
        $paymentCents = collect($payments)->map(fn (array $payment) => $this->moneyInCents($payment['amount'] ?? 0))->all();
        $changeRecorded = [];

        foreach (PaymentAllocator::allocate($orderCents, $paymentCents) as $chunk) {
            $payment = $payments[$chunk['payment']];
            $amount = $chunk['cents'] / 100;
            $isCash = $payment['method'] === 'cash';
            $change = 0.0;
            if ($isCash && ! isset($changeRecorded[$chunk['payment']])) {
                $change = round((float) ($payment['cash_change'] ?? 0), 2);
                $changeRecorded[$chunk['payment']] = true;
            }

            OrderPayment::create([
                'order_id' => $chunk['target'],
                'method' => $this->mapPaymentMethod($payment['method']),
                'amount' => $amount,
                'received_amount' => $isCash ? round($amount + $change, 2) : null,
                'change_amount' => $isCash ? $change : null,
                'card_last4' => $payment['method'] === 'card' ? ($payment['card_last4'] ?? null) : null,
                'transfer_reference' => $payment['method'] === 'transfer' ? ($payment['transfer_ref'] ?? null) : null,
            ]);
        }
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
        $paidInCents = $this->paymentSumInCents($this->mesaPayments);
        $mesaTotalInCents = $this->moneyInCents($mesaTotal);

        if ($paidInCents !== $mesaTotalInCents) {
            $message = $paidInCents > $mesaTotalInCents
                ? 'El monto pagado no puede superar el total de la cuenta.'
                : 'El monto es insuficiente.';
            $this->dispatch('notify', type: 'warning', message: $message);

            return;
        }

        $this->recordAllocatedPayments(
            $orders->mapWithKeys(fn (Order $order) => [$order->id => $this->moneyInCents($order->total)])->all(),
            $this->mesaPayments,
        );
        foreach ($orders as $order) {
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
                trackingUrl: $this->mesaTrackingUrl($orders),
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

        $paidInCents = $this->paymentSumInCents($this->mesaPayments);
        $accountTotalInCents = $this->moneyInCents($accountTotal);
        if ($paidInCents !== $accountTotalInCents) {
            $message = $paidInCents > $accountTotalInCents
                ? 'El monto pagado no puede superar el total de la cuenta.'
                : 'El monto es insuficiente.';
            $this->dispatch('notify', type: 'warning', message: $message);

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

        $this->recordAllocatedPayments(
            PaymentAllocator::toCentsSummingTo($orderAmounts, $this->moneyInCents($accountTotal)),
            $this->mesaPayments,
        );

        // Mark account paid
        $splitData[$this->mesaSplitAccountIdx]['paid'] = true;
        $splitData[$this->mesaSplitAccountIdx]['payments'] = $this->mesaPayments;
        $splitData[$this->mesaSplitAccountIdx]['paid_at'] = now()->toIso8601String();
        $splitData[$this->mesaSplitAccountIdx]['paid_by'] = auth()->id();
        $splitData[$this->mesaSplitAccountIdx]['tracking_order_id'] = array_key_first($orderAmounts);
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
                trackingUrl: $this->mesaTrackingUrl(
                    Order::whereIn('id', array_keys($orderAmounts))->get()
                ),
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
        ?string $trackingUrl = null,
    ): string {
        return app(ThermalTicketRenderer::class)->renderMesaAccount(
            $mesa,
            $accountLabel,
            $items,
            $total,
            $payments,
            $assignment,
            $cashierName,
            trackingUrl: $trackingUrl,
        );

        $appName = config('app.name');
        $now = BusinessTime::format(BusinessTime::now());
        $waiterName = $assignment?->waiter?->name ?? '—';
        $openedAt = $assignment ? BusinessTime::format($assignment->assigned_at) : '—';

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

    private function mesaTrackingUrl($orders): ?string
    {
        $order = collect($orders)->first();

        return $order instanceof Order
            ? route('kiosk.track', $order->ensurePublicToken())
            : null;
    }

    public function addPickupPayment(): void
    {
        // Si no ingresaron monto, usar el restante exacto
        $order = Order::where('cash_register_id', $this->activeCashRegister?->id)
            ->find($this->pickupPayOrderId);
        $paid = collect($this->pickupPayments)->sum('amount');
        // Contra el saldo vivo: una orden que ya recibió un pago parcial sólo
        // debe cobrar lo que falta, no el total otra vez.
        $rem = $order ? max(0, $order->balance_due - $paid) : 0;
        $amount = (float) $this->pickupPayAmount ?: $rem;
        if ($amount <= 0) {
            return;
        }

        if ($this->moneyInCents($amount) > $this->moneyInCents($rem)) {
            $this->dispatch('notify', type: 'warning', message: 'El pago no puede superar el saldo pendiente.');

            return;
        }

        $amount = $this->moneyInCents($amount) / 100;

        $payment = ['method' => $this->pickupPayMethod, 'amount' => $amount];

        if ($this->pickupPayMethod === 'cash') {
            $received = (float) $this->pickupPayReceived;
            // Vacío = pago exacto. Menor al monto = el cajón quedaría corto.
            if ($received > 0 && $this->moneyInCents($received) < $this->moneyInCents($amount)) {
                $message = 'El efectivo recibido ($'.number_format($received, 2).') no cubre el monto ($'.number_format($amount, 2).').';
                $this->addError('pickupPayReceived', $message);
                $this->dispatch('notify', type: 'warning', message: $message);

                return;
            }
            $payment['cash_received'] = $received > 0 ? $received : $amount;
            $payment['cash_change'] = max(0, round(($received > 0 ? $received : $amount) - $amount, 2));
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
        abort_unless(auth()->user()?->can('cobrar pedidos en punto de venta'), 403);

        // Validar y registrar bajo bloqueo: dos pestañas cobrando la misma
        // orden a la vez no pueden registrar el pago dos veces.
        $result = DB::transaction(function (): Order|string|null {
            $order = Order::with(['items', 'payments', 'refunds'])
                ->where('cash_register_id', $this->activeCashRegister?->id)
                ->lockForUpdate()
                ->find($this->pickupPayOrderId);
            if (! $order) {
                return null;
            }

            $isPayableArea = $order->source === 'kiosk'
                || in_array($order->type, ['pick_up', 'ventanilla', 'delivery', 'mesa'], true);

            if (! $isPayableArea || $order->status !== 'lista') {
                return 'El pedido debe estar listo antes de cobrarlo.';
            }

            $balanceDue = $order->balance_due;
            if ($balanceDue <= 0.009) {
                return 'Este pedido ya no tiene saldo pendiente.';
            }

            $paidInCents = $this->paymentSumInCents($this->pickupPayments);
            $totalInCents = $this->moneyInCents($balanceDue);
            if ($paidInCents !== $totalInCents) {
                return $paidInCents > $totalInCents
                    ? 'El monto pagado no puede superar el saldo pendiente del pedido.'
                    : 'El monto es insuficiente.';
            }

            foreach ($this->pickupPayments as $p) {
                $isCash = $p['method'] === 'cash';
                OrderPayment::create([
                    'order_id' => $order->id,
                    'method' => $this->mapPaymentMethod($p['method']),
                    'amount' => $p['amount'],
                    'received_amount' => $isCash ? ($p['cash_received'] ?? $p['amount']) : null,
                    'change_amount' => $isCash ? ($p['cash_change'] ?? 0) : null,
                    'card_last4' => $p['method'] === 'card' ? (($p['card_last4'] ?? '') ?: null) : null,
                    'transfer_reference' => $p['method'] === 'transfer' ? (($p['transfer_ref'] ?? '') ?: null) : null,
                ]);
            }

            $order->update(['status' => 'pagada', 'paid_at' => now()]);
            app(ManualDeliveryAccountingService::class)->syncProvision($order);

            return $order;
        });

        if (! $result instanceof Order) {
            if (is_string($result)) {
                $this->dispatch('notify', type: 'warning', message: $result);
            }

            return;
        }
        $order = $result;

        $mesaWasReleased = false;
        if ($order->mesa_id) {
            $mesaWasReleased = $this->releaseMesaIfSettled((int) $order->mesa_id);
        }

        $this->showPickupPayModal = false;
        $this->pickupPayOrderId = null;
        $this->pickupPayments = [];
        $this->dispatch('pos-orders-changed');
        unset(
            $this->toolbarPendingCounts,
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
            ? "Orden {$order->display_folio} cobrada. Era la última nota y la mesa quedó disponible."
            : "Orden {$order->display_folio} cobrada.";
        $this->dispatch('notify', type: 'success', message: $message);

        // La primera impresión forma parte del cobro y no es una reimpresión.
        $this->dispatchOrderTicketPreview($order);
    }

    // ─── Kitchen panel ────────────────────────────────────────────────────────

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
        $requiredPermission = $nextStatus === 'en_preparacion'
            ? 'iniciar preparacion en punto de venta'
            : 'marcar pedidos listos en punto de venta';
        abort_unless(auth()->user()?->can($requiredPermission), 403);
        $order->update(['status' => $nextStatus]);
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
            $this->toolbarPendingCounts,
        );

        // El panel de cocina es un componente hijo y no se entera de que el
        // padre cambió una orden: este evento es su única señal para recargar.
        $this->dispatch('pos-orders-changed');

        if ($nextStatus === 'en_preparacion') {
            $this->dispatch('pos-reprint-show-cocina',
                html_cliente: '',
                html_cocina: $this->buildKitchenTicketHtml($order),
            );
        }

        $message = $nextStatus === 'en_preparacion'
            ? "Orden {$order->display_folio} enviada a cocina."
            : "Orden {$order->display_folio} marcada como lista.";
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
            'refunds',
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

    public function openActiveMesaAccountTicket(int $serviceId): void
    {
        abort_unless(auth()->user()?->can('reimprimir tickets'), 403);

        $service = MesaService::query()
            ->where('cash_register_id', $this->activeCashRegister?->id)
            ->where('status', 'en_cuenta')
            ->with([
                'primaryMesa.area',
                'primaryMesa.currentAssignment.waiter',
                'assignments' => fn ($query) => $query
                    ->with('waiter')
                    ->whereNull('released_at')
                    ->latest('assigned_at'),
                'orders' => fn ($query) => $query
                    ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada'])
                    ->with(['items', 'payments'])
                    ->oldest('created_at'),
            ])
            ->findOrFail($serviceId);

        $mesa = $service->primaryMesa;
        $items = $service->orders->flatMap(fn (Order $order) => $order->items
            ->reject(fn (OrderItem $item) => (bool) $item->is_cancelled)
            ->map(fn (OrderItem $item) => [
                'qty' => (float) $item->quantity,
                'name' => $item->product_name,
                'subtotal' => (float) $item->subtotal,
            ]))->values()->all();

        if (! $mesa || $items === []) {
            $this->dispatch('notify', type: 'warning', message: 'La cuenta vigente no tiene productos disponibles para imprimir.');

            return;
        }

        $trackingOrder = $service->orders->first();
        $assignment = $service->assignments->first() ?? $mesa->currentAssignment;

        $this->dispatch('pos-reprint-show',
            html_cliente: app(ThermalTicketRenderer::class)->renderMesaAccount(
                mesa: $mesa,
                accountLabel: $service->service_label ?: $mesa->display_name,
                items: $items,
                total: (float) $service->orders->sum('total'),
                payments: $service->orders->flatMap->payments->values()->all(),
                assignment: $assignment,
                cashierName: auth()->user()->name,
                autoPrint: false,
                trackingUrl: $trackingOrder instanceof Order
                    ? route('kiosk.track', $trackingOrder->ensurePublicToken())
                    : null,
            ),
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
        $now = BusinessTime::format(BusinessTime::now());
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
  <div class="center small">Pedido {$order->display_folio} &mdash; {$typeLabel}</div>
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
        $now = BusinessTime::format(BusinessTime::now());
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
  <div class="center small">Pedido {$order->display_folio} &mdash; {$typeLabel}</div>
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

    // ──────────────────────────────────────────────────────────────────────────

    public function render()
    {
        return view('livewire.pos.point-of-sale')
            ->layout('layouts.pos');
    }
}
