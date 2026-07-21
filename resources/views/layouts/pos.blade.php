<!DOCTYPE html>
<html lang="es" class="light-style" dir="ltr"
      data-theme="theme-default"
      data-assets-path="{{ asset('assets/') }}"
      data-template="vertical-menu-template-free">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>POS — {{ $businessSettings?->platform_name ?? config('app.name') }}</title>

<link rel="icon" href="{{ $businessSettings?->logo_path ? Storage::url($businessSettings->logo_path) : asset('assets/img/favicon/favicon.ico') }}"/>

<!-- Fonts (misma que el tema) -->
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet"/>

<!-- Icons -->
<link rel="stylesheet" href="{{ asset('assets/vendor/fonts/boxicons.css') }}"/>

<!-- Core CSS del tema (variables de color, componentes) -->
<link rel="stylesheet" href="{{ asset('assets/vendor/css/core.css') }}" class="template-customizer-core-css"/>
<link rel="stylesheet" href="{{ asset('assets/vendor/css/theme-default.css') }}" class="template-customizer-theme-css"/>
<link rel="stylesheet" href="{{ asset('assets/css/demo.css') }}"/>
<link rel="stylesheet" href="{{ asset('assets/css/pos.css') }}?v={{ filemtime(public_path('assets/css/pos.css')) }}"/>
<link rel="stylesheet" href="{{ asset('assets/css/extracted-ui.css') }}"/>
<link rel="stylesheet" href="{{ asset('assets/css/pos-modern.css') }}"/>
<link rel="stylesheet" href="{{ asset('assets/css/confirm-modal.css') }}"/>

<!-- Helpers -->
<script src="{{ asset('assets/vendor/js/helpers.js') }}"></script>
<script src="{{ asset('assets/js/config.js') }}"></script>

@livewireStyles


</head>
<body>
<div id="pos-loading-screen">
    <div id="pos-loading-spinner"></div>
    <span>Cargando POS…</span>
</div>

{{ $slot }}
<livewire:ui.confirm-modal />

<!-- Core JS (mismo que app.blade.php) -->
<script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/popper/popper.js') }}"></script>
<script src="{{ asset('assets/vendor/js/bootstrap.js') }}"></script>
<script src="{{ asset('assets/js/main.js') }}"></script>

@livewireScripts
@stack('scripts')

<script>
(function () {
    function hidePosLoader() {
        var el = document.getElementById('pos-loading-screen');
        if (!el) return;
        el.classList.add('fade-out');
        setTimeout(function () { el.remove(); }, 350);
    }
    document.addEventListener('livewire:initialized', hidePosLoader);
    // Fallback: remove after 3s even if Livewire hook doesn't fire
    setTimeout(hidePosLoader, 3000);
})();
</script>
</body>
</html>
