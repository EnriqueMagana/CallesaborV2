<?php

use App\Livewire\Forms\LoginForm;
use App\Services\SingleSessionManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component {
    public LoginForm $form;

    public bool $showSessionConfirmation = false;

    public function login(): void
    {
        $this->validate();

        if ($this->form->hasActiveSession()) {
            $this->showSessionConfirmation = true;

            return;
        }

        $this->completeLogin();
    }

    public function confirmSessionTakeover(): void
    {
        $this->validate();
        $this->showSessionConfirmation = false;
        $this->completeLogin();
    }

    public function cancelSessionTakeover(): void
    {
        $this->showSessionConfirmation = false;
        $this->form->password = '';
    }

    private function completeLogin(): void
    {
        $requiresTwoFactor = $this->form->authenticate();

        Session::regenerate();

        if ($requiresTwoFactor) {
            $this->redirectRoute('two-factor.login', navigate: true);
            return;
        }

        app(SingleSessionManager::class)->start(Auth::guard('web')->user(), Session::driver());

        // Dashboard uses a different layout and stylesheet bundle.
        // A full navigation prevents a flash of unstyled admin content.
        $this->redirectIntended(default: route('app.dashboard', absolute: false), navigate: false);
    }
}; ?>

<div class="auth-login" x-data="{ showPassword: false }">
    <header class="auth-login__header">
        <h2>¡Qué gusto verte!</h2>
        <p>Usa las credenciales asignadas a tu cuenta para entrar al espacio de trabajo.</p>
    </header>

    @if (session('auth_warning'))
        <div class="auth-notice auth-notice--warning" role="alert">
            <i class="bx bx-log-out-circle" aria-hidden="true"></i><span>{{ session('auth_warning') }}</span>
        </div>
    @endif

    @if (session('status'))
        <div class="auth-notice auth-notice--success" role="status">
            <i class="bx bx-check-circle" aria-hidden="true"></i><span>{{ session('status') }}</span>
        </div>
    @endif

    <form wire:submit="login" class="auth-form" novalidate>
        <div class="auth-field {{ $errors->has('form.email') ? 'has-error' : '' }}">
            <label for="email">Correo electrónico</label>
            <div class="auth-input-wrap">
                <i class="bx bx-envelope" aria-hidden="true"></i>
                <input wire:model.blur="form.email" id="email" type="email" name="email" required autofocus
                    autocomplete="username" autocapitalize="none" spellcheck="false" placeholder="nombre@callesabor.com"
                    aria-describedby="email-help email-error">
            </div>
            <small id="email-help" class="auth-field__help">Usa el correo asignado por el administrador.</small>
            @error('form.email')
                <p id="email-error" class="auth-field__error" role="alert"><i class="bx bx-error-circle"
                        aria-hidden="true"></i>{{ $message }}</p>
            @enderror
        </div>

        <div class="auth-field {{ $errors->has('form.password') ? 'has-error' : '' }}">
            <label for="password">Contraseña</label>
            <div class="auth-input-wrap">
                <i class="bx bx-lock-alt" aria-hidden="true"></i>
                <input wire:model="form.password" id="password" x-bind:type="showPassword ? 'text' : 'password'"
                    name="password" required autocomplete="current-password" placeholder="Ingresa tu contraseña"
                    aria-describedby="password-error">
                <button type="button" class="auth-password-toggle" x-on:click="showPassword = !showPassword"
                    x-bind:aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                    x-bind:aria-pressed="showPassword">
                    <i class="bx" x-bind:class="showPassword ? 'bx-hide' : 'bx-show'" aria-hidden="true"></i>
                </button>
            </div>
            @error('form.password')
                <p id="password-error" class="auth-field__error" role="alert"><i class="bx bx-error-circle"
                        aria-hidden="true"></i>{{ $message }}</p>
            @enderror
        </div>

        <label class="auth-remember" for="remember">
            <input wire:model="form.remember" id="remember" type="checkbox" name="remember">
            <span aria-hidden="true"><i class="bx bx-check"></i></span>
            <span>Mantener mi sesión iniciada en este dispositivo</span>
        </label>

        <button type="submit" class="auth-submit" wire:loading.attr="disabled" wire:target="login">
            <span wire:loading.remove wire:target="login">Iniciar sesión <i class="bx bx-right-arrow-alt"
                    aria-hidden="true"></i></span>
            <span wire:loading.flex wire:target="login"><i class="bx bx-loader-alt bx-spin" aria-hidden="true"></i>
                Verificando acceso…</span>
        </button>
    </form>

    @if (Route::has('password.request'))
        <aside class="auth-recovery" aria-labelledby="auth-recovery-title">
            <span class="auth-recovery__icon"><i class="bx bx-key" aria-hidden="true"></i></span>
            <div>
                <strong id="auth-recovery-title">¿No puedes acceder?</strong>
                <span>Recibe por correo un enlace seguro para crear una nueva contraseña.</span>
            </div>
            <a href="{{ route('password.request') }}" wire:navigate>Recuperar contraseña</a>
        </aside>
    @endif

    <div class="auth-security-note">
        <i class="bx bx-rocket" aria-hidden="true"></i>
        <p>
            <strong>Tu negocio, todo en un solo lugar.</strong>
            <span>Gestiona tus operaciones de forma rápida, simple y organizada.</span>
        </p>
    </div>

    @if ($showSessionConfirmation)
        <div class="auth-session-modal" role="presentation">
            <button type="button" class="auth-session-modal__backdrop" wire:click="cancelSessionTakeover"
                aria-label="Cerrar confirmación"></button>
            <section class="auth-session-dialog" role="alertdialog" aria-modal="true"
                aria-labelledby="active-session-title" aria-describedby="active-session-description">
                <span class="auth-session-dialog__icon" aria-hidden="true"><i class="bx bx-devices"></i></span>
                <span class="auth-session-dialog__eyebrow">Sesión activa detectada</span>
                <h3 id="active-session-title">Ya tienes una sesión iniciada en otro dispositivo</h3>
                <p id="active-session-description">Si continúas aquí, cerraremos la sesión anterior y este navegador
                    quedará como el único acceso activo.</p>
                <div class="auth-session-dialog__notice">
                    <i class="bx bx-info-circle" aria-hidden="true"></i>
                    <span>No se perderán ventas ni información guardada; solo se cerrará el acceso anterior.</span>
                </div>
                <div class="auth-session-dialog__actions">
                    <button type="button" class="auth-session-dialog__cancel" wire:click="cancelSessionTakeover"
                        wire:loading.attr="disabled" wire:target="confirmSessionTakeover,cancelSessionTakeover">
                        Cerrar
                    </button>
                    <button type="button" class="auth-session-dialog__confirm" wire:click="confirmSessionTakeover"
                        wire:loading.attr="disabled" wire:target="confirmSessionTakeover">
                        <span wire:loading.remove wire:target="confirmSessionTakeover">Iniciar aquí <i
                                class="bx bx-right-arrow-alt" aria-hidden="true"></i></span>
                        <span wire:loading.flex wire:target="confirmSessionTakeover"><i
                                class="bx bx-loader-alt bx-spin" aria-hidden="true"></i> Cerrando sesión
                            anterior…</span>
                    </button>
                </div>
            </section>
        </div>
    @endif
</div>
