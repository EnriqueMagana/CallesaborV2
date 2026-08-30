<?php

namespace App\Livewire\Orders;

use App\Models\CashRegister;
use App\Models\Order;
use App\Models\OrderChangeRequest;
use App\Models\Product;
use App\Services\OrderChangeRequestService;
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

    public string $productSearch = '';

    public string $reasonCode = '';

    public string $reasonDetail = '';

    public string $customerConfirmed = '';

    public string $preparationStage = '';

    public string $inventoryDisposition = '';

    public string $source = 'detail';

    public function mount(Order $order): void
    {
        abort_unless(auth()->user()?->can('ver ordenes'), 403);
        abort_unless($this->canRequestCancellation || $this->canRequestModification, 403);

        $activeRegisterId = CashRegister::where('is_open', true)->latest('opened_at')->value('id');
        abort_unless($activeRegisterId && $order->cash_register_id === (int) $activeRegisterId, 404);

        $this->order = $order->load(['customer', 'items' => fn ($query) => $query->where('is_cancelled', false), 'changeRequests', 'payments', 'refunds']);
        abort_if($this->order->changeRequests->contains('status', OrderChangeRequest::STATUS_PENDING), 409, 'Esta orden ya tiene una solicitud pendiente.');
        abort_unless(
            (in_array($this->order->status, ['pendiente', 'en_preparacion', 'lista'], true) && $this->order->payments->isEmpty()) || $this->isPaidOrder,
            409,
            'La orden ya no admite solicitudes de cambio.'
        );

        $this->source = request()->query('source') === 'list' ? 'list' : 'detail';
        $this->preparationStage = match ($this->order->status) {
            'en_preparacion' => 'in_progress',
            'lista', 'pagada' => 'ready',
            default => 'not_started',
        };

        $requestedScope = request()->query('scope');
        if (in_array($requestedScope, ['full', 'partial', 'adjustment'], true) && $this->canUseScope($requestedScope)) {
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
    public function productResults()
    {
        if (! in_array($this->scope, ['partial', 'adjustment'], true)) {
            return collect();
        }

        return Product::query()
            ->where('is_active', true)
            ->when(trim($this->productSearch) !== '', fn ($query) => $query->where('name', 'like', '%'.trim($this->productSearch).'%'))
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'price']);
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

    #[Computed]
    public function refundAmount(): float
    {
        if (! $this->isPaidOrder) {
            return 0;
        }

        $target = $this->scope === 'full' ? 0 : $this->proposedTotal;

        return max(0, round((float) $this->order->total - $target, 2));
    }

    #[Computed]
    public function refundAllocations(): array
    {
        $available = $this->order->payments->groupBy('method')->map(fn ($payments) => (float) $payments->sum('amount'));
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
        return [
            'customer_changed_mind' => ['El cliente cambió de opinión', 'bx-user-x'],
            'duplicate_order' => ['Pedido duplicado', 'bx-copy'],
            'wrong_order' => ['Pedido capturado incorrectamente', 'bx-error'],
            'out_of_stock' => ['Producto o insumo no disponible', 'bx-package'],
            'preparation_error' => ['Error durante la preparación', 'bx-restaurant'],
            'delivery_issue' => ['Problema de entrega o dirección', 'bx-cycling'],
            'payment_issue' => ['Problema con el cobro', 'bx-credit-card'],
            'other' => ['Otro motivo', 'bx-message-square-detail'],
        ];
    }

    public function chooseScope(string $scope): void
    {
        abort_unless($this->canUseScope($scope), 403);
        $this->resetValidation();
        $this->scope = $scope;
        $this->productSearch = '';
        $this->requestItems = $scope === 'full' ? [] : $this->originalLines();
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
        abort_unless($this->canRequestModification && isset($this->requestItems[$index]), 403);
        $this->requestItems[$index]['quantity'] = max(0, min(99, (int) $this->requestItems[$index]['quantity'] + $delta));
        if ($this->requestItems[$index]['kind'] === 'new' && $this->requestItems[$index]['quantity'] === 0) {
            array_splice($this->requestItems, $index, 1);
        }
        unset($this->proposedTotal, $this->changeSummary);
    }

    public function addProductToRequest(int $productId): void
    {
        abort_unless($this->canRequestModification, 403);
        $product = Product::where('is_active', true)->findOrFail($productId);
        foreach ($this->requestItems as $index => $line) {
            if ($line['kind'] === 'new' && (int) $line['product_id'] === $product->id) {
                $this->adjustRequestItem($index, 1);

                return;
            }
        }

        $this->requestItems[] = [
            'key' => 'new-'.$product->id,
            'kind' => 'new',
            'product_id' => $product->id,
            'name' => $product->name,
            'quantity' => 1,
            'original_quantity' => 0,
            'unit_subtotal' => (float) $product->price,
        ];
        unset($this->proposedTotal, $this->changeSummary);
    }

    public function submit(OrderChangeRequestService $service)
    {
        $this->validateScope();
        $this->validateDetails();

        $type = $this->scope === 'full' ? OrderChangeRequest::TYPE_CANCELLATION : OrderChangeRequest::TYPE_MODIFICATION;
        $reasonLabel = $this->reasonOptions[$this->reasonCode][0];
        $reason = filled($this->reasonDetail) ? "{$reasonLabel}: ".trim($this->reasonDetail) : $reasonLabel;

        $service->create($this->order, auth()->user(), $type, $reason, $this->requestItems, [
            'scope' => $this->scope,
            'reason_code' => $this->reasonCode,
            'customer_confirmed' => $this->customerConfirmed,
            'preparation_stage' => $this->preparationStage,
            'source' => $this->source,
            'inventory_disposition' => $this->inventoryDisposition,
        ]);

        session()->flash('success', 'Solicitud enviada. La orden no cambiará hasta que sea autorizada.');

        return redirect()->route($this->source === 'list' ? 'app.ordenes' : 'app.ordenes.show', $this->source === 'list' ? [] : ['order' => $this->order->id]);
    }

    public function render()
    {
        return view('livewire.orders.order-change-request-wizard')->layout('layouts.app');
    }

    private function validateScope(): void
    {
        $this->validate(['scope' => ['required', Rule::in(['full', 'partial', 'adjustment'])]]);
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
        if ($this->isPaidOrder) {
            $rules['inventoryDisposition'] = ['required', Rule::in(['restock', 'waste', 'not_applicable'])];
        }
        $this->validate($rules);

        if ($this->scope !== 'full') {
            if ($this->proposedTotal <= 0) {
                $this->addError('requestItems', 'No dejes la orden vacía. Para retirar todo, selecciona cancelación total.');
            }
            $summary = $this->changeSummary;
            if ($summary['removed'] === 0 && $summary['added'] === 0 && $summary['updated'] === 0) {
                $this->addError('requestItems', 'Ajusta una cantidad, retira un artículo o agrega uno nuevo.');
            }
            if ($this->isPaidOrder && $this->proposedTotal > (float) $this->order->total + 0.009) {
                $this->addError('requestItems', 'En una orden pagada el nuevo total no puede ser mayor. Genera otra orden para cobrar productos adicionales.');
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
            'partial', 'adjustment' => $this->canRequestModification,
            default => false,
        };
    }

    private function originalLines(): array
    {
        return $this->order->items->map(fn ($item) => [
            'key' => 'existing-'.$item->id,
            'kind' => 'existing',
            'order_item_id' => $item->id,
            'product_id' => $item->product_id,
            'name' => $item->product_name,
            'quantity' => (int) $item->quantity,
            'original_quantity' => (int) $item->quantity,
            'unit_subtotal' => round((float) $item->subtotal / max(1, (int) $item->quantity), 2),
        ])->values()->all();
    }
}
