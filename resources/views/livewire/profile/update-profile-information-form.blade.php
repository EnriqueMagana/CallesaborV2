<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $phone = '';

    public function mount(): void
    {
        $this->name  = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->phone = Auth::user()->phone ?? '';
    }

    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    public function sendVerification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('app.dashboard', absolute: false));
            return;
        }

        $user->sendEmailVerificationNotification();
        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<div>
    @if($this->getErrorBag()->any())
        <div class="alert alert-danger alert-dismissible mb-3" role="alert">
            Por favor revisa los errores en el formulario.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form wire:submit="updateProfileInformation">
        <div class="row">
            <div class="mb-3 col-md-6">
                <label for="name" class="form-label">Nombre completo</label>
                <input wire:model="name" type="text" id="name" class="form-control @error('name') is-invalid @enderror"
                       placeholder="Tu nombre" autofocus autocomplete="name" />
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3 col-md-6">
                <label for="email" class="form-label">Correo electrónico</label>
                <input wire:model="email" type="email" id="email" class="form-control @error('email') is-invalid @enderror"
                       placeholder="usuario@ejemplo.com" autocomplete="username" />
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror

                @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                    <div class="form-text text-warning">
                        Tu correo no está verificado.
                        <button wire:click.prevent="sendVerification" type="button" class="btn btn-link btn-sm p-0 text-warning">
                            Reenviar verificación
                        </button>
                    </div>
                    @if (session('status') === 'verification-link-sent')
                        <div class="form-text text-success">Enlace de verificación enviado.</div>
                    @endif
                @endif
            </div>

            <div class="mb-3 col-md-6">
                <label for="phone" class="form-label">Teléfono</label>
                <input wire:model="phone" type="text" id="phone" class="form-control @error('phone') is-invalid @enderror"
                       placeholder="+1 (555) 000-0000" />
                @error('phone')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="mt-2 d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">
                <span wire:loading.remove wire:target="updateProfileInformation">Guardar cambios</span>
                <span wire:loading wire:target="updateProfileInformation">
                    <span class="spinner-border spinner-border-sm me-1"></span> Guardando...
                </span>
            </button>

            <div wire:dirty wire:target="name,email,phone">
                <span class="text-muted small">Tienes cambios sin guardar</span>
            </div>

            <div x-data="{ show: false }" x-on:profile-updated.window="show = true; setTimeout(() => show = false, 3000)" x-show="show" x-transition>
                <span class="badge bg-label-success"><i class="bx bx-check me-1"></i>Guardado</span>
            </div>
        </div>
    </form>
</div>
