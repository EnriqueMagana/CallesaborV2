<?php

namespace App\Livewire\Admin;

use App\Models\RoleNotificationSetting;
use App\Support\NotificationEventCatalog;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class RoleNotificationManager extends Component
{
    public ?int $notificationRoleId = null;

    public array $roleNotificationEvents = [];

    public bool $notificationRoleConfigured = false;

    public function mount(): void
    {
        $this->authorizeManagement();

        if ($firstRole = Role::query()->orderBy('name')->first()) {
            $this->selectNotificationRole($firstRole->id);
        }
    }

    #[Computed]
    public function roles()
    {
        return Role::query()
            ->withCount(['permissions', 'users'])
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function configuredRolesCount(): int
    {
        return RoleNotificationSetting::query()->count();
    }

    #[Computed]
    public function notificationEventGroups(): array
    {
        return NotificationEventCatalog::definitions();
    }

    #[Computed]
    public function notificationRole(): ?Role
    {
        return $this->notificationRoleId
            ? Role::with(['permissions', 'users'])->find($this->notificationRoleId)
            : null;
    }

    public function selectNotificationRole(int $id): void
    {
        $this->authorizeManagement();
        $role = Role::findOrFail($id);
        $setting = RoleNotificationSetting::query()->where('role_id', $role->id)->first();

        $this->notificationRoleId = $role->id;
        $this->roleNotificationEvents = $setting?->event_keys ?? [];
        $this->notificationRoleConfigured = $setting !== null;
        $this->resetValidation('roleNotificationEvents');
        unset($this->notificationRole);
    }

    public function saveRoleNotifications(): void
    {
        $this->authorizeManagement();
        $role = Role::with('permissions')->findOrFail($this->notificationRoleId);
        $selected = collect($this->roleNotificationEvents)
            ->filter(fn ($eventKey): bool => is_string($eventKey))
            ->unique()
            ->values();
        $unknown = $selected->diff(NotificationEventCatalog::keys());
        $incompatible = $selected->reject(fn (string $eventKey): bool => $this->roleSupportsEvent($role, $eventKey));

        if ($unknown->isNotEmpty() || $incompatible->isNotEmpty()) {
            throw ValidationException::withMessages([
                'roleNotificationEvents' => 'El rol no tiene los permisos necesarios para una o más notificaciones seleccionadas.',
            ]);
        }

        RoleNotificationSetting::query()->updateOrCreate(
            ['role_id' => $role->id],
            ['event_keys' => $selected->all(), 'updated_by' => auth()->id()]
        );

        $this->roleNotificationEvents = $selected->all();
        $this->notificationRoleConfigured = true;
        unset($this->notificationRole, $this->configuredRolesCount);
        $this->dispatch('notify', type: 'success', message: "Notificaciones de {$role->name} actualizadas.");
    }

    public function restoreAutomaticRoleNotifications(): void
    {
        $this->authorizeManagement();
        RoleNotificationSetting::query()->where('role_id', $this->notificationRoleId)->delete();
        $this->roleNotificationEvents = [];
        $this->notificationRoleConfigured = false;
        unset($this->notificationRole, $this->configuredRolesCount);
        $this->dispatch('notify', type: 'info', message: 'El rol volvió al comportamiento automático anterior.');
    }

    public function roleSupportsEvent(Role $role, string $eventKey): bool
    {
        if (in_array($role->name, ['owner', 'super-admin'], true)) {
            return true;
        }

        $permissions = NotificationEventCatalog::get($eventKey)['permissions'] ?? [];

        return $permissions === [] || $role->hasAnyPermission($permissions);
    }

    public function render()
    {
        return view('livewire.admin.role-notification-manager')
            ->layout('layouts.app');
    }

    private function authorizeManagement(): void
    {
        abort_unless(auth()->user()?->can('gestionar notificaciones por rol'), 403);
    }
}
