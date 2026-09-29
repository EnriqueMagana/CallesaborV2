@props(['business'])

@php
    $logoUrl = $business->logo_path
        ? Storage::url($business->logo_path)
        : asset('assets/img/restaurant/logo_light.png');
@endphp

<div class="public-page-loader" data-public-page-loader role="status" aria-live="polite"
    aria-label="Cargando {{ $business->business_name }}">
    <div class="public-page-loader__content" aria-hidden="true">
        <span class="public-page-loader__pulse"></span>
        <span class="public-page-loader__logo">
            <img src="{{ $logoUrl }}" alt="" width="150" height="86" loading="eager" fetchpriority="high"
                decoding="async">
        </span>
    </div>
    <span class="sr-only">Cargando {{ $business->business_name }}</span>
</div>

<noscript>
    <style>
        body.has-public-page-loader { overflow: auto; }
        .public-page-loader { display: none; }
    </style>
</noscript>

<script>
    (() => {
        const loader = document.querySelector('[data-public-page-loader]');
        if (!loader) return;

        const startedAt = performance.now();
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        let dismissed = false;

        const removeLoader = () => {
            if (dismissed) return;
            dismissed = true;

            const minimumVisibleTime = reduceMotion ? 0 : 180;
            const remainingTime = Math.max(0, minimumVisibleTime - (performance.now() - startedAt));

            window.setTimeout(() => {
                loader.classList.add('is-leaving');
                document.body.classList.remove('has-public-page-loader');
                document.body.setAttribute('aria-busy', 'false');
                window.setTimeout(() => loader.remove(), reduceMotion ? 0 : 240);
            }, remainingTime);
        };

        if (document.readyState === 'complete') {
            window.requestAnimationFrame(removeLoader);
        } else {
            window.addEventListener('load', removeLoader, { once: true });
        }

        window.setTimeout(removeLoader, 3000);
    })();
</script>
