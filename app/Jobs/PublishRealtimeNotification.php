<?php

namespace App\Jobs;

use App\Models\AppNotification;
use App\Services\Firebase\FirebaseRealtimeDatabase;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PublishRealtimeNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 15;

    public function __construct(public readonly string $notificationId) {}

    public function handle(FirebaseRealtimeDatabase $database): void
    {
        $notification = AppNotification::query()->find($this->notificationId);
        if ($notification) {
            $database->publish($notification);
        }
    }
}
