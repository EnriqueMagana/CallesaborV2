<?php

namespace App\Livewire\Orders;

use App\Models\CashRegister;
use App\Models\Order;
use App\Models\OrderChangeRequest;
use App\Services\OrderChangeRequestService;
use App\Support\OrderChangeDraft;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

class OrderChangeRequestWizard extends Component
{
    public Order $order;

    public int $step = 1;

    public string $scope = '';

    public array $requestItems = [];

    public string $reasonCode = '';

    public string $reasonDetail = '';

    public string $customerConfirmed = '';

    public string $preparationStage = '';

    public string $inventoryDisposition = '';

    public string $newPaymentMethod = '';

    public string $previousPaymentReceived = '';

    public string $paymentCashReceived = '';

    public string $paymentCardLast4 = '';

    public string $paymentTransferReference = '';

    public string $newAddress = '';

    public string $newNeighborhood = '';

    public string $newReferences = '';

    public string $newPhone = '';

    public bool $updateCustomerProfile = false;

    public string $source = 'detail';

    public function mount(Order $order): void
    {
        abort_unless(auth()->user()?->can('ver ordenes'), 403);
        $activeRegisterId = CashRegister::where('is_open', true)->latest('opened_at')->value('id');
        abort_unless($activeRegisterId && $order->cash_register_id === (int) $activeRegisterId, 404);

        $this->order = $order->load(['customer', 'items' => fn ($query) => $query->where('is_cancelled', false)->with(['addons', 'ingredients']), 'changeRequests', 'payments', 'refunds', 'deliveryAssignment']);
        abort_unless($this->canRequestCancellation || $this->canRequestModification || $this->canRequestPaymentChange || $this->canRequestAddressChange, 403);
        abort_if($this->order->changeRequests->contains('status', OrderChangeRequest::STATUS_PENDING), 409, 'Esta orden ya tiene una solicitud pendiente.');
        abort_unless(
            (in_array($this->order->status, ['pendiente', 'en_preparacion', 'lista'], true)
                && ($this->order->payments->isEmpty() || $this->isPendingCashOnDelivery))
                || $this->isPaidOrder
                || $this->canRequestAddressChange,
            409,
            'La orden ya no admite solicitudes de cambio.'
        );

        $this->source = request()->query('source') === 'list' ? 'list' : 'detail';
        $this->preparationStage = match ($this->order->status) {
            'en_preparacion' => 'in_progress',
            'lista', 'pagada' => 'ready',
            default => 'not_started',
        };
        $this->newAddress = (string) $this->order->customer_address;
        $this->newNeighborhood = (string) $this->order->customer_neighborhood;
        $this->newReferences = (string) $this->order->customer_references;
        $this->newPhone = (string) $this->order->customer_phone;

        $requestedScope = request()->query('scope');
        if (in_array($requestedScope, ['full', 'partial', 'adjustment', 'payment', 'address'], true) && $this->canUseScope($requestedScope)) {
            $this->chooseScope($requestedScope);
        }
    }

    #[Computed]
    public function canRequestCancellation(): bool
    {
        return auth()->user()?->can('solicitar cancelacion de ordenes') ?? false;
    }

    #[Computed]
    public function canRequestModification(): bool
    {
        return auth()->user()?->can('solicitar modificacion de ordenes') ?? false;
    }

    #[Computed]
    public function canRequestPartialCancellation(): bool
    {
        return $this->canRequestCancellation || $this->canRequestModification;
    }

    #[Computed]
    public function canRequestPaymentChange(): bool
    {
        return (auth()->user()?->can('solicitar cambio de metodo de pago') ?? false)
            && $this->order->type === 'delivery'
            && $this->isPaidOrder
            && $this->order->payments->count() === 1
            && $this->order->refunds->isEmpty();
    }

    #[Computed]
    public function canRequestAddressChange(): bool
    {
        return (auth()->user()?->can('solicitar cambio de direccion') ?? false)
            && $this->order->type === 'delivery'
            && in_array($this->order->status, ['pendiente', 'en_preparacion', 'lista', 'pagada'], true)
            && $this->order->deliveryAssignment?->status !== 'entregado';
    }

    #[Computed]
    public function proposedTotal(): float
    {
        return round(collect($this->requestItems)->sum(
            fn (array $line) => (float) ($line['unit_subtotal'] ?? 0) * max(0, (int) ($line['quantity'] ?? 0))
        ), 2);
    }

