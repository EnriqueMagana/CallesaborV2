@props([
    'business',
    'menuSettings' => null,
    'openingStatus',
    'eyebrow' => 'Bienvenido a',
    'message' => null,
    'actionLabel' => 'Explorar la carta',
    'actionHref' => null,
    'actionIcon' => 'bx-down-arrow-alt',
])
@php
    $resolvedActionHref = $actionHref ?: route('public.menu') . '#menu';
    $heroMessage =
        $message ?:
        $business->business_name .
            ' te espera con platos preparados para compartir, mesas listas y un ambiente cuidadosamente diseñado para tu próxima visita.';
    $bannerItems = $menuSettings
        ? collect($menuSettings->show_banners ? $menuSettings->bannerItems() : [])
        : collect($business->banner_path ? [['path' => $business->banner_path, 'alt' => '']] : []);
    $hasCarousel = $bannerItems->count() > 1;
@endphp

<header class="menu-cover" id="inicio">
    <div class="menu-cover__media {{ $bannerItems->isNotEmpty() ? 'has-image' : '' }}"
        @if ($hasCarousel) data-menu-banner-carousel data-autoplay="{{ $menuSettings?->autoplay_banners ? 'true' : 'false' }}" data-interval="{{ ((int) ($menuSettings?->banner_interval_seconds ?? 5)) * 1000 }}" @endif>
        @if ($bannerItems->isNotEmpty())
            <div class="menu-cover__slides">
                @foreach ($bannerItems as $item)
                    <figure class="menu-cover__slide menu-image-shell is-image-loading {{ $loop->first ? 'is-active' : '' }}"
                        data-menu-banner-slide data-menu-image-shell
                        aria-hidden="{{ $loop->first ? 'false' : 'true' }}">
                        <img src="{{ Storage::url($item['path']) }}"
                            alt="{{ $item['alt'] ?: 'Ambiente de ' . $business->business_name }}" width="1600"
                            height="640" loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                            fetchpriority="{{ $loop->first ? 'high' : 'auto' }}" decoding="async"
                            data-menu-image>
                    </figure>
                @endforeach
            </div>
        @endif
        <div class="menu-cover__overlay"></div>
        <div class="menu-container menu-cover__topbar">
            <div class="menu-cover__top-actions">
                <a class="menu-status menu-status--{{ $openingStatus['is_open'] ? 'open' : 'closed' }}"
                    href="{{ route('public.hours') }}">
                    <span aria-hidden="true"></span>
                    <span><strong>{{ $openingStatus['label'] }}</strong><small>{{ $openingStatus['detail'] }}</small></span>
                </a>
                <a class="menu-system-link" href="{{ route('login') }}" aria-label="Acceso al sistema">
                    <i class="bx bx-user" aria-hidden="true"></i>
                </a>
            </div>
        </div>
        @if ($hasCarousel)
            <div class="menu-container menu-cover__carousel-controls">
                <div role="tablist" aria-label="Seleccionar banner">
                    @foreach ($bannerItems as $item)
                        <button type="button" data-banner-dot="{{ $loop->index }}"
                            class="{{ $loop->first ? 'is-active' : '' }}" role="tab"
                            aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                            aria-label="Mostrar banner {{ $loop->iteration }}"></button>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <div class="menu-container menu-identity">
        <div class="menu-identity__brand">
            @if ($business->logo_path)
                <img src="{{ Storage::url($business->logo_path) }}" alt="Logo de {{ $business->business_name }}"
                    width="112" height="112">
            @else
                <span class="menu-brand__fallback" aria-hidden="true"><i class="bx bx-restaurant"></i></span>
            @endif
            <span class="menu-identity__brand-copy">
                <small><i class="bx bx-store-alt" aria-hidden="true"></i>{{ $eyebrow }}</small>
                <strong>{{ $business->business_name }}</strong>
            </span>
        </div>
        <a class="menu-identity__menu-link" href="{{ $resolvedActionHref }}"><span>{{ $actionLabel }}</span><i
                class="bx {{ $actionIcon }}" aria-hidden="true"></i></a>
    </div>
</header>
