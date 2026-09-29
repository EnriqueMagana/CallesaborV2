@php
    $currentKey = match (true) {
        request()->routeIs('public.home') => 'home',
        request()->routeIs('public.reservation') => 'reservation',
        request()->routeIs('public.hours') => 'hours',
        request()->routeIs('public.social') => 'social',
        request()->routeIs('public.location') => 'location',
        default => null,
    };
    $quickAccesses = [
        ['key' => 'home', 'label' => 'Inicio', 'mobile_label' => 'Inicio', 'icon' => 'bx-home-alt', 'mobile_icon' => 'bx-home', 'href' => route('public.home')],
        ['key' => 'reservation', 'label' => 'Haz una reservación', 'mobile_label' => 'Reservar', 'icon' => 'bx-calendar-check', 'mobile_icon' => 'bx-calendar', 'href' => route('public.reservation')],
        ['key' => 'hours', 'label' => 'Horarios', 'mobile_label' => 'Horarios', 'icon' => 'bx-time-five', 'mobile_icon' => 'bx-time-five', 'href' => route('public.hours')],
        ['key' => 'social', 'label' => 'Redes sociales', 'mobile_label' => 'Redes', 'icon' => 'bx-share-alt', 'mobile_icon' => 'bx-share-alt', 'href' => route('public.social')],
        ['key' => 'location', 'label' => 'Ubicación', 'mobile_label' => 'Ubicación', 'icon' => 'bx-map-pin', 'mobile_icon' => 'bx-map', 'href' => route('public.location')],
    ];
@endphp

<section class="menu-farewell" aria-labelledby="menu-farewell-title" data-quick-access-carousel>
    <div class="menu-container">
        <header class="menu-farewell__heading">
            <h2 id="menu-farewell-title">Gracias por tu visita</h2>
            <p>Esperamos volver a verte pronto.</p>
        </header>

        <div class="menu-farewell__carousel">
            <button class="menu-farewell__control menu-farewell__control--previous" type="button"
                data-quick-access-previous aria-label="Ver acceso anterior" aria-controls="menu-quick-access-rail">
                <i class="bx bx-chevron-left" aria-hidden="true"></i>
            </button>
            <nav class="menu-farewell__rail" id="menu-quick-access-rail" data-quick-access-rail
                aria-label="Navegación de información del restaurante" aria-roledescription="carrusel" tabindex="0">
                @foreach ($quickAccesses as $access)
                    @php($isCurrent = $currentKey === $access['key'])
                    <a @class(['menu-farewell__item', 'is-current' => $isCurrent])
                        href="{{ $access['href'] }}" data-quick-access-item
                        data-access-key="{{ $access['key'] }}" data-access-label="{{ $access['label'] }}"
                        @if ($isCurrent) aria-current="page" @endif>
                        <span class="menu-farewell__icon" aria-hidden="true">
                            <i class="bx {{ $access['icon'] }} menu-farewell__icon-desktop"></i>
                            <i class="bx {{ $access['mobile_icon'] }} menu-farewell__icon-mobile"></i>
                        </span>
                        <span class="menu-farewell__label">
                            <span class="menu-farewell__label-desktop">{{ $access['label'] }}</span>
                            <span class="menu-farewell__label-mobile">{{ $access['mobile_label'] }}</span>
                        </span>
                    </a>
                @endforeach
            </nav>
            <button class="menu-farewell__control menu-farewell__control--next" type="button"
                data-quick-access-next aria-label="Ver siguiente acceso" aria-controls="menu-quick-access-rail">
                <i class="bx bx-chevron-right" aria-hidden="true"></i>
            </button>
        </div>

        <div class="menu-farewell__pagination">
            <span class="menu-farewell__position" aria-hidden="true">
                <strong data-quick-access-current>1</strong><span>de</span><strong>{{ count($quickAccesses) }}</strong>
            </span>
            <div class="menu-farewell__dots" aria-label="Elegir acceso rápido">
                @foreach ($quickAccesses as $access)
                    <button type="button" data-quick-access-dot="{{ $loop->index }}"
                        aria-label="Mostrar {{ $access['label'] }}" aria-pressed="false"></button>
                @endforeach
            </div>
            <p class="menu-farewell__current-label" data-quick-access-label aria-live="polite" aria-atomic="true"></p>
        </div>
    </div>
</section>