    #[Computed]
    public function isPaidOrder(): bool
    {
        return $this->order->status === 'pagada' && $this->order->payments->isNotEmpty();
    }

    /**
     * Dinero ya recibido por la orden. Es lo que decide si hay que devolver o
     * cobrar, sin importar si el estado es "pagada" o sigue operativo.
     */
    #[Computed]
    public function netPaidAmount(): float
    {
        return $this->order->net_paid_amount;
    }

    #[Computed]
    public function refundAmount(): float
    {
        if ($this->netPaidAmount <= 0.009 || ! in_array($this->scope, ['full', 'partial', 'adjustment'], true)) {
            return 0;
        }

        $target = $this->scope === 'full' ? 0 : $this->proposedTotal;

        return max(0, round($this->netPaidAmount - $target, 2));
    }

    #[Computed]
    public function hasProductReductions(): bool
    {
        if ($this->scope === 'full') {
            return true;
        }

        return collect($this->requestItems)->contains(
            fn (array $line) => ($line['kind'] ?? null) !== 'new'
                && (int) ($line['quantity'] ?? 0) < (int) ($line['original_quantity'] ?? 0)
        );
    }

    /**
     * Lo que la orden sube por encima del dinero real recibido. No se cobra al
     * autorizar: queda pendiente para el cajero o el repartidor.
     */
    #[Computed]
    public function pendingBalance(): float
    {
        if ($this->netPaidAmount <= 0.009 || ! in_array($this->scope, ['partial', 'adjustment'], true)) {
            return 0;
        }

        return max(0, round($this->proposedTotal - $this->netPaidAmount, 2));
    }

    #[Computed]
    public function refundAllocations(): array
    {
        $available = $this->order->payments
            ->where('is_provisional', false)
            ->groupBy('method')
            ->map(fn ($payments) => (float) $payments->sum('amount'));
        foreach ($this->order->refunds as $refund) {
            foreach ($refund->allocations ?? [] as $method => $amount) {
                $available[$method] = max(0, round((float) ($available[$method] ?? 0) - (float) $amount, 2));
            }
        }

        $amount = $this->refundAmount;
        $total = (float) $available->sum();
        $remaining = $amount;
        $allocations = [];
        $methods = $available->filter(fn ($value) => $value > 0)->keys()->values();
        foreach ($methods as $index => $method) {
            $part = $index === $methods->count() - 1
                ? $remaining
                : min((float) $available[$method], round($amount * ((float) $available[$method] / max(0.01, $total)), 2));
            if ($part > 0) {
                $allocations[$method] = $part;
                $remaining = round($remaining - $part, 2);
            }
        }

        return $allocations;
    }

    #[Computed]
    public function changeSummary(): array
    {
        if ($this->scope === 'full') {
            return ['removed' => $this->order->items->sum('quantity'), 'added' => 0, 'updated' => 0];
        }

        return collect($this->requestItems)->reduce(function (array $summary, array $line): array {
            $before = (int) ($line['original_quantity'] ?? 0);
            $after = (int) ($line['quantity'] ?? 0);
            if (($line['kind'] ?? null) === 'new') {
                $summary['added'] += $after;
            } elseif ($after === 0) {
                $summary['removed'] += $before;
            } elseif ($after !== $before) {
                $summary['updated']++;
            }

            return $summary;
        }, ['removed' => 0, 'added' => 0, 'updated' => 0]);
    }

    #[Computed]
    public function reasonOptions(): array
    {
        $options = [
            'customer_changed_mind' => ['El cliente cambió de opinión', 'bx-user-x'],
            'duplicate_order' => ['Pedido duplicado', 'bx-copy'],
            'wrong_order' => ['Pedido capturado incorrectamente', 'bx-error'],
            'out_of_stock' => ['Producto o insumo no disponible', 'bx-package'],
            'preparation_error' => ['Error durante la preparación', 'bx-restaurant'],
            'delivery_issue' => ['Problema de entrega o dirección', 'bx-cycling'],
            'payment_issue' => ['Problema con el cobro', 'bx-credit-card'],
            'other' => ['Otro motivo', 'bx-message-square-detail'],
        ];

        return match ($this->scope) {
            'payment' => collect($options)->only(['payment_issue', 'wrong_order', 'customer_changed_mind', 'other'])->all(),
            'address' => collect($options)->only(['delivery_issue', 'wrong_order', 'customer_changed_mind', 'other'])->all(),
            default => $options,
        };
    }

