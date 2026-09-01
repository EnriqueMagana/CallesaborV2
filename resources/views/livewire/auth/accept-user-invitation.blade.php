<div class="invite-wizard">
    @if ($registrationComplete)
        <div class="invite-result" role="status">
            <span class="invite-result__icon is-success"><i class="bx bx-check" aria-hidden="true"></i></span>
            <span class="auth-login__eyebrow">Registro completado</span>
            <h2>Tu cuenta está lista</h2>
            <p>Ya formas parte del equipo como <strong>{{ $roleLabel }}</strong>. Utiliza el correo {{ $email }} y la contraseña que acabas de crear.</p>
            <a href="{{ route('login') }}" class="invite-primary-link"><i class="bx bx-log-in" aria-hidden="true"></i>Ir al inicio de sesión</a>
        </div>
    @elseif (! $invitationValid)
        <div class="invite-result" role="alert">
            <span class="invite-result__icon is-warning"><i class="bx bx-time-five" aria-hidden="true"></i></span>
            <span class="auth-login__eyebrow">Invitación no disponible</span>
            <h2>Este enlace ya no puede utilizarse</h2>
            <p>{{ $invalidReason }}</p>
            <div class="invite-expired-note"><i class="bx bx-info-circle" aria-hidden="true"></i><span>Por seguridad, cada invitación dura exactamente una hora y solo permite crear una cuenta.</span></div>
            <a href="{{ route('login') }}" class="invite-secondary-link"><i class="bx bx-arrow-back" aria-hidden="true"></i>Volver al inicio de sesión</a>
        </div>
    @else
        <header class="invite-header">
            <div>
                <span class="auth-login__eyebrow">Invitación al equipo</span>
                <h2>Crea tu cuenta</h2>
                <p>Completa tus datos una sola vez. Tu acceso tendrá el rol <strong>{{ $roleLabel }}</strong>.</p>
            </div>
            <span class="invite-role-chip"><i class="bx bx-badge-check" aria-hidden="true"></i>{{ $roleLabel }}</span>
        </header>

        <form wire:submit="completeRegistration" class="invite-form" novalidate
            x-data="{ showPassword: false, showConfirmation: false, uploading: false }"
            x-on:livewire-upload-start="uploading = true"
            x-on:livewire-upload-finish="uploading = false"
            x-on:livewire-upload-error="uploading = false">
            <section class="invite-profile-summary" aria-labelledby="invite-profile-title">
                <label class="invite-photo" for="invite-photo">
                    <span class="invite-photo__preview">
                        @if ($photo)
                            <img src="{{ $photo->temporaryUrl() }}" alt="Vista previa de tu fotografía">
                        @else
                            <i class="bx bx-camera" aria-hidden="true"></i>
                        @endif
                    </span>
                    <span><strong>{{ $photo ? 'Cambiar fotografía' : 'Añadir fotografía' }}</strong><small>Opcional · JPG, PNG o WEBP · máximo 3 MB</small></span>
                    <i class="bx bx-upload invite-photo__action" aria-hidden="true"></i>
                    <input id="invite-photo" type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp">
                </label>
                <div>
                    <h3 id="invite-profile-title">Información de tu cuenta</h3>
                    <p>Los campos con <b>*</b> son obligatorios.</p>
                    <div wire:loading.flex wire:target="photo" class="invite-uploading" role="status"><i class="bx bx-loader-alt bx-spin" aria-hidden="true"></i>Preparando imagen…</div>
                    @error('photo')<p class="invite-field-error" role="alert"><i class="bx bx-error-circle" aria-hidden="true"></i>{{ $message }}</p>@enderror
                </div>
            </section>

            <div class="invite-form-grid">
                <label class="invite-field is-full" for="invite-name">
                    <span>Nombre completo <b>*</b></span>
                    <div><i class="bx bx-user" aria-hidden="true"></i><input id="invite-name" type="text" wire:model="name" autocomplete="name" placeholder="Ej. Adriana López" autofocus></div>
                    @error('name')<small class="invite-field-error" role="alert">{{ $message }}</small>@enderror
                </label>

                <div class="invite-locked-field" aria-label="Correo electrónico asignado">
                    <i class="bx bx-envelope" aria-hidden="true"></i>
                    <span><small>Correo asignado</small><strong>{{ $email }}</strong></span>
                    <i class="bx bx-lock-alt" aria-hidden="true"></i>
                </div>

                <label class="invite-field" for="invite-phone">
                    <span>Teléfono <em>Opcional</em></span>
                    <div><i class="bx bx-phone" aria-hidden="true"></i><input id="invite-phone" type="tel" wire:model="phone" autocomplete="tel" inputmode="tel" placeholder="Ej. 5512345678"></div>
                    @error('phone')<small class="invite-field-error" role="alert">{{ $message }}</small>@enderror
                </label>

                <label class="invite-field" for="invite-password">
                    <span>Contraseña <b>*</b></span>
                    <div class="invite-password-control">
                        <i class="bx bx-lock-alt" aria-hidden="true"></i>
                        <input id="invite-password" x-bind:type="showPassword ? 'text' : 'password'" wire:model="password" autocomplete="new-password" placeholder="Mínimo 8 caracteres">
                        <button type="button" class="invite-password-toggle" x-on:click="showPassword = !showPassword" x-bind:aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'" x-bind:aria-pressed="showPassword"><i class="bx" x-bind:class="showPassword ? 'bx-hide' : 'bx-show'" aria-hidden="true"></i></button>
                    </div>
                    @error('password')<small class="invite-field-error" role="alert">{{ $message }}</small>@enderror
                </label>

                <label class="invite-field" for="invite-password-confirmation">
                    <span>Confirmar contraseña <b>*</b></span>
                    <div class="invite-password-control">
                        <i class="bx bx-check-shield" aria-hidden="true"></i>
                        <input id="invite-password-confirmation" x-bind:type="showConfirmation ? 'text' : 'password'" wire:model="password_confirmation" autocomplete="new-password" placeholder="Repite la contraseña">
                        <button type="button" class="invite-password-toggle" x-on:click="showConfirmation = !showConfirmation" x-bind:aria-label="showConfirmation ? 'Ocultar confirmación' : 'Mostrar confirmación'" x-bind:aria-pressed="showConfirmation"><i class="bx" x-bind:class="showConfirmation ? 'bx-hide' : 'bx-show'" aria-hidden="true"></i></button>
                    </div>
                </label>
            </div>

            <aside class="invite-security-summary">
                <i class="bx bx-shield-quarter" aria-hidden="true"></i>
                <span><strong>Acceso protegido</strong><small>El correo y el rol fueron definidos por administración y no pueden modificarse desde este enlace.</small></span>
            </aside>

            @error('registration')<p class="invite-form-error" role="alert"><i class="bx bx-error-circle" aria-hidden="true"></i>{{ $message }}</p>@enderror

            <footer class="invite-actions is-single">
                <p class="invite-expiration"><i class="bx bx-time-five" aria-hidden="true"></i>Vence el {{ $expiresAt }}</p>
                <button type="submit" class="auth-submit invite-submit-button"
                    x-bind:disabled="uploading"
                    wire:loading.attr="disabled" wire:loading.class="is-loading" wire:target="completeRegistration">
                    <span class="invite-submit__idle"><i class="bx bx-user-check" aria-hidden="true"></i>Crear mi cuenta</span>
                    <span class="invite-submit__loading"><i class="bx bx-loader-alt bx-spin" aria-hidden="true"></i>Validando información…</span>
                </button>
            </footer>
        </form>
    @endif
</div>
