<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ $menuSettings->primary_color ?? '#15803d' }}">
    <meta name="description" content="Descubre el menú, reserva una mesa y conoce {{ $business->business_name }}.">
    <title>{{ $business->business_name }} | Menú y reservaciones</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Instrument+Sans:ital,wght@0,400;0,500;0,600;1,400&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet"
        href="{{ asset('assets/vendor/fonts/boxicons.css') }}?v={{ filemtime(public_path('assets/vendor/fonts/boxicons.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('assets/css/public-menu.css') }}?v={{ filemtime(public_path('assets/css/public-menu.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('assets/css/public-home.css') }}?v={{ filemtime(public_path('assets/css/public-home.css')) }}">
    @livewireStyles
</head>

<body class="public-home" style="--menu-primary: {{ $menuSettings->primary_color ?? '#15803d' }}">
    <a class="menu-skip-link" href="#experiencia">Saltar al contenido</a>
    <div class="home-preloader" data-home-preloader role="status" aria-live="polite"
        aria-label="Cargando {{ $business->business_name }}">
        <div class="home-preloader__chase" aria-hidden="true">
            @for ($dot = 0; $dot < 6; $dot++)
                <span></span>
            @endfor
        </div>
    </div>
    @php
        $heroSlides = collect($menuSettings->show_banners ? $menuSettings->bannerItems() : []);
    @endphp

    <header class="home-hero" id="inicio" data-home-hero role="region"
        data-autoplay="{{ $menuSettings->autoplay_banners ? 'true' : 'false' }}"
        data-interval="{{ ((int) $menuSettings->banner_interval_seconds) * 1000 }}"
        aria-label="Presentación de {{ $business->business_name }}">
        <div class="home-hero__slides" aria-live="off">
            @foreach ($heroSlides as $slide)
                <figure class="home-hero__slide {{ $loop->first ? 'is-active' : '' }}" data-home-hero-slide
                    role="group" aria-label="Imagen {{ $loop->iteration }} de {{ $heroSlides->count() }}"
                    aria-hidden="{{ $loop->first ? 'false' : 'true' }}">
                    <img src="{{ Storage::url($slide['path']) }}"
                        alt="{{ $slide['alt'] ?: 'Ambiente de ' . $business->business_name }}"
                        width="1920" height="1080" loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                        fetchpriority="{{ $loop->first ? 'high' : 'auto' }}" decoding="async">
                </figure>
            @endforeach
        </div>
        <div class="home-hero__veil" aria-hidden="true"></div>

        <div class="menu-container home-hero__topbar">
            <a class="home-hero__brand" href="{{ route('public.home') }}"
                aria-label="Inicio de {{ $business->business_name }}">
                @if ($business->logo_path)
                    <img src="{{ Storage::url($business->logo_path) }}" alt="" width="60" height="60">
                @else
                    <img src="{{ asset('assets/img/restaurant/logo_light.png') }}" alt="" width="100"
                        height="98">
                @endif
                <span><small>Restaurante</small><strong>{{ $business->business_name }}</strong></span>
            </a>
            <nav class="home-hero__links" aria-label="Accesos principales">
                <a href="{{ route('public.menu') }}">Menú</a>
                <a href="#reservar">Reservaciones</a>
                @if ($menuSettings->show_gallery)
                    <a href="#galeria">Galería</a>
                @endif
                <a href="#contacto">Contacto</a>
            </nav>
            <div class="home-hero__utilities">
                <a class="home-hero__status home-hero__status--{{ $openingStatus['is_open'] ? 'open' : 'closed' }}"
                    href="{{ route('public.hours') }}"
                    aria-label="{{ $openingStatus['label'] }}: {{ $openingStatus['detail'] }}. Ver horarios">
                    <span aria-hidden="true"></span>
                    <span><strong>{{ $openingStatus['label'] }}</strong><small>{{ $openingStatus['detail'] }}</small></span>
                </a>
                <a class="home-hero__login" href="{{ route('login') }}" aria-label="Acceso al sistema">
                    <i class="bx bx-user" aria-hidden="true"></i>
                </a>
            </div>
        </div>

        <div class="menu-container home-hero__content">
            <div class="home-hero__copy">
                @if ($business->home_badge)
                    <span class="home-hero__badge"><i class="bx bx-star" aria-hidden="true"></i>{{ $business->home_badge }}</span>
                @endif
                <h1>{{ $business->home_headline ?: 'Sabores que convierten una comida en un recuerdo.' }}</h1>
                <p>{{ $business->home_description ?: 'Descubre una carta preparada con ingredientes frescos, recetas con identidad y la hospitalidad que hace especial cada visita.' }}
                </p>
                <div class="home-hero__actions">
                    <a class="home-hero__primary" href="{{ route('public.menu') }}">
                        <span>Explorar el menú</span><i class="bx bx-right-arrow-alt" aria-hidden="true"></i>
                    </a>
                    <a class="home-hero__secondary" href="#reservar">
                        <i class="bx bx-calendar-check" aria-hidden="true"></i><span>Reservar una mesa</span>
                    </a>
                </div>
                <ul class="home-hero__proof" aria-label="Características del restaurante">
                    <li><i class="bx bx-check" aria-hidden="true"></i> Menú actualizado</li>
                    <li><i class="bx bx-check" aria-hidden="true"></i> Reserva desde tu celular</li>
                    <li><i class="bx bx-check" aria-hidden="true"></i> Atención cercana</li>
                </ul>
            </div>
        </div>
    </header>

    <nav class="home-nav" aria-label="Navegación principal">
        <div class="menu-container">
            <a href="{{ route('public.menu') }}"><i class="bx bx-food-menu" aria-hidden="true"></i>Menú</a>
            <a href="#reservar"><i class="bx bx-calendar-check" aria-hidden="true"></i>Reservar</a>
            <a href="#horarios"><i class="bx bx-time-five" aria-hidden="true"></i>Horarios</a>
            @if ($menuSettings->show_gallery)
                <a href="#galeria"><i class="bx bx-images" aria-hidden="true"></i>Galería</a>
            @endif
            <a href="#contacto"><i class="bx bx-message-rounded-dots" aria-hidden="true"></i>Contacto</a>
        </div>
    </nav>

    <main id="experiencia" tabindex="-1">
        <section class="home-intro menu-container" aria-labelledby="home-intro-title" data-home-reveal>
            <div class="home-intro__copy">
                <span class="menu-kicker">{{ $business->home_intro_kicker ?: 'Hospitalidad con sabor local' }}</span>
                <h2 id="home-intro-title">
                    {{ $business->home_intro_title ?: 'Una experiencia auténtica, pensada para disfrutarse sin prisa.' }}
                </h2>
                <p>{{ $business->home_intro_description ?: 'En ' . $business->business_name . ' reunimos cocina, ambiente y servicio para que cada visita se sienta especial. Conoce nuestra propuesta antes de llegar y reserva en pocos pasos desde cualquier dispositivo.' }}
                </p>
                <div class="home-intro__badges" aria-label="Beneficios de la experiencia">
                    <span><i class="bx bx-dish" aria-hidden="true"></i>Ingredientes frescos</span>
                    <span><i class="bx bx-time" aria-hidden="true"></i>Servicio ágil</span>
                    <span><i class="bx bx-heart" aria-hidden="true"></i>Ambiente acogedor</span>
                </div>
            </div>
            <div class="home-intro__actions">
                <a href="{{ route('public.menu') }}" class="home-primary-action"><span><i class="bx bx-food-menu"
                            aria-hidden="true"></i></span><span><small>Descubre nuestros
                            sabores</small><strong>Explorar
                            el menú</strong></span><i class="bx bx-right-arrow-alt" aria-hidden="true"></i></a>
                <a href="#reservar" class="home-secondary-action"><span><i class="bx bx-calendar-check"
                            aria-hidden="true"></i></span><span><small>Planea tu experiencia</small><strong>Reservar
                            una
                            mesa</strong></span><i class="bx bx-down-arrow-alt" aria-hidden="true"></i></a>
            </div>
        </section>

        <section class="home-highlights" aria-labelledby="home-highlights-title" data-home-reveal>
            <div class="menu-container">
                <div class="section-heading">
                    <div><span class="menu-kicker">Experiencia</span>
                        <h2 id="home-highlights-title">Tres razones para elegirnos</h2>
                    </div>
                </div>
                <div class="home-highlights__grid">
                    <article class="home-highlight-card">
                        <span><i class="bx bx-star" aria-hidden="true"></i></span>
                        <strong>Menú profesional</strong>
                        <p>Platos cuidados y opciones para cada gusto.</p>
                    </article>
                    <article class="home-highlight-card">
                        <span><i class="bx bx-home-heart" aria-hidden="true"></i></span>
                        <strong>Ambiente auténtico</strong>
                        <p>Un espacio cálido, elegante y con identidad.</p>
                    </article>
                    <article class="home-highlight-card">
                        <span><i class="bx bx-phone-call" aria-hidden="true"></i></span>
                        <strong>Reserva sin esfuerzo</strong>
                        <p>Agenda tu mesa desde el celular en segundos.</p>
                    </article>
                </div>
            </div>
        </section>

        @if ($menuSettings->show_categories && $categories->isNotEmpty())
            <section @class([
                'home-categories',
                'home-categories--circles' => $menuSettings->category_style === 'circles',
            ]) aria-labelledby="home-categories-title" data-home-reveal>
                <div class="menu-container">
                    <div class="section-heading">
                        <div><span class="menu-kicker">La carta</span>
                            <h2 id="home-categories-title">Encuentra tu próximo favorito</h2>
                        </div><a href="{{ route('public.menu') }}">Ver menú completo <i class="bx bx-right-arrow-alt"
                                aria-hidden="true"></i></a>
                    </div>
                    <div class="home-categories__rail">
                        @foreach ($categories as $category)
                            @php($preview = $category->products->first())
                            <a href="{{ route('public.menu') }}#category-{{ $category->id }}"
                                @class([
                                    'home-category-card',
                                    'home-category-card--circle' => $menuSettings->category_style === 'circles',
                                ])
                                style="--category-color: {{ $category->color ?: $menuSettings->primary_color }}">
                                <div class="home-category-card__media">
                                    @if ($preview?->image)
                                        <img src="{{ Storage::url($preview->image) }}" alt="{{ $category->name }}"
                                            width="560" height="420" loading="lazy" decoding="async">
                                    @else
                                        <span><i class="bx {{ $category->icon ?: 'bx-food-menu' }}"
                                                aria-hidden="true"></i></span>
                                    @endif
                                    <span>{{ $category->products_count }}
                                        {{ $category->products_count === 1 ? 'opción' : 'opciones' }}</span>
                                </div>
                                <div>
                                    <span><i class="bx {{ $category->icon ?: 'bx-food-menu' }}"
                                            aria-hidden="true"></i></span>
                                    <span><strong>{{ $category->name }}</strong><small>{{ $category->description ?: 'Explora esta categoría' }}</small></span>
                                    <i class="bx bx-right-arrow-alt" aria-hidden="true"></i>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <section class="home-reservation home-section--white-divider" id="reservar"
            aria-labelledby="reservation-section-title" data-home-reveal>
            <div class="menu-container home-reservation__compact">
                <span class="home-reservation__icon"><i class="bx bx-calendar-heart" aria-hidden="true"></i></span>
                <div>
                    <span class="menu-kicker">Una mesa para tu momento</span>
                    <h2 id="reservation-section-title">Reserva fácil, llega y disfruta</h2>
                    <p>Elige una fecha, consulta los horarios disponibles y registra tu mesa en menos de un minuto.</p>
                </div>
                <livewire:public-reservation />
            </div>
        </section>

        @if ($menuSettings->show_gallery)
        <section class="home-gallery" id="galeria" aria-labelledby="home-gallery-title" data-home-reveal>
            <div class="menu-container">
                <div class="section-heading">
                    <div><span class="menu-kicker">Ambiente y sabor</span>
                        <h2 id="home-gallery-title">Conoce la experiencia</h2>
                    </div>
                </div>
                @if ($galleryImages->isNotEmpty())
                    <div class="home-gallery__viewport" data-gallery-carousel tabindex="0"
                        aria-label="Galería del restaurante" role="region" aria-roledescription="carrusel">
                        <div class="home-gallery__track">
                            @foreach ($galleryImages as $item)
                                <figure>
                                    <img src="{{ Storage::url($item['path']) }}"
                                        alt="{{ $item['caption'] ?: 'Experiencia en ' . $business->business_name }}"
                                        width="1200" height="620"
                                        loading="{{ $loop->first ? 'eager' : 'lazy' }}" decoding="async">
                                    @if ($item['caption'])
                                        <figcaption>{{ $item['caption'] }}</figcaption>
                                    @endif
                                </figure>
                            @endforeach
                        </div>
                    </div>
                    <a class="home-gallery__more" href="{{ route('public.gallery') }}">Ver
                        {{ $galleryImages->count() }}
                        {{ $galleryImages->count() === 1 ? 'fotografía' : 'fotografías' }} <i
                            class="bx bx-right-arrow-alt" aria-hidden="true"></i></a>
                @else
                    <div class="home-gallery__empty"><i class="bx bx-images" aria-hidden="true"></i>
                        <p>Muy pronto compartiremos nuevas fotografías.</p>
                    </div>
                @endif
            </div>
        </section>
        @endif

        <section class="home-visit" id="horarios" aria-labelledby="home-hours-title" data-home-reveal>
            <div class="menu-container home-visit__grid">
                <article class="home-hours-card">
                    <header>
                        <span><i class="bx bx-time-five" aria-hidden="true"></i></span>
                        <div><small>Planea tu visita</small>
                            <h2 id="home-hours-title">Horarios</h2>
                        </div>
                        <span
                            class="{{ $openingStatus['is_open'] ? 'is-open' : '' }}">{{ $openingStatus['label'] }}</span>
                    </header>
                    @php($todayKey = strtolower(now()->englishDayOfWeek))
                    <dl>
                        @foreach ($business->business_hours ?: \App\Models\BusinessSetting::DEFAULT_HOURS as $day)
                            @php($isToday = ($day['key'] ?? '') === $todayKey)
                            <div @class(['is-today' => $isToday])>
                                <dt>{{ $day['label'] }} @if ($isToday)
                                        <span>Hoy</span>
                                    @endif
                                </dt>
                                <dd>{{ $day['enabled'] ? $day['opens'] . ' – ' . $day['closes'] : 'Cerrado' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    <a href="{{ route('public.hours') }}">Ver detalles del horario <i class="bx bx-right-arrow-alt"
                            aria-hidden="true"></i></a>
                </article>
                <article class="home-contact-card" id="contacto">
                    <span class="menu-kicker">Estamos cerca</span>
                    <h2>Contacto y redes</h2>
                    <p>Usa únicamente nuestros canales oficiales para comunicarte o encontrarnos.</p>
                    <div class="home-contact-card__links">
                        @if ($business->whatsapp)
                            <a href="https://wa.me/{{ preg_replace('/\D/', '', $business->whatsapp) }}"
                                target="_blank" rel="noopener noreferrer"><i class="bx bxl-whatsapp"
                                    aria-hidden="true"></i><span>WhatsApp</span></a>
                        @endif
                        @if ($business->instagram_url)
                            <a href="{{ $business->instagram_url }}" target="_blank" rel="noopener noreferrer"><i
                                    class="bx bxl-instagram" aria-hidden="true"></i><span>Instagram</span></a>
                        @endif
                        @if ($business->facebook_url)
                            <a href="{{ $business->facebook_url }}" target="_blank" rel="noopener noreferrer"><i
                                    class="bx bxl-facebook" aria-hidden="true"></i><span>Facebook</span></a>
                        @endif
                        @if ($business->tiktok_url)
                            <a href="{{ $business->tiktok_url }}" target="_blank" rel="noopener noreferrer"><i
                                    class="bx bxl-tiktok" aria-hidden="true"></i><span>TikTok</span></a>
                        @endif
                        @if ($business->phone)
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $business->phone) }}"><i
                                    class="bx bx-phone" aria-hidden="true"></i><span>Teléfono</span></a>
                        @endif
                        @if ($business->map_link)
                            <a href="{{ $business->map_link }}" target="_blank" rel="noopener noreferrer"><i
                                    class="bx bx-map" aria-hidden="true"></i><span>Cómo llegar</span></a>
                        @endif
                    </div>
                    <a class="home-contact-card__more" href="{{ route('public.contact') }}">Ver todos los datos <i
                            class="bx bx-right-arrow-alt" aria-hidden="true"></i></a>
                </article>
            </div>
        </section>

        <section class="home-menu-cta home-section--white-divider" data-home-reveal>
            <div class="menu-container home-menu-cta__inner">
                <span><i class="bx bx-restaurant " aria-hidden="true"></i></span>
                <div>
                    <small>¿Ya sabes qué se te antoja?</small>
                    <h2>La carta completa está lista para ti</h2>
                    <p>Categorías, ingredientes, opciones y precios en una experiencia diseñada para explorar.</p>
                </div>
                <a href="{{ route('public.menu') }}">Ver el menú <i class="bx bx-right-arrow-alt"
                        aria-hidden="true"></i></a>
            </div>
        </section>
    </main>

    <x-public-menu.footer :business="$business" :menu-settings="$menuSettings" />
    @livewireScripts
    <script src="{{ asset('assets/js/public-home.js') }}?v={{ filemtime(public_path('assets/js/public-home.js')) }}"
        defer></script>
</body>

</html>
