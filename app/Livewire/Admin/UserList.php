<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

class UserList extends Component
{
    use WithPagination;

    // Filtros
    public string $search    = '';
    public string $filterRole = '';
    public string $filterStatus = 'active'; // active | trashed | all

    // Panel nivel 1 — detalle + asignar rol
    public ?int  $panelUserId = null;
    public ?User $panelUser   = null;
    public bool  $showRolePanel = false;

    // Edición de nombre/email inline en panel
    public string $editName  = '';
    public string $editEmail = '';
    public string $editPhone = '';

    // Asignación de roles
    public array $selectedRoles = [];

    // Crear usuario
    public bool   $showCreatePanel   = false;
    public string $createName        = '';
    public string $createEmail       = '';
    public string $createPhone       = '';
    public string $createPassword    = '';
    public string $createPasswordCon = '';
    public string $createRole        = '';

    // ── Crear usuario ─────────────────────────────────────────────────

    public function openCreatePanel(): void
    {
        $this->reset('createName', 'createEmail', 'createPhone', 'createPassword', 'createPasswordCon', 'createRole');
        $this->showCreatePanel = true;
    }

    public function closeCreatePanel(): void
    {
        $this->showCreatePanel = false;
        $this->resetValidation();
    }

    public function createUser(): void
    {
        $this->validate([
            'createName'        => 'required|string|max:255',
            'createEmail'       => 'required|email|max:255|unique:users,email',
            'createPhone'       => 'nullable|string|max:20',
            'createPassword'    => 'required|string|min:8|same:createPasswordCon',
            'createPasswordCon' => 'required|string',
            'createRole'        => 'nullable|string|exists:roles,name',
        ], [
            'createName.required'        => 'El nombre es obligatorio.',
            'createEmail.required'       => 'El correo es obligatorio.',
            'createEmail.unique'         => 'Este correo ya está registrado.',
            'createPassword.required'    => 'La contraseña es obligatoria.',
            'createPassword.min'         => 'Mínimo 8 caracteres.',
            'createPassword.same'        => 'Las contraseñas no coinciden.',
            'createPasswordCon.required' => 'Confirma la contraseña.',
        ]);

        $user = User::create([
            'name'     => $this->createName,
            'email'    => $this->createEmail,
            'phone'    => $this->createPhone ?: null,
            'password' => Hash::make($this->createPassword),
        ]);

        if ($this->createRole) {
            $user->assignRole($this->createRole);
        }

        $this->closeCreatePanel();
        $this->dispatch('notify', type: 'success', message: "Usuario {$user->name} creado correctamente.");
    }

    public function updatingSearch(): void    { $this->resetPage(); }
    public function updatingFilterRole(): void { $this->resetPage(); }
    public function updatingFilterStatus(): void { $this->resetPage(); }

    // ── Panel Nivel 1: detalle ─────────────────────────────────────────

    public function openPanel(int $id): void
    {
        $this->panelUserId   = $id;
        $this->panelUser     = User::withTrashed()->with('roles')->findOrFail($id);
        $this->editName      = $this->panelUser->name;
        $this->editEmail     = $this->panelUser->email;
        $this->editPhone     = $this->panelUser->phone ?? '';
        $this->selectedRoles = $this->panelUser->roles->pluck('name')->toArray();
        $this->showRolePanel = false;
    }

    public function closePanel(): void
    {
        $this->reset('panelUserId', 'panelUser', 'showRolePanel', 'editName', 'editEmail', 'editPhone', 'selectedRoles');
    }

    public function saveUserInfo(): void
    {
        $this->validate([
            'editName'  => 'required|string|max:255',
            'editEmail' => 'required|email|max:255|unique:users,email,' . $this->panelUserId,
            'editPhone' => 'nullable|string|max:20',
        ]);

        $this->panelUser->update([
            'name'  => $this->editName,
            'email' => $this->editEmail,
            'phone' => $this->editPhone,
        ]);

        $this->panelUser = $this->panelUser->fresh('roles');
        $this->dispatch('notify', type: 'success', message: 'Usuario actualizado.');
    }

