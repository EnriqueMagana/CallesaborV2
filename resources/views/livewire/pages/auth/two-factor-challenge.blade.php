<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public bool $usingRecoveryCode = false;

    public function toggleRecoveryCode(): void
    {
        $this->usingRecoveryCode = ! $this->usingRecoveryCode;
    }
}; ?>

<div class="w-full max-w-md">
    <div class="mb-7 text-center">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
            <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6.5 4h13a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-13a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2ZM8 9V6a4 4 0 0 1 8 0v3" />
            </svg>
        </div>
        <h1 class="text-2xl font-semibold text-slate-900">Verifica que eres tú</h1>
        <p class="mt-2 text-sm leading-6 text-slate-600">
            {{ $usingRecoveryCode
                ? 'Ingresa uno de tus códigos de recuperación para continuar.'
                : 'Abre tu aplicación de autenticación e ingresa el código de 6 dígitos.' }}
        </p>
    </div>

    <form method="POST" action="{{ route('two-factor.login.store') }}" class="space-y-5">
        @csrf

        @if ($usingRecoveryCode)
            <div>
                <x-input-label for="recovery_code" value="Código de recuperación" />
                <x-text-input id="recovery_code" name="recovery_code" type="text"
                    class="mt-2 block w-full font-mono tracking-wide" autofocus autocomplete="one-time-code"
                    placeholder="xxxx-xxxx-xxxx" required />
                <x-input-error :messages="$errors->get('recovery_code')" class="mt-2" />
            </div>
        @else
            <div>
                <x-input-label for="code" value="Código de seguridad" />
                <x-text-input id="code" name="code" type="text" inputmode="numeric"
                    pattern="[0-9]*" maxlength="6" class="mt-2 block w-full text-center text-2xl font-semibold tracking-[0.35em]"
                    autofocus autocomplete="one-time-code" placeholder="000000" required />
                <x-input-error :messages="$errors->get('code')" class="mt-2" />
            </div>
        @endif

        <button type="submit"
            class="flex min-h-11 w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
            Verificar y entrar
        </button>

        <button type="button" wire:click="toggleRecoveryCode"
            class="min-h-11 w-full rounded-xl px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            {{ $usingRecoveryCode ? 'Usar código de la aplicación' : 'Usar un código de recuperación' }}
        </button>
    </form>
</div>
