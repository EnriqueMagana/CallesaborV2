<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component {
    public string $email = '';

    /**
     * Envía un enlace para restablecer la contraseña al correo electrónico proporcionado.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        // Enviamos el enlace para restablecer la contraseña al usuario.
        // Después verificamos el resultado para mostrar el mensaje
        // correspondiente al usuario.
        try {
            $status = Password::sendResetLink($this->only('email'));
        } catch (\Throwable $exception) {
            report($exception);
            $this->addError('email', 'No pudimos enviar el correo en este momento. Inténtalo nuevamente.');

            return;
        }

        if (! in_array($status, [Password::RESET_LINK_SENT, Password::INVALID_USER], true)) {
            $this->addError('email', 'Espera un momento antes de solicitar otro enlace.');

            return;
        }

        $this->reset('email');

        session()->flash('status', 'Si existe una cuenta con ese correo, recibirás un enlace para restablecer tu contraseña.');
    }
}; ?>

<div class="auth-login">
    <header class="auth-login__header">
        <span class="auth-login__eyebrow">Recuperación segura</span>
        <h2>Recupera tu contraseña</h2>
        <p>Escribe el correo de tu cuenta y te enviaremos un enlace de uso único.</p>
    </header>

    @if (session('status'))
        <div class="auth-notice auth-notice--success" role="status">
            <i class="bx bx-envelope-open" aria-hidden="true"></i><span>{{ session('status') }}</span>
        </div>
    @endif

    <form wire:submit="sendPasswordResetLink" class="auth-form" novalidate>
        <div class="auth-field {{ $errors->has('email') ? 'has-error' : '' }}">
            <label for="email">Correo electrónico</label>
            <div class="auth-input-wrap">
                <i class="bx bx-envelope" aria-hidden="true"></i>
                <input wire:model.blur="email" id="email" type="email" name="email" required autofocus
                    autocomplete="email" autocapitalize="none" spellcheck="false" placeholder="nombre@callesabor.com"
                    aria-describedby="recovery-email-help recovery-email-error">
            </div>
            <small id="recovery-email-help" class="auth-field__help">Usa el mismo correo con el que inicias sesión.</small>
            @error('email')
                <p id="recovery-email-error" class="auth-field__error" role="alert"><i class="bx bx-error-circle"
                        aria-hidden="true"></i>{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="auth-submit" wire:loading.attr="disabled" wire:target="sendPasswordResetLink">
            <span wire:loading.remove wire:target="sendPasswordResetLink">Enviar enlace <i
                    class="bx bx-send" aria-hidden="true"></i></span>
            <span wire:loading.flex wire:target="sendPasswordResetLink"><i class="bx bx-loader-alt bx-spin"
                    aria-hidden="true"></i> Enviando correo…</span>
        </button>
    </form>

    <a class="auth-back-link" href="{{ route('login') }}" wire:navigate><i class="bx bx-left-arrow-alt"
            aria-hidden="true"></i> Volver al inicio de sesión</a>
</div>
