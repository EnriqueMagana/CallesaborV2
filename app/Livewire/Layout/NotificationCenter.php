<?php

namespace App\Livewire\Layout;

use App\Models\AppNotification;
use App\Models\NotificationPreference;
use App\Models\Order;
use App\Models\User;
use App\Services\DeliveryModulePolicy;
use App\Services\DeliveryWorkflow;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class NotificationCenter extends Component
{
    public bool $open = false;

    public bool $confirmingClearAll = false;

    public string $placement = 'navbar';

    public bool $soundEnabled = true;

    public int $volume = 65;

    public bool $notificationsEnabled = true;

    public bool $quietHoursEnabled = false;

    public string $quietHoursStart = '22:00';

    public string $quietHoursEnd = '07:00';

    public function mount(string $placement = 'navbar'): void
    {
        $this->placement = in_array($placement, ['navbar', 'pos'], true) ? $placement : 'navbar';

        if (! Schema::hasTable('notifications')) {
            $this->notificationsEnabled = false;

            return;
        }

        $this->reloadPreferences();
        $this->announceNew();
    }

    #[Computed]
    public function notifications()
    {
        if (! $this->notificationsEnabled || ! Schema::hasTable('notifications')) {
            return collect();
        }

        return $this->baseQuery()
            ->with('subject')
            ->latest()
            ->limit(40)
            ->get();
    }

    #[Computed]
    public function unreadCount(): int
    {
        if (! $this->notificationsEnabled || ! Schema::hasTable('notifications')) {
            return 0;
        }

        return $this->baseQuery()->whereNull('read_at')->count();
    }

    public function togglePanel(): void
    {
        $this->open = ! $this->open;
        if (! $this->open) {
            $this->confirmingClearAll = false;
        }
        if ($this->open) {
            $this->announceNew();
        }
    }

    public function closePanel(): void
    {
        $this->open = false;
        $this->confirmingClearAll = false;
    }

    public function requestClearAll(): void
    {
        if ($this->notifications->isEmpty()) {
            return;
        }

        $this->confirmingClearAll = true;
    }

    public function cancelClearAll(): void
    {
        $this->confirmingClearAll = false;
    }

    public function handleEscape(): void
    {
        if ($this->confirmingClearAll) {
            $this->cancelClearAll();

            return;
        }

        $this->closePanel();
    }

    public function markRead(string $id): void
    {
        $this->baseQuery()->whereKey($id)->whereNull('read_at')->update(['read_at' => now(), 'updated_at' => now()]);
        unset($this->notifications, $this->unreadCount);
    }

    public function markAllRead(): void
    {
        $this->baseQuery()->whereNull('read_at')->update(['read_at' => now(), 'updated_at' => now()]);
        unset($this->notifications, $this->unreadCount);
    }

    public function clearAll(): void
    {
        if (! $this->notificationsEnabled || ! Schema::hasTable('notifications')) {
            return;
        }

        $this->baseQuery()->delete();
        $this->confirmingClearAll = false;
        unset($this->notifications, $this->unreadCount);

        $this->dispatch('notify', type: 'success', message: 'Notificaciones eliminadas permanentemente.');
    }

    public function openNotification(string $id): void
    {
        $notification = $this->baseQuery()->with('subject')->whereKey($id)->firstOrFail();
        $this->followNotification($notification);
    }

    public function actionLabel(AppNotification $notification): ?string
    {
        $order = $notification->subject;
        if (! $order instanceof Order) {
            return null;
        }

        $user = auth()->user();
        if ($notification->event_key === 'delivery.available'
            && app(DeliveryModulePolicy::class)->enabled()
            && $user?->canAny(['tomar delivery', 'gestionar delivery'])) {
            return 'Tomar delivery';
        }

        if ($order->type === 'mesa' && $order->mesa_id && $user?->can('ver mesas')) {
            return 'Ir a mesa';
        }

        return $user?->canAny(['ver ordenes', 'ver pedidos en punto de venta', 'ver delivery'])
            ? 'Abrir pedido'
            : null;
    }

    public function performAction(string $id, DeliveryWorkflow $workflow): void
    {
        $notification = $this->baseQuery()->with('subject')->whereKey($id)->firstOrFail();
        $order = $notification->subject;

        if ($notification->event_key === 'delivery.available'
            && $order instanceof Order
            && auth()->user()?->canAny(['tomar delivery', 'gestionar delivery'])) {
            try {
                $workflow->assignTo($order, auth()->user());
            } catch (ValidationException $exception) {
                $message = collect($exception->errors())->flatten()->first()
                    ?? 'El pedido ya no estÃ¡ disponible para asignaciÃ³n.';
                $this->dispatch('notify', type: 'warning', message: $message);

                return;
            }

            $notification->update(['read_at' => $notification->read_at ?? now()]);
            $this->closePanel();
            unset($this->notifications, $this->unreadCount);
            $this->redirect(route('app.delivery', ['order' => $order->id], false), navigate: true);

            return;
        }

        $this->followNotification($notification);
    }

    private function followNotification(AppNotification $notification): void
    {
        $notification->update(['read_at' => $notification->read_at ?? now()]);
        $url = $this->destinationFor($notification);
        $this->closePanel();
        unset($this->notifications, $this->unreadCount);

        if (str_starts_with($url, '/')) {
            $this->redirect($url, navigate: true);
        }
    }

    #[On('notifications-check')]
    public function checkForNew(): void
    {
        unset($this->notifications, $this->unreadCount);
        $this->announceNew();
    }

    private function announceNew(): void
    {
        if (! $this->notificationsEnabled || ! Schema::hasTable('notifications')) {
            return;
        }

        $new = $this->baseQuery()->whereNull('announced_at')->oldest()->limit(20)->get();
        if ($new->isEmpty()) {
            return;
        }

        $ids = $new->pluck('id');
        $this->baseQuery()->whereKey($ids)->update(['announced_at' => now(), 'updated_at' => now()]);
        $latest = $new->last();
        $this->dispatch('app-notifications-new',
            count: $new->count(),
            title: $new->count() === 1 ? ($latest->data['title'] ?? 'Nueva notificación') : $new->count().' notificaciones nuevas',
            message: $new->count() === 1 ? ($latest->data['message'] ?? '') : 'Abre el centro de notificaciones para revisarlas.',
            sound: $latest->data['sound'] ?? 'order',
            playSound: $this->soundEnabled && ! $this->isQuietTime(),
            volume: $this->volume,
        );

        unset($this->notifications, $this->unreadCount);
    }

    #[On('notification-preferences-updated')]
    public function reloadPreferences(): void
    {
        if (! Schema::hasTable('notification_preferences')) {
            return;
        }

        $preference = NotificationPreference::query()->firstOrCreate(['user_id' => auth()->id()]);
        $this->notificationsEnabled = (bool) $preference->notifications_enabled;
        $this->soundEnabled = (bool) $preference->sound_enabled;
        $this->volume = (int) $preference->volume;
        $this->quietHoursEnabled = (bool) $preference->quiet_hours_enabled;
        $this->quietHoursStart = substr((string) $preference->quiet_hours_start, 0, 5) ?: '22:00';
        $this->quietHoursEnd = substr((string) $preference->quiet_hours_end, 0, 5) ?: '07:00';
        unset($this->notifications, $this->unreadCount);
    }

    private function isQuietTime(): bool
    {
        if (! $this->quietHoursEnabled) {
            return false;
        }

        $now = now()->format('H:i');
        $start = $this->quietHoursStart;
        $end = $this->quietHoursEnd;

        return $start <= $end ? $now >= $start && $now < $end : $now >= $start || $now < $end;
    }

    private function baseQuery()
    {
        return AppNotification::query()
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', auth()->id());
    }

    private function destinationFor(AppNotification $notification): string
    {
        if ($notification->subject_type === Order::class && $notification->subject_id) {
            $order = Order::query()->find($notification->subject_id);

            if ($order?->type === 'mesa' && $order->mesa_id && auth()->user()?->can('ver mesas')) {
                return route('app.mesas.ordenes', $order->mesa_id, false);
            }

            if ($order?->type === 'delivery'
                && ($order->delivery_flow_mode ?: 'managed') === 'managed'
                && app(DeliveryModulePolicy::class)->enabled()
                && auth()->user()?->can('ver delivery')) {
                return route('app.delivery', ['order' => $order->id], false);
            }

            if ($order && auth()->user()?->can('ver ordenes')) {
                return route('app.ordenes.show', $order, false);
            }

            if ($order && auth()->user()?->can('ver pedidos en punto de venta')) {
                return route('app.pos', [], false);
            }

            if ($order) {
                return route('app.dashboard', [], false);
            }
        }

        $storedUrl = (string) ($notification->data['url'] ?? '');

        return str_starts_with($storedUrl, '/') ? $storedUrl : route('app.dashboard', [], false);
    }

    public function render()
    {
        return view('livewire.layout.notification-center');
    }
}
