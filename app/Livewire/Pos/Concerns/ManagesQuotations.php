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

/**
 * Cotizaciones y borradores del punto de venta.
 * 
 * Guardar, cargar y descartar un pedido a medio armar. El estado del checkout se
 * serializa aparte del carrito para que un borrador pueda restaurarse sin
 * arrastrar pagos ni promociones ya aplicadas.
 */
trait ManagesQuotations
{
    #[Computed]
    public function quotations()
    {
        return Quotation::where('status', 'active')
            ->where('created_by', auth()->id())
            ->orderByDesc('created_at')
            ->get();
    }

    public function saveQuotation(): void
    {
        abort_unless(auth()->user()?->can('gestionar borradores en punto de venta'), 403);
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
                'discount_id' => $item->discount_id,
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
                'discount_amount' => (float) ($item->discount_amount ?? 0),
                'notes' => $item->notes ?? '',
                'addons' => $addons,
                'ingredients' => $ingredients,
                'promotion_selections' => $item->promotion_selections ?? [],
                'promotion_rule_snapshot' => $item->promotion_rule_snapshot,
                'discount_snapshot' => $item->discount_snapshot,
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
        $this->saveCart();
        $this->quotationName = $quotation->name ?? '';
        $this->quotationNotes = $quotation->notes ?? '';

        $this->activeQuotationId = $quotation->id;
        $this->showQuotationsModal = false;
        unset($this->cartTotal, $this->cartCount, $this->quotations);
        $this->dispatch('notify', type: 'info', message: "Borrador \"{$quotation->display_name}\" cargado.");
    }

    public function deleteQuotation(int $id): void
    {
        abort_unless(auth()->user()?->can('gestionar borradores en punto de venta'), 403);
        Quotation::query()
            ->where('created_by', auth()->id())
            ->findOrFail($id)
            ->delete();
        unset($this->quotations);
        $this->dispatch('notify', type: 'warning', message: 'Borrador eliminado.');
    }

    /**
     * Snapshot versionado de todos los datos editables del pedido.
     * Las partidas también se guardan en tablas relacionales para conservar
     * compatibilidad con los borradores creados antes de esta versión.
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
                'identity_type' => $this->checkoutIdentityType,
                'discount_employee_id' => $this->discountEmployeeId,
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
        $savedEmployeeId = filter_var($customer['discount_employee_id'] ?? null, FILTER_VALIDATE_INT);
        $this->discountEmployeeId = auth()->user()?->can('aplicar descuentos') && $savedEmployeeId
            && User::query()->whereKey($savedEmployeeId)->whereNull('banned_at')->exists()
                ? (int) $savedEmployeeId
                : null;
        $savedIdentityType = (string) ($customer['identity_type'] ?? '');
        $this->checkoutIdentityType = $this->discountEmployeeId
            ? 'employee'
            : ($savedIdentityType === 'employee' && auth()->user()?->can('aplicar descuentos') ? 'employee' : 'customer');

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

        unset($this->checkoutIdentitySearchResults, $this->paidTotal, $this->paymentRemaining);
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
                $item['discount_id'] = $item['discount_id'] ?? null;
                $item['discount_amount'] = (float) ($item['discount_amount'] ?? 0);
                $item['discount_snapshot'] = is_array($item['discount_snapshot'] ?? null)
                    ? $item['discount_snapshot']
                    : null;

                return $item;
            })
            ->values()
            ->all();
    }

    public function openSavedOrdersModal(): void
    {
        abort_unless(auth()->user()?->can('gestionar borradores en punto de venta'), 403);
        $this->resetOperationalPanelState();
        $this->showQuotationsModal = true;
    }
}
