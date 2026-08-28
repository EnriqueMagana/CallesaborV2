<?php

namespace App\Livewire\Layout;

use App\Models\AppNotification;
use App\Models\NotificationPreference;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
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
        $notification = $this->baseQuery()->whereKey($id)->firstOrFail();
        $notification->update(['read_at' => $notification->read_at ?? now()]);
        $url = $this->destinationFor($notification);

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

            if ($order?->type === 'delivery' && auth()->user()?->can('ver delivery')) {
                return route('app.delivery', ['order' => $order->id], false);
            }

            if ($order && auth()->user()?->can('ver ordenes')) {
                return route('app.ordenes.show', $order, false);
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
