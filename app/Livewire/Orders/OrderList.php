<?php

namespace App\Livewire\Orders;

use App\Models\CashRegister;
use App\Models\Order;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class OrderList extends Component
{
    use WithPagination;

    public string  $search           = '';
    public string  $statusFilter     = '';
    public string  $typeFilter       = '';
    public string  $dateFrom         = '';
    public string  $dateTo           = '';
    public ?int    $cashRegisterFilter = null;

    // ─── Cancel order modal ────────────────────────────────────────────────────
    public bool    $showCancelModal   = false;
    public ?int    $cancelOrderId     = null;
    public string  $cancelReason      = '';

    // ─── Edit status modal ─────────────────────────────────────────────────────
    public bool    $showStatusModal   = false;
    public ?int    $editStatusOrderId = null;
    public string  $editStatus        = '';

    public function updatingSearch(): void    { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingTypeFilter(): void   { $this->resetPage(); }
    public function updatingDateFrom(): void     { $this->resetPage(); }
    public function updatingDateTo(): void       { $this->resetPage(); }

    #[Computed]
    public function orders()
    {
        return Order::with(['seller', 'cashRegister', 'customer'])
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->where('id', 'like', "%{$this->search}%")
                      ->orWhere('customer_name', 'like', "%{$this->search}%")
                      ->orWhereHas('customer', fn($q) => $q->where('name', 'like', "%{$this->search}%"));
                });
            })
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->typeFilter,   fn($q) => $q->where('type', $this->typeFilter))
            ->when($this->cashRegisterFilter, fn($q) => $q->where('cash_register_id', $this->cashRegisterFilter))
            ->when($this->dateFrom, fn($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo,   fn($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->latest()
            ->paginate(15);
    }

    #[Computed]
    public function cashRegisters()
    {
        return CashRegister::orderByDesc('opened_at')->get();
    }

    // ─── Status edit ───────────────────────────────────────────────────────────

    public function openStatusModal(int $id): void
    {
        $order = Order::findOrFail($id);
        if ($order->status === 'cancelada') {
            $this->dispatch('notify', type: 'warning', message: 'No se puede cambiar el estado de una orden cancelada.');
            return;
        }
        $this->editStatusOrderId = $id;
        $this->editStatus        = $order->status;
        $this->showStatusModal   = true;
    }

    public function saveStatus(): void
    {
        $this->validate(['editStatus' => 'required|in:pendiente,en_preparacion,pagada,cancelada']);

        $order = Order::findOrFail($this->editStatusOrderId);

        $data = ['status' => $this->editStatus];
        if ($this->editStatus === 'pagada' && !$order->paid_at) {
            $data['paid_at'] = now();
        }

        $order->update($data);
        $this->showStatusModal   = false;
        $this->editStatusOrderId = null;
        unset($this->orders);
        $this->dispatch('notify', type: 'success', message: 'Estado actualizado.');
    }

    // ─── Cancel order ──────────────────────────────────────────────────────────

    public function openCancelModal(int $id): void
    {
        $this->cancelOrderId  = $id;
        $this->cancelReason   = '';
        $this->showCancelModal = true;
    }

    public function confirmCancel(): void
    {
        $this->validate(['cancelReason' => 'required|string|min:5|max:255']);

        Order::findOrFail($this->cancelOrderId)->update([
            'status'              => 'cancelada',
            'cancelled_by'        => auth()->id(),
            'cancellation_reason' => $this->cancelReason,
            'cancelled_at'        => now(),
        ]);

        $this->showCancelModal = false;
        $this->cancelOrderId   = null;
        $this->cancelReason    = '';
        unset($this->orders);
        $this->dispatch('notify', type: 'warning', message: 'Orden cancelada.');
    }

    // ─── Delete order (permission guarded in blade) ────────────────────────────

    #[On('modal-confirmed')]
    public function handleModalConfirmed(string $action, array $params = []): void
    {
        match ($action) {
            'deleteOrder' => $this->deleteOrder($params['id']),
            default       => null,
        };
    }

    public function confirmDeleteOrder(int $id): void
    {
        $this->dispatch('open-confirm',
            type: 'danger',
            title: 'Eliminar orden',
            message: 'Esta acción es permanente. ¿Seguro que deseas eliminar esta orden y todos sus ítems?',
            action: 'deleteOrder',
            params: ['id' => $id],
        );
    }

    private function deleteOrder(int $id): void
    {
        Order::findOrFail($id)->delete();
        unset($this->orders);
        $this->dispatch('notify', type: 'danger', message: 'Orden eliminada permanentemente.');
    }

    public function render()
    {
        return view('livewire.orders.order-list')
            ->layout('layouts.app');
    }
}
