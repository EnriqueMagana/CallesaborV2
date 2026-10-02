<?php

namespace App\Livewire\Pos\Panels;

use App\Models\OnlineOrder;
use App\Services\OnlineOrderService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class OnlineOrdersPanel extends Component
{
    public bool $loaded = false;

    public string $tab = 'pending';

    public string $search = '';

    #[On('open-online-orders')]
    public function open(): void
    {
        abort_unless(auth()->user()?->can('ver pedidos en punto de venta'), 403);
        $this->loaded = true;
        unset($this->orders, $this->pendingCount);
    }

    #[On('pos-panels-closed')]
    public function close(): void
    {
        $this->loaded = false;
        unset($this->orders);
    }

    public function setTab(string $tab): void
    {
        abort_unless(in_array($tab, ['pending', 'history'], true), 422);
        $this->tab = $tab;
        unset($this->orders);
    }

    public function refreshOrders(): void
    {
        abort_unless(auth()->user()?->can('ver pedidos en punto de venta'), 403);
        unset($this->orders, $this->pendingCount);
    }

    public function confirmOrder(int $id, OnlineOrderService $service): void
    {
        abort_unless(auth()->user()?->can('ver pedidos en punto de venta'), 403);
        try {
            $order = $service->confirm(OnlineOrder::findOrFail($id), auth()->user());
        } catch (ValidationException $exception) {
            $this->addError('onlineOrder', $exception->validator->errors()->first());

            return;
        }

        unset($this->orders, $this->pendingCount);
        $this->dispatch('pos-orders-changed');
        $this->dispatch('notify', message: "Pedido {$order->display_folio} confirmado y enviado a cocina.", type: 'success');
        if (auth()->user()?->can('reimprimir tickets')) {
            $this->dispatch('online-order-print', url: route('print.cocina', $order));
        }
    }

    public function rejectOrder(int $id): void
    {
        abort_unless(auth()->user()?->can('ver pedidos en punto de venta'), 403);
        $order = OnlineOrder::query()->whereNull('order_id')->whereIn('status', ['awaiting_whatsapp', 'pending_confirmation'])->findOrFail($id);
        $order->update(['status' => 'rejected', 'rejection_reason' => 'El pedido no se concretó por WhatsApp.', 'rejected_at' => now()]);
        unset($this->orders, $this->pendingCount);
        $this->dispatch('notify', message: "{$order->display_folio} cerrada sin afectar ventas.", type: 'success');
    }

    #[Computed]
    public function orders()
    {
        if (! $this->loaded) {
            return collect();
        }

        return OnlineOrder::query()->with('order')
            ->when($this->tab === 'pending', fn ($q) => $q->where('status', 'pending_confirmation'))
            ->when($this->tab === 'history', fn ($q) => $q->whereIn('status', ['confirmed', 'rejected']))
            ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($s) => $s
                ->where('folio', 'like', '%'.trim($this->search).'%')
                ->orWhere('customer_name', 'like', '%'.trim($this->search).'%')
                ->orWhere('customer_phone', 'like', '%'.trim($this->search).'%')))
            ->latest()->limit(100)->get();
    }

    #[Computed]
    public function pendingCount(): int
    {
        if (! $this->loaded) {
            return 0;
        }

        return OnlineOrder::where('status', 'pending_confirmation')->count();
    }

    public function render()
    {
        return view('livewire.pos.panels.online-orders-panel');
    }
}
