<?php

namespace App\Http\Middleware;

use App\Services\DeliveryModulePolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeliveryModuleEnabled
{
    public function __construct(private readonly DeliveryModulePolicy $policy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->policy->assertEnabled();

        return $next($request);
    }
}
