<?php

namespace App\Livewire\Pos\Concerns;

use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Discount;
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
use App\Models\User;
use App\Services\DeliveryModulePolicy;
use App\Services\DeliveryWorkflow;
use App\Services\DiscountPricingService;
use App\Services\InventoryService;
use App\Services\ManualDeliveryAccountingService;
use App\Services\MesaServiceManager;
use App\Services\OrderOperationalDataService;
use App\Services\PromotionPricingService;
use App\Services\ThermalTicketRenderer;
use App\Support\BusinessTime;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use ManagesQuotations;

/**
 * Cobro: pagos, tipo de orden y cierre de la venta.
 * 
 * Desde que el cajero abre el modal de cobro hasta que la orden queda persistida.
 * La autoridad sobre importes vive aqui: el cliente puede anticipar el resultado
 * visual, pero el total que se cobra se calcula en este archivo.
 */
trait ManagesCheckout
{
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
        // El catálogo es un componente hijo: recibe `orderType` como propiedad
        // reactiva (recalcula sus promociones en el mismo request) y este evento
        // para volver a la pestaña de productos, porque las promociones del modo
        // anterior pueden no existir en el nuevo.
        $this->dispatch('pos-order-type-changed');
        unset($this->promotionOpportunities);
        $this->saveCart();
        $this->payments = [];
        $this->payAmount = number_format($this->cartTotal, 2, '.', '');
        unset($this->paidTotal, $this->paymentRemaining);
    }

    public function addPayment(): void
    {
        $amountInCents = $this->moneyInCents($this->payAmount);
        $remainingInCents = max(
            0,
            $this->moneyInCents($this->cartTotal) - $this->paymentSumInCents($this->payments),
        );

        if ($amountInCents <= 0) {
            $this->dispatch('notify', type: 'warning', message: 'Ingresa un monto válido.');

            return;
        }

        if ($amountInCents > $remainingInCents) {
            $this->dispatch('notify', type: 'warning', message: 'El pago no puede superar el saldo pendiente.');

            return;
        }

        $amount = $amountInCents / 100;

        $payment = [
            'method' => $this->payMethod,
            'amount' => $amount,
        ];

        if ($this->payMethod === 'cash') {
            $received = (float) $this->payCashReceived;
            // Vacío = pago exacto. Menor al monto = el cajón quedaría corto.
            if ($received > 0 && $this->moneyInCents($received) < $amountInCents) {
                $message = 'El efectivo recibido ($'.number_format($received, 2).') no cubre el monto ($'.number_format($amount, 2).').';
                $this->addError('payCashReceived', $message);
                $this->dispatch('notify', type: 'warning', message: $message);

                return;
            }
            $payment['cash_received'] = $received > 0 ? $received : $amount;
            $payment['cash_change'] = max(0, round(($received > 0 ? $received : $amount) - $amount, 2));
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

    private function moneyInCents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function paymentSumInCents(array $payments): int
    {
        return (int) collect($payments)->sum(
            fn ($payment) => $this->moneyInCents($payment['amount'] ?? 0),
        );
    }

    private function mapDeliveryMethodForStorage(string $method): string
    {
        return match ($method) {
            'cash' => 'contra_entrega',
            'card' => 'tarjeta',
            'transfer' => 'transferencia',
            'contra_entrega', 'tarjeta', 'transferencia' => $method,
            default => 'contra_entrega',
        };
    }

    public function submitOrder(
        DeliveryModulePolicy $deliveryPolicy,
        ManualDeliveryAccountingService $manualAccounting,
    ): void {
        abort_unless(auth()->user()?->can('crear ventas en punto de venta'), 403);
        if (empty($this->cart)) {
            return;
        }
        if (! $this->cartContentsAreValid()) {
            return;
        }

        $isContraEntrega = $this->orderType === 'delivery' && $this->deliveryMethod === 'contra_entrega';

        if ($this->orderType === 'delivery' && ! $isContraEntrega) {
            $this->prepareDeliveryAdvancePayment();
        }

        if (! $isContraEntrega) {
            $paidInCents = $this->paymentSumInCents($this->payments);
            $totalInCents = $this->moneyInCents($this->cartTotal);
            if ($paidInCents < $totalInCents) {
                $this->dispatch('notify', type: 'warning', message: 'El monto pagado es insuficiente.');

                return;
            }

            if ($paidInCents > $totalInCents) {
                $this->dispatch('notify', type: 'warning', message: 'El monto pagado no puede superar el total del pedido.');

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

            // persistOrder recalcula promociones: si el total guardado ya no es lo
            // que se cobró, se revierte la venta completa en vez de guardarla
            // descuadrada.
            if (! $isContraEntrega && $this->paymentSumInCents($this->payments) !== $this->moneyInCents($order->total)) {
                throw ValidationException::withMessages([
                    'payments' => 'El total cambió al confirmar (promociones o descuentos). Revisa el cobro e intenta de nuevo.',
                ]);
            }

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
        abort_unless(auth()->user()?->can('crear ventas en punto de venta'), 403);
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
        abort_unless(auth()->user()?->can('crear ventas en punto de venta'), 403);
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

    public function updatedDeliveryMethod(): void
    {
        if (in_array($this->deliveryMethod, ['cash', 'card', 'transfer'], true)) {
            $this->payMethod = $this->deliveryMethod;
        }

        $this->payments = [];
        $this->payAmount = $this->deliveryMethod === 'contra_entrega'
            ? ''
            : number_format($this->cartTotal, 2, '.', '');
        $this->payCashReceived = '';
        $this->payCardLast4 = '';
        $this->payTransferRef = '';
        unset($this->paidTotal, $this->paymentRemaining);
    }

    private function prepareDeliveryAdvancePayment(): void
    {
        if (! in_array($this->deliveryMethod, ['cash', 'card', 'transfer'], true)) {
            return;
        }

        $this->payMethod = $this->deliveryMethod;
        $existingPayment = count($this->payments) === 1
            && ($this->payments[0]['method'] ?? null) === $this->deliveryMethod
                ? $this->payments[0]
                : [];
        $payment = array_merge($existingPayment, [
            'method' => $this->deliveryMethod,
            'amount' => round($this->cartTotal, 2),
        ]);

        if ($this->deliveryMethod === 'cash') {
            $payment['cash_received'] = round($this->cartTotal, 2);
            $payment['cash_change'] = 0;
        } elseif ($this->deliveryMethod === 'card') {
            if (filled($this->payCardLast4) || ! array_key_exists('card_last4', $payment)) {
                $payment['card_last4'] = trim($this->payCardLast4);
            }
        } else {
            if (filled($this->payTransferRef) || ! array_key_exists('transfer_ref', $payment)) {
                $payment['transfer_ref'] = trim($this->payTransferRef);
            }
        }

        $this->payments = [$payment];
        $this->payAmount = number_format($this->cartTotal, 2, '.', '');
        unset($this->paidTotal, $this->paymentRemaining);
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
                'discount_beneficiary_user_id' => $this->validDiscountEmployeeId(),
                'customer_name' => $this->customerName ?: null,
                'customer_phone' => $this->customerPhone ?: null,
                'customer_address' => $type === 'delivery' ? $this->formattedCustomerDeliveryAddress() : null,
                'customer_neighborhood' => $type === 'delivery' ? ($this->customerNeighborhood ?: null) : null,
                'customer_references' => $type === 'delivery' ? ($this->customerReferences ?: null) : null,
                'served_by' => auth()->id(),
                'type' => $type,
                'delivery_method' => $type === 'delivery' ? $this->mapDeliveryMethodForStorage($this->deliveryMethod) : null,
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
                    'discount_id' => $item['discount_id'] ?? null,
                    'product_name' => $item['product_name'],
                    'product_price' => $item['product_price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $item['subtotal'],
                    'promotion_discount' => $item['promotion_discount'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'notes' => $item['notes'] ?: null,
                    'promotion_selections' => $item['promotion_selections'] ?? null,
                    'promotion_rule_snapshot' => $item['promotion_rule_snapshot'] ?? null,
                    'discount_snapshot' => $item['discount_snapshot'] ?? null,
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
        $this->dispatch('notify', type: 'success', message: "Orden {$order->display_folio} creada.");

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
        $this->discountEmployeeId = null;
        $this->checkoutIdentityType = 'customer';
        $this->payments = [];
        $this->payMethod = 'cash';
        $this->payAmount = '';
        $this->payCashReceived = '';
        $this->payCardLast4 = '';
        $this->payTransferRef = '';
        unset($this->paidTotal, $this->paymentRemaining, $this->checkoutIdentitySearchResults);
    }

    private function syncPendingPaymentAmount(): void
    {
        $remaining = max(0, $this->cartTotal - collect($this->payments)->sum('amount'));
        $this->payAmount = number_format($remaining, 2, '.', '');
        unset($this->paidTotal, $this->paymentRemaining);
    }

    public function startNewSale(): void
    {
        $this->showOrderSuccess = false;
        $this->lastOrderId = null;
        $this->lastOrderFolio = null;
        $this->lastOrderType = null;
    }
}
