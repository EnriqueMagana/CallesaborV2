<?php

namespace App\Livewire\Profile;

use App\Models\NotificationPreference;
use App\Support\NotificationEventCatalog;
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
        $user = auth()->user();

        return collect(NotificationEventCatalog::all())
            ->filter(fn (array $event): bool => $user->canAny($event['permissions']))
            ->mapWithKeys(fn (array $event, string $eventKey): array => [
                str_replace('.', '_', $eventKey) => [
                    $event['icon'],
                    $event['label'],
                    $event['description'],
                ],
            ])->all();
    }

    public function render()
    {
        return view('livewire.profile.notification-preferences-form');
    }
}
