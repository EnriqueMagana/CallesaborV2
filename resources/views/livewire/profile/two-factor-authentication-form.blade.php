<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Volt\Component;

new class extends Component
{
    public string $enablePassword = '';
    public string $recoveryPassword = '';
    public string $disablePassword = '';
    public string $code = '';
    public bool $showRecoveryCodes = false;

    public function enableTwoFactorAuthentication(EnableTwoFactorAuthentication $enable): void
    {
        $this->validatePassword('enablePassword');
        $enable(Auth::user());
        $this->reset('enablePassword', 'code');
        $this->dispatch('two-factor-state-changed');
    }

    public function confirmTwoFactorAuthentication(ConfirmTwoFactorAuthentication $confirm): void
    {
        $this->validate(['code' => ['required', 'digits:6']]);

        try {
            $confirm(Auth::user(), $this->code);
        } catch (ValidationException) {
            $this->addError('code', 'El código no es válido o ya expiró. Intenta con el código actual.');
            return;
        }

        $this->reset('enablePassword', 'code');
        $this->showRecoveryCodes = true;
        $this->dispatch('two-factor-confirmed');
    }

    public function cancelSetup(DisableTwoFactorAuthentication $disable): void
    {
        $disable(Auth::user());
        $this->reset('enablePassword', 'recoveryPassword', 'disablePassword', 'code', 'showRecoveryCodes');
    }

    public function disableTwoFactorAuthentication(DisableTwoFactorAuthentication $disable): void
    {
        $this->validatePassword('disablePassword');
        $disable(Auth::user());
        $this->reset('enablePassword', 'recoveryPassword', 'disablePassword', 'code', 'showRecoveryCodes');
        $this->dispatch('two-factor-state-changed');
    }

    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generate): void
    {
        $this->validatePassword('recoveryPassword');
        $generate(Auth::user());
        $this->reset('recoveryPassword');
        $this->showRecoveryCodes = true;
        $this->dispatch('recovery-codes-regenerated');
    }

    public function toggleRecoveryCodes(): void
    {
        $this->showRecoveryCodes = ! $this->showRecoveryCodes;
    }

    protected function validatePassword(string $field): void
    {
        $this->validate([$field => ['required', 'string']]);

        if (! Hash::check($this->{$field}, Auth::user()->password)) {
            throw ValidationException::withMessages([
                $field => 'La contraseña actual no es correcta.',
            ]);
        }
    }
}; ?>

@php
    $user = Auth::user()->fresh();
    $isEnabled = $user->hasEnabledTwoFactorAuthentication();
    $isPending = filled($user->two_factor_secret) && ! $isEnabled;
@endphp

