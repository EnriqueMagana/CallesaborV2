<?php

namespace App\Livewire\Profile;

use App\Models\NotificationPreference;
use Livewire\Component;

class NotificationPreferencesForm extends Component
{
    public bool $notificationsEnabled = true;

    public bool $soundEnabled = true;

    public int $volume = 65;

    public bool $quietHoursEnabled = false;

    public string $quietHoursStart = '22:00';

    public string $quietHoursEnd = '07:00';

    public array $eventPreferences = [];

    public function mount(): void
    {
        $preference = NotificationPreference::query()->firstOrCreate(['user_id' => auth()->id()]);
        $this->notificationsEnabled = (bool) $preference->notifications_enabled;
        $this->soundEnabled = (bool) $preference->sound_enabled;
        $this->volume = (int) $preference->volume;
        $this->quietHoursEnabled = (bool) $preference->quiet_hours_enabled;
        $this->quietHoursStart = substr((string) $preference->quiet_hours_start, 0, 5) ?: '22:00';
        $this->quietHoursEnd = substr((string) $preference->quiet_hours_end, 0, 5) ?: '07:00';
        $this->eventPreferences = $preference->eventPreferences();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'notificationsEnabled' => 'required|boolean',
            'soundEnabled' => 'required|boolean',
            'volume' => 'required|integer|min:0|max:100',
            'quietHoursEnabled' => 'required|boolean',
            'quietHoursStart' => 'required|date_format:H:i',
            'quietHoursEnd' => 'required|date_format:H:i',
            'eventPreferences' => 'required|array',
            'eventPreferences.*' => 'required|boolean',
        ]);

        NotificationPreference::query()->updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'notifications_enabled' => $validated['notificationsEnabled'],
                'sound_enabled' => $validated['soundEnabled'],
                'volume' => $validated['volume'],
                'quiet_hours_enabled' => $validated['quietHoursEnabled'],
                'quiet_hours_start' => $validated['quietHoursStart'],
                'quiet_hours_end' => $validated['quietHoursEnd'],
                'event_preferences' => array_replace(NotificationPreference::DEFAULT_EVENTS, $validated['eventPreferences']),
            ]
        );

        $this->dispatch('notification-preferences-updated');
        $this->dispatch('profile-notifications-saved');
    }

    public function eventOptions(): array
    {
        $all = [
            'order_created' => ['bx-receipt', 'Pedidos nuevos', 'Nuevos pedidos relacionados con tu operación.'],
            'order_ready' => ['bx-check-circle', 'Pedidos listos', 'Avisos cuando cocina termina un pedido.'],
            'order_cancelled' => ['bx-error-circle', 'Cancelaciones', 'Incidencias bajo tu responsabilidad.'],
            'order_paid' => ['bx-credit-card', 'Pedidos cobrados', 'Confirmaciones de cierre y cobro.'],
            'order_cancellation_requested' => ['bx-error-circle', 'Solicitudes de cancelación', 'Solicitudes pendientes que requieren autorización.'],
            'order_modification_requested' => ['bx-edit-alt', 'Solicitudes de modificación', 'Cambios de productos pendientes de autorización.'],
            'delivery_available' => ['bx-cycling', 'Delivery disponible', 'Pedidos listos para que un repartidor los tome.'],
            'delivery_assigned' => ['bx-user-check', 'Delivery asignado', 'Confirmaciones de asignación de entrega.'],
            'delivery_picked_up' => ['bx-package', 'Delivery recogido', 'Seguimiento cuando el pedido sale a reparto.'],
            'delivery_completed' => ['bx-home-heart', 'Delivery completado', 'Confirmación final de la entrega.'],
        ];

        $roles = auth()->user()->getRoleNames();
        if ($roles->intersect(['owner', 'super-admin', 'admin', 'gerente'])->isNotEmpty()) {
            return $all;
        }

        $keys = collect();
        if ($roles->contains('cocinero')) {
            $keys->push('order_created', 'order_cancelled');
        }
        if ($roles->contains('mesero')) {
            $keys->push('order_created', 'order_ready', 'order_cancelled');
        }
        if ($roles->contains('cajero')) {
            $keys->push('order_created', 'order_ready', 'order_cancelled', 'order_paid', 'delivery_picked_up', 'delivery_completed');
        }
        if ($roles->contains('repartidor')) {
            $keys->push('delivery_available', 'delivery_assigned');
        }

        return collect($all)->only($keys->unique())->all();
    }

    public function render()
    {
        return view('livewire.profile.notification-preferences-form');
    }
}
