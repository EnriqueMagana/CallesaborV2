<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\Order;
use Illuminate\Support\Facades\Schema;

class DeliveryModulePolicy
{
    public function enabled(): bool
    {
        if (! Schema::hasTable('business_settings')
            || ! Schema::hasColumn('business_settings', 'delivery_management_enabled')) {
            return true;
        }

        return (bool) BusinessSetting::current()->delivery_management_enabled;
    }

    public function modeForNewOrder(): string
    {
        return $this->enabled() ? 'managed' : 'manual';
    }

    public function enabledForUpdate(): bool
    {
        if (! Schema::hasTable('business_settings')
            || ! Schema::hasColumn('business_settings', 'delivery_management_enabled')) {
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
}
