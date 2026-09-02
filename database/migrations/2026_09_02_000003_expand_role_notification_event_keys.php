<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CREATED_EVENTS = [
        'table.order_created',
        'counter.order_created',
        'pickup.order_created',
        'kiosk.order_created',
        'delivery.order_created',
    ];

    private const READY_EVENTS = [
        'table.order_ready',
        'counter.order_ready',
        'pickup.order_ready',
        'kiosk.order_ready',
        'delivery.order_ready',
    ];

    public function up(): void
    {
        $this->expandRoleSettings();
        $this->expandUserPreferences();
    }

    public function down(): void
    {
        $this->collapseRoleSettings();
        $this->collapseUserPreferences();
    }

    private function expandRoleSettings(): void
    {
        if (! Schema::hasTable('role_notification_settings')) {
            return;
        }

        DB::table('role_notification_settings')->orderBy('id')->each(function (object $row): void {
            $events = $this->decodeArray($row->event_keys);
            $events = $this->replaceEvent($events, 'order.created', self::CREATED_EVENTS);
            $events = $this->replaceEvent($events, 'order.ready', self::READY_EVENTS);
            DB::table('role_notification_settings')->where('id', $row->id)->update([
                'event_keys' => json_encode(array_values(array_unique($events)), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]);
        });
    }

    private function expandUserPreferences(): void
    {
        if (! Schema::hasTable('notification_preferences')) {
            return;
        }

        DB::table('notification_preferences')->orderBy('id')->each(function (object $row): void {
            $preferences = $this->decodeObject($row->event_preferences ?? null);
            $preferences = $this->expandPreference($preferences, 'order_created', self::CREATED_EVENTS);
            $preferences = $this->expandPreference($preferences, 'order_ready', self::READY_EVENTS);
            DB::table('notification_preferences')->where('id', $row->id)->update([
                'event_preferences' => json_encode($preferences, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]);
        });
    }

    private function collapseRoleSettings(): void
    {
        if (! Schema::hasTable('role_notification_settings')) {
            return;
        }

        DB::table('role_notification_settings')->orderBy('id')->each(function (object $row): void {
            $events = $this->collapseEvent($this->decodeArray($row->event_keys), self::CREATED_EVENTS, 'order.created');
            $events = $this->collapseEvent($events, self::READY_EVENTS, 'order.ready');
            DB::table('role_notification_settings')->where('id', $row->id)->update([
                'event_keys' => json_encode(array_values(array_unique($events)), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]);
        });
    }

    private function collapseUserPreferences(): void
    {
        if (! Schema::hasTable('notification_preferences')) {
            return;
        }

        DB::table('notification_preferences')->orderBy('id')->each(function (object $row): void {
            $preferences = $this->collapsePreference($this->decodeObject($row->event_preferences ?? null), 'order_created', self::CREATED_EVENTS);
            $preferences = $this->collapsePreference($preferences, 'order_ready', self::READY_EVENTS);
            DB::table('notification_preferences')->where('id', $row->id)->update([
                'event_preferences' => json_encode($preferences, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]);
        });
    }

    private function replaceEvent(array $events, string $legacy, array $replacements): array
    {
        if (! in_array($legacy, $events, true)) {
            return $events;
        }

        return array_merge(array_values(array_diff($events, [$legacy])), $replacements);
    }

    private function collapseEvent(array $events, array $expanded, string $legacy): array
    {
        if (array_intersect($events, $expanded) !== []) {
            $events[] = $legacy;
        }

        return array_values(array_diff($events, $expanded));
    }

    private function expandPreference(array $preferences, string $legacy, array $events): array
    {
        if (! array_key_exists($legacy, $preferences)) {
            return $preferences;
        }

        foreach ($events as $event) {
            $preferences[str_replace('.', '_', $event)] ??= (bool) $preferences[$legacy];
        }
        unset($preferences[$legacy]);

        return $preferences;
    }

    private function collapsePreference(array $preferences, string $legacy, array $events): array
    {
        $keys = array_map(fn (string $event): string => str_replace('.', '_', $event), $events);
        $present = array_intersect_key($preferences, array_flip($keys));
        if ($present !== []) {
            $preferences[$legacy] = ! in_array(false, array_map('boolval', $present), true);
        }
        foreach ($keys as $key) {
            unset($preferences[$key]);
        }

        return $preferences;
    }

    private function decodeArray(mixed $value): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return is_array($decoded) ? array_values($decoded) : [];
    }

    private function decodeObject(mixed $value): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return is_array($decoded) ? $decoded : [];
    }
};
