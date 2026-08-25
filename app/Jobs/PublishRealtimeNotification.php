<?php

namespace App\Jobs;

use App\Models\AppNotification;
use App\Services\Firebase\FirebaseRealtimeDatabase;
use Illuminate\Foundation\Queue\Queueable;

class PublishRealtimeNotification
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly string $notificationId) {}

    public function handle(FirebaseRealtimeDatabase $database): void
    {
        $notification = AppNotification::query()->find($this->notificationId);
        if ($notification) {
            $database->publish($notification);
        }
    }
}
