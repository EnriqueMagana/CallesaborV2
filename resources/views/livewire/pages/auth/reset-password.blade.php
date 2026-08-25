<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    #[Locked]
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Mount the component.
     */
    public function mount(string $token): void
    {
        $this->token = $token;

        $this->email = request()->string('email');
    }

    /**
     * Reset the password for the given user.
     */
    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        if ($status != Password::PASSWORD_RESET) {
            $this->addError('email', __($status));

            return;
        }

        Session::flash('status', __($status));

        $this->redirectRoute('login', navigate: true);
    }
}; ?>

<div class="auth-login" x-data="{ showPassword: false, showConfirmation: false }">
    <header class="auth-login__header">
        <span class="auth-login__eyebrow">Enlace verificado</span>
        <h2>Crea una nueva contraseña</h2>
        <p>Utiliza una contraseña que no hayas usado anteriormente en esta cuenta.</p>
    </header>

    <form wire:submit="resetPassword" class="auth-form" novalidate>
        <div class="auth-field {{ $errors->has('email') ? 'has-error' : '' }}">
            <label for="email">Correo electrónico</label>
            <div class="auth-input-wrap">
                <i class="bx bx-envelope" aria-hidden="true"></i>
                <input wire:model="email" id="email" type="email" name="email" required autofocus
                    autocomplete="username" autocapitalize="none" spellcheck="false" aria-describedby="reset-email-error">
            </div>
            @error('email')
                <p id="reset-email-error" class="auth-field__error" role="alert"><i class="bx bx-error-circle"
                        aria-hidden="true"></i>{{ $message }}</p>
            @enderror
        </div>

        <div class="auth-field {{ $errors->has('password') ? 'has-error' : '' }}">
            <label for="password">Nueva contraseña</label>
            <div class="auth-input-wrap">
                <i class="bx bx-lock-alt" aria-hidden="true"></i>
                <input wire:model="password" id="password" x-bind:type="showPassword ? 'text' : 'password'"
                    name="password" required autocomplete="new-password" aria-describedby="new-password-help password-error">
                <button type="button" class="auth-password-toggle" x-on:click="showPassword = !showPassword"
                    x-bind:aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                    x-bind:aria-pressed="showPassword">
                    <i class="bx" x-bind:class="showPassword ? 'bx-hide' : 'bx-show'" aria-hidden="true"></i>
                </button>
            </div>
            <small id="new-password-help" class="auth-field__help">Usa al menos 8 caracteres.</small>
            @error('password')
                <p id="password-error" class="auth-field__error" role="alert"><i class="bx bx-error-circle"
                        aria-hidden="true"></i>{{ $message }}</p>
            @enderror
        </div>

        <div class="auth-field {{ $errors->has('password_confirmation') ? 'has-error' : '' }}">
            <label for="password_confirmation">Confirma la contraseña</label>
            <div class="auth-input-wrap">
                <i class="bx bx-lock-open-alt" aria-hidden="true"></i>
                <input wire:model="password_confirmation" id="password_confirmation"
                    x-bind:type="showConfirmation ? 'text' : 'password'" name="password_confirmation" required
                    autocomplete="new-password" aria-describedby="password-confirmation-error">
                <button type="button" class="auth-password-toggle"
                    x-on:click="showConfirmation = !showConfirmation"
                    x-bind:aria-label="showConfirmation ? 'Ocultar confirmación' : 'Mostrar confirmación'"
                    x-bind:aria-pressed="showConfirmation">
                    <i class="bx" x-bind:class="showConfirmation ? 'bx-hide' : 'bx-show'" aria-hidden="true"></i>
                </button>
            </div>
            @error('password_confirmation')
                <p id="password-confirmation-error" class="auth-field__error" role="alert"><i
                        class="bx bx-error-circle" aria-hidden="true"></i>{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="auth-submit" wire:loading.attr="disabled" wire:target="resetPassword">
            <span wire:loading.remove wire:target="resetPassword">Guardar nueva contraseña <i
                    class="bx bx-check-shield" aria-hidden="true"></i></span>
            <span wire:loading.flex wire:target="resetPassword"><i class="bx bx-loader-alt bx-spin"
                    aria-hidden="true"></i> Actualizando…</span>
        </button>
    </form>

    <a class="auth-back-link" href="{{ route('login') }}" wire:navigate><i class="bx bx-left-arrow-alt"
            aria-hidden="true"></i> Volver al inicio de sesión</a>
</div>
