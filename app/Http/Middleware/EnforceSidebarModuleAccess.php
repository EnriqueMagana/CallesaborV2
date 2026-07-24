<?php

namespace App\Http\Middleware;

use App\Services\SidebarModuleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceSidebarModuleAccess
{
    public function __construct(private readonly SidebarModuleAccess $access)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $this->access->allows($request->user(), $request->route()?->getName()),
            403,
            'Este módulo no está disponible en tu menú o no tienes el permiso requerido.'
        );

        return $next($request);
    }
}
