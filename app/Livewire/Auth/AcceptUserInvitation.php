<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\Permission\Models\Role;
use Throwable;

class AcceptUserInvitation extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $invitationId;

    #[Locked]
    public string $token;

    #[Locked]
    public string $email = '';

    #[Locked]
    public string $roleName = '';

    #[Locked]
    public string $roleLabel = '';

    #[Locked]
    public string $expiresAt = '';

    public bool $invitationValid = false;

    public bool $registrationComplete = false;

    public string $invalidReason = '';

    public int $step = 1;

    public $photo = null;

    public string $name = '';

    public string $phone = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(UserInvitation $invitation, string $token): void
    {
        $this->invitationId = $invitation->id;
        $this->token = $token;
        $this->hydrateInvitation($invitation);
    }

    public function nextStep(): void
    {
        if (! $this->ensureInvitationIsUsable()) {
            return;
        }

        $this->validate($this->rulesForStep($this->step), $this->validationMessages());
        $this->step = min(3, $this->step + 1);
        $this->resetValidation();
    }

    public function previousStep(): void
    {
        $this->step = max(1, $this->step - 1);
        $this->resetValidation();
    }

    public function completeRegistration(): void
    {
        if (! $this->ensureInvitationIsUsable()) {
            return;
        }

        $this->validate($this->allRules(), $this->validationMessages());

        $rateLimitKey = 'accept-user-invitation:'.$this->invitationId.':'.request()->ip();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            $this->addError('registration', 'Se alcanzó el límite temporal de intentos. Espera un minuto y vuelve a intentarlo.');

            return;
        }
        RateLimiter::hit($rateLimitKey, 60);

        $avatarPath = $this->photo?->store('avatars', 'public');

        try {
            DB::transaction(function () use ($avatarPath): void {
                $invitation = UserInvitation::query()->with('role')->lockForUpdate()->findOrFail($this->invitationId);

                if (! $invitation->isUsable($this->token)) {
                    throw ValidationException::withMessages([
                        'registration' => 'La invitación venció o ya fue utilizada. Solicita una nueva invitación.',
                    ]);
                }

                if (User::withTrashed()->where('email', $invitation->email)->exists()) {
                    throw ValidationException::withMessages([
                        'registration' => 'Ya existe una cuenta registrada con este correo.',
                    ]);
                }

                $role = $invitation->role;
                if (! $role instanceof Role) {
                    throw ValidationException::withMessages([
                        'registration' => 'El rol de esta invitación ya no está disponible.',
                    ]);
                }

                $user = new User([
                    'name' => trim($this->name),
                    'email' => $invitation->email,
                    'phone' => filled($this->phone) ? trim($this->phone) : null,
                    'avatar' => $avatarPath,
                    'password' => Hash::make($this->password),
                ]);
                $user->email_verified_at = now();
                $user->save();
                $user->assignRole($role);

                $invitation->update([
                    'accepted_at' => now(),
                    'accepted_user_id' => $user->id,
                ]);
            });

            RateLimiter::clear($rateLimitKey);
            $this->registrationComplete = true;
            $this->step = 3;
            $this->reset('photo', 'password', 'password_confirmation');
        } catch (ValidationException $exception) {
            if ($avatarPath) {
                Storage::disk('public')->delete($avatarPath);
            }

            throw $exception;
        } catch (Throwable $exception) {
            if ($avatarPath) {
                Storage::disk('public')->delete($avatarPath);
            }

            report($exception);
            $this->addError('registration', 'No pudimos completar el registro. Intenta nuevamente.');
        }
    }

    private function hydrateInvitation(UserInvitation $invitation): void
    {
        $invitation->loadMissing('role');
        $this->email = $invitation->email;
        $this->roleName = $invitation->role?->name ?? '';
        $this->roleLabel = $this->roleName !== ''
            ? str($this->roleName)->replace('-', ' ')->title()->toString()
            : 'Rol no disponible';
        $this->expiresAt = $invitation->expires_at->translatedFormat('d/m/Y H:i');
        $this->invitationValid = $invitation->isUsable($this->token) && $invitation->role !== null;

        if (! $this->invitationValid) {
            $this->invalidReason = $invitation->accepted_at
                ? 'Esta invitación ya fue utilizada.'
                : ($invitation->expires_at->isAfter(now())
                    ? 'El enlace de invitación no es válido.'
                    : 'Esta invitación venció. Solicita al administrador que envíe una nueva.');
        }
    }

    private function ensureInvitationIsUsable(): bool
    {
        $invitation = UserInvitation::query()->with('role')->find($this->invitationId);

        if (! $invitation || ! $invitation->isUsable($this->token) || ! $invitation->role) {
            $this->invitationValid = false;
            $this->invalidReason = 'Esta invitación venció, ya fue utilizada o dejó de ser válida.';

            return false;
        }

        return true;
    }

    /** @return array<string, array<int, mixed>> */
    private function rulesForStep(int $step): array
    {
        return match ($step) {
            1 => [
                'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
                'name' => ['required', 'string', 'max:255', 'regex:/^[\pL\pM\s\'\-.]+$/u'],
            ],
            2 => [
                'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+()\s.\-]+$/'],
            ],
            default => [
                'password' => ['required', 'string', 'confirmed', Password::defaults()],
            ],
        };
    }

    /** @return array<string, array<int, mixed>> */
    private function allRules(): array
    {
        return array_merge(
            $this->rulesForStep(1),
            $this->rulesForStep(2),
            $this->rulesForStep(3),
        );
    }

    /** @return array<string, string> */
    private function validationMessages(): array
    {
        return [
            'photo.image' => 'Selecciona una imagen válida.',
            'photo.mimes' => 'La foto debe ser JPG, PNG o WEBP.',
            'photo.max' => 'La foto no puede superar 3 MB.',
            'name.required' => 'Escribe tu nombre completo.',
            'name.regex' => 'El nombre solo puede contener letras, espacios, apóstrofes, puntos y guiones.',
            'phone.regex' => 'Usa únicamente números, espacios y los símbolos + ( ) . -.',
            'password.required' => 'Crea una contraseña para ingresar.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ];
    }

    public function render()
    {
        return view('livewire.auth.accept-user-invitation')->layout('layouts.guest');
    }
}
