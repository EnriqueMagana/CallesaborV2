<?php

namespace App\Services\Firebase;

use App\Models\AppNotification;
use Illuminate\Support\Carbon;
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

    /**
     * @return array{ok: bool, available: bool, message: string, root: string, total: int, shown: int, fetched_at: string, signals: list<array{id: string, user_uid: string, event_key: string, created_at_ms: int, created_at: string}>}
     */
    public function notificationSignals(int $limit = 100): array
    {
        $limit = max(1, min($limit, 250));
        $snapshot = [
            'ok' => false,
            'available' => $this->configuration->ready(),
            'message' => 'Firebase Realtime Database no está disponible.',
            'root' => $this->configuration->rootPath(),
            'total' => 0,
            'shown' => 0,
            'fetched_at' => now()->format('d/m/Y H:i:s'),
            'signals' => [],
        ];

        if (! $this->configuration->requested()) {
            $snapshot['message'] = 'Firebase Realtime Database está desactivado.';

            return $snapshot;
        }

        try {
            $this->assertReady();
            $response = Http::withToken($this->tokens->token())
                ->acceptJson()
                ->timeout(max(1, (int) config('firebase.realtime.request_timeout', 3)))
                ->get($this->url($this->configuration->rootPath()));

            if (! $response->successful()) {
                throw new RuntimeException('Firebase respondió con HTTP '.$response->status().'.');
            }

            $signals = [];
            $payload = $response->json();
            foreach (is_array($payload) ? $payload : [] as $userUid => $userSignals) {
                if (! is_array($userSignals)) {
                    continue;
                }

                foreach ($userSignals as $signalId => $signal) {
                    if (! is_array($signal)) {
                        continue;
                    }

                    $createdAtMs = is_numeric($signal['created_at_ms'] ?? null)
                        ? (int) $signal['created_at_ms']
                        : 0;
                    $signals[] = [
                        'id' => (string) ($signal['id'] ?? $signalId),
                        'user_uid' => (string) $userUid,
                        'event_key' => (string) ($signal['event_key'] ?? 'unknown'),
                        'created_at_ms' => $createdAtMs,
                        'created_at' => $createdAtMs > 0
                            ? Carbon::createFromTimestampMs($createdAtMs)
                                ->timezone((string) config('firebase.realtime.cleanup_timezone', 'America/Mexico_City'))
                                ->format('d/m/Y H:i:s')
                            : 'Sin fecha',
                    ];
                }
            }

            usort($signals, fn (array $left, array $right): int => $right['created_at_ms'] <=> $left['created_at_ms']);
            $total = count($signals);
            $signals = array_slice($signals, 0, $limit);

            return array_merge($snapshot, [
                'ok' => true,
                'available' => true,
                'message' => $total === 0
                    ? 'No hay señales pendientes en Firebase.'
                    : "Se encontraron {$total} señales pendientes.",
                'total' => $total,
                'shown' => count($signals),
                'signals' => $signals,
            ]);
        } catch (Throwable $failure) {
            $this->monitor->capture('inspect', $failure, [
                'database_host' => $this->databaseHost(),
                'path' => $this->configuration->rootPath(),
            ]);
            $snapshot['message'] = 'No se pudo consultar Firebase. Revisa la configuración y Laravel Pulse.';

            return $snapshot;
        }
    }

    public function clearNotifications(string $operation = 'daily_cleanup'): bool
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
            $this->monitor->capture($operation, $failure, [
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
