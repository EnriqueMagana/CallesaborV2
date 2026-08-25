<?php

namespace App\Services\Firebase;

use App\Models\AppNotification;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class FirebaseRealtimeDatabase
{
    public function __construct(
        private readonly FirebaseConfiguration $configuration,
        private readonly FirebaseAccessTokenProvider $tokens,
        private readonly FirebaseFailureMonitor $monitor,
    ) {}

    public function publish(AppNotification $notification): bool
    {
        if (! $this->configuration->requested()) {
            return false;
        }

        try {
            $this->assertReady();
            $path = $this->configuration->rootPath().'/'.$this->configuration->userUid($notification->notifiable_id).'/'.$notification->getKey();
            $response = Http::withToken($this->tokens->token())
                ->acceptJson()
                ->timeout(max(1, (int) config('firebase.realtime.request_timeout', 3)))
                ->put($this->url($path), [
                    'id' => (string) $notification->getKey(),
                    'event_key' => (string) $notification->event_key,
                    'created_at_ms' => $notification->created_at?->getTimestampMs() ?? now()->getTimestampMs(),
                ]);

            if (! $response->successful()) {
                throw new RuntimeException('Firebase respondió con HTTP '.$response->status().'.');
            }

            return true;
        } catch (Throwable $failure) {
            $this->monitor->capture('publish', $failure, [
                'notification_id' => (string) $notification->getKey(),
                'user_id' => (string) $notification->notifiable_id,
                'database_host' => $this->databaseHost(),
            ]);

            return false;
        }
    }

    public function clearNotifications(): bool
    {
        if (! $this->configuration->requested()) {
            return false;
        }

        try {
            $this->assertReady();
            $response = Http::withToken($this->tokens->token())
                ->acceptJson()
                ->timeout(max(1, (int) config('firebase.realtime.request_timeout', 3)))
                ->delete($this->url($this->configuration->rootPath()));

            if (! $response->successful()) {
                throw new RuntimeException('Firebase respondió con HTTP '.$response->status().'.');
            }

            return true;
        } catch (Throwable $failure) {
            $this->monitor->capture('daily_cleanup', $failure, [
                'database_host' => $this->databaseHost(),
                'path' => $this->configuration->rootPath(),
            ]);

            return false;
        }
    }

    private function assertReady(): void
    {
        if (! $this->configuration->ready()) {
            throw new RuntimeException('Configuración Firebase incompleta: '.implode(', ', $this->configuration->missingRequirements()));
        }
    }

    private function url(string $path): string
    {
        return $this->configuration->databaseUrl().'/'.trim($path, '/').'.json';
    }

    private function databaseHost(): ?string
    {
        $url = $this->configuration->databaseUrl();

        return $url ? parse_url($url, PHP_URL_HOST) : null;
    }
}
