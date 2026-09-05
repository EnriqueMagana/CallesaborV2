<?php

namespace App\Livewire\Orders;

use App\Models\OrderChangeRequest;
use App\Services\OrderChangeRequestService;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class OrderChangeRequestInbox extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = OrderChangeRequest::STATUS_PENDING;

    public string $typeFilter = '';

    public ?int $selectedRequestId = null;

    public string $reviewNotes = '';

    public string $refundReference = '';

    public bool $refundConfirmed = false;

    public function mount(): void
    {
        $this->authorizeReviewer();

        $requestedId = request()->integer('request');
        $this->selectedRequestId = OrderChangeRequest::query()
            ->when($requestedId, fn ($query) => $query->whereKey($requestedId))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest()
            ->value('id');
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

    #[Computed]
    public function requests()
    {
        $term = trim($this->search);

        return OrderChangeRequest::query()
            ->with(['order.customer', 'order.deliveryAssignment.driver', 'requester', 'reviewer', 'refund'])
            ->when($this->statusFilter, fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->typeFilter, fn ($query) => $query->where('type', $this->typeFilter))
            ->when($term !== '', function ($query) use ($term): void {
                $query->where(function ($query) use ($term): void {
                    $query->when(is_numeric($term), fn ($numeric) => $numeric->orWhere('order_id', (int) $term))
                        ->orWhere('reason', 'like', "%{$term}%")
                        ->orWhereHas('requester', fn ($requester) => $requester->where('name', 'like', "%{$term}%"))
                        ->orWhereHas('order', function ($order) use ($term): void {
                            $order->where('customer_name', 'like', "%{$term}%")
                                ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$term}%"));
                        });
                });
            })
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest()
            ->paginate(15);
    }

    #[Computed]
    public function selectedRequest(): ?OrderChangeRequest
    {
        return $this->selectedRequestId
            ? OrderChangeRequest::with(['order.customer', 'order.deliveryAssignment.driver', 'requester', 'reviewer', 'refund'])->find($this->selectedRequestId)
            : null;
    }

    #[Computed]
    public function summary(): array
    {
        return [
            'pending' => OrderChangeRequest::where('status', OrderChangeRequest::STATUS_PENDING)->count(),
            'cancellations' => OrderChangeRequest::where('status', OrderChangeRequest::STATUS_PENDING)->where('type', OrderChangeRequest::TYPE_CANCELLATION)->count(),
            'modifications' => OrderChangeRequest::where('status', OrderChangeRequest::STATUS_PENDING)->where('type', OrderChangeRequest::TYPE_MODIFICATION)->count(),
            'payment_changes' => OrderChangeRequest::where('status', OrderChangeRequest::STATUS_PENDING)->where('type', OrderChangeRequest::TYPE_PAYMENT_CHANGE)->count(),
            'address_changes' => OrderChangeRequest::where('status', OrderChangeRequest::STATUS_PENDING)->where('type', OrderChangeRequest::TYPE_ADDRESS_CHANGE)->count(),
            'resolved' => OrderChangeRequest::whereIn('status', [OrderChangeRequest::STATUS_APPROVED, OrderChangeRequest::STATUS_REJECTED])->count(),
        ];
    }

    public function selectRequest(int $requestId): void
    {
        $this->authorizeReviewer();
        $this->selectedRequestId = OrderChangeRequest::query()->findOrFail($requestId)->id;
        $this->reviewNotes = '';
        $this->refundReference = '';
        $this->refundConfirmed = false;
        unset($this->selectedRequest);
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'typeFilter']);
        $this->statusFilter = OrderChangeRequest::STATUS_PENDING;
        $this->resetPage();
        unset($this->requests);
    }

    public function approveRequest(OrderChangeRequestService $service): void
    {
        $this->authorizeReviewer();
        $request = OrderChangeRequest::query()->findOrFail($this->selectedRequestId);
        $refundAmount = (float) data_get($request->proposed_changes, 'request_context.refund_amount', 0);
        if ($refundAmount > 0) {
            $rules = ['refundConfirmed' => ['accepted']];
            $allocations = collect(data_get($request->proposed_changes, 'request_context.refund_allocations', []));
            if ($allocations->except(['efectivo', 'contra_entrega'])->sum() > 0) {
                $rules['refundReference'] = ['required', 'string', 'min:4', 'max:120'];
            }
            $this->validate($rules, [
                'refundConfirmed.accepted' => 'Confirma que se realizó la devolución antes de aprobar.',
                'refundReference.required' => 'Captura la referencia externa de la devolución.',
            ]);
        }
        $service->approve($request, auth()->user(), $this->reviewNotes, ['external_reference' => $this->refundReference]);
        $this->afterReview('Solicitud aprobada y aplicada a la orden.');
    }

    public function rejectRequest(OrderChangeRequestService $service): void
    {
        $this->authorizeReviewer();
        $this->validate(['reviewNotes' => 'required|string|min:5|max:1000']);
        $request = OrderChangeRequest::query()->findOrFail($this->selectedRequestId);
        $service->reject($request, auth()->user(), $this->reviewNotes);
        $this->afterReview('Solicitud rechazada. La orden no fue modificada.');
    }

    public function render()
    {
        return view('livewire.orders.order-change-request-inbox')->layout('layouts.app');
    }

    private function afterReview(string $message): void
    {
        $this->reviewNotes = '';
        $this->refundReference = '';
        $this->refundConfirmed = false;
        unset($this->requests, $this->selectedRequest, $this->summary);
        $this->dispatch('notify', type: 'success', message: $message);
    }

    private function authorizeReviewer(): void
    {
        $user = auth()->user();
        abort_unless($user?->can('revisar solicitudes de ordenes') && $user->hasAnyRole(['owner', 'super-admin']), 403);
    }
}
