<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;
use App\Notifications\OperationalNotification;
use App\Services\Firebase\FirebaseConfiguration;
use App\Services\Firebase\FirebaseCustomTokenFactory;
use App\Services\Firebase\FirebaseRealtimeDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Pulse\Facades\Pulse;
use Throwable;

class DeveloperDiagnosticsService
{
    public function __construct(
        private readonly FirebaseConfiguration $firebase,
        private readonly FirebaseCustomTokenFactory $tokens,
        private readonly FirebaseRealtimeDatabase $realtime,
    ) {}

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $database = $this->checkDatabase();
        $pulseTables = collect(['pulse_values', 'pulse_entries', 'pulse_aggregates'])
            ->every(fn (string $table): bool => Schema::hasTable($table));

        return [
            'generated_at' => now()->format('d/m/Y H:i:s'),
            'application' => [
                'environment' => app()->environment(),
                'debug' => (bool) config('app.debug'),
                'url_host' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: config('app.url'),
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
                'maintenance' => app()->isDownForMaintenance(),
            ],
            'database' => $database,
            'queue' => [
                'connection' => config('queue.default'),
                'pending' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : null,
                'failed' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null,
            ],
            'pulse' => [
                'enabled' => (bool) config('pulse.enabled'),
                'tables_ready' => $pulseTables,
                'path' => '/'.trim((string) config('pulse.path', 'pulse'), '/'),
                'entries' => $pulseTables ? DB::table('pulse_entries')->count() : null,
                'firebase_failures' => $pulseTables
                    ? DB::table('pulse_entries')->where('type', 'firebase_rtdb_failure')->count()
                        + DB::table('pulse_aggregates')->where('type', 'firebase_rtdb_failure')->count()
                    : null,
            ],
            'firebase' => [
                'enabled' => $this->firebase->requested(),
                'ready' => $this->firebase->ready(),
                'database_host' => $this->firebase->databaseUrl()
                    ? parse_url($this->firebase->databaseUrl(), PHP_URL_HOST)
                    : null,
                'root' => $this->firebase->rootPath(),
                'missing' => $this->firebase->missingRequirements(),
                'rules_file' => is_file(base_path('firebase.database.rules.json')),
                'cleanup' => config('firebase.realtime.cleanup_time').' · '.config('firebase.realtime.cleanup_timezone'),
            ],
            'notifications' => $this->notificationSummary(),
        ];
    }

    /** @return array<string, mixed> */
    public function probeFirebase(User $user): array
    {
        $started = microtime(true);

        try {
            if (! $this->firebase->ready()) {
                throw new \RuntimeException('Firebase no tiene todos los requisitos configurados.');
            }

            $web = $this->firebase->web();
            $signIn = Http::asJson()->timeout(8)->post(
                'https://identitytoolkit.googleapis.com/v1/accounts:signInWithCustomToken?key='.$web['apiKey'],
                ['token' => $this->tokens->forUser($user), 'returnSecureToken' => true]
            );

            if (! $signIn->successful() || ! $signIn->json('idToken')) {
                throw new \RuntimeException('Firebase Authentication respondió con HTTP '.$signIn->status().'.');
            }

            $read = Http::timeout(8)->get(
                $this->firebase->databaseUrl().'/'.$this->firebase->rootPath().'/'.$this->firebase->userUid($user->getKey()).'.json',
                ['auth' => $signIn->json('idToken'), 'shallow' => 'true']
            );

            if (! $read->successful()) {
                throw new \RuntimeException('Realtime Database respondió con HTTP '.$read->status().'.');
            }

            return [
                'ok' => true,
                'message' => 'Authentication y reglas de lectura funcionan correctamente.',
                'auth_status' => $signIn->status(),
                'database_status' => $read->status(),
                'signals' => is_array($read->json()) ? count($read->json()) : 0,
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'checked_at' => now()->format('H:i:s'),
            ];
        } catch (Throwable $failure) {
            return [
                'ok' => false,
                'message' => $failure->getMessage(),
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'checked_at' => now()->format('H:i:s'),
            ];
        }
    }

    /** @return array{notification: AppNotification, realtime: bool} */
    public function createTestNotification(User $user, bool $publishRealtime): array
    {
        $id = (string) Str::uuid();
        $notification = AppNotification::query()->create([
            'id' => $id,
            'type' => OperationalNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->getKey(),
            'event_key' => $publishRealtime ? 'developer.realtime_test' : 'developer.livewire_test',
            'category' => 'system',
            'priority' => 'normal',
            'subject_type' => null,
            'subject_id' => null,
            'dedupe_key' => 'developer-test:'.$id,
            'data' => [
                'title' => $publishRealtime ? 'Prueba Firebase completada' : 'Prueba Livewire completada',
                'message' => 'Notificación técnica generada desde el panel Super Admin a las '.now()->format('H:i:s').'.',
                'url' => route('app.super-admin', [], false),
                'sound' => 'success',
            ],
        ]);

        return [
            'notification' => $notification,
            'realtime' => $publishRealtime && $this->realtime->publish($notification),
        ];
    }

    public function recordPulseTest(User $user): void
    {
        Pulse::record('developer_diagnostic', 'manual:user_'.$user->getKey(), 1)
            ->count()
            ->onlyBuckets();
    }

    /** @return array<string, mixed> */
    private function checkDatabase(): array
    {
        $started = microtime(true);

        try {
            DB::connection()->getPdo();

            return [
                'ok' => true,
                'connection' => config('database.default'),
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
            ];
        } catch (Throwable $failure) {
            return [
                'ok' => false,
                'connection' => config('database.default'),
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'message' => $failure->getMessage(),
            ];
        }
    }

    /** @return array<string, mixed> */
    private function notificationSummary(): array
    {
        if (! Schema::hasTable('notifications')) {
            return ['tables_ready' => false];
        }

        return [
            'tables_ready' => Schema::hasTable('notification_preferences'),
            'total' => AppNotification::query()->count(),
            'unread' => AppNotification::query()->whereNull('read_at')->count(),
            'unannounced' => AppNotification::query()->whereNull('announced_at')->count(),
            'last_created_at' => AppNotification::query()->max('created_at'),
            'by_category' => AppNotification::query()
                ->selectRaw('category, COUNT(*) total')
                ->groupBy('category')
                ->pluck('total', 'category')
                ->all(),
        ];
    }
}
