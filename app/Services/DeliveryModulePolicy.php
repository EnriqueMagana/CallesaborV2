<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class DeliveryModulePolicy
{
    /**
     * Clave de cache del interruptor del modulo.
     *
     * `BusinessSetting` la invalida al guardarse, asi que cubre cualquier via
     * de escritura, no solo `DeliveryModuleManager`.
     */
    public const CACHE_KEY = 'delivery.module.enabled';

    private const CACHE_TTL = 300;

    /**
     * El esquema no cambia a mitad de un request, pero `Schema::hasColumn()`
     * barre `information_schema` cada vez que se pregunta: eran las dos
     * consultas mas lentas de cada click del POS. Se memoriza por proceso.
     */
    private static ?bool $schemaSupportsToggle = null;

    public function enabled(): bool
    {
        if (! $this->schemaSupportsToggle()) {
            return true;
        }

        return (bool) Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn () => (bool) BusinessSetting::current()->delivery_management_enabled,
        );
    }

    public function modeForNewOrder(): string
    {
        return $this->enabled() ? 'managed' : 'manual';
    }

    /**
     * Lectura transaccional: bloquea la fila y NO usa cache a proposito.
     * Es la que decide si una escritura concurrente puede continuar.
     */
    public function enabledForUpdate(): bool
    {
        if (! $this->schemaSupportsToggle()) {
            return true;
        }

        BusinessSetting::current();

        return (bool) BusinessSetting::query()
            ->lockForUpdate()
            ->firstOrFail()
            ->delivery_management_enabled;
    }

    public function isManaged(Order $order): bool
    {
        return ($order->delivery_flow_mode ?: 'managed') === 'managed';
    }

    public function assertEnabled(): void
    {
        abort_unless($this->enabled(), 403, 'El módulo de delivery está desactivado.');
    }

    public function assertEnabledForUpdate(): void
    {
        abort_unless($this->enabledForUpdate(), 403, 'El módulo de delivery está desactivado.');
    }

    /**
     * Olvida el valor cacheado y la memoria de esquema.
     *
     * Los tests que crean las tablas a mitad del caso, y cualquier escritura de
     * la configuracion de delivery, deben llamarla.
     */
    public static function flush(): void
    {
        self::$schemaSupportsToggle = null;
        Cache::forget(self::CACHE_KEY);
    }

    private function schemaSupportsToggle(): bool
    {
        if (self::$schemaSupportsToggle !== null) {
            return self::$schemaSupportsToggle;
        }

        return self::$schemaSupportsToggle = Schema::hasTable('business_settings')
            && Schema::hasColumn('business_settings', 'delivery_management_enabled');
    }
}
