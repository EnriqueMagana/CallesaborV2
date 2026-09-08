<?php

namespace App\Livewire\Forms;

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

    private function validatedUser(): \App\Models\User
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
                    'form.email' => 'Tu cuenta está bloqueada. Contacta al administrador del negocio.',
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
            'form.email' => trans('auth.failed'),
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
            'form.email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }
}
