<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\DeliveryAssignment;
use App\Models\DeliverySettlement;
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
                ->with('order.payments')
                ->lockForUpdate()
                ->get();

            if ($assignments->isEmpty()) {
                throw ValidationException::withMessages([
                    'deliverySettlement' => 'No hay entregas pendientes de arqueo para este repartidor.',
                ]);
            }

            $orders = $assignments->pluck('order');
            $sumMethod = fn (string $method): float => (float) $orders
                ->flatMap(fn ($order) => $order->payments->where('method', $method))
                ->sum('amount');
            $expectedCash = $sumMethod('efectivo');

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

            return $settlement->load(['driver', 'assignments.order.payments']);
        });
    }
}
