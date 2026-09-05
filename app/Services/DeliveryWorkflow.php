<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryAssignmentEvent;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryWorkflow
{
    public function __construct(
        private readonly DeliveryModulePolicy $policy,
        private readonly OperationalNotificationService $notifications,
    ) {}

    public function assignTo(Order $order, User $driver): DeliveryAssignment
    {
        return DB::transaction(function () use ($order, $driver): DeliveryAssignment {
            $this->policy->assertEnabledForUpdate();
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $this->ensureOrderBelongsToOpenRegister($lockedOrder);

            if ($lockedOrder->type !== 'delivery') {
                throw ValidationException::withMessages(['delivery' => 'Este pedido no corresponde a delivery.']);
            }

            if (! $this->policy->isManaged($lockedOrder)) {
                throw ValidationException::withMessages(['delivery' => 'Este pedido pertenece a la gestión manual y no admite asignación digital.']);
            }

            if (! in_array($lockedOrder->status, ['pendiente', 'en_preparacion', 'lista', 'pagada'], true)) {
                throw ValidationException::withMessages(['delivery' => 'Este pedido ya no está disponible para asignación.']);
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

            return $assignment->load('driver');
        });
    }

    public function markPickedUp(Order $order, User $actor, bool $canManageAll = false): DeliveryAssignment
    {
        return DB::transaction(function () use ($order, $actor, $canManageAll): DeliveryAssignment {
            $this->policy->assertEnabledForUpdate();
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $this->ensureOrderBelongsToOpenRegister($lockedOrder);

            $assignment = DeliveryAssignment::query()
                ->where('order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->first();

            if (! $this->policy->isManaged($lockedOrder)) {
                throw ValidationException::withMessages(['delivery' => 'Este pedido pertenece a la gestión manual.']);
            }

            if (! $assignment || $assignment->status !== 'asignado') {
                throw ValidationException::withMessages(['delivery' => 'Este pedido no está asignado para entrega.']);
            }

            if (! $canManageAll && $assignment->driver_id !== $actor->id) {
                throw new AuthorizationException('Solo el repartidor asignado puede recoger este pedido.');
            }

            if (! in_array($lockedOrder->status, ['pendiente', 'en_preparacion', 'lista', 'pagada'], true)) {
                throw ValidationException::withMessages(['delivery' => 'El pedido ya fue recogido o dejó de estar disponible.']);
            }

            $lockedOrder->update(['status' => 'en_reparto']);

            return $assignment->fresh('driver');
        });
    }

    public function reassign(Order $order, User $newDriver, User $actor, string $reason): DeliveryAssignment
    {
        if (! $actor->can('reasignar pedidos delivery')) {
            throw new AuthorizationException('No tienes permiso para reasignar pedidos de delivery.');
        }

        $result = DB::transaction(function () use ($order, $newDriver, $actor, $reason): array {
            $this->policy->assertEnabledForUpdate();
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $this->ensureOrderBelongsToOpenRegister($lockedOrder);

            if ($lockedOrder->type !== 'delivery' || ! $this->policy->isManaged($lockedOrder)) {
                throw ValidationException::withMessages(['reassignDriverId' => 'Este pedido no admite asignaciÃ³n digital.']);
            }

            if (! in_array($lockedOrder->status, ['pendiente', 'en_preparacion', 'lista', 'pagada', 'en_reparto'], true)) {
                throw ValidationException::withMessages(['reassignDriverId' => 'Este pedido ya no puede reasignarse.']);
            }

            $assignment = DeliveryAssignment::query()
                ->where('order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->first();

            if (! $assignment || $assignment->status !== 'asignado') {
                throw ValidationException::withMessages(['reassignDriverId' => 'El pedido no tiene una asignaciÃ³n activa.']);
            }

            if ($newDriver->isBanned() || $newDriver->trashed() || ! $newDriver->hasAnyPermission(['entregar delivery', 'gestionar delivery'])) {
                throw ValidationException::withMessages(['reassignDriverId' => 'Selecciona un repartidor activo con permiso para entregar pedidos.']);
            }

            if ($assignment->driver_id === $newDriver->id) {
                throw ValidationException::withMessages(['reassignDriverId' => 'El pedido ya estÃ¡ asignado a ese repartidor.']);
            }

            $previousDriverId = $assignment->driver_id;
            $assignment->update([
                'driver_id' => $newDriver->id,
                'assigned_by' => $actor->id,
                'assigned_at' => now(),
            ]);

            $event = DeliveryAssignmentEvent::create([
                'delivery_assignment_id' => $assignment->id,
                'order_id' => $lockedOrder->id,
                'from_driver_id' => $previousDriverId,
                'to_driver_id' => $newDriver->id,
                'actor_id' => $actor->id,
                'event_type' => 'reassigned',
                'reason' => trim($reason),
            ]);

            return [
                'assignment' => $assignment->fresh(['order', 'driver']),
                'event' => $event->load(['fromDriver', 'toDriver', 'actor']),
            ];
        });

        $this->notifications->deliveryReassigned($result['assignment'], $result['event']);

        return $result['assignment'];
    }

    public function markDelivered(Order $order, User $actor, bool $canManageAll = false): DeliveryAssignment
    {
        return DB::transaction(function () use ($order, $actor, $canManageAll): DeliveryAssignment {
            $this->policy->assertEnabledForUpdate();
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $this->ensureOrderBelongsToOpenRegister($lockedOrder);

            $assignment = DeliveryAssignment::query()
                ->where('order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->first();

            if (! $this->policy->isManaged($lockedOrder)) {
                throw ValidationException::withMessages(['delivery' => 'Este pedido pertenece a la gestión manual.']);
            }

            if (! $assignment || $assignment->status !== 'asignado') {
                throw ValidationException::withMessages(['delivery' => 'Este pedido no tiene una entrega pendiente.']);
            }

            if (! $canManageAll && $assignment->driver_id !== $actor->id) {
                throw new AuthorizationException('Solo el repartidor asignado puede completar esta entrega.');
            }

            if ($lockedOrder->status !== 'en_reparto') {
                throw ValidationException::withMessages(['delivery' => 'Primero confirma que recogiste el pedido.']);
            }

            $assignment->update([
                'status' => 'entregado',
                'delivered_by' => $actor->id,
                'delivered_at' => now(),
            ]);

            $paid = (float) $lockedOrder->payments()->sum('amount');
            $remaining = max(0, round((float) $lockedOrder->total - $paid, 2));

            if ($remaining > 0) {
                $paymentMethod = match ($lockedOrder->delivery_method) {
                    'contra_entrega' => 'efectivo',
                    'tarjeta' => 'tarjeta',
                    'transferencia' => 'transferencia',
                    default => throw ValidationException::withMessages([
                        'delivery' => 'Ventanilla debe definir el método de pago antes de completar la entrega.',
                    ]),
                };

                OrderPayment::create([
                    'order_id' => $lockedOrder->id,
                    'method' => $paymentMethod,
                    'amount' => $remaining,
                    'received_amount' => $paymentMethod === 'efectivo' ? $remaining : null,
                    'change_amount' => $paymentMethod === 'efectivo' ? 0 : null,
                ]);
            }

            $lockedOrder->update([
                'status' => 'pagada',
                'paid_at' => $lockedOrder->paid_at ?? now(),
            ]);

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
