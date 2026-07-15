<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public $photo;

    public function updatedPhoto(): void
    {
        $this->validate(['photo' => ['image', 'max:2048']]);

        $user = Auth::user();

        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $this->photo->store('avatars', 'public');
        $user->update(['avatar' => $path]);

        $this->photo = null;
        $this->dispatch('profile-updated', name: $user->name);
    }

    public function removeAvatar(): void
    {
        $user = Auth::user();

        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->update(['avatar' => null]);
    }
}; ?>

<div class="profile-identity text-center">
    <div class="profile-avatar-wrap">
        @if (Auth::user()->avatar)
            <img src="{{ Storage::url(Auth::user()->avatar) }}" alt="Foto de perfil de {{ Auth::user()->name }}" class="profile-avatar">
        @else
            <div class="profile-avatar profile-avatar--initials" aria-label="Inicial de {{ Auth::user()->name }}">
                {{ mb_strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}
            </div>
        @endif
        <span class="profile-avatar-status" aria-label="Cuenta activa"></span>
    </div>

    <h2 class="profile-identity__name">{{ Auth::user()->name }}</h2>
    <span class="profile-role-pill">{{ Auth::user()->getRoleNames()->first() ?? 'Usuario' }}</span>

    <div class="profile-photo-actions">
        <label for="avatar-upload" class="btn btn-primary profile-btn mb-0">
            <i class="bx bx-camera me-1" aria-hidden="true"></i>Cambiar foto
            <input id="avatar-upload" type="file" wire:model="photo" accept="image/png,image/jpeg,image/gif" class="visually-hidden">
        </label>

        @if (Auth::user()->avatar)
            <button type="button" wire:click="removeAvatar" class="btn btn-label-secondary profile-icon-btn"
                aria-label="Quitar foto de perfil" title="Quitar foto"
                wire:confirm="¿Quitar la foto de perfil?">
                <i class="bx bx-trash" aria-hidden="true"></i>
            </button>
        @endif
    </div>

    <div wire:loading.flex wire:target="photo" class="profile-uploading" role="status">
        <span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>Subiendo foto…</span>
    </div>

    @error('photo') <div class="text-danger small mt-2" role="alert">{{ $message }}</div> @enderror
    <p class="profile-photo-hint">PNG, JPG o GIF · máximo 2 MB</p>
</div>
