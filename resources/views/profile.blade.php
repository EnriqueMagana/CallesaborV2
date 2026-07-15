@php
    use Illuminate\Support\Facades\Auth;
    $user = Auth::user();
@endphp



<x-app-layout>
    <main class="profile-page">
        <header class="profile-hero">
            <div>
                <nav class="profile-breadcrumb" aria-label="Ruta de navegación">
                    <span>Cuenta</span><i class="bx bx-chevron-right" aria-hidden="true"></i><span
                        aria-current="page">Perfil</span>
                </nav>
                <h1 class="profile-title">Tu cuenta, a tu manera</h1>
                <p class="profile-subtitle">Actualiza tus datos personales y administra la seguridad de tu acceso desde
                    un solo lugar.</p>
            </div>
        </header>

        <div class="profile-layout">
            <aside class="profile-sidebar" aria-label="Resumen del perfil">
                <section class="profile-card">
                    <div class="profile-card__body pb-3">
                        <livewire:profile.update-avatar-form />
                    </div>
                    <div class="profile-details">
                        <p class="profile-details__label">Información de la cuenta</p>
                        <ul class="profile-detail-list">
                            <li><i class="bx bx-envelope" aria-hidden="true"></i>
                                <div><small>Correo</small><span>{{ $user->email }}</span></div>
                            </li>
                            @if ($user->phone)
                                <li><i class="bx bx-phone" aria-hidden="true"></i>
                                    <div><small>Teléfono</small><span>{{ $user->phone }}</span></div>
                                </li>
                            @endif
                            <li><i class="bx bx-calendar" aria-hidden="true"></i>
                                <div><small>Miembro
                                        desde</small><span>{{ $user->created_at->translatedFormat('F Y') }}</span></div>
                            </li>
                            <li><i class="bx bx-shield" aria-hidden="true"></i>
                                <div><small>Seguridad
                                        2FA</small><span>{{ $user->hasEnabledTwoFactorAuthentication() ? 'Activada' : 'Disponible' }}</span>
                                </div>
                            </li>
                        </ul>
                    </div>
                </section>
            </aside>

            <div class="profile-content">
                <section class="profile-card" aria-labelledby="personal-info-title">
                    <div class="profile-card__header">
                        <div class="section-icon" aria-hidden="true"><i class="bx bx-user"></i></div>
                        <div>
                            <h2 id="personal-info-title" class="profile-section-title mb-0">Información personal</h2>
                            <p class="profile-section-copy mb-0">Mantén actualizados los datos que usamos para
                                identificar tu cuenta.</p>
                        </div>
                    </div>
                    <div class="profile-card__body"><livewire:profile.update-profile-information-form /></div>
                </section>

                <section class="profile-card" aria-label="Autenticación en dos pasos">
                    <div class="profile-card__body"><livewire:profile.two-factor-authentication-form /></div>
                </section>

                <section class="profile-card" aria-labelledby="password-title">
                    <div class="profile-card__header">
                        <div class="section-icon" aria-hidden="true"><i class="bx bx-key"></i></div>
                        <div>
                            <h2 id="password-title" class="profile-section-title mb-0">Contraseña</h2>
                            <p class="profile-section-copy mb-0">Usa una contraseña única que no utilices en otros
                                servicios.</p>
                        </div>
                    </div>
                    <div class="profile-card__body"><livewire:profile.update-password-form /></div>
                </section>

                <section class="profile-card">
                    <details class="danger-disclosure">
                        <summary>
                            <div class="section-icon section-icon--danger" aria-hidden="true"><i
                                    class="bx bx-error-circle"></i></div>
                            <div>
                                <h2 class="profile-section-title mb-0">Zona de riesgo</h2>
                                <p class="profile-section-copy mb-0">Acciones permanentes relacionadas con tu cuenta.
                                </p>
                            </div>
                        </summary>
                        <div class="danger-disclosure__content"><livewire:profile.delete-user-form /></div>
                    </details>
                </section>
            </div>
        </div>
    </main>
</x-app-layout>


