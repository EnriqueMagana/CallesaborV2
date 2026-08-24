<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light-style layout-menu-fixed" dir="ltr"
    data-theme="theme-default" data-assets-path="{{ asset('assets/') }}" data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light">

    <title>{{ $businessSettings?->platform_name ?? config('app.name', 'Laravel') }} - Admin</title>

    {{-- Modo oscuro pausado temporalmente.
    <script src="{{ asset('assets/js/theme.js') }}?v={{ filemtime(public_path('assets/js/theme.js')) }}" data-navigate-once></script>
    --}}

    <!-- Favicon -->
    @include('partials.favicon')

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
        rel="stylesheet" />

    <!-- Icons -->
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/boxicons.css') }}" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/core.css') }}" class="template-customizer-core-css" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/theme-default.css') }}"
        class="template-customizer-theme-css" />
    <link rel="stylesheet" href="{{ asset('assets/css/demo.css') }}" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/apex-charts/apex-charts.css') }}" />

    <!-- App CSS -->
    <link rel="stylesheet" href="{{ asset('assets/css/global-search.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/mesa-orden.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/app-ui.css') }}?v={{ filemtime(public_path('assets/css/app-ui.css')) }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/profile.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/reservations.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/menu-builder.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/mesas.css') }}?v={{ filemtime(public_path('assets/css/mesas.css')) }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/admin-pages.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/user-management.css') }}?v={{ filemtime(public_path('assets/css/user-management.css')) }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/kiosk-admin.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/cash-cut.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/confirm-modal.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/extracted-ui.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/sales-history.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/business-settings.css') }}?v={{ filemtime(public_path('assets/css/business-settings.css')) }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/delivery.css') }}?v={{ filemtime(public_path('assets/css/delivery.css')) }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/inventory.css') }}?v={{ filemtime(public_path('assets/css/inventory.css')) }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}?v={{ filemtime(public_path('assets/css/dashboard.css')) }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/customers.css') }}?v={{ filemtime(public_path('assets/css/customers.css')) }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/customers-responsive.css') }}?v={{ filemtime(public_path('assets/css/customers-responsive.css')) }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/orders.css') }}?v={{ filemtime(public_path('assets/css/orders.css')) }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/role-permissions.css') }}?v={{ filemtime(public_path('assets/css/role-permissions.css')) }}" />

    @stack('styles')
    {{-- Modo oscuro pausado temporalmente.
    <link rel="stylesheet" href="{{ asset('assets/css/dark-theme.css') }}?v={{ filemtime(public_path('assets/css/dark-theme.css')) }}" />
    --}}

    <!-- Helpers -->
    <script src="{{ asset('assets/vendor/js/helpers.js') }}"></script>
    <script src="{{ asset('assets/js/config.js') }}"></script>

    @livewireStyles
</head>

<body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">

            <!-- Sidebar -->
            <livewire:layout.admin-sidebar />
            <!-- / Sidebar -->

            <!-- Layout page -->
            <div class="layout-page">

                <!-- Navbar -->
                <livewire:layout.admin-navbar />
                <!-- / Navbar -->

                <!-- Content wrapper -->
                <div class="content-wrapper">
                    <!-- Content -->
                    <div class="container-xxl flex-grow-1 container-p-y">
                        {{ $slot }}
                    </div>
                    <!-- / Content -->

                    <!-- Footer -->
                    <footer class="content-footer footer bg-footer-theme">
                        <div
                            class="container-xxl d-flex flex-wrap justify-content-between py-2 flex-md-row flex-column">
                            <div class="mb-2 mb-md-0">
                                © {{ date('Y') }}, Creado por ❤️ Cubittech <a href="#"
                                    class="fw-bolder">{{ $businessSettings?->platform_name ?? config('app.name') }}</a>
                            </div>
                        </div>
                    </footer>
                    <!-- / Footer -->

                    <div class="content-backdrop fade"></div>
                </div>
                <!-- / Content wrapper -->

            </div>
            <!-- / Layout page -->
        </div>

        <!-- Overlay -->
        <div class="layout-overlay layout-menu-toggle"></div>
    </div>
    <!-- / Layout wrapper -->

    <!-- Global confirm modal -->
    <livewire:ui.confirm-modal />

    <div id="app-session-ended-modal" class="app-session-ended-modal" role="alertdialog" aria-modal="true"
        aria-labelledby="app-session-ended-title" aria-describedby="app-session-ended-description"
        data-status-url="{{ route('auth.session-status') }}" data-login-url="{{ route('login') }}" hidden>
        <div class="app-session-ended-modal__backdrop" aria-hidden="true"></div>
        <section class="app-session-ended-dialog">
            <span class="app-session-ended-dialog__icon" aria-hidden="true"><i class="bx bx-log-out-circle"></i></span>
            <span class="app-session-ended-dialog__eyebrow">Sesión finalizada</span>
            <h2 id="app-session-ended-title">Tu sesión se cerró</h2>
            <p id="app-session-ended-description">Esta cuenta inició sesión en otro navegador. Por seguridad, este dispositivo ya no tiene acceso al sistema.</p>
            <button type="button" id="app-session-ended-accept">
                Aceptar e ir al login <i class="bx bx-right-arrow-alt" aria-hidden="true"></i>
            </button>
        </section>
    </div>

    <!-- Core JS -->
    <script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/popper/popper.js') }}"></script>
    <script src="{{ asset('assets/vendor/js/bootstrap.js') }}" data-navigate-once></script>
    <script src="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}" data-navigate-once></script>
    <script src="{{ asset('assets/vendor/js/menu.js') }}" data-navigate-once></script>

    <!-- Vendors JS -->
    <script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}" data-navigate-once></script>

    <!-- Main JS -->
    <script src="{{ asset('assets/js/main.js') }}?v={{ filemtime(public_path('assets/js/main.js')) }}" data-navigate-once></script>

    @livewireScripts

    <script src="{{ asset('assets/js/dashboard.js') }}?v={{ filemtime(public_path('assets/js/dashboard.js')) }}" data-navigate-once></script>

    @stack('scripts')
</body>

</html>
