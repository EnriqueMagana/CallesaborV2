<?php

namespace App\Livewire\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use Livewire\Attributes\On;
use Livewire\Component;

class OrderDetail extends Component
{
    public Order $order;

    // ─── Cancel item modal ─────────────────────────────────────────────────────
    public bool   $showCancelItemModal = false;
    public ?int   $cancelItemId        = null;

    // ─── Cancel order modal ────────────────────────────────────────────────────
    public bool   $showCancelModal  = false;
    public string $cancelReason     = '';

    public function mount(Order $order): void
    {
        $this->order = $order->load([
            'seller',
            'cashRegister',
            'customer',
            'cancelledBy',
            'payments',
            'items.product',
            'items.addons',
            'items.ingredients',
            'items.cancelledBy',
        ]);
    }

    // ─── Cancel single item ────────────────────────────────────────────────────

    public function openCancelItemModal(int $itemId): void
    {
        $this->cancelItemId       = $itemId;
        $this->showCancelItemModal = true;
    }

    public function confirmCancelItem(): void
    {
        $item = OrderItem::findOrFail($this->cancelItemId);

        $item->update([
            'is_cancelled' => true,
            'cancelled_by' => auth()->id(),
            'cancelled_at' => now(),
        ]);

        $this->recalculateOrder($item->order_id);

        $this->showCancelItemModal = false;
        $this->cancelItemId        = null;
        $this->refreshOrder();
        $this->dispatch('notify', type: 'warning', message: 'Ítem cancelado.');
    }

    // ─── Cancel full order ─────────────────────────────────────────────────────

    public function openCancelModal(): void
    {
        $this->cancelReason   = '';
        $this->showCancelModal = true;
    }

    public function confirmCancel(): void
    {
        $this->validate(['cancelReason' => 'required|string|min:5|max:255']);

        $this->order->update([
            'status'              => 'cancelada',
            'cancelled_by'        => auth()->id(),
            'cancellation_reason' => $this->cancelReason,
            'cancelled_at'        => now(),
        ]);

        $this->showCancelModal = false;
        $this->cancelReason    = '';
        $this->refreshOrder();
        $this->dispatch('notify', type: 'warning', message: 'Orden cancelada.');
    }

    // ─── Delete item (permission guarded in blade) ─────────────────────────────

    #[On('modal-confirmed')]
    public function handleModalConfirmed(string $action, array $params = []): void
    {
        match ($action) {
            'deleteItem' => $this->deleteItem($params['id']),
            default      => null,
        };
    }

    public function confirmDeleteItem(int $id): void
    {
        $this->dispatch('open-confirm',
            type: 'danger',
            title: 'Eliminar ítem',
            message: '¿Eliminar este ítem de la orden permanentemente?',
            action: 'deleteItem',
            params: ['id' => $id],
        );
    }

    private function deleteItem(int $id): void
    {
        $item = OrderItem::findOrFail($id);
        $orderId = $item->order_id;
        $item->delete();
        $this->recalculateOrder($orderId);
        $this->refreshOrder();
        $this->dispatch('notify', type: 'danger', message: 'Ítem eliminado.');
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    private function recalculateOrder(int $orderId): void
    {
        $order = Order::with('items')->findOrFail($orderId);
        $subtotal = $order->items
            ->where('is_cancelled', false)
            ->sum('subtotal');
        $order->update(['subtotal' => $subtotal, 'total' => $subtotal]);
    }

    private function refreshOrder(): void
    {
        $this->order = Order::with([
            'seller', 'cashRegister', 'customer', 'cancelledBy',
            'payments', 'items.product', 'items.addons', 'items.ingredients', 'items.cancelledBy',
        ])->findOrFail($this->order->id);
    }

    public function render()
    {
        return view('livewire.orders.order-detail')
            ->layout('layouts.app');
    }
}
