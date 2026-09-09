<?php

namespace App\Livewire\Forms;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Livewire\Attributes\Validate;
use Livewire\Form;

class LoginForm extends Form
{
    private const AUTHENTICATION_TIMEBOX_MICROSECONDS = 200_000;

    #[Validate('required|string|email:rfc|max:254')]
    public string $email = '';

    #[Validate('required|string|max:1024')]
    public string $password = '';

    #[Validate('boolean')]
    public bool $remember = false;

    /**
     * Check valid credentials for an existing browser before authenticating
     * this one, so replacing that session always requires confirmation.
     */
    public function hasActiveSession(): bool
    {
        return filled($this->validatedUser()->active_session_token_hash);
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): bool
    {
        $guard = Auth::guard('web');
        $user = $this->validatedUser();

        if (Hash::needsRehash($user->getAuthPassword())) {
            $user->forceFill(['password' => Hash::make($this->password)])->save();
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => $this->remember,
            ]);

            TwoFactorAuthenticationChallenged::dispatch($user);

            return true;
        }

        $guard->login($user, $this->remember);

        return false;
    }

    private function validatedUser(): User
    {
        $this->ensureIsNotRateLimited();

        $guard = Auth::guard('web');

        return $guard->getTimebox()->call(function ($timebox) use ($guard) {
            $user = $guard->getProvider()->retrieveByCredentials(['email' => $this->email]);

            if (! $user || ! $guard->getProvider()->validateCredentials($user, ['password' => $this->password])) {
                $this->rejectCredentials();
            }

            if ($user->isBanned()) {
                RateLimiter::hit($this->throttleKey());

                throw ValidationException::withMessages([
                    'form.email' => 'Tu acceso está bloqueado. Si crees que es un error, comunícate con el administrador.',
                ]);
            }

            RateLimiter::clear($this->throttleKey());
            $timebox->returnEarly();

            return $user;
        }, self::AUTHENTICATION_TIMEBOX_MICROSECONDS);
    }

    private function rejectCredentials(): never
    {
        RateLimiter::hit($this->throttleKey());

        throw ValidationException::withMessages([
            'form.email' => 'No pudimos iniciar sesión con esos datos. Revisa tu correo y contraseña e inténtalo de nuevo.',
        ]);
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'form.email' => "Hiciste varios intentos seguidos. Espera {$seconds} segundos antes de volver a intentarlo.",
        ]);
    }

    /**
     * Human-readable login validation messages that do not depend on server locale files.
     */
    protected function messages(): array
    {
        return [
            'email.required' => 'Escribe tu correo electrónico.',
            'email.string' => 'El correo electrónico no tiene un formato válido.',
            'email.email' => 'Revisa el formato de tu correo electrónico.',
            'email.max' => 'El correo electrónico es demasiado largo.',
            'password.required' => 'Escribe tu contraseña.',
            'password.string' => 'La contraseña no tiene un formato válido.',
            'password.max' => 'La contraseña es demasiado larga.',
            'remember.boolean' => 'No pudimos reconocer la opción de mantener la sesión.',
        ];
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
}
