<!DOCTYPE html>
<html lang="es" class="light-style" dir="ltr"
      data-theme="theme-default"
      data-assets-path="{{ asset('assets/') }}"
      data-template="vertical-menu-template-free">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="realtime-notification-session" content="{{ route('app.notifications.realtime-session') }}">
<title>POS — {{ $businessSettings?->platform_name ?? config('app.name') }}</title>

@include('partials.favicon')

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
<link rel="stylesheet" href="@assetVersion('assets/vendor/css/core.min.css')" class="template-customizer-core-css"/>
<link rel="stylesheet" href="@assetVersion('assets/vendor/css/theme-default.min.css')" class="template-customizer-theme-css"/>
<link rel="stylesheet" href="@assetVersion('assets/css/demo.min.css')"/>
<link rel="stylesheet" href="@assetVersion('assets/css/pos.min.css')"/>
<link rel="stylesheet" href="@assetVersion('assets/css/extracted-ui.min.css')"/>
<link rel="stylesheet" href="@assetVersion('assets/css/pos-modern.min.css')"/>
{{-- Modo oscuro pausado temporalmente.
<link rel="stylesheet" href="{{ asset('assets/css/dark-theme.css') }}?v={{ filemtime(public_path('assets/css/dark-theme.css')) }}"/>
--}}
<link rel="stylesheet" href="@assetVersion('assets/css/confirm-modal.min.css')"/>
<link rel="stylesheet" href="@assetVersion('assets/css/notification-center.min.css')"/>
<link rel="stylesheet" href="@assetVersion('assets/css/pos-mobile-navigation.min.css')"/>
<link rel="stylesheet" href="@assetVersion('assets/css/ticket-preview-modal.min.css')"/>

<!-- Helpers -->
<script src="@assetVersion('assets/vendor/js/helpers.min.js')"></script>
<script src="@assetVersion('assets/js/config.min.js')"></script>
<script src="@assetVersion('assets/js/ticket-preview-modal.min.js')"></script>
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

{{-- jQuery, Popper y Bootstrap JS se retiraron de esta pantalla: eran 642 KB
     que el POS descargaba sin usar. La interactividad es de Alpine (que viene
     con Livewire), no de Bootstrap.

     La comprobación está automatizada en `PosVendorUsageTest`: si alguien
     agrega un `data-bs-*`, un `$(...)` o un `new bootstrap.Modal` a una vista
     del POS, la prueba falla y explica que hay que volver a cargarlos aquí.

     El CSS del tema (`core.min.css`) sí se queda: las clases de Bootstrap se
     siguen usando para el diseño, y eso no necesita su JavaScript. --}}

{{-- Estado raíz del POS. Va antes de Livewire para que su `alpine:init` corra
     antes de que Alpine arranque los componentes. --}}
<script src="@assetVersion('assets/js/pos-root.min.js')"></script>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('posProductImage', () => ({
        state: 'waiting',
        observer: null,
        image: null,
        init() {
            this.image = this.$refs.image;
            if (!this.image) return;

            const requestImage = () => {
                if (this.state !== 'waiting') return;

                this.state = 'loading';
                this.observer?.disconnect();
                this.observer = null;
                this.image.addEventListener('load', () => this.reveal(), { once: true });
                this.image.addEventListener('error', () => this.fail(), { once: true });
                this.image.src = this.image.dataset.src;

                if (this.image.complete) {
                    this.image.naturalWidth > 0 ? this.reveal() : this.fail();
                }
            };

            if (!('IntersectionObserver' in window)) {
                requestImage();
                return;
            }

            this.observer = new IntersectionObserver((entries) => {
                if (entries.some(entry => entry.isIntersecting)) requestImage();
            }, {
                root: this.$el.closest('.catalog-grid'),
                rootMargin: '320px 0px',
                threshold: 0.01,
            });
            this.observer.observe(this.$el);
        },
        async reveal() {
            if (!this.image || this.state === 'decoding' || this.state === 'ready') return;

            this.state = 'decoding';

            try {
                if (typeof this.image.decode === 'function') await this.image.decode();
                requestAnimationFrame(() => { this.state = 'ready'; });
            } catch (error) {
                this.image.naturalWidth > 0
                    ? requestAnimationFrame(() => { this.state = 'ready'; })
                    : this.fail();
            }
        },
        fail() {
            this.state = 'error';
            this.observer?.disconnect();
            this.observer = null;
        },
        destroy() {
            this.observer?.disconnect();
        },
    }));
});
</script>

@livewireScripts
@vite('resources/js/app.js')
<script src="@assetVersion('assets/js/notification-center.min.js')" data-navigate-once></script>
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
