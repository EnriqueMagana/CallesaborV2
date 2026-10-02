<?php

namespace App\Services;

use App\Models\BusinessSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class OnlineSalesPolicy
{
    public const CACHE_KEY = 'online-sales.enabled';

    public function enabled(): bool
    {
        if (! Schema::hasTable('business_settings') || ! Schema::hasColumn('business_settings', 'online_sales_enabled')) {
            return false;
        }

        return (bool) Cache::remember(self::CACHE_KEY, 300, fn () => BusinessSetting::current()->online_sales_enabled);
    }

    public function assertEnabled(): void
    {
        abort_unless($this->enabled(), 403, 'Las ventas en línea están desactivadas.');
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
