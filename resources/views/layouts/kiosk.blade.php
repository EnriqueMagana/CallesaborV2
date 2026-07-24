<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#6956e8">
    <title>Kiosco · {{ $businessSettings?->business_name ?? config('app.name') }}</title>
    <link rel="icon" href="{{ $businessSettings?->logo_path ? Storage::url($businessSettings->logo_path) : asset('assets/img/favicon/favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/boxicons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/kiosk.css') }}?v={{ filemtime(public_path('assets/css/kiosk.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/delivery-tracking.css') }}?v={{ filemtime(public_path('assets/css/delivery-tracking.css')) }}">
    @livewireStyles
</head>
<body class="kiosk-body kiosk-is-booting">
    <div class="kiosk-initial-skeleton" role="status" aria-label="Cargando kiosco">
        <div class="kiosk-initial-skeleton-header">
            <i></i><span></span><b></b>
        </div>
        <div class="kiosk-initial-skeleton-body">
            <section>
                <i></i><b></b><b></b>
                <article><span></span><div><i></i><i></i><b></b></div></article>
            </section>
            <aside>
                <article><i></i><div><b></b><span></span></div></article>
                <article><i></i><div><b></b><span></span></div></article>
            </aside>
        </div>
        <span class="visually-hidden">Preparando productos y opciones del kiosco.</span>
    </div>
    {{ $slot }}
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('kioskImageLoader', () => ({
                loading: false,
                init() {
                    const image = this.$refs.image;

                    // Cached images are already complete here, so they never flash a skeleton.
                    if (!image || image.complete) return;

                    this.loading = true;
                    const finish = () => { this.loading = false; };
                    image.addEventListener('load', finish, { once: true });
                    image.addEventListener('error', finish, { once: true });
                },
            }));
        });
    </script>
    @livewireScripts
    <script>
        (() => {
            const revealKiosk = () => requestAnimationFrame(() => document.body.classList.remove('kiosk-is-booting'));
            if (document.readyState === 'complete') revealKiosk();
            else window.addEventListener('load', revealKiosk, { once: true });
        })();
    </script>
</body>
</html>
