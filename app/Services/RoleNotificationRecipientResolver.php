<?php

namespace App\Services;

use App\Models\RoleNotificationSetting;
use App\Models\User;
use App\Support\NotificationEventCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class RoleNotificationRecipientResolver
{
    public function resolve(string $eventKey, Collection $legacyRecipients): Collection
    {
        if (! Schema::hasTable('role_notification_settings')) {
            return $legacyRecipients;
        }

        $settings = RoleNotificationSetting::query()->get(['role_id', 'event_keys']);
        if ($settings->isEmpty()) {
            return $legacyRecipients;
        }

        $configuredRoleIds = $settings->pluck('role_id')->map(fn ($id): int => (int) $id);
        $selectedRoleIds = $settings
            ->filter(fn (RoleNotificationSetting $setting): bool => in_array($eventKey, $setting->event_keys ?? [], true))
            ->pluck('role_id')
            ->map(fn ($id): int => (int) $id);

        $legacyWithoutConfiguredRole = $legacyRecipients
            ->loadMissing('roles:id')
            ->filter(fn (User $user): bool => $user->roles->pluck('id')->intersect($configuredRoleIds)->isEmpty());

        $configuredRecipients = $selectedRoleIds->isEmpty()
            ? collect()
            : User::query()
                ->whereNull('banned_at')
                ->whereHas('roles', fn ($query) => $query->whereIn('roles.id', $selectedRoleIds))
                ->with('roles:id')
                ->get();

        $permissions = NotificationEventCatalog::get($eventKey)['permissions'] ?? [];

        return $legacyWithoutConfiguredRole
            ->merge($configuredRecipients)
            ->filter(fn (User $user): bool => $permissions === [] || $user->canAny($permissions))
            ->unique('id')
            ->values();
    }
}
