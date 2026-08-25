<?php

namespace App\Observers;

use App\Models\DeliveryAssignment;
use App\Services\OperationalNotificationService;

class DeliveryAssignmentObserver
{
    public bool $afterCommit = true;

    public function __construct(private readonly OperationalNotificationService $notifications) {}

    public function created(DeliveryAssignment $assignment): void
    {
        $this->notifications->deliveryAssigned($assignment);
    }
}