<div class="security-panel">
    <div class="security-panel__header">
        <div class="section-icon section-icon--secure" aria-hidden="true"><i class="bx bx-shield-quarter"></i></div>
        <div class="flex-grow-1">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <h2 class="profile-section-title mb-0">Autenticación en dos pasos</h2>
                @if ($isEnabled)
                    <span class="status-pill status-pill--success"><i class="bx bx-check-circle"></i> Activa</span>
                @elseif ($isPending)
                    <span class="status-pill status-pill--warning"><i class="bx bx-time-five"></i> Pendiente</span>
                @else
                    <span class="status-pill status-pill--neutral">Desactivada</span>
                @endif
            </div>
            <p class="profile-section-copy mb-0">Protege tu cuenta con un código temporal además de tu contraseña.</p>
        </div>
    </div>

    @if (! $isEnabled && ! $isPending)
        <div class="security-callout">
            <div class="security-callout__visual" aria-hidden="true"><i class="bx bx-mobile-alt"></i></div>
            <div>
                <h3>Agrega una capa extra de seguridad</h3>
                <p>Usa Google Authenticator, Microsoft Authenticator, 1Password u otra aplicación compatible con TOTP.</p>
            </div>
        </div>

        <form wire:submit="enableTwoFactorAuthentication" class="security-action-form">
            <div class="form-field-grow">
                <label for="two_factor_enable_password" class="form-label">Confirma tu contraseña</label>
                <input wire:model="enablePassword" id="two_factor_enable_password" type="password"
                    class="form-control @error('enablePassword') is-invalid @enderror" autocomplete="current-password"
                    placeholder="Tu contraseña actual" required>
                @error('enablePassword') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <button type="submit" class="btn btn-primary profile-btn" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="enableTwoFactorAuthentication"><i class="bx bx-shield-quarter me-1"></i>Activar protección</span>
                <span wire:loading wire:target="enableTwoFactorAuthentication">Preparando…</span>
            </button>
        </form>
    @elseif ($isPending)
        <div class="setup-grid">
            <div class="qr-frame" aria-label="Código QR para configurar la aplicación de autenticación">
                {!! $user->twoFactorQrCodeSvg() !!}
            </div>
            <div class="setup-copy">
                <span class="eyebrow">Paso final</span>
                <h3>Escanea y confirma</h3>
                <ol class="setup-steps">
                    <li>Escanea el QR con tu aplicación de autenticación.</li>
                    <li>Escribe el código de 6 dígitos que aparece.</li>
                </ol>
                <form wire:submit="confirmTwoFactorAuthentication" class="confirm-code-form">
                    <div>
                        <label for="two_factor_code" class="form-label">Código de seguridad</label>
                        <input wire:model="code" id="two_factor_code" type="text" inputmode="numeric" pattern="[0-9]*"
                            maxlength="6" autocomplete="one-time-code"
                            class="form-control code-input @error('code') is-invalid @enderror" placeholder="000000" required>
                        @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary profile-btn" wire:loading.attr="disabled">Confirmar y activar</button>
                        <button type="button" wire:click="cancelSetup" class="btn btn-label-secondary profile-btn">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    @else
        <div class="security-active-summary">
            <div class="security-active-summary__icon" aria-hidden="true"><i class="bx bx-check-shield"></i></div>
            <div>
                <h3>Tu cuenta está protegida</h3>
                <p>Al iniciar sesión se solicitará un código de tu aplicación de autenticación.</p>
            </div>
        </div>

        <div class="recovery-section">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <h3 class="recovery-title">Códigos de recuperación</h3>
                    <p class="mb-0">Guárdalos en un lugar seguro. Cada código se puede usar una sola vez.</p>
                </div>
                <button type="button" wire:click="toggleRecoveryCodes" class="btn btn-label-secondary profile-btn">
                    <i class="bx {{ $showRecoveryCodes ? 'bx-hide' : 'bx-show' }} me-1"></i>
                    {{ $showRecoveryCodes ? 'Ocultar' : 'Mostrar códigos' }}
                </button>
            </div>

            @if ($showRecoveryCodes)
                <div class="recovery-code-grid" aria-label="Códigos de recuperación">
                    @foreach ($user->recoveryCodes() as $recoveryCode)
                        <code>{{ $recoveryCode }}</code>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="security-management-grid">
            <form wire:submit="regenerateRecoveryCodes" class="security-management-card">
                <div>
                    <h3>Generar códigos nuevos</h3>
                    <p>Los códigos anteriores dejarán de funcionar.</p>
                </div>
                <label for="recovery_password" class="form-label">Contraseña actual</label>
                <input wire:model="recoveryPassword" id="recovery_password" type="password"
                    class="form-control @error('recoveryPassword') is-invalid @enderror" autocomplete="current-password" required>
                @error('recoveryPassword') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <button type="submit" class="btn btn-outline-primary profile-btn">Regenerar códigos</button>
            </form>

            <form wire:submit="disableTwoFactorAuthentication" class="security-management-card security-management-card--danger">
                <div>
                    <h3>Desactivar protección</h3>
                    <p>Volverás a iniciar sesión solo con tu contraseña.</p>
                </div>
                <label for="disable_two_factor_password" class="form-label">Contraseña actual</label>
                <input wire:model="disablePassword" id="disable_two_factor_password" type="password"
                    class="form-control @error('disablePassword') is-invalid @enderror" autocomplete="current-password" required>
                @error('disablePassword') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <button type="submit" class="btn btn-outline-danger profile-btn" wire:confirm="¿Desactivar la autenticación en dos pasos?">
                    Desactivar 2FA
                </button>
            </form>
        </div>
    @endif
</div>
