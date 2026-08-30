<?php

namespace App\Services;

use App\Models\Mesa;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CashRegisterClosingGuard
{
    public function blockers(int $registerId): array
    {
        $orders = Order::query()
            ->where('cash_register_id', $registerId)
            ->whereNotIn('status', ['pagada', 'cancelada'])
            ->where(fn ($query) => $query
                ->where('type', '!=', 'delivery')
                ->orWhere('delivery_flow_mode', '!=', 'manual')
                ->orWhereNull('accounted_at'))
            ->with(['mesa.area', 'seller', 'kioskTerminal', 'deliveryAssignment.driver'])
            ->orderBy('created_at')
            ->get();

        $unassignedDeliveries = Order::query()
            ->where('cash_register_id', $registerId)
            ->where('type', 'delivery')
            ->where('delivery_flow_mode', 'managed')
            ->where('status', '!=', 'cancelada')
            ->whereDoesntHave('deliveryAssignment')
            ->with('kioskTerminal')
            ->oldest()
            ->get();

        // El arqueo del repartidor es un control administrativo independiente:
        // una entrega pagada ya no bloquea el corte general de la caja.
        $unsettledDeliveries = collect();

        $tables = Mesa::query()
            ->whereIn('status', ['ocupada', 'en_cuenta'])
            ->with([
                'area',
                'currentAssignment.waiter',
                'orders' => fn ($query) => $query
                    ->where('cash_register_id', $registerId)
                    ->whereNotIn('status', ['pagada', 'cancelada'])
                    ->orderBy('created_at'),
                'splits' => fn ($query) => $query
                    ->whereIn('status', ['pendiente', 'parcial'])
                    ->latest(),
            ])
            ->orderBy('area_id')
            ->orderBy('number')
            ->get();

        $summary = $this->summarize($orders, $tables);
        $summary['unassigned_delivery'] = $unassignedDeliveries->count();
        $summary['unsettled_delivery'] = 0;
        $tablesWithOrders = $orders->whereNotNull('mesa_id')->pluck('mesa_id')->unique();
        $standaloneTables = $tables->whereNotIn('id', $tablesWithOrders);
        $unassignedOutsideOrders = $unassignedDeliveries->whereNotIn('id', $orders->pluck('id'));
        $blockingOrders = $orders
            ->concat($unassignedOutsideOrders)
            ->sortBy('created_at')
            ->values();

        return [
            'has_blockers' => $orders->isNotEmpty()
                || $tables->isNotEmpty()
                || $unassignedDeliveries->isNotEmpty(),
            'count' => $blockingOrders->count()
                + $standaloneTables->count(),
            'unpaid_total' => (float) $orders->sum('total'),
            'orders' => $blockingOrders,
            'tables' => $tables,
            'unassigned_deliveries' => $unassignedDeliveries,
            'unsettled_deliveries' => $unsettledDeliveries,
            'summary' => $summary,
        ];
    }

    public function assertCanClose(int $registerId, ?array $blockers = null): void
    {
        $blockers ??= $this->blockers($registerId);

        if (! $blockers['has_blockers']) {
            return;
        }

        throw ValidationException::withMessages([
            'cutBlockers' => sprintf(
                'No se puede cerrar la caja: resuelve %d pendiente%s antes de continuar.',
                $blockers['count'],
                $blockers['count'] === 1 ? '' : 's',
            ),
        ]);
    }

    private function summarize(Collection $orders, Collection $tables): array
    {
        $kiosk = $orders->where('source', 'kiosk');
        $tableOrders = $orders
            ->where('source', '!=', 'kiosk')
            ->where('type', 'mesa');
        $delivery = $orders
            ->where('source', '!=', 'kiosk')
            ->where('type', 'delivery');
        $counter = $orders->reject(fn (Order $order) => $order->source === 'kiosk'
            || in_array($order->type, ['mesa', 'delivery'], true));

        return [
            'tables' => $tables->count(),
            'table_orders' => $tableOrders->count(),
            'kiosk' => $kiosk->count(),
            'delivery' => $delivery->count(),
            'counter' => $counter->count(),
        ];
    }
}