    // ── Panel Nivel 2: asignar roles ──────────────────────────────────

    public function openRolePanel(): void  { $this->showRolePanel = true; }
    public function closeRolePanel(): void { $this->showRolePanel = false; }

    public function saveRoles(): void
    {
        $this->panelUser->syncRoles($this->selectedRoles);
        $this->panelUser = $this->panelUser->fresh('roles');
        $this->showRolePanel = false;
        $this->dispatch('notify', type: 'success', message: 'Roles actualizados correctamente.');
    }

    // ── Acciones de usuario ───────────────────────────────────────────

    public function confirmSoftDelete(int $id): void
    {
        $user = User::find($id);
        $this->dispatch('open-confirm',
            type: 'warning',
            title: 'Mover a papelera',
            message: "¿Mover a la papelera a <strong>{$user?->name}</strong>? Podrás restaurarlo después.",
            action: 'softDelete',
            params: [$id],
            confirmText: 'Mover a papelera',
        );
    }

    public function confirmForceDelete(int $id): void
    {
        $user = User::withTrashed()->find($id);
        $this->dispatch('open-confirm',
            type: 'danger',
            title: 'Eliminar permanentemente',
            message: "¿Eliminar <strong>{$user?->name}</strong> de forma permanente? <br><small class='text-danger'>Esta acción no se puede deshacer.</small>",
            action: 'forceDelete',
            params: [$id],
            confirmText: 'Eliminar para siempre',
        );
    }

    #[On('modal-confirmed')]
    public function handleModalConfirmed(string $action, array $params = []): void
    {
        match($action) {
            'softDelete'  => $this->softDelete($params[0]),
            'forceDelete' => $this->forceDelete($params[0]),
            default       => null,
        };
    }

    public function softDelete(int $id): void
    {
        if ($id === auth()->id()) {
            $this->dispatch('notify', type: 'error', message: 'No puedes eliminarte a ti mismo.');
            return;
        }
        User::find($id)?->delete();
        $this->closePanel();
        $this->dispatch('notify', type: 'success', message: 'Usuario movido a la papelera.');
    }

    public function restore(int $id): void
    {
        User::withTrashed()->find($id)?->restore();
        $this->dispatch('notify', type: 'success', message: 'Usuario restaurado.');
    }

    public function forceDelete(int $id): void
    {
        if ($id === auth()->id()) {
            $this->dispatch('notify', type: 'error', message: 'No puedes eliminarte a ti mismo.');
            return;
        }
        User::withTrashed()->find($id)?->forceDelete();
        $this->closePanel();
        $this->dispatch('notify', type: 'success', message: 'Usuario eliminado permanentemente.');
    }

    // ── Computed ──────────────────────────────────────────────────────

    #[Computed]
    public function roles(): \Illuminate\Database\Eloquent\Collection
    {
        return Role::orderBy('name')->get();
    }

    // ── Render ────────────────────────────────────────────────────────

    public function render()
    {
        $query = User::withTrashed()->with('roles')
            ->when($this->search, fn($q) =>
                $q->where(fn($q2) =>
                    $q2->where('name', 'like', "%{$this->search}%")
                       ->orWhere('email', 'like', "%{$this->search}%")
                )
            )
            ->when($this->filterRole, fn($q) =>
                $q->whereHas('roles', fn($r) => $r->where('name', $this->filterRole))
            );

        $query = match($this->filterStatus) {
            'trashed' => $query->onlyTrashed(),
            'all'     => $query,
            default   => $query->whereNull('deleted_at'),
        };

        $users = $query->latest()->paginate(10);

        $counts = [
            'active'  => User::whereNull('deleted_at')->count(),
            'trashed' => User::onlyTrashed()->count(),
        ];

        return view('livewire.admin.user-list', compact('users', 'counts'))
            ->layout('layouts.app');
    }
}
