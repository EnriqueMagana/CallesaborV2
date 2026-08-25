<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\OperationalNotificationService;

class OrderObserver
{
    public bool $afterCommit = true;

    public function __construct(private readonly OperationalNotificationService $notifications) {}

    public function created(Order $order): void
    {
        $this->notifications->orderCreated($order);
    }

    public function updated(Order $order): void
    {
        if ($order->wasChanged('status')) {
            $this->notifications->orderStatusChanged($order, (string) $order->getOriginal('status'));
        }
    }
}
