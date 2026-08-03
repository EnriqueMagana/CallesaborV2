<?php

namespace App\Http\Middleware;

use App\Models\CashRegister;
use App\Models\Order;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrderBelongsToCurrentRegister
{
    public function handle(Request $request, Closure $next): Response
    {
        $order = $request->route('order');

        if (! $order instanceof Order || auth()->user()?->can('ver reportes')) {
            return $next($request);
        }

        $activeRegisterId = CashRegister::query()
            ->where('is_open', true)
            ->latest('opened_at')
            ->value('id');

        abort_unless($activeRegisterId && $order->cash_register_id === (int) $activeRegisterId, 404);

        return $next($request);
    }
}
