<?php

namespace App\Services;

use App\Models\SidebarMenuItem;
use Illuminate\Support\Str;

class CashRegisterModuleAccess
{
    public const ALWAYS_AVAILABLE_ROUTES = [
        'app.caja',
        'app.configuracion-negocio',
        'app.configuracion-negocio.menu',
        'app.menu-digital',
        'profile',
    ];

    public static function isAlwaysAvailable(?string $routeName): bool
    {
        return $routeName !== null && in_array($routeName, self::ALWAYS_AVAILABLE_ROUTES, true);
    }

    public static function itemMatchesRoute(SidebarMenuItem $item, string $routeName): bool
    {
        if ($routeName === $item->route_name || str_starts_with($routeName, $item->route_name.'.')) {
            return true;
        }

        return $item->active_pattern
            ? Str::is($item->active_pattern, $routeName)
            : false;
    }
}
