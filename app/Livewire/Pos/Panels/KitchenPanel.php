<?php

namespace App\Livewire\Pos\Panels;

use App\Models\CashRegister;
use App\Models\Order;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Panel de flujo de preparación.
 *
 * Las acciones sobre la orden (`markKitchenReady`, `reprintKitchenOrder`) se
 * quedan en el padre porque las comparten el panel de pickup, el de delivery y
 * el de reimpresión: duplicarlas aquí crearía dos verdades sobre el estado de
 * una orden. El panel las invoca con `$parent.` y se refresca cuando el padre
 * anuncia que una orden cambió de estado.
 *
 * Nota: hoy ninguna vista abre este panel — no hay botón ni atajo que ponga
 * `panels.kitchen = true`. `open()` queda listo para cuando se le conecte un
 * disparador; mientras tanto el panel no consulta la base.
 */
class KitchenPanel extends Component
{
    public string $kitchenSearch = '';

    public bool $loaded = false;

    public function open(): void
    {
        abort_unless(auth()->user()?->can('ver pedidos en punto de venta'), 403);
        $this->loaded = true;
        unset($this->kitchenOrders);
    }

    #[On('pos-panels-closed')]
    public function close(): void
    {
        $this->loaded = false;
        unset($this->kitchenOrders, $this->kitchenPendingCount);
    }

    /**
     * El padre avisa cuando una orden cambia de estado desde cualquier panel.
     */
    #[On('pos-orders-changed')]
    public function refreshOrders(): void
    {
        unset($this->kitchenOrders, $this->kitchenPendingCount);
    }

    public function updatedKitchenSearch(): void
    {
        unset($this->kitchenOrders);
    }

    #[Computed]
    public function kitchenOrders()
    {
        if (! $this->loaded) {
            return collect();
        }

        $cashRegisterId = $this->activeCashRegister?->id;

        if (! $cashRegisterId) {
            return collect();
        }

        return Order::with([
            'items.addons',
            'items.ingredients',
            'items.product.category.printArea',
            'mesa.area',
        ])
            ->where('cash_register_id', $cashRegisterId)
            ->whereIn('status', ['pendiente', 'en_preparacion'])
            ->where(fn ($query) => $query
                ->whereIn('type', ['mesa', 'delivery'])
                ->orWhere('source', 'kiosk'))
            ->when($this->kitchenSearch, function ($q) {
                $search = $this->kitchenSearch;
                $q->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhereHas('mesa', fn ($q) => $q->where('number', 'like', "%{$search}%"));
                });
            })
            ->orderBy('created_at')
            ->get();
    }

    #[Computed]
    public function kitchenPendingCount(): int
    {
        $cashRegisterId = $this->activeCashRegister?->id;

        if (! $cashRegisterId) {
            return 0;
        }

        return Order::where('cash_register_id', $cashRegisterId)
            ->whereIn('status', ['pendiente', 'en_preparacion'])
            ->where(fn ($query) => $query
                ->whereIn('type', ['mesa', 'delivery'])
                ->orWhere('source', 'kiosk'))
            ->count();
    }

    #[Computed]
    public function activeCashRegister(): ?CashRegister
    {
        return CashRegister::where('is_open', true)->latest('opened_at')->first();
    }

    public function render()
    {
        return view('livewire.pos.panels.kitchen');
    }
}
