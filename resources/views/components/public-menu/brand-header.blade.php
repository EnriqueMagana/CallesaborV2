@props([
    'business',
    'menuSettings' => null,
    'openingStatus',
    'eyebrow' => 'Banner 1',
    'message' => null,
    'actionLabel' => 'Explorar la carta',
    'actionHref' => null,
    'actionIcon' => 'bx-down-arrow-alt',
])
@php
    $resolvedActionHref = $actionHref ?: route('public.menu') . '#menu';
    $heroMessage = $message ?: $business->business_name . ' te espera con platos preparados para compartir, mesas listas y un ambiente cuidadosamente diseñado para tu próxima visita.';
    $bannerItems = $menuSettings
        ? collect($menuSettings->show_banners ? $menuSettings->bannerItems() : [])
        : collect($business->banner_path ? [['path' => $business->banner_path, 'alt' => '']] : []);
    $hasCarousel = $bannerItems->count() > 1;
@endphp

<header class="menu-cover" id="inicio">
    <div class="menu-cover__media {{ $bannerItems->isNotEmpty() ? 'has-image' : '' }}"
        @if($hasCarousel) data-menu-banner-carousel data-autoplay="{{ $menuSettings?->autoplay_banners ? 'true' : 'false' }}" data-interval="{{ ((int) ($menuSettings?->banner_interval_seconds ?? 5)) * 1000 }}" @endif>
        @if ($bannerItems->isNotEmpty())
            <div class="menu-cover__slides">
                @foreach($bannerItems as $item)
                    <figure class="menu-cover__slide {{ $loop->first ? 'is-active' : '' }}" data-menu-banner-slide aria-hidden="{{ $loop->first ? 'false' : 'true' }}">
                        <img src="{{ Storage::url($item['path']) }}" alt="{{ $item['alt'] ?: 'Ambiente de '.$business->business_name }}"
                            width="1600" height="640" loading="{{ $loop->first ? 'eager' : 'lazy' }}" fetchpriority="{{ $loop->first ? 'high' : 'auto' }}" decoding="async">
                    </figure>
                @endforeach
            </div>
        @endif
        <div class="menu-cover__overlay"></div>
        <div class="menu-container menu-cover__topbar">
            <a href="{{ route('public.menu') }}" class="menu-cover__restaurant">{{ $business->business_name }}</a>
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
        @if($hasCarousel)
            <div class="menu-container menu-cover__carousel-controls">
                <button type="button" data-banner-previous aria-label="Banner anterior"><i class="bx bx-chevron-left" aria-hidden="true"></i></button>
                <div role="tablist" aria-label="Seleccionar banner">
                    @foreach($bannerItems as $item)
                        <button type="button" data-banner-dot="{{ $loop->index }}" class="{{ $loop->first ? 'is-active' : '' }}" role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}" aria-label="Mostrar banner {{ $loop->iteration }}"></button>
                    @endforeach
                </div>
                <button type="button" data-banner-next aria-label="Banner siguiente"><i class="bx bx-chevron-right" aria-hidden="true"></i></button>
                <button type="button" data-banner-pause aria-label="Pausar carrusel" aria-pressed="false"><i class="bx bx-pause" aria-hidden="true"></i></button>
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
            <span>
                <small>Bienvenido a</small>
                <strong>{{ $business->business_name }}</strong>
                @if ($business->full_address)
                    @if ($business->map_link)
                        <a href="{{ $business->map_link }}" target="_blank" rel="noopener noreferrer"><i
                                class="bx bx-map" aria-hidden="true"></i>{{ $business->full_address }}<i
                                class="bx bx-link-external" aria-hidden="true"></i></a>
                    @else
                        <span><i class="bx bx-map" aria-hidden="true"></i>{{ $business->full_address }}</span>
                    @endif
                @endif
            </span>
        </div>
        <a class="menu-identity__menu-link" href="{{ $resolvedActionHref }}"><span>{{ $actionLabel }}</span><i
                class="bx {{ $actionIcon }}" aria-hidden="true"></i></a>
    </div>
</header>
