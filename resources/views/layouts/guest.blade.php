<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $businessSettings?->platform_name ?? config('app.name', 'Calle Sabor') }}</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/boxicons.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet"
        href="{{ asset('assets/css/login.css') }}?v={{ filemtime(public_path('assets/css/login.css')) }}">
</head>

<body class="auth-body">
    <main class="auth-shell">
        <section class="auth-brand-panel" aria-label="Información del negocio">
            @if ($businessSettings?->banner_path)
                <img class="auth-brand-panel__image" src="{{ Storage::url($businessSettings->banner_path) }}"
                    alt="Ambiente de {{ $businessSettings->business_name }}">
            @endif
            <div class="auth-brand-panel__overlay" aria-hidden="true"></div>
            <div class="auth-brand-panel__content">
                <a href="/" class="auth-brand"
                    aria-label="Ir al inicio de {{ $businessSettings?->business_name ?? config('app.name') }}">
                    <span class="auth-brand__logo"><x-application-logo /></span>
                    <span><strong>{{ $businessSettings?->platform_name ?? config('app.name', 'Calle Sabor') }}</strong><small>{{ $businessSettings?->business_name ?? 'Administración del restaurante' }}</small></span>
                </a>

                <div class="auth-brand-message">
                    <h1>Todo listo para comenzar el turno.</h1>
                    <ul>
                        <li><i class="bx bx-check" aria-hidden="true"></i><span>Información centralizada y disponible
                                según tus permisos.</span></li>
                        <li><i class="bx bx-check" aria-hidden="true"></i><span>Una sola sesión activa para proteger
                                cada cuenta.</span></li>
                    </ul>
                    <div class="auth-brand-modules" aria-label="Módulos operativos">
                        <span><i class="bx bx-receipt" aria-hidden="true"></i>Ventas</span>
                        <span><i class="bx bx-chair" aria-hidden="true"></i>Mesas</span>
                        <span><i class="bx bx-dish" aria-hidden="true"></i>Cocina</span>
                        <span><i class="bx bx-package" aria-hidden="true"></i>Productos</span>
                        <span><i class="bx bx-user" aria-hidden="true"></i>Clientes</span>
                        <span><i class="bx bx-calendar" aria-hidden="true"></i>Reservas</span>
                        <span><i class="bx bx-cycling" aria-hidden="true"></i>Delivery</span>
                        <span><i class="bx bx-user-pin" aria-hidden="true"></i>Empleados</span>
                        <span><i class="bx bx-purchase-tag" aria-hidden="true"></i>Promociones</span>
                    </div>
                </div>

                @if ($businessSettings?->phone || $businessSettings?->email || $businessSettings?->full_address)
                    <address class="auth-business-contact">
                        @if ($businessSettings?->phone)
                            <span><i class="bx bx-phone" aria-hidden="true"></i>{{ $businessSettings->phone }}</span>
                        @endif
                        @if ($businessSettings?->email)
                            <span><i class="bx bx-envelope"
                                    aria-hidden="true"></i>{{ $businessSettings->email }}</span>
                        @endif
                        @if ($businessSettings?->full_address)
                            <span><i class="bx bx-map"
                                    aria-hidden="true"></i>{{ $businessSettings->full_address }}</span>
                        @endif
                    </address>
                @endif
            </div>
        </section>

        <section class="auth-content">
            <div class="auth-mobile-brand">
                <span class="auth-brand__logo"><x-application-logo /></span>
                <span><strong>{{ $businessSettings?->platform_name ?? config('app.name', 'Calle Sabor') }}</strong><small>{{ $businessSettings?->business_name ?? 'Administración del restaurante' }}</small></span>
            </div>

            <div class="auth-card">
                {{ $slot }}
            </div>

            <footer class="auth-footer">
                <span>
                    © {{ now()->year }} {{ $businessSettings?->business_name ?? config('app.name') }}
                </span>

                <span>
                    Powered by <strong>Cubittech</strong>
                </span>
            </footer>
        </section>
    </main>
</body>

</html>
