<?php

namespace App\Console\Commands;

use App\Services\Firebase\FirebaseConfiguration;
use App\Services\Firebase\FirebaseRealtimeDatabase;
use Illuminate\Console\Command;

class ClearFirebaseRealtimeNotifications extends Command
{
    protected $signature = 'notifications:clear-realtime';

    protected $description = 'Elimina las señales efímeras de notificaciones de Firebase Realtime Database';

    public function handle(FirebaseConfiguration $configuration, FirebaseRealtimeDatabase $database): int
    {
        if (! $configuration->requested()) {
            $this->components->info('Firebase Realtime Database está desactivado; no hay datos que limpiar.');

            return self::SUCCESS;
        }

        if (! $database->clearNotifications()) {
            $this->components->warn('No se pudo limpiar Firebase. Livewire sigue activo y Pulse recibió el incidente.');

            return self::FAILURE;
        }

        $this->components->info('Se limpiaron las señales de notificaciones de Firebase.');

        return self::SUCCESS;
    }
}
