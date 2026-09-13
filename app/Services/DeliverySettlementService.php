<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\DeliveryAssignment;
use App\Models\DeliverySettlement;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliverySettlementService
{
    public function __construct(private readonly DeliveryModulePolicy $policy) {}

    public function complete(
        CashRegister $register,
        User $driver,
        User $actor,
        float $declaredCash,
        ?string $notes = null,
    ): DeliverySettlement {
        return DB::transaction(function () use ($register, $driver, $actor, $declaredCash, $notes): DeliverySettlement {
            $this->policy->assertEnabledForUpdate();
            $lockedRegister = CashRegister::query()->lockForUpdate()->findOrFail($register->id);

            if (! $lockedRegister->is_open) {
                throw ValidationException::withMessages(['deliverySettlement' => 'La caja ya está cerrada.']);
            }

            $inRoute = DeliveryAssignment::query()
                ->where('driver_id', $driver->id)
                ->where('status', 'asignado')
                ->whereHas('order', fn ($orders) => $orders->where('cash_register_id', $lockedRegister->id))
                ->exists();

            if ($inRoute) {
                throw ValidationException::withMessages([
                    'deliverySettlement' => 'El repartidor todavía tiene pedidos en ruta.',
                ]);
            }

            $assignments = DeliveryAssignment::query()
                ->where('driver_id', $driver->id)
                ->where('status', 'entregado')
                ->whereNull('delivery_settlement_id')
                ->whereHas('order', fn ($orders) => $orders->where('cash_register_id', $lockedRegister->id))
                ->with(['order.payments', 'order.refunds'])
                ->lockForUpdate()
                ->get();

            if ($assignments->isEmpty()) {
                throw ValidationException::withMessages([
                    'deliverySettlement' => 'No hay entregas pendientes de arqueo para este repartidor.',
                ]);
            }

            $orders = $assignments->pluck('order')->where('status', '!=', 'cancelada')->values();
            $financial = app(OrderFinancialSummaryService::class)->forOrders($orders);
            $sumMethod = fn (string $method): float => (float) ($financial['net'][$method] ?? 0);
            $expectedCash = $sumMethod('efectivo') + $sumMethod('contra_entrega');

            $settlement = DeliverySettlement::create([
                'cash_register_id' => $lockedRegister->id,
                'driver_id' => $driver->id,
                'completed_by' => $actor->id,
                'orders_count' => $orders->count(),
                'sales_total' => (float) $orders->sum('total'),
                'expected_cash' => $expectedCash,
                'declared_cash' => $declaredCash,
                'difference' => round($declaredCash - $expectedCash, 2),
                'transfer_total' => $sumMethod('transferencia'),
                'card_total' => $sumMethod('tarjeta'),
                'notes' => filled($notes) ? trim($notes) : null,
                'completed_at' => now(),
            ]);

            DeliveryAssignment::query()
                ->whereKey($assignments->modelKeys())
                ->update(['delivery_settlement_id' => $settlement->id]);

            return $settlement->load(['driver', 'assignments.order.payments', 'assignments.order.refunds']);
        });
    }

    public function refreshForOrder(Order $order): void
    {
        $settlementId = $order->deliveryAssignment?->delivery_settlement_id;
        if (! $settlementId) {
            return;
        }

        $settlement = DeliverySettlement::query()->lockForUpdate()->find($settlementId);
        if (! $settlement) {
            return;
        }

        $orders = $settlement->assignments()
            ->with(['order.payments', 'order.refunds'])
            ->get()
            ->pluck('order')
            ->where('status', '!=', 'cancelada')
            ->values();
        $financial = app(OrderFinancialSummaryService::class)->forOrders($orders);
        $cash = (float) ($financial['net']['efectivo'] ?? 0) + (float) ($financial['net']['contra_entrega'] ?? 0);

        $settlement->update([
            'orders_count' => $orders->count(),
            'sales_total' => round((float) $orders->sum('total'), 2),
            'expected_cash' => round($cash, 2),
            'difference' => round((float) $settlement->declared_cash - $cash, 2),
            'transfer_total' => round((float) ($financial['net']['transferencia'] ?? 0), 2),
            'card_total' => round((float) ($financial['net']['tarjeta'] ?? 0), 2),
        ]);
    }
}
