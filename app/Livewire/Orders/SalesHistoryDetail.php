<?php

namespace App\Livewire\Orders;

use App\Models\Order;
use App\Services\OrderAuditTrailService;
use App\Services\OrderFinancialSummaryService;
use App\Services\ThermalTicketRenderer;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SalesHistoryDetail extends Component
{
    public int $orderId;

    public function mount(Order $order): void
    {
        abort_unless(auth()->user()?->can('ver reportes'), 403);

        $this->orderId = $order->id;
    }

    #[Computed]
    public function order(): Order
    {
        return Order::query()->with([
            'seller' => fn ($query) => $query->withTrashed(),
            'cancelledBy' => fn ($query) => $query->withTrashed(),
            'discountBeneficiary' => fn ($query) => $query->withTrashed(),
            'cashRegister.opener' => fn ($query) => $query->withTrashed(),
            'cashRegister.closer' => fn ($query) => $query->withTrashed(),
            'mesaService.opener' => fn ($query) => $query->withTrashed(),
            'mesaService.closer' => fn ($query) => $query->withTrashed(),
            'items.cancelledBy' => fn ($query) => $query->withTrashed(),
            'changeRequests.requester' => fn ($query) => $query->withTrashed(),
            'changeRequests.reviewer' => fn ($query) => $query->withTrashed(),
            'refunds.processor' => fn ($query) => $query->withTrashed(),
            'dataChangeAudits.changedBy' => fn ($query) => $query->withTrashed(),
            'cashRegister', 'customer', 'mesa.area',
            'deliveryAssignment.driver', 'deliveryAssignment.assignedBy', 'deliveryAssignment.deliveredBy',
            'deliveryAssignment.events.fromDriver', 'deliveryAssignment.events.toDriver', 'deliveryAssignment.events.actor',
            'items.product', 'items.promotion', 'items.discount', 'items.addons', 'items.ingredients',
            'payments', 'changeRequests.refund', 'refunds', 'dataChangeAudits',
        ])->findOrFail($this->orderId);
    }

    #[Computed]
    public function financial(): array
    {
        return app(OrderFinancialSummaryService::class)->forOrder($this->order);
    }

    #[Computed]
    public function timeline()
    {
        return app(OrderAuditTrailService::class)->forOrder($this->order);
    }

    #[Computed]
    public function discountTotal(): float
    {
        return round((float) $this->order->items->where('is_cancelled', false)->sum(
            fn ($item) => (float) $item->promotion_discount + (float) $item->discount_amount
        ), 2);
    }

    #[Computed]
    public function refundTotal(): float
    {
        return round((float) collect($this->financial['refunds'])->sum(), 2);
    }

    public function previewTicket(): void
    {
        abort_unless(auth()->user()?->can('reimprimir tickets'), 403);

        $type = match ($this->order->type) {
            'delivery' => 'delivery',
            'ventanilla', 'pick_up' => 'counter',
            default => 'customer',
        };

        $this->dispatch('sales-history-ticket-show',
            html: app(ThermalTicketRenderer::class)->renderOrder($this->order, $type, autoPrint: false),
        );
    }

    public function render()
    {
        return view('livewire.orders.sales-history-detail', [
            'order' => $this->order,
        ])->layout('layouts.app');
    }
}
