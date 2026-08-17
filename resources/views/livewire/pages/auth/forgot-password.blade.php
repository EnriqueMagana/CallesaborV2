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
        $status = Password::sendResetLink($this->only('email'));

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));

            return;
        }

        $this->reset('email');

        session()->flash('status', __($status));
    }
}; ?>

<div>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('¿Olvidaste tu contraseña? No te preocupes. Ingresa tu correo electrónico y te enviaremos un enlace para restablecerla y crear una nueva.') }}
    </div>

    <!-- Estado de la sesión -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink">

        <!-- Correo electrónico -->
        <div>
            <x-input-label for="email" :value="__('Correo electrónico')" />

            <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required
                autofocus />

            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('Enviar enlace para restablecer contraseña') }}
            </x-primary-button>
        </div>
    </form>
</div>
