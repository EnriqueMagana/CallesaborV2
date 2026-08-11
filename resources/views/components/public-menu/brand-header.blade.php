@props([
    'business',
    'openingStatus',
    'eyebrow' => 'Banner 1',
    'message' => null,
    'actionLabel' => 'Explorar la carta',
    'actionHref' => null,
    'actionIcon' => 'bx-down-arrow-alt',
])
@php($resolvedActionHref = $actionHref ?: route('public.menu') . '#menu')
@php($heroMessage = $message ?: $business->business_name . ' te espera con platos preparados para compartir, mesas listas y un ambiente cuidadosamente diseñado para tu próxima visita.')

<header class="menu-cover" id="inicio">
    <div class="menu-cover__media {{ $business->banner_path ? 'has-image' : '' }}">
        @if ($business->banner_path)
            <img src="{{ Storage::url($business->banner_path) }}" alt="Ambiente de {{ $business->business_name }}"
                width="1600" height="640" fetchpriority="high">
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
    </div>

    <div class="menu-container menu-identity">
        <div class="menu-identity__brand">
            @if ($business->logo_path)
                <img src="{{ Storage::url($business->logo_path) }}" alt="Logo de {{ $business->business_name }}"
                    width="112" height="112">
            @else
                <span class="menu-brand__fallback" aria-hidden="true"><i class="bx bx-bowl-hot"></i></span>
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
