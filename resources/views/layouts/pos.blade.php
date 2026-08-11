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

{{-- Reserva la geometría esencial antes de cargar las hojas completas. --}}
<style>
html,body{width:100%;height:100%;margin:0;overflow:hidden;background:#f6f5f8}
#pos-loading-screen{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:14px;background:#f6f5f8;color:#6f6b80;font:600 13px/1.4 "Public Sans","Segoe UI",sans-serif;transition:opacity .2s ease}
#pos-loading-screen.fade-out{opacity:0;pointer-events:none}
#pos-loading-spinner{width:42px;height:42px;border:3px solid rgba(105,86,232,.16);border-top-color:#6956e8;border-radius:50%;animation:pos-critical-spin .7s linear infinite}
.pos-root{position:fixed;inset:0;display:flex;overflow:hidden;flex-direction:column;background:#f6f5f8}
.pos-logo,.pos-logo-img{width:42px;height:42px;max-width:42px;max-height:42px}
.pos-logo-img{display:block;object-fit:contain}
@keyframes pos-critical-spin{to{transform:rotate(360deg)}}
@media (prefers-reduced-motion:reduce){#pos-loading-spinner{animation-duration:1.5s}}
/* Modo oscuro pausado temporalmente.
html.dark-style body,html.dark-style .pos-root{background:#11131a;color:#f2f4f8}
html.dark-style #pos-loading-screen{background:#11131a;color:#a8b0bf}
*/
</style>

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
<link rel="stylesheet" href="{{ asset('assets/css/extracted-ui.css') }}?v={{ filemtime(public_path('assets/css/extracted-ui.css')) }}"/>
<link rel="stylesheet" href="{{ asset('assets/css/pos-modern.css') }}?v={{ filemtime(public_path('assets/css/pos-modern.css')) }}"/>
{{-- Modo oscuro pausado temporalmente.
<link rel="stylesheet" href="{{ asset('assets/css/dark-theme.css') }}?v={{ filemtime(public_path('assets/css/dark-theme.css')) }}"/>
--}}
<link rel="stylesheet" href="{{ asset('assets/css/confirm-modal.css') }}"/>

<!-- Helpers -->
<script src="{{ asset('assets/vendor/js/helpers.js') }}"></script>
<script src="{{ asset('assets/js/config.js') }}"></script>
{{-- Modo oscuro pausado temporalmente.
<script src="{{ asset('assets/js/theme.js') }}?v={{ filemtime(public_path('assets/js/theme.js')) }}"></script>
--}}

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

@livewireScripts
@stack('scripts')

<script>
(function () {
    function hidePosLoader() {
        var el = document.getElementById('pos-loading-screen');
        if (!el) return;
        el.classList.add('fade-out');
        setTimeout(function () { el.remove(); }, 220);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hidePosLoader, { once: true });
    } else {
        requestAnimationFrame(hidePosLoader);
    }

    document.addEventListener('livewire:navigated', hidePosLoader);
    setTimeout(hidePosLoader, 900);
})();
</script>
</body>
</html>
