<?php

namespace App\Livewire\Kiosk;

use App\Models\Order;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.kiosk')]
class OrderTracking extends Component
{
    public string $publicToken;

    public ?string $lastCheckedAt = null;

    public function mount(string $publicToken): void
    {
        abort_unless(strlen($publicToken) === 64, 404);
        abort_unless(Order::where('public_token', $publicToken)->exists(), 404);
        $this->publicToken = $publicToken;
    }

    #[Computed]
    public function order(): Order
    {
        return Order::with(['items.addons', 'items.ingredients', 'deliveryAssignment.driver'])
            ->where('public_token', $this->publicToken)
            ->firstOrFail();
    }

    public function refreshStatus(): void
    {
        unset($this->order);
        $this->lastCheckedAt = now()->format('H:i:s');
    }

    public function render()
    {
        return view('livewire.kiosk.order-tracking');
    }
}
