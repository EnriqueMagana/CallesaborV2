<?php

namespace App\Livewire\Delivery;

use App\Models\CashRegister;
use App\Models\Order;
use App\Services\DeliveryWorkflow;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

class DeliveryBoard extends Component
{
    public string $tab = 'available';

    public ?int $highlightOrderId = null;

    public ?int $confirmingDeliveryOrderId = null;

    public ?string $lastCheckedAt = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('ver delivery'), 403);

        $orderId = request()->integer('order');
        if (! $orderId) {
            return;
        }

        $order = Order::query()->with('deliveryAssignment')->where('type', 'delivery')->find($orderId);
        if (! $order) {
            return;
        }

        $this->highlightOrderId = $order->id;
        $this->tab = match ($order->deliveryAssignment?->status) {
            'entregado' => 'delivered',
            'asignado' => 'assigned',
            default => 'available',
        };
    }

    #[Computed]
    public function activeRegister(): ?CashRegister
    {
        return CashRegister::query()->where('is_open', true)->latest('opened_at')->first();
    }

    #[Computed]
    public function orders(): Collection
    {
        if (! $this->activeRegister) {
            return collect();
        }

        return Order::query()
            ->with(['deliveryAssignment.driver', 'payments', 'kioskTerminal', 'items'])
            ->where('cash_register_id', $this->activeRegister->id)
            ->where('type', 'delivery')
            ->where('status', '!=', 'cancelada')
            ->oldest('created_at')
            ->get();
    }

    public function refreshBoard(): void
    {
        $this->clearComputedData();
        $this->lastCheckedAt = now()->format('H:i:s');
    }

    public function takeOrder(int $orderId, DeliveryWorkflow $workflow): void
    {
        abort_unless($this->canTakeOrders(), 403);

        try {
            $workflow->assignTo($this->currentRegisterOrder($orderId), auth()->user());
        } catch (ValidationException $exception) {
            $this->addError('delivery', $exception->validator->errors()->first('delivery'));

            return;
        }

        $this->resetErrorBag('delivery');
        $this->tab = 'assigned';
        $this->clearComputedData();
        $this->dispatch('notify', type: 'success', message: 'Pedido asignado a ti. Ya aparece en Mis pedidos.');
    }

    public function markPickedUp(int $orderId, DeliveryWorkflow $workflow): void
    {
        abort_unless($this->canCompleteOrders(), 403);

        try {
            $workflow->markPickedUp(
                $this->currentRegisterOrder($orderId),
                auth()->user(),
                auth()->user()->can('gestionar delivery'),
            );
        } catch (ValidationException $exception) {
            $this->addError('delivery', $exception->validator->errors()->first('delivery'));

            return;
        } catch (AuthorizationException) {
            abort(403);
        }

        $this->resetErrorBag('delivery');
        $this->tab = 'assigned';
        $this->clearComputedData();
        $this->dispatch('notify', type: 'success', message: 'Pedido recogido. Ya puedes iniciar la entrega.');
    }

    public function askToMarkDelivered(int $orderId): void
    {
        abort_unless($this->canCompleteOrders(), 403);
        $this->confirmingDeliveryOrderId = $orderId;
    }

    public function cancelDeliveryConfirmation(): void
    {
        $this->confirmingDeliveryOrderId = null;
    }

    public function markDelivered(DeliveryWorkflow $workflow): void
    {
        abort_unless($this->canCompleteOrders(), 403);
        abort_unless($this->confirmingDeliveryOrderId, 422);

        try {
            $workflow->markDelivered(
                $this->currentRegisterOrder($this->confirmingDeliveryOrderId),
                auth()->user(),
                auth()->user()->can('gestionar delivery'),
            );
        } catch (ValidationException $exception) {
            $this->addError('delivery', $exception->validator->errors()->first('delivery'));

            return;
        } catch (AuthorizationException) {
            abort(403);
        }

        $this->resetErrorBag('delivery');
        $this->confirmingDeliveryOrderId = null;
        $this->tab = 'delivered';
        $this->clearComputedData();
        $this->dispatch('notify', type: 'success', message: 'Entrega completada y registrada.');
    }

    public function dismissDeliveryError(): void
    {
        $this->resetErrorBag('delivery');
    }

    public function canTakeOrders(): bool
    {
        return auth()->user()?->canAny(['tomar delivery', 'gestionar delivery']) ?? false;
    }

    public function canCompleteOrders(): bool
    {
        return auth()->user()?->canAny(['entregar delivery', 'gestionar delivery']) ?? false;
    }

    public function canManageAll(): bool
    {
        return auth()->user()?->can('gestionar delivery') ?? false;
    }

    private function currentRegisterOrder(int $orderId): Order
    {
        abort_unless($this->activeRegister, 422);

        return Order::query()
            ->where('cash_register_id', $this->activeRegister->id)
            ->where('type', 'delivery')
            ->findOrFail($orderId);
    }

    private function clearComputedData(): void
    {
        unset($this->activeRegister, $this->orders);
    }

    public function render()
    {
        return view('livewire.delivery.delivery-board')->layout('layouts.app');
    }
}
