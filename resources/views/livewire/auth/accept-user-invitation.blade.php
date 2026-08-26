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
                <h2>Completa tu perfil</h2>
                <p>Te registrarás con el rol <strong>{{ $roleLabel }}</strong>.</p>
            </div>
            <span class="invite-role-chip"><i class="bx bx-badge-check" aria-hidden="true"></i>{{ $roleLabel }}</span>
        </header>

        <ol class="invite-progress" aria-label="Progreso del registro">
            @foreach ([1 => ['Perfil', 'bx-user'], 2 => ['Contacto', 'bx-envelope'], 3 => ['Acceso', 'bx-lock-alt']] as $number => $item)
                <li class="{{ $step === $number ? 'is-current' : ($step > $number ? 'is-complete' : '') }}" @if ($step === $number) aria-current="step" @endif>
                    <span><i class="bx {{ $step > $number ? 'bx-check' : $item[1] }}" aria-hidden="true"></i></span>
                    <small>{{ $item[0] }}</small>
                </li>
            @endforeach
        </ol>

        <form wire:submit="completeRegistration" class="invite-form" novalidate>
            @if ($step === 1)
                <section class="invite-step" wire:key="invite-step-profile">
                    <div class="invite-step__heading"><span><i class="bx bx-id-card" aria-hidden="true"></i></span><div><h3>Tu información personal</h3><p>La fotografía es opcional y podrás actualizarla posteriormente.</p></div></div>

                    <label class="invite-photo" for="invite-photo">
                        <span class="invite-photo__preview">
                            @if ($photo)
                                <img src="{{ $photo->temporaryUrl() }}" alt="Vista previa de tu fotografía">
                            @else
                                <i class="bx bx-camera" aria-hidden="true"></i>
                            @endif
                        </span>
                        <span><strong>{{ $photo ? 'Cambiar fotografía' : 'Añadir fotografía' }}</strong><small>JPG, PNG o WEBP · máximo 3 MB</small></span>
                        <input id="invite-photo" type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp">
                    </label>
                    <div wire:loading wire:target="photo" class="invite-uploading"><i class="bx bx-loader-alt bx-spin" aria-hidden="true"></i>Preparando imagen…</div>
                    @error('photo')<p class="invite-field-error" role="alert"><i class="bx bx-error-circle" aria-hidden="true"></i>{{ $message }}</p>@enderror

                    <label class="invite-field" for="invite-name">
                        <span>Nombre completo <b>*</b></span>
                        <div><i class="bx bx-user" aria-hidden="true"></i><input id="invite-name" type="text" wire:model="name" autocomplete="name" placeholder="Ej. Adriana López" autofocus></div>
                        @error('name')<small class="invite-field-error" role="alert">{{ $message }}</small>@enderror
                    </label>
                </section>
            @elseif ($step === 2)
                <section class="invite-step" wire:key="invite-step-contact">
                    <div class="invite-step__heading"><span><i class="bx bx-envelope" aria-hidden="true"></i></span><div><h3>Datos de contacto</h3><p>El correo proviene de la invitación y no puede modificarse.</p></div></div>

                    <div class="invite-locked-field" aria-label="Correo electrónico asignado">
                        <i class="bx bx-envelope" aria-hidden="true"></i>
                        <span><small>Correo electrónico</small><strong>{{ $email }}</strong></span>
                        <i class="bx bx-lock-alt" aria-hidden="true"></i>
                    </div>

                    <label class="invite-field" for="invite-phone">
                        <span>Teléfono <em>Opcional</em></span>
                        <div><i class="bx bx-phone" aria-hidden="true"></i><input id="invite-phone" type="tel" wire:model="phone" autocomplete="tel" inputmode="tel" placeholder="Ej. 5512345678"></div>
                        @error('phone')<small class="invite-field-error" role="alert">{{ $message }}</small>@enderror
                    </label>
                </section>
            @else
                <section class="invite-step" wire:key="invite-step-access">
                    <div class="invite-step__heading"><span><i class="bx bx-shield-quarter" aria-hidden="true"></i></span><div><h3>Protege tu acceso</h3><p>Crea la contraseña con la que iniciarás sesión.</p></div></div>

                    <div class="invite-role-summary"><i class="bx bx-badge-check" aria-hidden="true"></i><span><small>Rol asignado por administración</small><strong>{{ $roleLabel }}</strong></span></div>

                    <label class="invite-field" for="invite-password">
                        <span>Contraseña <b>*</b></span>
                        <div><i class="bx bx-lock-alt" aria-hidden="true"></i><input id="invite-password" type="password" wire:model="password" autocomplete="new-password" placeholder="Mínimo 8 caracteres"></div>
                        @error('password')<small class="invite-field-error" role="alert">{{ $message }}</small>@enderror
                    </label>
                    <label class="invite-field" for="invite-password-confirmation">
                        <span>Confirmar contraseña <b>*</b></span>
                        <div><i class="bx bx-check-shield" aria-hidden="true"></i><input id="invite-password-confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" placeholder="Repite la contraseña"></div>
                    </label>
                    @error('registration')<p class="invite-form-error" role="alert"><i class="bx bx-error-circle" aria-hidden="true"></i>{{ $message }}</p>@enderror
                </section>
            @endif

            <footer class="invite-actions">
                @if ($step > 1)
                    <button type="button" class="invite-back-button" wire:click="previousStep" wire:loading.attr="disabled"><i class="bx bx-arrow-back" aria-hidden="true"></i>Anterior</button>
                @else
                    <span></span>
                @endif

                @if ($step < 3)
                    <button type="button" class="auth-submit invite-next-button" wire:click="nextStep" wire:loading.attr="disabled" wire:target="nextStep,photo">
                        <span wire:loading.remove wire:target="nextStep"><span>Siguiente</span><i class="bx bx-right-arrow-alt" aria-hidden="true"></i></span>
                        <span wire:loading wire:target="nextStep"><i class="bx bx-loader-alt bx-spin" aria-hidden="true"></i>Validando</span>
                    </button>
                @else
                    <button type="submit" class="auth-submit invite-next-button" wire:loading.attr="disabled" wire:target="completeRegistration">
                        <span wire:loading.remove wire:target="completeRegistration"><i class="bx bx-user-check" aria-hidden="true"></i>Crear mi cuenta</span>
                        <span wire:loading wire:target="completeRegistration"><i class="bx bx-loader-alt bx-spin" aria-hidden="true"></i>Creando cuenta</span>
                    </button>
                @endif
            </footer>
        </form>

        <p class="invite-expiration"><i class="bx bx-time-five" aria-hidden="true"></i>Esta invitación vence el {{ $expiresAt }}.</p>
    @endif
</div>
