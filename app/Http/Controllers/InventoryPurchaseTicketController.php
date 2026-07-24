<?php

namespace App\Http\Controllers;

use App\Models\InventoryPurchase;
use App\Services\ThermalTicketRenderer;
use Illuminate\Http\Response;

class InventoryPurchaseTicketController extends Controller
{
    public function __invoke(InventoryPurchase $purchase, ThermalTicketRenderer $renderer): Response
    {
        $user = auth()->user();
        abort_unless(
            $user && ($user->can('generar compras inventario') || $user->can('recepcionar compras inventario')),
            403
        );

        return response()
            ->make($renderer->renderInventoryPurchase($purchase, request()->boolean('autoprint')))
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
