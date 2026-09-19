<?php

namespace App\Livewire\Pos\Panels;

use App\Models\CashRegister;
use App\Models\Order;
use App\Services\ManualDeliveryAccountingService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Saldos pendientes: órdenes que recibieron algo pero no cubren su total.
 *
 * Hay dos cobradores posibles y el panel los separa: el cajero cobra en caja
 * lo que se agregó después de pagar, y el repartidor trae el efectivo contra
 * entrega. Cobrar en caja y reimprimir se quedan en el padre (los comparten
 * otros paneles); liquidar al repartidor sólo existe aquí.
 */
class BalancesPanel extends Component
{
    public const FILTERS = ['all', 'register', 'driver'];

    public bool $loaded = false;

    public string $filter = 'all';

    public string $search = '';

    #[On('pos-open-balances')]
    public function open(): void
    {
        abort_unless(auth()->user()?->can('ver pedidos en punto de venta'), 403);
        $this->loaded = true;
        $this->filter = 'all';
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

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, self::FILTERS, true) ? $filter : 'all';
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

    /**
     * Todas las órdenes con saldo de la caja activa, sin aplicar el filtro de
     * cobrador: el resumen necesita ambos grupos.
     */
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
            ->withPendingBalance()
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('folio', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%")
                ->orWhere('customer_phone', 'like', "%{$search}%")))
            ->oldest()
            ->get();
    }

    #[Computed]
    public function visibleOrders(): Collection
    {
        return match ($this->filter) {
            'register' => $this->orders->reject(fn (Order $order) => $this->collectedByDriver($order))->values(),
            'driver' => $this->orders->filter(fn (Order $order) => $this->collectedByDriver($order))->values(),
            default => $this->orders,
        };
    }

    /**
     * @return array{count: int, due: float, register: array{count: int, due: float}, driver: array{count: int, due: float}}
     */
    #[Computed]
    public function summary(): array
    {
        [$driver, $register] = $this->orders->partition(fn (Order $order) => $this->collectedByDriver($order));
        $due = fn (Collection $orders) => round((float) $orders->sum(fn (Order $order) => $order->balance_due), 2);

        return [
            'count' => $this->orders->count(),
            'due' => $due($this->orders),
            'register' => ['count' => $register->count(), 'due' => $due($register)],
            'driver' => ['count' => $driver->count(), 'due' => $due($driver)],
        ];
    }

    /**
     * El repartidor entregó el efectivo: la provisión pasa a dinero recibido.
     */
    public function settle(int $orderId, ManualDeliveryAccountingService $manualAccounting): void
    {
        abort_unless(auth()->user()?->can('cobrar pedidos en punto de venta'), 403);

        $order = Order::query()
            ->where('cash_register_id', $this->activeCashRegister?->id)
            ->findOrFail($orderId);
        $order = $manualAccounting->settle($order);

        unset($this->orders);
        $this->dispatch('pos-balances-changed');
        $this->dispatch('notify', type: 'success', message: "Efectivo de {$order->display_folio} recibido del repartidor: $".number_format((float) $order->total, 2).'.');
    }

    public function collectedByDriver(Order $order): bool
    {
        return $order->provisional_amount > 0.009;
    }

    public function render()
    {
        return view('livewire.pos.panels.balances-panel');
    }
}
