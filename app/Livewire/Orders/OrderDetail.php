<?php

namespace App\Livewire\Orders;

use App\Models\CashRegister;
use App\Models\Order;
use Livewire\Component;

class OrderDetail extends Component
{
    public Order $order;

    public function mount(Order $order): void
    {
        abort_unless(auth()->user()?->can('ver ordenes'), 403);
        $canViewHistory = auth()->user()?->can('ver reportes') ?? false;
        $activeRegisterId = CashRegister::where('is_open', true)->latest('opened_at')->value('id');
        abort_unless($canViewHistory || ($activeRegisterId && $order->cash_register_id === (int) $activeRegisterId), 404);

        $this->order = $order->load([
            'seller', 'cashRegister', 'customer', 'cancelledBy', 'payments',
            'deliveryAssignment',
            'items.product', 'items.addons', 'items.ingredients', 'items.cancelledBy',
            'changeRequests.requester', 'changeRequests.reviewer', 'refunds.processor',
        ]);
    }

    public function render()
    {
        return view('livewire.orders.order-detail')->layout('layouts.app');
    }
}
