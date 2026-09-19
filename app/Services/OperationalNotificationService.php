<?php

namespace App\Services;

use App\Jobs\PublishRealtimeNotification;
use App\Models\AppNotification;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryAssignmentEvent;
use App\Models\Mesa;
use App\Models\MesaHelpRequest;
use App\Models\Order;
use App\Models\OrderChangeRequest;
use App\Models\OrderDataChangeAudit;
use App\Models\User;
use App\Notifications\OperationalNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OperationalNotificationService
{
    private const SUPERVISORS = ['owner', 'super-admin', 'admin', 'gerente'];

    public function orderCreated(Order $order): void
    {
        $order->loadMissing(['mesa.activeAssignments', 'seller']);
        $category = $this->category($order);

        $this->send(
            eventKey: $this->createdEventKey($order),
            category: $category,
            priority: 'high',
            subject: $order,
            recipients: $this->createdRecipients($order),
            title: $this->createdTitle($order),
            message: $this->orderContext($order).' · $'.number_format((float) $order->total, 2),
            url: $this->urlFor($order),
            sound: 'order',
            dedupeSuffix: 'created'
        );
    }

    public function orderStatusChanged(Order $order, string $previousStatus): void
    {
        $order->loadMissing(['mesa.activeAssignments', 'deliveryAssignment']);

        if ($order->status === 'lista') {
            $managedDelivery = $order->type === 'delivery' && $this->managedDelivery($order);
            $event = $managedDelivery ? 'delivery.available' : $this->readyEventKey($order);
            $this->send(
                $event,
                $this->category($order),
                'high',
                $order,
                $this->readyRecipients($order),
                $managedDelivery ? 'Nuevo delivery listo para tomar' : $this->readyTitle($order),
                $this->orderContext($order).' ya está listo.',
                $this->urlFor($order),
                $managedDelivery ? 'delivery' : 'ready',
                "{$previousStatus}-lista"
            );
        } elseif ($order->status === 'en_reparto' && $order->type === 'delivery') {
            $this->send('delivery.picked_up', 'delivery', 'normal', $order, $this->deliveryRecipients($order, false),
                'Delivery recogido', $this->orderContext($order).' salió a reparto.', $this->urlFor($order), 'delivery', "{$previousStatus}-en_reparto");
        } elseif ($order->status === 'cancelada') {
            $this->send('order.cancelled', $this->category($order), 'urgent', $order, $this->exceptionRecipients($order),
                'Pedido cancelado', $this->orderContext($order).' fue cancelado.', $this->urlFor($order), 'alert', "{$previousStatus}-cancelada");
        } elseif ($order->status === 'pagada') {
            $event = $order->type === 'delivery' ? 'delivery.completed' : 'order.paid';
            $this->send($event, $this->category($order), 'normal', $order, $this->completionRecipients($order),
                $order->type === 'delivery' ? 'Delivery completado' : 'Pedido cobrado',
                $this->orderContext($order).' quedó pagado.', $this->urlFor($order), 'success', "{$previousStatus}-pagada");
        }
    }

    public function deliveryAssigned(DeliveryAssignment $assignment): void
    {
        $assignment->loadMissing(['order', 'driver']);
        if (! $assignment->order || ! $assignment->driver) {
            return;
        }

        if (! $this->managedDelivery($assignment->order)) {
            return;
        }

        $recipients = $this->usersByRoles(self::SUPERVISORS)->push($assignment->driver)->unique('id');
        $this->send('delivery.assigned', 'delivery', 'high', $assignment->order, $recipients,
            'Delivery asignado', "Pedido {$assignment->order->display_folio} asignado a {$assignment->driver->name}.",
            $this->urlFor($assignment->order), 'delivery', 'assignment-'.$assignment->id);
    }

    public function deliveryReassigned(DeliveryAssignment $assignment, DeliveryAssignmentEvent $event): void
    {
        $assignment->loadMissing(['order', 'driver']);
        $event->loadMissing(['fromDriver', 'toDriver', 'actor']);
        if (! $assignment->order || ! $assignment->driver || ! $this->managedDelivery($assignment->order)) {
            return;
        }

        $recipients = $this->usersByRoles(self::SUPERVISORS)
            ->merge(User::query()->whereKey([$event->from_driver_id, $event->to_driver_id])->get())
            ->unique('id');

        $from = $event->fromDriver?->name ?? 'Sin repartidor';
        $to = $event->toDriver?->name ?? $assignment->driver->name;
        $this->send(
            'delivery.reassigned',
            'delivery',
            'high',
            $assignment->order,
            $recipients,
            'Delivery reasignado',
            "Pedido {$assignment->order->display_folio}: {$from} → {$to}. Motivo: {$event->reason}",
            $this->urlFor($assignment->order),
            'delivery',
            'reassignment-'.$event->id,
        );
    }

    /**
     * Un cajero cambió la forma de pago de una orden sin pasar por
     * autorización. Cada cambio genera su propio aviso (el sujeto es el
     * registro de auditoría), con el efecto sobre el efectivo esperado.
     *
     * @param  array<int, array{amount: ?float, from: string, to: string}>  $changes
     */
    public function paymentTypeChanged(OrderDataChangeAudit $audit, Order $order, array $changes): void
    {
        $audit->loadMissing('changedBy');
        $label = fn (string $method) => match ($method) {
            'efectivo' => 'Efectivo',
            'tarjeta' => 'Tarjeta',
            'transferencia' => 'Transferencia',
            'contra_entrega' => 'Contra entrega',
            default => ucfirst($method),
        };
        $isCash = fn (string $method) => in_array($method, ['efectivo', 'contra_entrega'], true);

        $cashDelta = 0.0;
        $parts = [];
        foreach ($changes as $change) {
            $amount = $change['amount'] !== null ? '$'.number_format($change['amount'], 2).' ' : '';
            $parts[] = $amount.$label($change['from']).' → '.$label($change['to']);
            if ($change['amount'] !== null) {
                $cashDelta += ($isCash($change['to']) ? 1 : 0) * $change['amount'] - ($isCash($change['from']) ? 1 : 0) * $change['amount'];
            }
        }

        $effect = match (true) {
            $cashDelta < -0.009 => ' El efectivo esperado del corte baja $'.number_format(abs($cashDelta), 2).'.',
            $cashDelta > 0.009 => ' El efectivo esperado del corte sube $'.number_format($cashDelta, 2).'.',
            default => '',
        };

        $this->send(
            eventKey: 'order.payment_reclassified',
            category: 'orders',
            priority: $cashDelta < -0.009 ? 'urgent' : 'high',
            subject: $audit,
            recipients: $this->usersByRoles(['owner', 'super-admin']),
            title: 'Cambio de forma de pago',
            message: "Pedido {$order->display_folio} · ".($audit->changedBy?->name ?? 'Usuario').': '.implode(', ', $parts).'.'.$effect,
            url: route('app.ordenes.show', $order, false),
            sound: $cashDelta < -0.009 ? 'alert' : 'order',
            dedupeSuffix: 'payment-type'
        );
    }

    public function orderChangeRequested(OrderChangeRequest $request): void
    {
        $request->loadMissing(['order', 'requester']);
        if (! $request->order) {
            return;
        }

        $event = match ($request->type) {
            OrderChangeRequest::TYPE_CANCELLATION => 'order.cancellation_requested',
            OrderChangeRequest::TYPE_PAYMENT_CHANGE => 'order.payment_change_requested',
            OrderChangeRequest::TYPE_ADDRESS_CHANGE => 'order.address_change_requested',
            default => 'order.modification_requested',
        };
        $isCancellation = $request->type === OrderChangeRequest::TYPE_CANCELLATION;
        $this->send(
            eventKey: $event,
            category: 'orders',
            priority: $isCancellation ? 'urgent' : 'high',
            subject: $request,
            recipients: $this->usersByRoles(['owner', 'super-admin']),
            title: 'Solicitud: '.$request->type_label,
            message: "Pedido {$request->order->display_folio} · {$request->requester?->name}: {$request->reason}",
            url: route('app.solicitudes-ordenes', ['request' => $request->id], false),
            sound: $isCancellation ? 'alert' : 'order',
            dedupeSuffix: 'requested'
        );
    }

    public function orderChangeResolved(OrderChangeRequest $request): void
    {
        $request->loadMissing(['order.deliveryAssignment.driver', 'requester', 'reviewer']);
        if (! $request->order || ! $request->requester) {
            return;
        }

        $approved = $request->status === OrderChangeRequest::STATUS_APPROVED;
        $recipients = collect([$request->requester]);
        if ($approved && $request->type === OrderChangeRequest::TYPE_ADDRESS_CHANGE) {
            $driver = $request->order->deliveryAssignment?->driver;
            if ($driver) {
                $recipients->push($driver);
            }
        }

        $this->send(
            eventKey: $approved ? 'order.change_approved' : 'order.change_rejected',
            category: $request->type === OrderChangeRequest::TYPE_ADDRESS_CHANGE ? 'delivery' : 'orders',
            priority: $request->type === OrderChangeRequest::TYPE_ADDRESS_CHANGE ? 'urgent' : 'high',
            subject: $request,
            recipients: $recipients->unique('id'),
            title: ($approved ? 'Solicitud aprobada: ' : 'Solicitud rechazada: ').$request->type_label,
            message: "Pedido {$request->order->display_folio} · revisó {$request->reviewer?->name}.",
            url: $this->urlFor($request->order),
            sound: $approved ? 'success' : 'alert',
            dedupeSuffix: $request->status,
            resolveByRole: false,
        );
    }

    public function mesaHelpRequested(MesaHelpRequest $request): void
    {
        $request->loadMissing(['mesa.area', 'group', 'requester', 'requestedUser']);
        if (! $request->requestedUser || ! $request->mesa) {
            return;
        }

        $target = $request->scope === 'group' && $request->group
            ? "el grupo {$request->group->name}"
            : $request->mesa->display_name;

        $this->send(
            eventKey: 'table.help_requested',
            category: 'tables',
            priority: 'high',
            subject: $request,
            recipients: collect([$request->requestedUser]),
            title: 'Solicitud de apoyo en mesas',
            message: "{$request->requester?->name} solicita apoyo en {$target}.",
            url: route('app.mesas', ['help_request' => $request->id], false),
            sound: 'order',
            dedupeSuffix: 'requested',
            resolveByRole: false,
        );
    }

    public function mesaHelpResponded(MesaHelpRequest $request): void
    {
        $request->loadMissing(['mesa', 'requester', 'requestedUser']);
        if (! $request->requester || ! $request->mesa) {
            return;
        }

        $accepted = $request->status === MesaHelpRequest::STATUS_ACCEPTED;
        $this->send(
            eventKey: $accepted ? 'table.help_accepted' : 'table.help_declined',
            category: 'tables',
            priority: 'normal',
            subject: $request,
            recipients: collect([$request->requester]),
            title: $accepted ? 'Apoyo confirmado' : 'Apoyo no disponible',
            message: "{$request->requestedUser?->name} ".($accepted ? 'aceptó' : 'rechazó')." apoyar en {$request->mesa->display_name}.",
            url: route('app.mesas', [], false),
            sound: $accepted ? 'success' : 'alert',
            dedupeSuffix: 'responded-'.$request->status,
            resolveByRole: false,
        );
    }

    public function mesaSupportAssigned(Mesa $mesa, User $waiter, User $assigner, bool $group): void
    {
        $mesa->loadMissing('group');
        $target = $group && $mesa->group ? "el grupo {$mesa->group->name}" : $mesa->display_name;
        $this->send(
            eventKey: 'table.support_assigned',
            category: 'tables',
            priority: 'high',
            subject: $mesa,
            recipients: collect([$waiter]),
            title: 'Te agregaron a un equipo de mesa',
            message: "{$assigner->name} te agregó como apoyo en {$target}.",
            url: route('app.mesas', [], false),
            sound: 'order',
            dedupeSuffix: 'support-'.$waiter->id.'-'.Str::uuid(),
            resolveByRole: false,
        );
    }

    private function send(string $eventKey, string $category, string $priority, Model $subject, Collection $recipients,
        string $title, string $message, string $url, string $sound, string $dedupeSuffix, bool $resolveByRole = true): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $settingKey = str_replace('.', '_', $eventKey);
        if ($resolveByRole) {
            $recipients = app(RoleNotificationRecipientResolver::class)->resolve($eventKey, $recipients);
        }

        $actorId = auth()->id();
        $now = now();
        $rows = $recipients
            ->filter(fn (User $user) => ! $user->trashed() && ! $user->isBanned() && $user->id !== $actorId)
            ->unique('id')
            ->filter(function (User $user) use ($settingKey): bool {
                $preference = $user->notificationPreference;

                return ! $preference
                    || ($preference->notifications_enabled && ($preference->eventPreferences()[$settingKey] ?? true));
            })
            ->map(function (User $user) use ($eventKey, $category, $priority, $subject, $title, $message, $url, $sound, $dedupeSuffix, $now) {
                return [
                    'id' => (string) Str::uuid(),
                    'type' => OperationalNotification::class,
                    'notifiable_type' => User::class,
                    'notifiable_id' => $user->id,
                    'event_key' => $eventKey,
                    'category' => $category,
                    'priority' => $priority,
                    'subject_type' => $subject::class,
                    'subject_id' => $subject->id,
                    'dedupe_key' => "{$eventKey}:{$subject->getMorphClass()}:{$subject->id}:{$dedupeSuffix}",
                    'data' => json_encode(compact('title', 'message', 'url', 'sound'), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })->values()->all();

        if ($rows !== []) {
            DB::table('notifications')->insertOrIgnore($rows);

            AppNotification::query()
                ->whereIn('id', collect($rows)->pluck('id'))
                ->pluck('id')
                ->each(fn (string $id) => PublishRealtimeNotification::dispatchAfterResponse($id));
        }
    }

    private function createdRecipients(Order $order): Collection
    {
        $users = $this->usersByRoles(self::SUPERVISORS);
        $operationalRoles = $order->type === 'mesa' ? ['cocinero'] : ['cocinero', 'cajero'];
        $users = $users->merge($this->usersByRoles($operationalRoles));

        if ($order->type === 'mesa') {
            $users = $users->merge($this->waitersFor($order));
        }

        return $users->unique('id');
    }

    private function readyRecipients(Order $order): Collection
    {
        if ($order->type === 'mesa') {
            return $this->waitersFor($order)->merge($this->usersByRoles(self::SUPERVISORS))->unique('id');
        }

        if ($order->type === 'delivery') {
            if (! $this->managedDelivery($order)) {
                return $this->usersByRoles(array_merge(self::SUPERVISORS, ['cajero']));
            }

            $assigned = $order->deliveryAssignment?->driver_id
                ? User::query()->whereKey($order->deliveryAssignment->driver_id)->get()
                : $this->usersByRoles(['repartidor']);

            return $assigned->merge($this->usersByRoles(array_merge(self::SUPERVISORS, ['cajero'])))->unique('id');
        }

        return $this->usersByRoles(array_merge(self::SUPERVISORS, ['cajero']));
    }

    private function exceptionRecipients(Order $order): Collection
    {
        return $this->usersByRoles(array_merge(self::SUPERVISORS, ['cajero']))
            ->merge($order->type === 'mesa' ? $this->waitersFor($order) : collect())
            ->unique('id');
    }

    private function completionRecipients(Order $order): Collection
    {
        return $this->usersByRoles(array_merge(self::SUPERVISORS, ['cajero']));
    }

    private function deliveryRecipients(Order $order, bool $includeDrivers = true): Collection
    {
        $users = $this->usersByRoles(array_merge(self::SUPERVISORS, ['cajero']));
        if ($includeDrivers && $order->deliveryAssignment?->driver_id) {
            $users = $users->merge(User::query()->whereKey($order->deliveryAssignment->driver_id)->get());
        }

        return $users->unique('id');
    }

    private function waitersFor(Order $order): Collection
    {
        $ids = collect([$order->served_by])
            ->merge($order->mesa?->activeAssignments?->pluck('user_id') ?? [])
            ->filter()
            ->unique();

        return User::query()->whereKey($ids)->get();
    }

    private function usersByRoles(array $roles): Collection
    {
        return User::query()->whereNull('banned_at')->whereHas('roles', fn ($query) => $query->whereIn('name', $roles))->get();
    }

    private function category(Order $order): string
    {
        return match ($order->type) {
            'mesa' => 'tables',
            'delivery' => 'delivery',
            default => 'orders',
        };
    }

    private function createdTitle(Order $order): string
    {
        if ($order->source === 'kiosk') {
            return 'Nuevo pedido de kiosco';
        }

        return match ($order->type) {
            'mesa' => 'Nuevo pedido de mesa',
            'delivery' => 'Nuevo pedido de delivery',
            'pick_up' => 'Nuevo pedido para recoger',
            'ventanilla' => 'Nuevo pedido de ventanilla',
            default => 'Nuevo pedido',
        };
    }

    private function readyTitle(Order $order): string
    {
        return match ($this->orderChannel($order)) {
            'table' => 'Pedido de mesa listo',
            'pickup' => 'Pedido para recoger listo',
            'kiosk' => 'Pedido de kiosco listo',
            'delivery' => 'Pedido de delivery listo',
            default => 'Pedido de ventanilla listo',
        };
    }

    private function createdEventKey(Order $order): string
    {
        return $this->orderChannel($order).'.order_created';
    }

    private function readyEventKey(Order $order): string
    {
        return $this->orderChannel($order).'.order_ready';
    }

    private function orderChannel(Order $order): string
    {
        if ($order->source === 'kiosk') {
            return 'kiosk';
        }

        return match ($order->type) {
            'mesa' => 'table',
            'pick_up' => 'pickup',
            'delivery' => 'delivery',
            default => 'counter',
        };
    }

    private function orderContext(Order $order): string
    {
        $label = $order->type === 'mesa' && $order->mesa
            ? $order->mesa->display_name
            : $order->type_label;

        return "{$label} · Pedido {$order->display_folio}";
    }

    private function urlFor(Order $order): string
    {
        return match ($order->type) {
            'mesa' => $order->mesa_id
                ? route('app.mesas.ordenes', $order->mesa_id, false)
                : route('app.ordenes.show', $order, false),
            'delivery' => $this->managedDelivery($order)
                ? route('app.delivery', ['order' => $order->id], false)
                : route('app.ordenes.show', $order, false),
            default => route('app.ordenes.show', $order, false),
        };
    }

    private function managedDelivery(Order $order): bool
    {
        return $order->type === 'delivery'
            && ($order->delivery_flow_mode ?: 'managed') === 'managed'
            && app(DeliveryModulePolicy::class)->enabled();
    }
}
