<?php

namespace App\Livewire\Admin;

use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionManager extends Component
{
    public function mount(): void
    {
        abort_unless(
            auth()->user()?->can('gestionar roles') || auth()->user()?->can('gestionar permisos'),
            403
        );

        if (! auth()->user()?->can('gestionar roles')) {
            $this->activeTab = 'permissions';
        }
    }

    // ── Vista activa: 'roles' | 'permissions' ─────────────────────────
    public string $activeTab = 'roles';

    // ── Panel de rol ──────────────────────────────────────────────────
    public ?int $rolePanel = null;  // id del rol seleccionado

    public ?Role $selectedRole = null;

    public bool $showRoleForm = false; // nivel 2: crear/editar rol

    public string $roleName = '';

    public string $roleIcon = 'bx-user';

    public array $rolePermissions = [];  // permisos del rol en edición

    public array $roleIconOptions = [
        'bx-user' => 'Persona',
        'bx-shield' => 'Seguridad',
        'bx-crown' => 'Dirección',
        'bx-briefcase' => 'Gerencia',
        'bx-money' => 'Caja',
        'bx-store' => 'Ventanilla',
        'bx-dish' => 'Servicio de mesa',
        'bx-restaurant' => 'Cocina',
        'bx-cycling' => 'Reparto',
        'bx-headphone' => 'Atención',
        'bx-package' => 'Inventario',
        'bx-bar-chart-alt-2' => 'Supervisión',
    ];

    // ── Panel de permiso ──────────────────────────────────────────────
    public ?int $permPanel = null;

    public ?Permission $selectedPerm = null;

    public bool $showPermForm = false;

    public string $permName = '';

    public string $permGroup = '';

    public string $permDescription = '';

    // ── Módulos / grupos disponibles ──────────────────────────────────
    public array $groups = [
        'punto_venta', 'usuarios', 'clientes', 'menu', 'promociones', 'ordenes', 'mesas', 'caja',
        'reservas', 'delivery', 'inventario', 'reportes', 'configuracion', 'kiosco', 'desarrollo',
    ];

    public array $groupDefinitions = [
        'punto_venta' => ['label' => 'Punto de venta', 'icon' => 'bx-store-alt', 'tone' => 'success', 'description' => 'Acceso al terminal de venta y cobro directo.'],
        'usuarios' => ['label' => 'Usuarios y roles', 'icon' => 'bx-group', 'tone' => 'primary', 'description' => 'Personal, cuentas y control de acceso.'],
        'clientes' => ['label' => 'Clientes', 'icon' => 'bx-user-pin', 'tone' => 'info', 'description' => 'Directorio y datos de clientes.'],
        'menu' => ['label' => 'Menú', 'icon' => 'bx-food-menu', 'tone' => 'success', 'description' => 'Productos, categorías y complementos.'],
        'promociones' => ['label' => 'Promociones', 'icon' => 'bx-purchase-tag-alt', 'tone' => 'warning', 'description' => 'Campañas, combos, vigencias y canales de publicación.'],
        'ordenes' => ['label' => 'Órdenes', 'icon' => 'bx-receipt', 'tone' => 'info', 'description' => 'Pedidos, estados y tickets.'],
        'mesas' => ['label' => 'Mesas', 'icon' => 'bx-table', 'tone' => 'warning', 'description' => 'Servicio, cuentas y distribución del salón.'],
        'caja' => ['label' => 'Caja', 'icon' => 'bx-wallet', 'tone' => 'danger', 'description' => 'Apertura, cobro, gastos y cortes.'],
        'reservas' => ['label' => 'Reservaciones', 'icon' => 'bx-calendar-check', 'tone' => 'primary', 'description' => 'Calendario y atención de reservaciones.'],
        'delivery' => ['label' => 'Delivery', 'icon' => 'bx-cycling', 'tone' => 'warning', 'description' => 'Asignación y entrega de pedidos.'],
        'inventario' => ['label' => 'Inventario', 'icon' => 'bx-package', 'tone' => 'success', 'description' => 'Insumos, ajustes y compras.'],
        'reportes' => ['label' => 'Reportes', 'icon' => 'bx-bar-chart-alt-2', 'tone' => 'secondary', 'description' => 'Indicadores, históricos y exportaciones.'],
        'configuracion' => ['label' => 'Configuración', 'icon' => 'bx-cog', 'tone' => 'secondary', 'description' => 'Negocio, navegación y reglas del sistema.'],
        'kiosco' => ['label' => 'Kioscos', 'icon' => 'bx-devices', 'tone' => 'info', 'description' => 'Terminales de autoservicio.'],
        'desarrollo' => ['label' => 'Herramientas técnicas', 'icon' => 'bx-code-alt', 'tone' => 'danger', 'description' => 'Diagnóstico y funciones internas de alto privilegio.'],
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
        $order = array_flip($this->groups);

        return Permission::orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission): string => $permission->group ?: 'sin_modulo')
            ->sortBy(fn ($permissions, string $group): int => $order[$group] ?? 999);
    }

    // ── Roles: acciones ───────────────────────────────────────────────

    public function selectRole(int $id): void
    {
        $this->authorizePermission('gestionar roles');
        $this->selectedRole = Role::with('permissions')->withCount('users')->findOrFail($id);
        $this->rolePanel = $id;
        $this->showRoleForm = false;
        $this->rolePermissions = $this->selectedRole->permissions->pluck('name')->toArray();
    }

    public function closeRolePanel(): void
    {
        $this->reset('rolePanel', 'selectedRole', 'showRoleForm', 'roleName', 'roleIcon', 'rolePermissions');
    }

    public function openCreateRole(): void
    {
        $this->authorizePermission('gestionar roles');
        $this->rolePanel = null;
        $this->selectedRole = null;
        $this->roleName = '';
        $this->roleIcon = 'bx-user';
        $this->rolePermissions = [];
        $this->showRoleForm = true;
    }

    public function openEditRole(): void
    {
        $this->authorizePermission('gestionar roles');
        $this->showRoleForm = true;
        $this->roleName = $this->selectedRole->name;
        $this->roleIcon = $this->selectedRole->icon ?: 'bx-user';
    }

    public function closeRoleForm(): void
    {
        $this->resetValidation();

        if (! $this->rolePanel || ! $this->selectedRole) {
            $this->closeRolePanel();

            return;
        }

        $this->showRoleForm = false;
        $this->roleName = $this->selectedRole->name;
        $this->roleIcon = $this->selectedRole->icon ?: 'bx-user';
        $this->rolePermissions = $this->selectedRole->permissions->pluck('name')->toArray();
    }

    public function saveRole(): void
    {
        $this->authorizePermission('gestionar roles');
        $this->validate([
            'roleName' => [
                'required',
                'string',
                'min:2',
                'max:50',
                Rule::unique('roles', 'name')->ignore($this->rolePanel),
            ],
            'roleIcon' => ['required', 'string', Rule::in(array_keys($this->roleIconOptions))],
        ]);

        if ($this->rolePanel) {
            abort_if(in_array($this->selectedRole->name, ['owner', 'super-admin'], true)
                && $this->roleName !== $this->selectedRole->name, 403);
            // Editar
            $this->selectedRole->update(['name' => $this->roleName, 'icon' => $this->roleIcon]);
            $this->selectedRole->syncPermissions($this->rolePermissions);
            $this->dispatch('notify', type: 'success', message: 'Rol actualizado.');
        } else {
            // Crear
            $role = Role::create(['name' => $this->roleName, 'guard_name' => 'web', 'icon' => $this->roleIcon]);
            $role->syncPermissions($this->rolePermissions);
            $this->dispatch('notify', type: 'success', message: 'Rol creado correctamente.');
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->closeRolePanel();
    }

    public function confirmDeleteRole(int $id): void
    {
        $this->authorizePermission('gestionar roles');
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
        $this->authorizePermission('gestionar roles');
        $role = Role::find($id);
        abort_if($role && in_array($role->name, ['owner', 'super-admin', 'admin'], true), 403);
        $role?->delete();
        $this->closeRolePanel();
        $this->dispatch('notify', type: 'success', message: 'Rol eliminado.');
    }

    // ── Permisos: acciones ────────────────────────────────────────────

    public function selectPerm(int $id): void
    {
        $this->authorizePermission('gestionar permisos');
        $this->selectedPerm = Permission::with('roles')->findOrFail($id);
        $this->permPanel = $id;
        $this->showPermForm = false;
        $this->permName = $this->selectedPerm->name;
        $this->permGroup = $this->selectedPerm->group ?? '';
        $this->permDescription = $this->selectedPerm->description ?? '';
    }

    public function closePermPanel(): void
    {
        $this->reset('permPanel', 'selectedPerm', 'showPermForm', 'permName', 'permGroup', 'permDescription');
    }

    public function openCreatePerm(): void
    {
        $this->authorizePermission('gestionar permisos');
        $this->closePermPanel();
        $this->showPermForm = true;
        $this->permName = '';
        $this->permGroup = '';
        $this->permDescription = '';
    }

    public function openEditPerm(): void
    {
        $this->authorizePermission('gestionar permisos');
        abort_unless($this->selectedPerm, 404);
        $this->showPermForm = true;
    }

    public function closePermForm(): void
    {
        $this->resetValidation();

        if (! $this->permPanel || ! $this->selectedPerm) {
            $this->closePermPanel();

            return;
        }

        $this->showPermForm = false;
        $this->permName = $this->selectedPerm->name;
        $this->permGroup = $this->selectedPerm->group ?? '';
        $this->permDescription = $this->selectedPerm->description ?? '';
    }

    public function savePerm(): void
    {
        $this->authorizePermission('gestionar permisos');
        $this->validate([
            'permName' => [
                'required',
                'string',
                'min:2',
                'max:100',
                Rule::unique('permissions', 'name')->ignore($this->permPanel),
            ],
            'permGroup' => ['required', 'string', Rule::in($this->groups)],
            'permDescription' => ['required', 'string', 'min:15', 'max:600'],
        ], [
            'permDescription.required' => 'Explica exactamente qué acción habilita este permiso.',
            'permDescription.min' => 'La descripción debe ser específica y tener al menos 15 caracteres.',
        ]);

        if ($this->permPanel) {
            $this->selectedPerm->update([
                'name' => $this->permName,
                'group' => $this->permGroup,
                'description' => trim($this->permDescription),
            ]);
            $this->dispatch('notify', type: 'success', message: 'Permiso actualizado.');
        } else {
            Permission::create([
                'name' => $this->permName,
                'guard_name' => 'web',
                'group' => $this->permGroup,
                'description' => trim($this->permDescription),
            ]);
            $this->dispatch('notify', type: 'success', message: 'Permiso creado.');
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->closePermPanel();
    }

    public function confirmDeletePerm(int $id): void
    {
        $this->authorizePermission('gestionar permisos');
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
        $this->authorizePermission('gestionar permisos');
        Permission::find($id)?->delete();
        $this->closePermPanel();
        $this->dispatch('notify', type: 'success', message: 'Permiso eliminado.');
    }

    #[On('modal-confirmed')]
    public function handleModalConfirmed(string $action, array $params = []): void
    {
        match ($action) {
            'deleteRole' => $this->deleteRole($params[0]),
            'deletePerm' => $this->deletePerm($params[0]),
            default => null,
        };
    }

    // ── Render ────────────────────────────────────────────────────────

    public function render()
    {
        return view('livewire.admin.role-permission-manager')
            ->layout('layouts.app');
    }

    private function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
