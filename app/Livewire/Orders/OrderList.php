<?php

namespace App\Livewire\Orders;

use App\Models\CashRegister;
use App\Models\Order;
use App\Models\OrderChangeRequest;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
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
        $register = $this->activeCashRegister;

        return Order::with(['seller', 'cashRegister', 'customer', 'changeRequests' => fn ($q) => $q->where('status', OrderChangeRequest::STATUS_PENDING)])
            ->when($register, fn ($q) => $q->where('cash_register_id', $register->id))
            ->when(! $register, fn ($q) => $q->whereRaw('1 = 0'))
            ->when($this->search, function ($q): void {
                $rawSearch = trim($this->search);
                $hasFolioPrefix = preg_match('/^#?ORD-/i', $rawSearch) === 1;
                $folioSearch = preg_replace('/^#?ORD-/i', '', $rawSearch);
                $folioSearch = ctype_digit((string) $folioSearch)
                    ? (string) max(0, (int) $folioSearch)
                    : null;

                $q->where(function ($q) use ($rawSearch, $folioSearch, $hasFolioPrefix): void {
                    $q->where('id', 'like', "%{$rawSearch}%")
                        ->when($folioSearch !== null, fn ($query) => $hasFolioPrefix
                            ? $query->orWhere('folio', (int) $folioSearch)
                            : $query->orWhere('folio', 'like', "%{$folioSearch}%"))
                        ->orWhere('customer_name', 'like', "%{$rawSearch}%")
                        ->orWhere('customer_phone', 'like', "%{$rawSearch}%")
                        ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$rawSearch}%"));
                });
            })
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->typeFilter === 'kiosk', fn ($q) => $q->where('source', 'kiosk'))
            ->when($this->typeFilter && $this->typeFilter !== 'kiosk', function ($q): void {
                $q->where(fn ($source) => $source->whereNull('source')->orWhere('source', '!=', 'kiosk'));
                $this->typeFilter === 'ventanilla'
                    ? $q->whereIn('type', ['ventanilla', 'pick_up'])
                    : $q->where('type', $this->typeFilter);
            })
            ->when($this->cashRegisterFilter, fn ($q) => $q->where('cash_register_id', $this->cashRegisterFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->latest()->paginate(15);
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
        $nonKiosk = fn ($q) => $q->where(fn ($source) => $source->whereNull('source')->orWhere('source', '!=', 'kiosk'));
        $open = fn ($q) => $registerId ? $q->where('cash_register_id', $registerId) : $q->whereRaw('1 = 0');

        return [
            'all' => $open(Order::query())->count(),
            'ventanilla' => $open(Order::whereIn('type', ['ventanilla', 'pick_up'])->where($nonKiosk))->count(),
            'mesa' => $open(Order::where('type', 'mesa')->where($nonKiosk))->count(),
            'delivery' => $open(Order::where('type', 'delivery')->where($nonKiosk))->count(),
            'kiosk' => $open(Order::where('source', 'kiosk'))->count(),
        ];
    }

    #[Computed]
    public function statusCounts(): array
    {
        $registerId = $this->activeCashRegister?->id;
        if (! $registerId) {
            return ['pending' => 0, 'preparing' => 0, 'ready' => 0, 'completed' => 0];
        }

        $counts = Order::where('cash_register_id', $registerId)
            ->selectRaw("SUM(CASE WHEN status = 'pendiente' THEN 1 ELSE 0 END) as pending")
            ->selectRaw("SUM(CASE WHEN status = 'en_preparacion' THEN 1 ELSE 0 END) as preparing")
            ->selectRaw("SUM(CASE WHEN status = 'lista' THEN 1 ELSE 0 END) as ready")
            ->selectRaw("SUM(CASE WHEN status IN ('pagada', 'entregada') THEN 1 ELSE 0 END) as completed")
            ->first();

        return ['pending' => (int) ($counts?->pending ?? 0), 'preparing' => (int) ($counts?->preparing ?? 0), 'ready' => (int) ($counts?->ready ?? 0), 'completed' => (int) ($counts?->completed ?? 0)];
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

    public function openStatusModal(int $id): void
    {
        abort_unless(auth()->user()?->can('editar ordenes'), 403);
        $order = $this->currentRegisterOrders()->findOrFail($id);
        if (in_array($order->status, ['cancelada', 'pagada'], true)) {
            $this->dispatch('notify', type: 'warning', message: 'Esta orden ya no admite cambios de estado.');

            return;
        }
        $this->editStatusOrderId = $id;
        $this->editStatus = $order->status;
        $this->showStatusModal = true;
    }

    public function saveStatus(): void
    {
        abort_unless(auth()->user()?->can('editar ordenes'), 403);
        $this->validate(['editStatus' => 'required|in:pendiente,en_preparacion,lista,pagada']);
        $order = $this->currentRegisterOrders()->findOrFail($this->editStatusOrderId);
        $order->update(['status' => $this->editStatus] + ($this->editStatus === 'pagada' && ! $order->paid_at ? ['paid_at' => now()] : []));
        $this->reset(['showStatusModal', 'editStatusOrderId', 'editStatus']);
        unset($this->orders);
        $this->dispatch('notify', type: 'success', message: 'Estado actualizado.');
    }

    public function render()
    {
        return view('livewire.orders.order-list')->layout('layouts.app');
    }

    private function currentRegisterOrders(): Builder
    {
        $registerId = CashRegister::where('is_open', true)->latest('opened_at')->value('id');

        return Order::query()->when($registerId, fn ($q) => $q->where('cash_register_id', $registerId))->when(! $registerId, fn ($q) => $q->whereRaw('1 = 0'));
    }
}
