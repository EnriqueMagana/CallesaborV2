<?php

namespace App\Livewire\Admin;

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionManager extends Component
{
    // ── Vista activa: 'roles' | 'permissions' ─────────────────────────
    public string $activeTab = 'roles';

    // ── Panel de rol ──────────────────────────────────────────────────
    public ?int  $rolePanel     = null;  // id del rol seleccionado
    public ?Role $selectedRole  = null;
    public bool  $showRoleForm  = false; // nivel 2: crear/editar rol
    public string $roleName     = '';
    public array  $rolePermissions = [];  // permisos del rol en edición

    // ── Panel de permiso ──────────────────────────────────────────────
    public ?int        $permPanel     = null;
    public ?Permission $selectedPerm  = null;
    public bool        $showPermForm  = false;
    public string      $permName      = '';
    public string      $permGroup     = '';

    // ── Módulos / grupos disponibles ──────────────────────────────────
    public array $groups = [
        'usuarios', 'menu', 'ordenes', 'mesas', 'caja', 'reportes', 'configuracion', 'kiosco',
    ];

    // ── Computed ──────────────────────────────────────────────────────

    #[Computed]
    public function roles()
    {
        return Role::withCount(['permissions', 'users'])->orderBy('name')->get();
    }

    #[Computed]
    public function permissionsByGroup()
    {
        return Permission::orderBy('group')->orderBy('name')
            ->get()
            ->groupBy('group');
    }

    // ── Roles: acciones ───────────────────────────────────────────────

    public function selectRole(int $id): void
    {
        $this->selectedRole   = Role::with('permissions')->find($id);
        $this->rolePanel      = $id;
        $this->showRoleForm   = false;
        $this->rolePermissions = $this->selectedRole->permissions->pluck('name')->toArray();
    }

    public function closeRolePanel(): void
    {
        $this->reset('rolePanel', 'selectedRole', 'showRoleForm', 'roleName', 'rolePermissions');
    }

    public function openCreateRole(): void
    {
        $this->closeRolePanel();
        $this->showRoleForm = true;
        $this->roleName = '';
        $this->rolePermissions = [];
    }

    public function openEditRole(): void
    {
        $this->showRoleForm   = true;
        $this->roleName       = $this->selectedRole->name;
    }

    public function saveRole(): void
    {
        $this->validate([
            'roleName' => 'required|string|min:2|max:50',
        ]);

        if ($this->rolePanel) {
            abort_if(in_array($this->selectedRole->name, ['owner', 'super-admin'], true)
                && $this->roleName !== $this->selectedRole->name, 403);
            // Editar
            $this->selectedRole->update(['name' => $this->roleName]);
            $this->selectedRole->syncPermissions($this->rolePermissions);
            $this->dispatch('notify', type: 'success', message: 'Rol actualizado.');
        } else {
            // Crear
            $role = Role::create(['name' => $this->roleName, 'guard_name' => 'web']);
            $role->syncPermissions($this->rolePermissions);
            $this->dispatch('notify', type: 'success', message: 'Rol creado correctamente.');
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->closeRolePanel();
    }

    public function confirmDeleteRole(int $id): void
    {
        $role = Role::find($id);
        abort_if($role && in_array($role->name, ['owner', 'super-admin', 'admin'], true), 403);
        $this->dispatch('open-confirm',
            type: 'danger',
            title: 'Eliminar rol',
            message: "¿Eliminar el rol <strong>{$role?->name}</strong>? Los usuarios perderán este rol.",
            action: 'deleteRole',
            params: [$id],
            confirmText: 'Sí, eliminar',
        );
    }

    public function deleteRole(int $id): void
    {
        $role = Role::find($id);
        abort_if($role && in_array($role->name, ['owner', 'super-admin', 'admin'], true), 403);
        $role?->delete();
        $this->closeRolePanel();
        $this->dispatch('notify', type: 'success', message: 'Rol eliminado.');
    }

    // ── Permisos: acciones ────────────────────────────────────────────

    public function selectPerm(int $id): void
    {
        $this->selectedPerm = Permission::find($id);
        $this->permPanel    = $id;
        $this->showPermForm = false;
        $this->permName     = $this->selectedPerm->name;
        $this->permGroup    = $this->selectedPerm->group ?? '';
    }

    public function closePermPanel(): void
    {
        $this->reset('permPanel', 'selectedPerm', 'showPermForm', 'permName', 'permGroup');
    }

    public function openCreatePerm(): void
    {
        $this->closePermPanel();
        $this->showPermForm = true;
        $this->permName  = '';
        $this->permGroup = '';
    }

    public function savePerm(): void
    {
        $this->validate([
            'permName'  => 'required|string|min:2|max:100',
            'permGroup' => 'required|string',
        ]);

        if ($this->permPanel) {
            $this->selectedPerm->update([
                'name'  => $this->permName,
                'group' => $this->permGroup,
            ]);
            $this->dispatch('notify', type: 'success', message: 'Permiso actualizado.');
        } else {
            Permission::create([
                'name'       => $this->permName,
                'guard_name' => 'web',
                'group'      => $this->permGroup,
            ]);
            $this->dispatch('notify', type: 'success', message: 'Permiso creado.');
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->closePermPanel();
    }

    public function confirmDeletePerm(int $id): void
    {
        $perm = Permission::find($id);
        $this->dispatch('open-confirm',
            type: 'danger',
            title: 'Eliminar permiso',
            message: "¿Eliminar el permiso <strong>{$perm?->name}</strong>? Se quitará de todos los roles que lo tengan.",
            action: 'deletePerm',
            params: [$id],
            confirmText: 'Sí, eliminar',
        );
    }

    public function deletePerm(int $id): void
    {
        Permission::find($id)?->delete();
        $this->closePermPanel();
        $this->dispatch('notify', type: 'success', message: 'Permiso eliminado.');
    }

    #[On('modal-confirmed')]
    public function handleModalConfirmed(string $action, array $params = []): void
    {
        match($action) {
            'deleteRole' => $this->deleteRole($params[0]),
            'deletePerm' => $this->deletePerm($params[0]),
            default      => null,
        };
    }

    // ── Render ────────────────────────────────────────────────────────

    public function render()
    {
        return view('livewire.admin.role-permission-manager')
            ->layout('layouts.app');
    }
}
