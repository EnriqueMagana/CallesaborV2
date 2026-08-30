<?php

namespace App\Services;

use App\Jobs\PublishRealtimeNotification;
use App\Models\AppNotification;
use App\Models\DeliveryAssignment;
use App\Models\Order;
use App\Models\OrderChangeRequest;
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
        $order->loadMissing(['mesa.currentAssignment', 'seller']);
        $category = $this->category($order);

        $this->send(
            eventKey: 'order.created',
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
        $order->loadMissing(['mesa.currentAssignment', 'deliveryAssignment']);

        if ($order->status === 'lista') {
            $managedDelivery = $order->type === 'delivery' && $this->managedDelivery($order);
            $event = $managedDelivery ? 'delivery.available' : 'order.ready';
            $this->send(
                $event,
                $this->category($order),
                'high',
                $order,
                $this->readyRecipients($order),
                $managedDelivery ? 'Delivery listo para recoger' : 'Pedido listo',
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
            'Delivery asignado', "Pedido #{$assignment->order->display_folio} asignado a {$assignment->driver->name}.",
            $this->urlFor($assignment->order), 'delivery', 'assignment-'.$assignment->id);
    }

    public function orderChangeRequested(OrderChangeRequest $request): void
    {
        $request->loadMissing(['order', 'requester']);
        if (! $request->order) {
            return;
        }

        $isCancellation = $request->type === OrderChangeRequest::TYPE_CANCELLATION;
        $this->send(
            eventKey: $isCancellation ? 'order.cancellation_requested' : 'order.modification_requested',
            category: 'orders',
            priority: $isCancellation ? 'urgent' : 'high',
            subject: $request,
            recipients: $this->usersByRoles(['owner', 'super-admin']),
            title: 'Solicitud: '.$request->type_label,
            message: "Pedido #{$request->order->display_folio} · {$request->requester?->name}: {$request->reason}",
            url: route('app.solicitudes-ordenes', ['request' => $request->id], false),
            sound: $isCancellation ? 'alert' : 'order',
            dedupeSuffix: 'requested'
        );
    }

    private function send(string $eventKey, string $category, string $priority, Model $subject, Collection $recipients,
        string $title, string $message, string $url, string $sound, string $dedupeSuffix): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $settingKey = str_replace('.', '_', $eventKey);

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
        $ids = collect([$order->served_by, $order->mesa?->currentAssignment?->user_id])->filter()->unique();

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
        return match ($order->type) {
            'mesa' => 'Nuevo pedido de mesa',
            'delivery' => 'Nuevo pedido de delivery',
            'pick_up', 'ventanilla' => 'Nuevo pedido para llevar',
            default => 'Nuevo pedido',
        };
    }

    private function orderContext(Order $order): string
    {
        $label = $order->type === 'mesa' && $order->mesa
            ? $order->mesa->display_name
            : $order->type_label;

        return "{$label} · Pedido #{$order->display_folio}";
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
