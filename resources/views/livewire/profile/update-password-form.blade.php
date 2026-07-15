<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component
{
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password'         => ['required', 'string', Password::defaults(), 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');
            throw $e;
        }

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');
        $this->dispatch('password-updated');
    }
}; ?>

<div>
    <form wire:submit="updatePassword">
        <div class="row">
            <div class="mb-3 col-md-6">
                <label for="current_password" class="form-label">Contraseña actual</label>
                <div class="input-group">
                    <input wire:model="current_password" type="password" id="current_password"
                           class="form-control @error('current_password') is-invalid @enderror"
                           placeholder="············" autocomplete="current-password" />
                    <span class="input-group-text cursor-pointer" onclick="togglePassword('current_password')">
                        <i class="bx bx-hide" id="icon_current_password"></i>
                    </span>
                    @error('current_password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="row">
            <div class="mb-3 col-md-6">
                <label for="new_password" class="form-label">Nueva contraseña</label>
                <div class="input-group">
                    <input wire:model="password" type="password" id="new_password"
                           class="form-control @error('password') is-invalid @enderror"
                           placeholder="············" autocomplete="new-password" />
                    <span class="input-group-text cursor-pointer" onclick="togglePassword('new_password')">
                        <i class="bx bx-hide" id="icon_new_password"></i>
                    </span>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mb-3 col-md-6">
                <label for="password_confirmation" class="form-label">Confirmar contraseña</label>
                <div class="input-group">
                    <input wire:model="password_confirmation" type="password" id="password_confirmation"
                           class="form-control @error('password_confirmation') is-invalid @enderror"
                           placeholder="············" autocomplete="new-password" />
                    <span class="input-group-text cursor-pointer" onclick="togglePassword('password_confirmation')">
                        <i class="bx bx-hide" id="icon_password_confirmation"></i>
                    </span>
                    @error('password_confirmation')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="mt-2 d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">
                <span wire:loading.remove wire:target="updatePassword">Actualizar contraseña</span>
                <span wire:loading wire:target="updatePassword">
                    <span class="spinner-border spinner-border-sm me-1"></span> Actualizando...
                </span>
            </button>

            <div x-data="{ show: false }" x-on:password-updated.window="show = true; setTimeout(() => show = false, 3000)" x-show="show" x-transition>
                <span class="badge bg-label-success"><i class="bx bx-check me-1"></i>Contraseña actualizada</span>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
function togglePassword(id) {
    const input = document.getElementById(id);
    const icon = document.getElementById('icon_' + id);
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bx-hide', 'bx-show');
    } else {
        input.type = 'password';
        icon.classList.replace('bx-show', 'bx-hide');
    }
}
</script>
@endpush
