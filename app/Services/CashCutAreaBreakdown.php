<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Collection;

/**
 * Desglose del corte por área: Delivery, Cocina (mesas) y Ventanilla
 * (incluye para recoger y kiosco).
 *
 * Lista TODOS los pedidos del turno, pero sólo suma los que el corte
 * contabiliza (`isFinalizedForAccounting`), con el mismo neto por método que
 * CorteDeCaja::totals(): cobros menos devoluciones, y lo cobrado contra
 * entrega como efectivo. Así el total de cada tabla y el total general
 * siempre coinciden con "Ventas por área".
 */
class CashCutAreaBreakdown
{
    public const AREAS = [
        'delivery' => ['label' => 'Delivery', 'icon' => 'bx-cycling', 'types' => ['delivery']],
        'cocina' => ['label' => 'Cocina · Mesas', 'icon' => 'bx-dish', 'types' => ['mesa']],
        'ventanilla' => ['label' => 'Ventanilla', 'icon' => 'bx-store', 'types' => ['ventanilla', 'pick_up']],
    ];

    public function __construct(private OrderFinancialSummaryService $financial) {}

    /**
     * @param  Collection<int, Order>  $orders  pedidos de la caja con payments, refunds y mesa cargados
     * @return array{areas: array<string, array>, grand: array{efectivo: float, tarjeta: float, transfer: float, total: float, counted: int, listed: int}}
     */
    public function build(Collection $orders): array
    {
        $areas = [];
        foreach (self::AREAS as $key => $area) {
            $rows = $orders
                ->filter(fn (Order $order) => in_array($order->type, $area['types'], true))
                ->sortBy('created_at')
                ->map(fn (Order $order) => $this->row($order))
                ->values();

            $counted = $rows->where('counted', true);
            $areas[$key] = $area + [
                'rows' => $rows->all(),
                'totals' => [
                    'efectivo' => round($counted->sum('efectivo'), 2),
                    'tarjeta' => round($counted->sum('tarjeta'), 2),
                    'transfer' => round($counted->sum('transfer'), 2),
                    'total' => round($counted->sum('total'), 2),
                    'counted' => $counted->count(),
                    'listed' => $rows->count(),
                ],
            ];
        }

        $totals = collect($areas)->pluck('totals');

        return [
            'areas' => $areas,
            'grand' => [
                'efectivo' => round($totals->sum('efectivo'), 2),
                'tarjeta' => round($totals->sum('tarjeta'), 2),
                'transfer' => round($totals->sum('transfer'), 2),
                'total' => round($totals->sum('total'), 2),
                'counted' => $totals->sum('counted'),
                'listed' => $totals->sum('listed'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Order $order): array
    {
        $net = $this->financial->forOrder($order)['net'];
        $counted = $order->isFinalizedForAccounting();

        $note = match (true) {
            $order->status === 'cancelada' => 'Cancelada · no suma',
            ! $counted => 'Sin cobrar · no suma',
            $order->provisional_amount > 0.009 => 'Lo trae el repartidor',
            $order->refunded_amount > 0.009 => 'Con devolución',
            default => null,
        };

        return [
            'id' => $order->id,
            'folio' => $order->display_folio,
            'created_at' => $order->created_at,
            'who' => $order->type === 'mesa'
                ? ($order->mesa?->display_name ?? 'Mesa')
                : ($order->customer_name ?: 'Cliente general'),
            'type_label' => $order->source === 'kiosk' ? 'Kiosco' : $order->type_label,
            'status' => $order->status,
            'status_label' => $order->status_label,
            'counted' => $counted,
            'note' => $note,
            'efectivo' => round((float) ($net['efectivo'] ?? 0) + (float) ($net['contra_entrega'] ?? 0), 2),
            'tarjeta' => round((float) ($net['tarjeta'] ?? 0), 2),
            'transfer' => round((float) ($net['transferencia'] ?? 0), 2),
            'refunded' => $order->refunded_amount,
            'total' => round((float) $order->total, 2),
        ];
    }
}
