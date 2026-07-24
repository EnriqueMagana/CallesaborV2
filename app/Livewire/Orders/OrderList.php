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

    public string $search = '';

    public string $statusFilter = '';

    public string $typeFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public ?int $cashRegisterFilter = null;

    // ─── Cancel order modal ────────────────────────────────────────────────────
    public bool $showCancelModal = false;

    public ?int $cancelOrderId = null;

    public string $cancelReason = '';

    // ─── Edit status modal ─────────────────────────────────────────────────────
    public bool $showStatusModal = false;

    public ?int $editStatusOrderId = null;

    public string $editStatus = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('ver ordenes'), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function orders()
    {
        $activeRegister = $this->activeCashRegister;
        $query = Order::with(['seller', 'cashRegister', 'customer'])
            ->when($activeRegister, fn ($q) => $q->where('cash_register_id', $activeRegister->id))
            ->when(! $activeRegister, fn ($q) => $q->whereRaw('1 = 0'))
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->where('id', 'like', "%{$this->search}%")
                        ->orWhere('customer_name', 'like', "%{$this->search}%")
                        ->orWhere('customer_phone', 'like', "%{$this->search}%")
                        ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', "%{$this->search}%"));
                });
            })
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->typeFilter === 'kiosk', fn ($q) => $q->where('source', 'kiosk'))
            ->when($this->typeFilter && $this->typeFilter !== 'kiosk', function ($q) {
                $q->where(fn ($source) => $source->whereNull('source')->orWhere('source', '!=', 'kiosk'));

                $this->typeFilter === 'ventanilla'
                    ? $q->whereIn('type', ['ventanilla', 'pick_up'])
                    : $q->where('type', $this->typeFilter);
            })
            ->when($this->cashRegisterFilter, fn ($q) => $q->where('cash_register_id', $this->cashRegisterFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->latest()
            ->paginate(15);

        return $query;
    }

    #[Computed]
    public function activeCashRegister(): ?CashRegister
    {
        return CashRegister::where('is_open', true)->latest('opened_at')->first();
    }

    #[Computed]
    public function channelCounts(): array
    {
        $registerId = $this->activeCashRegister?->id;
        $nonKiosk = fn ($query) => $query->where(fn ($source) => $source->whereNull('source')->orWhere('source', '!=', 'kiosk'));
        $openRegister = fn ($query) => $registerId
            ? $query->where('cash_register_id', $registerId)
            : $query->whereRaw('1 = 0');

        return [
            'all' => $openRegister(Order::query())->count(),
            'ventanilla' => $openRegister(Order::whereIn('type', ['ventanilla', 'pick_up'])->where($nonKiosk))->count(),
            'mesa' => $openRegister(Order::where('type', 'mesa')->where($nonKiosk))->count(),
            'delivery' => $openRegister(Order::where('type', 'delivery')->where($nonKiosk))->count(),
            'kiosk' => $openRegister(Order::where('source', 'kiosk'))->count(),
        ];
    }

    #[Computed]
    public function statusCounts(): array
    {
        $registerId = $this->activeCashRegister?->id;

        if (! $registerId) {
            return [
                'pending' => 0,
                'preparing' => 0,
                'ready' => 0,
                'completed' => 0,
            ];
        }

        $counts = Order::query()
            ->where('cash_register_id', $registerId)
            ->selectRaw("SUM(CASE WHEN status = 'pendiente' THEN 1 ELSE 0 END) as pending")
            ->selectRaw("SUM(CASE WHEN status = 'en_preparacion' THEN 1 ELSE 0 END) as preparing")
            ->selectRaw("SUM(CASE WHEN status = 'lista' THEN 1 ELSE 0 END) as ready")
            ->selectRaw("SUM(CASE WHEN status IN ('pagada', 'entregada') THEN 1 ELSE 0 END) as completed")
            ->first();

        return [
            'pending' => (int) ($counts?->pending ?? 0),
            'preparing' => (int) ($counts?->preparing ?? 0),
            'ready' => (int) ($counts?->ready ?? 0),
            'completed' => (int) ($counts?->completed ?? 0),
        ];
    }

    public function filterByChannel(string $channel): void
    {
        abort_unless(in_array($channel, ['', 'ventanilla', 'mesa', 'delivery', 'kiosk'], true), 422);
        $this->typeFilter = $channel;
        $this->resetPage();
        unset($this->orders);
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'typeFilter', 'dateFrom', 'dateTo', 'cashRegisterFilter']);
        $this->resetPage();
        unset($this->orders);
    }

    #[Computed]
    public function cashRegisters()
    {
        return CashRegister::orderByDesc('opened_at')->get();
    }

    // ─── Status edit ───────────────────────────────────────────────────────────

    public function openStatusModal(int $id): void
    {
        abort_unless(auth()->user()?->can('editar ordenes'), 403);
        $order = Order::findOrFail($id);
        if ($order->status === 'cancelada') {
            $this->dispatch('notify', type: 'warning', message: 'No se puede cambiar el estado de una orden cancelada.');

            return;
        }
        $this->editStatusOrderId = $id;
        $this->editStatus = $order->status;
        $this->showStatusModal = true;
    }

    public function saveStatus(): void
    {
        abort_unless(auth()->user()?->can('editar ordenes'), 403);
        $this->validate(['editStatus' => 'required|in:pendiente,en_preparacion,lista,pagada,cancelada']);

        $order = Order::findOrFail($this->editStatusOrderId);

        $data = ['status' => $this->editStatus];
        if ($this->editStatus === 'pagada' && ! $order->paid_at) {
            $data['paid_at'] = now();
        }

        $order->update($data);
        $this->showStatusModal = false;
        $this->editStatusOrderId = null;
        unset($this->orders);
        $this->dispatch('notify', type: 'success', message: 'Estado actualizado.');
    }

    // ─── Cancel order ──────────────────────────────────────────────────────────

    public function openCancelModal(int $id): void
    {
        abort_unless(auth()->user()?->can('cancelar ordenes'), 403);
        $this->cancelOrderId = $id;
        $this->cancelReason = '';
        $this->showCancelModal = true;
    }

    public function confirmCancel(): void
    {
        abort_unless(auth()->user()?->can('cancelar ordenes'), 403);
        $this->validate(['cancelReason' => 'required|string|min:5|max:255']);

        Order::findOrFail($this->cancelOrderId)->update([
            'status' => 'cancelada',
            'cancelled_by' => auth()->id(),
            'cancellation_reason' => $this->cancelReason,
            'cancelled_at' => now(),
        ]);

        $this->showCancelModal = false;
        $this->cancelOrderId = null;
        $this->cancelReason = '';
        unset($this->orders);
        $this->dispatch('notify', type: 'warning', message: 'Orden cancelada.');
    }

    // ─── Delete order (permission guarded in blade) ────────────────────────────

    #[On('modal-confirmed')]
    public function handleModalConfirmed(string $action, array $params = []): void
    {
        match ($action) {
            'deleteOrder' => $this->deleteOrder($params['id']),
            default => null,
        };
    }

    public function confirmDeleteOrder(int $id): void
    {
        abort_unless(auth()->user()?->can('eliminar ordenes'), 403);
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
        abort_unless(auth()->user()?->can('eliminar ordenes'), 403);
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
