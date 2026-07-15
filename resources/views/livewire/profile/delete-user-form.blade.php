<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $password = '';
    public bool $showModal = false;

    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 rounded-3 border border-danger-subtle bg-danger-subtle p-3">
        <div>
            <h3 class="h6 fw-bold text-danger mb-1">Eliminar la cuenta permanentemente</h3>
            <p class="text-muted small mb-0">Esta acción elimina tus datos y no se puede deshacer.</p>
        </div>
        <button type="button" class="btn btn-outline-danger profile-btn flex-shrink-0" wire:click="$set('showModal', true)">
            <i class="bx bx-trash me-1" aria-hidden="true"></i>Eliminar cuenta
        </button>
    </div>

    @if ($showModal)
        <div class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="delete-account-title"
            style="background:rgba(20, 16, 35, .58);" wire:click.self="$set('showModal', false)">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 rounded-4 shadow-lg">
                    <div class="modal-header border-0 pb-0">
                        <div>
                            <span class="section-icon section-icon--danger mb-3" aria-hidden="true"><i class="bx bx-error-circle"></i></span>
                            <h2 id="delete-account-title" class="modal-title h5 fw-bold">¿Eliminar tu cuenta?</h2>
                        </div>
                        <button type="button" class="btn-close" aria-label="Cerrar" wire:click="$set('showModal', false)"></button>
                    </div>
                    <form wire:submit="deleteUser">
                        <div class="modal-body">
                            <p class="text-muted">Todos los datos asociados serán eliminados. Para confirmar, ingresa tu contraseña actual.</p>
                            <label for="delete_password" class="form-label">Contraseña actual</label>
                            <input wire:model="password" type="password" id="delete_password" autocomplete="current-password"
                                class="form-control @error('password') is-invalid @enderror" required autofocus>
                            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-label-secondary profile-btn" wire:click="$set('showModal', false)">Conservar cuenta</button>
                            <button type="submit" class="btn btn-danger profile-btn" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="deleteUser">Sí, eliminar definitivamente</span>
                                <span wire:loading wire:target="deleteUser">Eliminando…</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
