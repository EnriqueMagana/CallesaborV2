<?php

namespace App\Livewire\Pos\Panels;

use App\Models\CashRegister;
use App\Models\Order;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Diferencias pendientes de cobro en ventanilla o para recoger.
 * Delivery se resuelve exclusivamente dentro de su propio flujo operativo.
 */
class BalancesPanel extends Component
{
    public bool $loaded = false;

    public string $search = '';

    #[On('pos-open-balances')]
    public function open(): void
    {
        abort_unless(auth()->user()?->can('ver pedidos en punto de venta'), 403);
        $this->loaded = true;
        $this->search = '';
        unset($this->orders);
    }

    #[On('pos-panels-closed')]
    public function close(): void
    {
        $this->loaded = false;
        unset($this->orders);
    }

    #[On('pos-orders-changed')]
    public function refreshOrders(): void
    {
        unset($this->orders);
    }

    public function updatedSearch(): void
    {
        unset($this->orders);
    }

    #[Computed]
    public function activeCashRegister(): ?CashRegister
    {
        return CashRegister::where('is_open', true)->latest('opened_at')->first();
    }

    #[Computed]
    public function orders(): Collection
    {
        $registerId = $this->activeCashRegister?->id;

        if (! $this->loaded || ! $registerId) {
            return collect();
        }

        $search = trim($this->search);

        return Order::query()
            ->with(['items' => fn ($query) => $query->where('is_cancelled', false), 'payments', 'refunds'])
            ->where('cash_register_id', $registerId)
            ->withRegisterPendingBalance()
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('folio', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%")
                ->orWhere('customer_phone', 'like', "%{$search}%")))
            ->oldest()
            ->get();
    }

    /** @return array{count: int, due: float} */
    #[Computed]
    public function summary(): array
    {
        return [
            'count' => $this->orders->count(),
            'due' => round((float) $this->orders->sum(fn (Order $order) => $order->uncovered_amount), 2),
        ];
    }

    public function render()
    {
        return view('livewire.pos.panels.balances-panel');
    }
}