    public function chooseScope(string $scope): void
    {
        abort_unless($this->canUseScope($scope), 403);
        $this->resetValidation();
        $this->scope = $scope;
        $this->reasonCode = '';
        $this->reasonDetail = '';

        // Agregar y quitar productos se hace en el editor (mini POS); el wizard
        // sólo recibe su borrador para pedir el motivo y enviarlo.
        if ($scope === 'adjustment') {
            $draft = OrderChangeDraft::load($this->order);
            if ($draft === null) {
                $this->redirectRoute('app.ordenes.productos', ['order' => $this->order, 'source' => $this->source]);

                return;
            }
            $this->requestItems = $draft;
            $this->step = 2;

            return;
        }

        $this->requestItems = $scope === 'partial' ? $this->originalLines() : [];
        if ($scope === 'payment') {
            $this->newPaymentMethod = '';
            $this->previousPaymentReceived = '';
            $this->paymentCashReceived = number_format((float) $this->order->total, 2, '.', '');
            $this->paymentCardLast4 = '';
            $this->paymentTransferReference = '';
        }
        $this->step = 2;
    }

    public function nextStep(): void
    {
        if ($this->step === 1) {
            $this->validateScope();
            $this->step = 2;

            return;
        }

        if ($this->step === 2) {
            $this->validateDetails();
            $this->step = 3;
        }
    }

    public function previousStep(): void
    {
        $this->resetValidation();
        $this->step = max(1, $this->step - 1);
    }

    public function selectReason(string $reasonCode): void
    {
        abort_unless(array_key_exists($reasonCode, $this->reasonOptions), 422);
        $this->reasonCode = $reasonCode;
        $this->resetValidation('reasonCode');
    }

    public function adjustRequestItem(int $index, int $delta): void
    {
        // En un ajuste las cantidades se editan en el editor de productos.
        abort_unless($this->scope === 'partial' && $this->canUseScope($this->scope) && isset($this->requestItems[$index]), 403);
        $maximum = $this->scope === 'partial'
            ? (int) $this->requestItems[$index]['original_quantity']
            : 99;
        $this->requestItems[$index]['quantity'] = max(0, min($maximum, (int) $this->requestItems[$index]['quantity'] + $delta));
        if ($this->requestItems[$index]['kind'] === 'new' && $this->requestItems[$index]['quantity'] === 0) {
            array_splice($this->requestItems, $index, 1);
        }
        $this->forgetMoneyCache();
    }

    private function forgetMoneyCache(): void
    {
        unset($this->proposedTotal, $this->changeSummary, $this->refundAmount, $this->refundAllocations, $this->pendingBalance, $this->hasProductReductions);
    }
    public function submit(OrderChangeRequestService $service)
    {
        $this->validateScope();
        $this->validateDetails();

        $type = match ($this->scope) {
            'full' => OrderChangeRequest::TYPE_CANCELLATION,
            'payment' => OrderChangeRequest::TYPE_PAYMENT_CHANGE,
            'address' => OrderChangeRequest::TYPE_ADDRESS_CHANGE,
            default => OrderChangeRequest::TYPE_MODIFICATION,
        };
        $reasonLabel = $this->reasonOptions[$this->reasonCode][0];
        $reason = filled($this->reasonDetail) ? "{$reasonLabel}: ".trim($this->reasonDetail) : $reasonLabel;

        $service->create($this->order, auth()->user(), $type, $reason, $this->requestItems, [
            'scope' => $this->scope,
            'reason_code' => $this->reasonCode,
            'customer_confirmed' => $this->customerConfirmed,
            'preparation_stage' => $this->preparationStage,
            'source' => $this->source,
            'inventory_disposition' => $this->inventoryDisposition,
            'previous_payment_received' => $this->previousPaymentReceived,
            'new_payment_method' => $this->newPaymentMethod,
            'cash_received' => $this->paymentCashReceived,
            'card_last4' => $this->paymentCardLast4,
            'transfer_reference' => $this->paymentTransferReference,
            'new_address' => $this->newAddress,
            'new_neighborhood' => $this->newNeighborhood,
            'new_references' => $this->newReferences,
            'new_phone' => $this->newPhone,
            'update_customer_profile' => $this->updateCustomerProfile,
        ]);

        OrderChangeDraft::forget($this->order);
        session()->flash('success', 'Solicitud enviada. La orden no cambiará hasta que sea autorizada.');

        return redirect()->route($this->source === 'list' ? 'app.ordenes' : 'app.ordenes.show', $this->source === 'list' ? [] : ['order' => $this->order->id]);
    }

