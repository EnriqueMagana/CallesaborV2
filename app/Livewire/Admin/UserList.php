<?php

namespace App\Livewire\Admin;

use App\Mail\UserInvitationMail;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;
use Throwable;

class UserList extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    public string $filterRole = '';

    public string $filterStatus = 'active';

    public ?int $panelUserId = null;

    public ?User $panelUser = null;

    public bool $showRolePanel = false;

    public string $editName = '';

    public string $editEmail = '';

    public string $editPhone = '';

    public array $selectedRoles = [];

    public bool $showCreatePanel = false;

    public string $createName = '';

    public string $createEmail = '';

    public string $createPhone = '';

    public string $createPassword = '';

    public string $createPasswordCon = '';

    public string $createRole = '';

    public bool $showInvitationPanel = false;

    public string $inviteEmail = '';

    public string $inviteRole = '';

    public bool $showBanPanel = false;

    public ?int $banUserId = null;

    public string $banReason = '';

    public function mount(): void
    {
        $this->authorize('ver usuarios');
    }

    public function openCreatePanel(): void
    {
        $this->authorize('crear usuarios');
        $this->reset('createName', 'createEmail', 'createPhone', 'createPassword', 'createPasswordCon', 'createRole');
        $this->resetValidation();
        $this->showCreatePanel = true;
    }

    public function closeCreatePanel(): void
    {
        $this->showCreatePanel = false;
        $this->resetValidation();
    }

    public function createUser(): void
    {
        $this->authorize('crear usuarios');
        $allowedRoles = $this->roles->pluck('name')->all();

        $this->validate([
            'createName' => ['required', 'string', 'max:255', 'regex:/^[\pL\pM\s\'\-.]+$/u'],
            'createEmail' => ['required', 'email', 'max:255', 'unique:users,email'],
            'createPhone' => ['nullable', 'string', 'max:20'],
            'createPassword' => ['required', 'string', 'min:8', 'same:createPasswordCon'],
            'createPasswordCon' => ['required', 'string'],
            'createRole' => ['required', 'string', Rule::in($allowedRoles)],
        ], [
            'createName.required' => 'El nombre es obligatorio.',
            'createName.regex' => 'El nombre solo puede contener letras, espacios, apóstrofes, puntos y guiones.',
            'createEmail.required' => 'El correo es obligatorio.',
            'createEmail.unique' => 'Este correo ya está registrado, incluso si está en la papelera.',
            'createPassword.required' => 'La contraseña es obligatoria.',
            'createPassword.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'createPassword.same' => 'Las contraseñas no coinciden.',
            'createPasswordCon.required' => 'Confirma la contraseña.',
            'createRole.required' => 'Selecciona el rol inicial del usuario.',
            'createRole.in' => 'No tienes autorización para asignar ese rol.',
        ]);

        $user = DB::transaction(function (): User {
            $user = User::create([
                'name' => trim($this->createName),
                'email' => mb_strtolower(trim($this->createEmail)),
                'phone' => $this->createPhone ? trim($this->createPhone) : null,
                'password' => Hash::make($this->createPassword),
            ]);
            $user->assignRole($this->createRole);

            return $user;
        });

        $this->closeCreatePanel();
        $this->dispatch('notify', type: 'success', message: "{$user->name} fue creado con el rol {$this->roleLabel($this->createRole)}.");
    }

    public function openInvitationPanel(): void
    {
        $this->authorize('crear usuarios');
        $this->reset('inviteEmail', 'inviteRole');
        $this->resetValidation();
        $this->showInvitationPanel = true;
    }

    public function closeInvitationPanel(): void
    {
        $this->showInvitationPanel = false;
        $this->reset('inviteEmail', 'inviteRole');
        $this->resetValidation();
    }

    public function sendUserInvitation(): void
    {
        $this->authorize('crear usuarios');
        $allowedRoles = $this->roles->pluck('name')->all();

        $validated = $this->validate([
            'inviteEmail' => ['required', 'string', 'email:rfc', 'max:254', Rule::unique('users', 'email')],
            'inviteRole' => ['required', 'string', Rule::in($allowedRoles)],
        ], [
            'inviteEmail.required' => 'Escribe el correo de la persona que deseas invitar.',
            'inviteEmail.email' => 'Escribe una dirección de correo válida.',
            'inviteEmail.unique' => 'Ya existe una cuenta con este correo, incluso si está en la papelera.',
            'inviteRole.required' => 'Selecciona el rol que desempeñará la persona.',
            'inviteRole.in' => 'No tienes autorización para asignar ese rol.',
        ]);

        if (config('mail.default') === 'resend' && blank(config('services.resend.key'))) {
            $this->addError('inviteEmail', 'RESEND_API_KEY no está configurada.');

            return;
        }

        if (str_ends_with(strtolower((string) config('mail.from.address')), '@example.com')) {
            $this->addError('inviteEmail', 'MAIL_FROM_ADDRESS todavía usa example.com. Configura un remitente autorizado.');

            return;
        }

        $rateLimitKey = 'send-user-invitation:'.auth()->id();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            $this->addError('inviteEmail', "Alcanzaste el límite de invitaciones. Intenta de nuevo en {$seconds} segundos.");

            return;
        }
        RateLimiter::hit($rateLimitKey, 60);

        $email = mb_strtolower(trim($validated['inviteEmail']));
        $role = Role::query()->where('name', $validated['inviteRole'])->firstOrFail();
        $roleLabel = $this->roleLabel($role->name);
        $token = Str::random(64);
        $tokenHash = UserInvitation::hashToken($token);
        $expiresAt = now()->addHour()->startOfSecond();

        $invitation = UserInvitation::query()->updateOrCreate(
            ['email' => $email],
            [
                'role_id' => $role->id,
                'token_hash' => $tokenHash,
                'invited_by' => auth()->id(),
                'expires_at' => $expiresAt,
                'accepted_at' => null,
                'accepted_user_id' => null,
            ],
        );

        $invitationUrl = route('invitations.accept', [
            'invitation' => $invitation->id,
            'token' => $token,
        ]);

        try {
            Mail::to($email)->send(new UserInvitationMail(
                invitationUrl: $invitationUrl,
                roleLabel: $roleLabel,
                invitedByName: auth()->user()?->name ?? 'Administración',
                expiresAt: $expiresAt->translatedFormat('d/m/Y H:i'),
            ));
        } catch (Throwable $exception) {
            UserInvitation::query()
                ->whereKey($invitation->id)
                ->where('token_hash', $tokenHash)
                ->delete();

            Log::warning('Falló el envío de una invitación de usuario.', [
                'email' => $email,
                'role' => $role->name,
                'exception' => $exception::class,
            ]);

            $this->addError('inviteEmail', 'No se pudo enviar la invitación. Revisa la configuración del remitente e intenta nuevamente.');

            return;
        }

        $this->closeInvitationPanel();
        $this->dispatch('notify', type: 'success', message: "Invitación enviada a {$email} para el rol {$roleLabel}. El enlace vencerá en 1 hora.");
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterRole(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'filterRole');
        $this->filterStatus = 'active';
        $this->resetPage();
    }

    public function openPanel(int $id): void
    {
        $this->authorize('ver usuarios');
        $this->panelUserId = $id;
        $this->panelUser = User::withTrashed()->with(['roles.permissions', 'bannedBy'])->findOrFail($id);
        $this->editName = $this->panelUser->name;
        $this->editEmail = $this->panelUser->email;
        $this->editPhone = $this->panelUser->phone ?? '';
        $this->selectedRoles = $this->panelUser->roles->pluck('name')->all();
        $this->showRolePanel = false;
        $this->resetValidation();
    }

    public function closePanel(): void
    {
        $this->reset('panelUserId', 'panelUser', 'showRolePanel', 'editName', 'editEmail', 'editPhone', 'selectedRoles');
    }

    public function saveUserInfo(): void
    {
        $this->authorize('editar usuarios');
        $user = $this->targetUser($this->panelUserId);
        $this->ensureManageable($user);

        $this->validate([
            'editName' => ['required', 'string', 'max:255', 'regex:/^[\pL\pM\s\'\-.]+$/u'],
            'editEmail' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'editPhone' => ['nullable', 'string', 'max:20'],
        ]);

        $user->update([
            'name' => trim($this->editName),
            'email' => mb_strtolower(trim($this->editEmail)),
            'phone' => $this->editPhone ? trim($this->editPhone) : null,
        ]);

        $this->openPanel($user->id);
        $this->dispatch('notify', type: 'success', message: 'Datos del usuario actualizados.');
    }

    public function openRolePanel(): void
    {
        $this->authorize('gestionar roles');
        $this->ensureManageable($this->targetUser($this->panelUserId));
        $this->showRolePanel = true;
    }

    public function closeRolePanel(): void
    {
        $this->showRolePanel = false;
        $this->resetValidation();
    }

    public function saveRoles(): void
    {
        $this->authorize('gestionar roles');
        $user = $this->targetUser($this->panelUserId);
        $this->ensureManageable($user);
        $allowedRoles = $this->roles->pluck('name')->all();

        $this->validate([
            'selectedRoles' => ['required', 'array', 'min:1'],
            'selectedRoles.*' => ['required', Rule::in($allowedRoles)],
        ], [
            'selectedRoles.required' => 'El usuario debe conservar al menos un rol.',
            'selectedRoles.min' => 'El usuario debe conservar al menos un rol.',
            'selectedRoles.*.in' => 'No tienes autorización para asignar uno de los roles seleccionados.',
        ]);

        $user->syncRoles($this->selectedRoles);
        $this->showRolePanel = false;
        $this->openPanel($user->id);
        $this->dispatch('notify', type: 'success', message: 'Roles actualizados correctamente.');
    }

    public function openBanPanel(int $id): void
    {
        $this->authorize('bloquear usuarios');
        $user = User::findOrFail($id);
        if (! $this->canChangeAvailability($user, 'bloquear')) {
            return;
        }
        $this->ensureManageable($user);

        $this->banUserId = $id;
        $this->banReason = '';
        $this->showBanPanel = true;
        $this->resetValidation();
    }

    public function closeBanPanel(): void
    {
        $this->reset('showBanPanel', 'banUserId', 'banReason');
        $this->resetValidation();
    }

    public function banUser(): void
    {
        $this->authorize('bloquear usuarios');
        $user = User::findOrFail($this->banUserId);
        if (! $this->canChangeAvailability($user, 'bloquear')) {
            return;
        }
        $this->ensureManageable($user);

        $this->validate([
            'banReason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'banReason.required' => 'Indica el motivo del bloqueo para dejar una referencia administrativa.',
            'banReason.min' => 'Describe el motivo con al menos 5 caracteres.',
            'banReason.max' => 'El motivo no puede superar 500 caracteres.',
        ]);

        $user->forceFill([
            'banned_at' => now(),
            'banned_by' => auth()->id(),
            'ban_reason' => trim($this->banReason),
            'remember_token' => null,
            'active_session_token_hash' => null,
        ])->save();
        $this->revokeSessions($user);
        $this->closeBanPanel();
        $this->closePanel();
        $this->dispatch('notify', type: 'success', message: "{$user->name} fue bloqueado y sus sesiones se cerraron.");
    }

    public function confirmUnban(int $id): void
    {
        $this->authorize('bloquear usuarios');
        $user = User::findOrFail($id);
        $this->ensureManageable($user);
        $this->dispatch('open-confirm',
            type: 'success',
            title: 'Desbloquear usuario',
            message: 'La cuenta de <strong>'.e($user->name).'</strong> podrá iniciar sesión nuevamente.',
            action: 'unbanUser',
            params: [$id],
            confirmText: 'Desbloquear cuenta',
        );
    }

    public function confirmSoftDelete(int $id): void
    {
        $this->authorize('eliminar usuarios');
        $user = User::findOrFail($id);
        if (! $this->canChangeAvailability($user, 'eliminar')) {
            return;
        }
        $this->ensureManageable($user);
        $this->dispatch('open-confirm',
            type: 'warning',
            title: 'Eliminar usuario',
            message: '<strong>'.e($user->name).'</strong> se moverá a la papelera y perderá acceso. Podrás restaurarlo posteriormente.',
            action: 'softDelete',
            params: [$id],
            confirmText: 'Mover a papelera',
        );
    }

    public function confirmRestore(int $id): void
    {
        $this->authorize('eliminar usuarios');
        $user = User::onlyTrashed()->findOrFail($id);
        $this->ensureManageable($user);
        $this->dispatch('open-confirm',
            type: 'success',
            title: 'Restaurar usuario',
            message: 'La cuenta de <strong>'.e($user->name).'</strong> volverá al directorio. Si estaba bloqueada, conservará ese estado.',
            action: 'restoreUser',
            params: [$id],
            confirmText: 'Restaurar usuario',
        );
    }

    #[On('modal-confirmed')]
    public function handleModalConfirmed(string $action, array $params = []): void
    {
        match ($action) {
            'softDelete' => $this->softDelete((int) ($params[0] ?? 0)),
            'restoreUser' => $this->restore((int) ($params[0] ?? 0)),
            'unbanUser' => $this->unbanUser((int) ($params[0] ?? 0)),
            default => null,
        };
    }

    public function softDelete(int $id): void
    {
        $this->authorize('eliminar usuarios');
        $user = User::findOrFail($id);
        if (! $this->canChangeAvailability($user, 'eliminar')) {
            return;
        }
        $this->ensureManageable($user);
        $this->revokeSessions($user);
        $user->delete();
        $this->closePanel();
        $this->dispatch('notify', type: 'success', message: 'Usuario eliminado y disponible en la papelera.');
    }

    public function restore(int $id): void
    {
        $this->authorize('eliminar usuarios');
        $user = User::onlyTrashed()->findOrFail($id);
        $this->ensureManageable($user);
        $user->restore();
        $this->closePanel();
        $this->dispatch('notify', type: 'success', message: 'Usuario restaurado correctamente.');
    }

    public function unbanUser(int $id): void
    {
        $this->authorize('bloquear usuarios');
        $user = User::findOrFail($id);
        $this->ensureManageable($user);
        $user->update(['banned_at' => null, 'banned_by' => null, 'ban_reason' => null]);
        $this->closePanel();
        $this->dispatch('notify', type: 'success', message: "{$user->name} puede iniciar sesión nuevamente.");
    }

    #[Computed]
    public function roles(): Collection
    {
        $query = Role::query()->with('permissions')->orderBy('name');
        if (! auth()->user()->hasAnyRole(['owner', 'super-admin'])) {
            $query->whereNotIn('name', ['owner', 'super-admin']);
        }

        return $query->get();
    }

    #[Computed]
    public function banTarget(): ?User
    {
        return $this->banUserId ? User::find($this->banUserId) : null;
    }

    public function render()
    {
        $query = User::withTrashed()->with(['roles', 'bannedBy'])
            ->when($this->search, fn ($q) => $q->where(fn ($search) => $search
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%")))
            ->when($this->filterRole, fn ($q) => $q->whereHas('roles', fn ($role) => $role->where('name', $this->filterRole)));

        $query = match ($this->filterStatus) {
            'banned' => $query->whereNull('deleted_at')->whereNotNull('banned_at'),
            'trashed' => $query->onlyTrashed(),
            'all' => $query,
            default => $query->whereNull('deleted_at')->whereNull('banned_at'),
        };

        $users = $query->orderByDesc('created_at')->paginate(12);
        $counts = [
            'total' => User::withTrashed()->count(),
            'active' => User::whereNull('banned_at')->count(),
            'banned' => User::whereNotNull('banned_at')->count(),
            'trashed' => User::onlyTrashed()->count(),
        ];

        return view('livewire.admin.user-list', compact('users', 'counts'))->layout('layouts.app');
    }

    private function targetUser(?int $id): User
    {
        abort_unless($id, 404);

        return User::withTrashed()->with('roles')->findOrFail($id);
    }

    private function ensureManageable(User $user): void
    {
        if ($user->hasAnyRole(['owner', 'super-admin']) && ! auth()->user()->hasAnyRole(['owner', 'super-admin'])) {
            abort(403, 'No puedes administrar una cuenta protegida.');
        }
    }

    private function canChangeAvailability(User $user, string $verb): bool
    {
        if ($user->id !== auth()->id()) {
            return true;
        }
        $this->dispatch('notify', type: 'error', message: "No puedes {$verb} tu propia cuenta.");

        return false;
    }

    private function revokeSessions(User $user): void
    {
        if (Schema::hasTable(config('session.table', 'sessions'))) {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }

    private function roleLabel(string $role): string
    {
        return str($role)->replace('-', ' ')->title()->toString();
    }
}
