<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ $business->primary_color ?? '#15803d' }}">
    <meta name="description" content="Descubre el menú, reserva una mesa y conoce {{ $business->business_name }}.">
    <title>{{ $business->business_name }} | Menú y reservaciones</title>
    <link rel="icon" href="{{ $business->logo_path ? Storage::url($business->logo_path) : asset('assets/img/favicon/favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/boxicons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/public-menu.css') }}?v={{ filemtime(public_path('assets/css/public-menu.css')) }}">
    @livewireStyles
</head>
<body class="public-home" style="--menu-primary: {{ $business->primary_color ?? '#15803d' }}">
    <a class="menu-skip-link" href="#experiencia">Saltar al contenido</a>
    <x-public-menu.brand-header
        :business="$business"
        :opening-status="$openingStatus"
        eyebrow="Bienvenido"
        message="Sabores memorables, una mesa para compartir y una experiencia hecha para ti."
        action-label="Ver el menú"
        :action-href="route('public.menu')"
        action-icon="bx-right-arrow-alt"
    />

    <nav class="home-nav" aria-label="Navegación principal">
        <div class="menu-container">
            <a href="{{ route('public.menu') }}"><i class="bx bx-food-menu"></i>Menú</a>
            <a href="#reservar"><i class="bx bx-calendar-check"></i>Reservar</a>
            <a href="#horarios"><i class="bx bx-time-five"></i>Horarios</a>
            <a href="#galeria"><i class="bx bx-images"></i>Galería</a>
            <a href="#contacto"><i class="bx bx-message-rounded-dots"></i>Contacto</a>
        </div>
    </nav>

    <main id="experiencia">
        <section class="home-intro menu-container" aria-labelledby="home-intro-title">
            <div class="home-intro__copy"><span class="menu-kicker">Tu visita comienza aquí</span><h1 id="home-intro-title">Todo lo que necesitas para disfrutar {{ $business->business_name }}</h1><p>Explora la carta por categorías, encuentra el mejor momento para visitarnos y solicita tu mesa desde cualquier dispositivo.</p></div>
            <div class="home-intro__actions">
                <a href="{{ route('public.menu') }}" class="home-primary-action"><span><i class="bx bx-food-menu"></i></span><span><small>Descubre nuestros sabores</small><strong>Explorar el menú</strong></span><i class="bx bx-right-arrow-alt"></i></a>
                <a href="#reservar" class="home-secondary-action"><span><i class="bx bx-calendar-check"></i></span><span><small>Planea tu experiencia</small><strong>Reservar una mesa</strong></span><i class="bx bx-down-arrow-alt"></i></a>
            </div>
        </section>

        @if($categories->isNotEmpty())
            <section class="home-categories" aria-labelledby="home-categories-title">
                <div class="menu-container">
                    <div class="section-heading"><div><span class="menu-kicker">La carta</span><h2 id="home-categories-title">Encuentra tu próximo favorito</h2></div><a href="{{ route('public.menu') }}">Ver menú completo <i class="bx bx-right-arrow-alt"></i></a></div>
                    <div class="home-categories__rail">
                        @foreach($categories as $category)
                            @php($preview = $category->products->first())
                            <a href="{{ route('public.menu') }}#category-{{ $category->id }}" class="home-category-card" style="--category-color: {{ $category->color ?: $business->primary_color }}">
                                <div class="home-category-card__media">
                                    @if($preview?->image)<img src="{{ Storage::url($preview->image) }}" alt="{{ $category->name }}" width="560" height="420" loading="lazy" decoding="async">@else<span><i class="bx {{ $category->icon ?: 'bx-food-menu' }}"></i></span>@endif
                                    <span>{{ $category->products_count }} {{ $category->products_count === 1 ? 'opción' : 'opciones' }}</span>
                                </div>
                                <div><span><i class="bx {{ $category->icon ?: 'bx-food-menu' }}"></i></span><span><strong>{{ $category->name }}</strong><small>{{ $category->description ?: 'Explora esta categoría' }}</small></span><i class="bx bx-right-arrow-alt"></i></div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <section class="home-reservation" id="reservar" aria-labelledby="reservation-section-title">
            <div class="menu-container home-reservation__compact">
                <span class="home-reservation__icon"><i class="bx bx-calendar-heart" aria-hidden="true"></i></span>
                <div><span class="menu-kicker">Una mesa para tu momento</span><h2 id="reservation-section-title">Reserva fácil, llega y disfruta</h2><p>Elige una fecha, consulta los horarios disponibles y registra tu mesa en menos de un minuto.</p></div>
                <livewire:public-reservation />
            </div>
        </section>

        <section class="home-gallery" id="galeria" aria-labelledby="home-gallery-title">
            <div class="menu-container">
                <div class="section-heading"><div><span class="menu-kicker">Ambiente y sabor</span><h2 id="home-gallery-title">Conoce la experiencia</h2></div>@if($galleryImages->isNotEmpty())<div class="home-gallery__controls"><button type="button" data-gallery-prev aria-label="Fotografía anterior"><i class="bx bx-left-arrow-alt"></i></button><span data-gallery-status>1 / {{ $galleryImages->count() }}</span><button type="button" data-gallery-next aria-label="Fotografía siguiente"><i class="bx bx-right-arrow-alt"></i></button></div>@endif</div>
                @if($galleryImages->isNotEmpty())
                    <div class="home-gallery__viewport" data-gallery-carousel tabindex="0" aria-label="Galería del restaurante">
                        <div class="home-gallery__track">
                            @foreach($galleryImages as $item)
                                <figure><img src="{{ Storage::url($item['path']) }}" alt="{{ $item['caption'] ?: 'Experiencia en '.$business->business_name }}" width="1200" height="620" loading="{{ $loop->first ? 'eager' : 'lazy' }}" decoding="async">@if($item['caption'])<figcaption>{{ $item['caption'] }}</figcaption>@endif</figure>
                            @endforeach
                        </div>
                    </div>
                    <a class="home-gallery__more" href="{{ route('public.gallery') }}">Ver {{ $galleryImages->count() }} {{ $galleryImages->count() === 1 ? 'fotografía' : 'fotografías' }} <i class="bx bx-right-arrow-alt"></i></a>
                @else
                    <div class="home-gallery__empty"><i class="bx bx-images"></i><p>Muy pronto compartiremos nuevas fotografías.</p></div>
                @endif
            </div>
        </section>

        <section class="home-visit" id="horarios" aria-labelledby="home-hours-title">
            <div class="menu-container home-visit__grid">
                <article class="home-hours-card">
                    <header><span><i class="bx bx-time-five"></i></span><div><small>Planea tu visita</small><h2 id="home-hours-title">Horarios</h2></div><span class="{{ $openingStatus['is_open'] ? 'is-open' : '' }}">{{ $openingStatus['label'] }}</span></header>
                    <dl>@foreach($business->business_hours ?: \App\Models\BusinessSetting::DEFAULT_HOURS as $day)<div><dt>{{ $day['label'] }}</dt><dd>{{ $day['enabled'] ? $day['opens'].' – '.$day['closes'] : 'Cerrado' }}</dd></div>@endforeach</dl>
                    <a href="{{ route('public.hours') }}">Ver detalles del horario <i class="bx bx-right-arrow-alt"></i></a>
                </article>
                <article class="home-contact-card" id="contacto">
                    <span class="menu-kicker">Estamos cerca</span><h2>Contacto y redes</h2><p>Usa únicamente nuestros canales oficiales para comunicarte o encontrarnos.</p>
                    <div class="home-contact-card__links">
                        @if($business->whatsapp)<a href="https://wa.me/{{ preg_replace('/\D/', '', $business->whatsapp) }}" target="_blank" rel="noopener noreferrer"><i class="bx bxl-whatsapp"></i><span>WhatsApp</span></a>@endif
                        @if($business->instagram_url)<a href="{{ $business->instagram_url }}" target="_blank" rel="noopener noreferrer"><i class="bx bxl-instagram"></i><span>Instagram</span></a>@endif
                        @if($business->facebook_url)<a href="{{ $business->facebook_url }}" target="_blank" rel="noopener noreferrer"><i class="bx bxl-facebook"></i><span>Facebook</span></a>@endif
                        @if($business->tiktok_url)<a href="{{ $business->tiktok_url }}" target="_blank" rel="noopener noreferrer"><i class="bx bxl-tiktok"></i><span>TikTok</span></a>@endif
                        @if($business->phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $business->phone) }}"><i class="bx bx-phone"></i><span>Teléfono</span></a>@endif
                        @if($business->map_link)<a href="{{ $business->map_link }}" target="_blank" rel="noopener noreferrer"><i class="bx bx-map"></i><span>Cómo llegar</span></a>@endif
                    </div>
                    <a class="home-contact-card__more" href="{{ route('public.contact') }}">Ver todos los datos <i class="bx bx-right-arrow-alt"></i></a>
                </article>
            </div>
        </section>

        <section class="home-menu-cta"><div class="menu-container"><span><i class="bx bx-bowl-hot"></i></span><div><small>¿Ya sabes qué se te antoja?</small><h2>La carta completa está lista para ti</h2><p>Categorías, ingredientes, opciones y precios en una experiencia diseñada para explorar.</p></div><a href="{{ route('public.menu') }}">Ver el menú <i class="bx bx-right-arrow-alt"></i></a></div></section>
    </main>

    <x-public-menu.footer :business="$business" />
    @livewireScripts
    <script src="{{ asset('assets/js/public-home.js') }}?v={{ filemtime(public_path('assets/js/public-home.js')) }}" defer></script>
</body>
</html>