    public function render()
    {
        return view('livewire.orders.order-change-request-wizard')->layout('layouts.app');
    }

    #[Computed]
    public function isPendingCashOnDelivery(): bool
    {
        return $this->order->type === 'delivery'
            && $this->order->delivery_method === 'contra_entrega'
            && in_array($this->order->status, ['pendiente', 'en_preparacion', 'lista'], true);
    }

    private function validateScope(): void
    {
        $this->validate(['scope' => ['required', Rule::in(['full', 'partial', 'adjustment', 'payment', 'address'])]]);
        abort_unless($this->canUseScope($this->scope), 403);
    }

    private function validateDetails(): void
    {
        $rules = [
            'reasonCode' => ['required', Rule::in(array_keys($this->reasonOptions))],
            'customerConfirmed' => ['required', Rule::in(['yes', 'no', 'not_applicable'])],
            'preparationStage' => ['required', Rule::in(['not_started', 'in_progress', 'ready', 'unknown'])],
            'reasonDetail' => ['nullable', 'string', 'max:1000'],
        ];
        if ($this->reasonCode === 'other') {
            $rules['reasonDetail'] = ['required', 'string', 'min:10', 'max:1000'];
        }
        // Sólo se pregunta por el destino del producto cuando algo sale de la
        // orden. Un ajuste que únicamente agrega no tiene nada que reintegrar.
        if ($this->netPaidAmount > 0.009
            && in_array($this->scope, ['full', 'partial', 'adjustment'], true)
            && ($this->pendingBalance <= 0.009 || $this->hasProductReductions)) {
            $rules['inventoryDisposition'] = ['required', Rule::in(['restock', 'waste', 'not_applicable'])];
        }
        if ($this->scope === 'payment') {
            $rules['previousPaymentReceived'] = ['required', Rule::in(['no'])];
            $rules['newPaymentMethod'] = ['required', Rule::in(['cash', 'card', 'transfer'])];
            if ($this->newPaymentMethod === 'cash') {
                $rules['paymentCashReceived'] = ['required', 'numeric', 'min:'.(float) $this->order->total];
            } elseif ($this->newPaymentMethod === 'card') {
                $rules['paymentCardLast4'] = ['required', 'digits:4'];
            } elseif ($this->newPaymentMethod === 'transfer') {
                $rules['paymentTransferReference'] = ['required', 'string', 'min:4', 'max:120'];
            }
        }
        if ($this->scope === 'address') {
            $rules['newAddress'] = ['required', 'string', 'min:5', 'max:255'];
            $rules['newNeighborhood'] = ['required', 'string', 'min:2', 'max:120'];
            $rules['newReferences'] = ['nullable', 'string', 'max:255'];
            $rules['newPhone'] = ['nullable', 'string', 'max:30'];
            $rules['updateCustomerProfile'] = ['boolean'];
        }
        $this->validate($rules);

        if (in_array($this->scope, ['partial', 'adjustment'], true)) {
            if ($this->proposedTotal <= 0) {
                $this->addError('requestItems', 'No dejes la orden vacía. Para retirar todo, selecciona cancelación total.');
            }
            $summary = $this->changeSummary;
            if ($summary['removed'] === 0 && $summary['added'] === 0 && $summary['updated'] === 0) {
                $this->addError('requestItems', 'Ajusta una cantidad, retira un artículo o agrega uno nuevo.');
            }
            if ($this->order->status === 'cancelada') {
                $this->addError('requestItems', 'Una orden cancelada no admite modificaciones.');
            }
            if ($this->getErrorBag()->has('requestItems')) {
                throw ValidationException::withMessages([
                    'requestItems' => $this->getErrorBag()->first('requestItems'),
                ]);
            }
        }
    }

    private function canUseScope(string $scope): bool
    {
        return match ($scope) {
            'full' => $this->canRequestCancellation,
            'partial' => $this->canRequestPartialCancellation,
            'adjustment' => $this->canRequestModification,
            'payment' => $this->canRequestPaymentChange,
            'address' => $this->canRequestAddressChange,
            default => false,
        };
    }

    private function originalLines(): array
    {
        return OrderChangeDraft::linesFromOrder($this->order);
    }
}
