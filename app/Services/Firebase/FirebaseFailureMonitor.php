<?php

namespace App\Services\Firebase;

use App\Exceptions\RealtimeDatabaseUnavailable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Pulse\Facades\Pulse;
use Throwable;

class FirebaseFailureMonitor
{
    /** @param array<string, mixed> $context */
    public function capture(string $operation, Throwable $failure, array $context = []): void
    {
        $diagnostics = array_merge([
            'service' => 'firebase_realtime_database',
            'operation' => $operation,
            'fallback' => 'livewire_database_notifications',
            'exception' => $failure::class,
        ], $context);

        Log::warning('Firebase Realtime Database no está disponible; se conserva el flujo Livewire.', $diagnostics);

        try {
            Pulse::record('firebase_rtdb_failure', $operation, 1)->count()->onlyBuckets();
        } catch (Throwable $pulseFailure) {
            Log::debug('No fue posible registrar la falla de Firebase en Pulse.', [
                'exception' => $pulseFailure::class,
            ]);
        }

        try {
            $seconds = max(60, (int) config('firebase.realtime.pulse_throttle_seconds', 300));
            if (Cache::add('firebase-rtdb:pulse:'.$operation, true, now()->addSeconds($seconds))) {
                report(new RealtimeDatabaseUnavailable(
                    'Firebase Realtime Database no está disponible; Livewire permanece activo.',
                    $diagnostics,
                    $failure,
                ));
            }
        } catch (Throwable) {
            // El sistema de monitoreo nunca debe interrumpir la operación del restaurante.
        }
    }
}
