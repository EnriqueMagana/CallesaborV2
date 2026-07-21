<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\DeliveryAssignment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryWorkflow
{
    public function assignTo(Order $order, User $driver): DeliveryAssignment
    {
        return DB::transaction(function () use ($order, $driver): DeliveryAssignment {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $this->ensureOrderBelongsToOpenRegister($lockedOrder);

            if ($lockedOrder->type !== 'delivery') {
                throw ValidationException::withMessages(['delivery' => 'Este pedido no corresponde a delivery.']);
            }

            if (! in_array($lockedOrder->status, ['lista', 'pagada'], true)) {
                throw ValidationException::withMessages(['delivery' => 'El pedido debe estar listo antes de asignarlo.']);
            }

            if (DeliveryAssignment::query()->where('order_id', $lockedOrder->id)->exists()) {
                throw ValidationException::withMessages(['delivery' => 'Otro repartidor ya tomó este pedido.']);
            }

            $assignment = DeliveryAssignment::create([
                'order_id' => $lockedOrder->id,
                'driver_id' => $driver->id,
                'assigned_by' => $driver->id,
                'status' => 'asignado',
                'assigned_at' => now(),
            ]);

            $lockedOrder->update(['status' => 'en_reparto']);

            return $assignment->load('driver');
        });
    }

    public function markDelivered(Order $order, User $actor, bool $canManageAll = false): DeliveryAssignment
    {
        return DB::transaction(function () use ($order, $actor, $canManageAll): DeliveryAssignment {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $this->ensureOrderBelongsToOpenRegister($lockedOrder);

            $assignment = DeliveryAssignment::query()
                ->where('order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->first();

            if (! $assignment || $assignment->status !== 'asignado') {
                throw ValidationException::withMessages(['delivery' => 'Este pedido no tiene una entrega pendiente.']);
            }

            if (! $canManageAll && $assignment->driver_id !== $actor->id) {
                throw new AuthorizationException('Solo el repartidor asignado puede completar esta entrega.');
            }

            $assignment->update([
                'status' => 'entregado',
                'delivered_by' => $actor->id,
                'delivered_at' => now(),
            ]);
            $lockedOrder->update(['status' => 'entregada']);

            return $assignment->fresh(['driver', 'deliveredBy']);
        });
    }

    private function ensureOrderBelongsToOpenRegister(Order $order): void
    {
        $isCurrent = CashRegister::query()
            ->whereKey($order->cash_register_id)
            ->where('is_open', true)
            ->exists();

        if (! $isCurrent) {
            throw ValidationException::withMessages(['delivery' => 'El pedido ya no pertenece a la caja activa.']);
        }
    }
}
