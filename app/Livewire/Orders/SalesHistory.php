<?php

namespace App\Livewire\Orders;

use App\Models\CashRegister;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class SalesHistory extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $typeFilter = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public ?int $cashRegisterId = null;
    public ?int $expandedOrderId = null;

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingTypeFilter(): void { $this->resetPage(); }
    public function updatingDateFrom(): void { $this->resetPage(); }
    public function updatingDateTo(): void { $this->resetPage(); }
    public function updatingCashRegisterId(): void { $this->resetPage(); }

    #[Computed]
    public function registers()
    {
        return CashRegister::where('is_open', false)->latest('closed_at')->get();
    }

    private function query(): Builder
    {
        return Order::query()
            ->with(['cashRegister', 'seller', 'cancelledBy', 'payments'])
            ->whereHas('cashRegister', fn ($q) => $q->where('is_open', false))
            ->when($this->cashRegisterId, fn ($q) => $q->where('cash_register_id', $this->cashRegisterId))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->typeFilter, fn ($q) => $q->where('type', $this->typeFilter))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->when($this->search, function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('id', 'like', $term)
                        ->orWhere('folio', 'like', $term)
                        ->orWhere('customer_name', 'like', $term)
                        ->orWhere('customer_phone', 'like', $term)
                        ->orWhereHas('seller', fn ($seller) => $seller->where('name', 'like', $term))
                        ->orWhereHas('cashRegister', fn ($register) => $register->where('name', 'like', $term));
                });
            });
    }

    #[Computed]
    public function orders()
    {
        return $this->query()->latest('created_at')->paginate(20);
    }

    #[Computed]
    public function summary(): array
    {
        $orders = $this->query()->get(['id', 'type', 'status', 'total']);
        return [
            'orders' => $orders->count(),
            'sales' => (float) $orders->where('status', 'pagada')->sum('total'),
            'cancelled' => $orders->where('status', 'cancelada')->count(),
            'open' => $orders->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'en_reparto'])->count(),
        ];
    }

    public function toggleOrder(int $orderId): void
    {
        $this->expandedOrderId = $this->expandedOrderId === $orderId ? null : $orderId;
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'typeFilter', 'dateFrom', 'dateTo', 'cashRegisterId']);
        $this->resetPage();
        unset($this->orders, $this->summary);
    }

    public function render()
    {
        return view('livewire.orders.sales-history')->layout('layouts.app');
    }
}
