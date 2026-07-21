<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public function login(): void
    {
        $this->validate();

        $requiresTwoFactor = $this->form->authenticate();

        Session::regenerate();

        if ($requiresTwoFactor) {
            $this->redirectRoute('two-factor.login', navigate: true);
            return;
        }

        $this->redirectIntended(default: route('app.dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="auth-login" x-data="{ showPassword: false }">
    <header class="auth-login__header">
        <span class="auth-login__eyebrow">Panel administrativo</span>
        <h2>Bienvenido de nuevo</h2>
        <p>Ingresa tus credenciales para continuar con tu turno.</p>
    </header>

    @if(session('status'))
        <div class="auth-notice auth-notice--success" role="status">
            <i class="bx bx-check-circle" aria-hidden="true"></i><span>{{ session('status') }}</span>
        </div>
    @endif

    <form wire:submit="login" class="auth-form" novalidate>
        <div class="auth-field {{ $errors->has('form.email') ? 'has-error' : '' }}">
            <label for="email">Correo electrónico</label>
            <div class="auth-input-wrap">
                <i class="bx bx-envelope" aria-hidden="true"></i>
                <input wire:model.blur="form.email" id="email" type="email" name="email" required autofocus autocomplete="username" placeholder="nombre@negocio.com" aria-describedby="email-help email-error">
            </div>
            <small id="email-help" class="auth-field__help">Usa el correo asignado por el administrador.</small>
            @error('form.email')<p id="email-error" class="auth-field__error" role="alert"><i class="bx bx-error-circle" aria-hidden="true"></i>{{ $message }}</p>@enderror
        </div>

        <div class="auth-field {{ $errors->has('form.password') ? 'has-error' : '' }}">
            <div class="auth-field__label-row">
                <label for="password">Contraseña</label>
                @if(Route::has('password.request'))
                    <a href="{{ route('password.request') }}" wire:navigate>¿Olvidaste tu contraseña?</a>
                @endif
            </div>
            <div class="auth-input-wrap">
                <i class="bx bx-lock-alt" aria-hidden="true"></i>
                <input wire:model="form.password" id="password" x-bind:type="showPassword ? 'text' : 'password'" name="password" required autocomplete="current-password" placeholder="Ingresa tu contraseña" aria-describedby="password-error">
                <button type="button" class="auth-password-toggle" x-on:click="showPassword = !showPassword" x-bind:aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'" x-bind:aria-pressed="showPassword">
                    <i class="bx" x-bind:class="showPassword ? 'bx-hide' : 'bx-show'" aria-hidden="true"></i>
                </button>
            </div>
            @error('form.password')<p id="password-error" class="auth-field__error" role="alert"><i class="bx bx-error-circle" aria-hidden="true"></i>{{ $message }}</p>@enderror
        </div>

        <label class="auth-remember" for="remember">
            <input wire:model="form.remember" id="remember" type="checkbox" name="remember">
            <span aria-hidden="true"><i class="bx bx-check"></i></span>
            <span>Mantener mi sesión iniciada en este dispositivo</span>
        </label>

        <button type="submit" class="auth-submit" wire:loading.attr="disabled" wire:target="login">
            <span wire:loading.remove wire:target="login">Iniciar sesión <i class="bx bx-right-arrow-alt" aria-hidden="true"></i></span>
            <span wire:loading.flex wire:target="login"><i class="bx bx-loader-alt bx-spin" aria-hidden="true"></i> Verificando acceso…</span>
        </button>
    </form>

    <div class="auth-security-note">
        <i class="bx bx-info-circle" aria-hidden="true"></i>
        <p><strong>Acceso exclusivo para personal autorizado.</strong><span>Por seguridad, cierra sesión cuando termines tu turno.</span></p>
    </div>
</div>
