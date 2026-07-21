<?php

namespace App\Http\Middleware;

use App\Models\CashRegister;
use App\Models\SidebarMenuItem;
use App\Services\CashRegisterModuleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireOpenCashRegisterForConfiguredModules
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        if (! $routeName || CashRegisterModuleAccess::isAlwaysAvailable($routeName)) {
            return $next($request);
        }

        $protectedItem = SidebarMenuItem::query()
            ->where('requires_open_register', true)
            ->where('is_active', true)
            ->whereNotNull('route_name')
            ->get(['label', 'route_name', 'active_pattern'])
            ->first(fn (SidebarMenuItem $item) => CashRegisterModuleAccess::itemMatchesRoute($item, $routeName));

        if (! $protectedItem || CashRegister::query()->where('is_open', true)->exists()) {
            return $next($request);
        }

        return redirect()->route('app.caja')->with(
            'cash_register_required',
            "El módulo {$protectedItem->label} está bloqueado hasta que se abra una caja."
        );
    }

}
