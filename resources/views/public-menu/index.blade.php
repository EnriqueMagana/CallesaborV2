<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ $business->primary_color ?? '#15803d' }}">
    <meta name="description" content="Consulta el menú, horarios y datos de {{ $business->business_name }}.">
    <title>Menú | {{ $business->business_name }}</title>
    <link rel="icon" href="{{ $business->logo_path ? Storage::url($business->logo_path) : asset('assets/img/favicon/favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/boxicons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/public-menu.css') }}?v={{ filemtime(public_path('assets/css/public-menu.css')) }}">
</head>
<body style="--menu-primary: {{ $business->primary_color ?? '#15803d' }}">
    <a class="menu-skip-link" href="#menu">Saltar al menú</a>
    <x-public-menu.brand-header
        :business="$business"
        :opening-status="$openingStatus"
        action-label="Volver al inicio"
        :action-href="route('public.home')"
        action-icon="bx-left-arrow-alt"
    />

    <main>
        <section class="menu-discovery" id="menu" tabindex="-1" aria-labelledby="catalog-title">
            <div class="menu-container">
                <div class="menu-search">
                    <label for="menu-search-input"><i class="bx bx-search" aria-hidden="true"></i><span class="sr-only">Buscar en el menú</span></label>
                    <input id="menu-search-input" type="search" placeholder="Buscar platillo o ingrediente…" autocomplete="off">
                    <button type="button" id="menu-search-clear" aria-label="Limpiar búsqueda" hidden><i class="bx bx-x" aria-hidden="true"></i></button>
                </div>
                <p class="menu-search__status" id="menu-search-status" aria-live="polite"></p>
            </div>

            <nav class="category-nav" aria-label="Categorías del menú">
                <div class="menu-container category-nav__scroll">
                    @foreach($categories as $category)
                        <a href="#category-{{ $category->id }}" data-category-link="category-{{ $category->id }}">
                            <i class="bx {{ $category->icon ?: 'bx-food-menu' }}" aria-hidden="true"></i>
                            {{ $category->name }}
                        </a>
                    @endforeach
                    @if($uncategorized->isNotEmpty())
                        <a href="#category-other" data-category-link="category-other"><i class="bx bx-dish" aria-hidden="true"></i>Otros</a>
                    @endif
                </div>
            </nav>

            @if($featured->isNotEmpty())
                <section class="featured-menu menu-container" aria-labelledby="featured-title">
                    <div class="section-heading">
                        <div><span class="menu-kicker">Recomendados</span><h2 id="featured-title">Favoritos de la casa</h2></div>
                        <p>Una selección especial para descubrir nuestros sabores.</p>
                    </div>
                    <div class="featured-menu__rail" role="list">
                        @foreach($featured as $product)
                            <x-public-menu.product-card :product="$product" featured role="listitem" />
                        @endforeach
                    </div>
                </section>
            @endif

            <div class="menu-catalog menu-container">
                <div class="section-heading section-heading--catalog">
                    <div><span class="menu-kicker">Todo el sabor</span><h2 id="catalog-title">Nuestro menú</h2></div>
                    <p>Precios e ingredientes disponibles para consultar antes de tu visita.</p>
                </div>

                @forelse($categories as $category)
                    <section class="menu-category" id="category-{{ $category->id }}" data-menu-section>
                        <header class="menu-category__header">
                            <span class="menu-category__icon" style="--category-color: {{ $category->color ?: $business->primary_color }}"><i class="bx {{ $category->icon ?: 'bx-food-menu' }}" aria-hidden="true"></i></span>
                            <div>
                                <h2>{{ $category->name }}</h2>
                                @if($category->description)<p>{{ $category->description }}</p>@endif
                            </div>
                            <span>{{ $category->products->count() }} {{ $category->products->count() === 1 ? 'opción' : 'opciones' }}</span>
                        </header>
                        <div class="product-grid">
                            @foreach($category->products as $product)
                                <x-public-menu.product-card :product="$product" />
                            @endforeach
                        </div>
                    </section>
                @empty
                    @if($uncategorized->isEmpty())
                        <div class="menu-empty">
                            <i class="bx bx-food-menu" aria-hidden="true"></i>
                            <h2>Estamos preparando el menú</h2>
                            <p>Muy pronto encontrarás aquí todas nuestras especialidades.</p>
                        </div>
                    @endif
                @endforelse

                @if($uncategorized->isNotEmpty())
                    <section class="menu-category" id="category-other" data-menu-section>
                        <header class="menu-category__header">
                            <span class="menu-category__icon"><i class="bx bx-dish" aria-hidden="true"></i></span>
                            <div><h2>Otros sabores</h2><p>Más opciones para disfrutar.</p></div>
                            <span>{{ $uncategorized->count() }} {{ $uncategorized->count() === 1 ? 'opción' : 'opciones' }}</span>
                        </header>
                        <div class="product-grid">
                            @foreach($uncategorized as $product)
                                <x-public-menu.product-card :product="$product" />
                            @endforeach
                        </div>
                    </section>
                @endif

                <div class="menu-no-results" id="menu-no-results" hidden>
                    <i class="bx bx-search-alt" aria-hidden="true"></i>
                    <h2>No encontramos coincidencias</h2>
                    <p>Prueba con otro nombre o explora las categorías.</p>
                </div>
            </div>
        </section>

        <section class="menu-info-links" aria-labelledby="restaurant-info-title">
            <div class="menu-container">
                <div class="section-heading">
                    <div><span class="menu-kicker">Conoce el restaurante</span><h2 id="restaurant-info-title">Información útil</h2></div>
                    <p>Consulta nuestros horarios, fotografías y canales oficiales.</p>
                </div>
                <div class="menu-info-links__grid">
                    <a href="{{ route('public.home') }}#reservar" class="menu-info-card menu-info-card--reservation">
                        <span class="menu-info-card__icon"><i class="bx bx-calendar-check" aria-hidden="true"></i></span>
                        <span><small>Planea tu visita</small><strong>Reservar una mesa</strong><span>Elige fecha, hora y número de personas</span></span>
                        <i class="bx bx-right-arrow-alt" aria-hidden="true"></i>
                    </a>
                    <a href="{{ route('public.hours') }}" class="menu-info-card menu-info-card--hours">
                        <span class="menu-info-card__icon"><i class="bx bx-time-five" aria-hidden="true"></i></span>
                        <span><small>Antes de visitarnos</small><strong>Horarios</strong><span>{{ $openingStatus['label'] }} · {{ $openingStatus['detail'] }}</span></span>
                        <i class="bx bx-right-arrow-alt" aria-hidden="true"></i>
                    </a>
                    <a href="{{ route('public.gallery') }}" class="menu-info-card menu-info-card--gallery">
                        @if($galleryImages->isNotEmpty())
                            <img src="{{ Storage::url($galleryImages->first()['path']) }}" alt="" width="520" height="320" loading="lazy">
                        @endif
                        <span class="menu-info-card__shade"></span>
                        <span class="menu-info-card__icon"><i class="bx bx-images" aria-hidden="true"></i></span>
                        <span><small>Nuestros espacios</small><strong>Galería</strong><span>{{ $galleryImages->count() ? $galleryImages->count().' '.($galleryImages->count() === 1 ? 'fotografía' : 'fotografías') : 'Próximamente' }}</span></span>
                        <i class="bx bx-right-arrow-alt" aria-hidden="true"></i>
                    </a>
                    <a href="{{ route('public.contact') }}" class="menu-info-card menu-info-card--contact">
                        <span class="menu-info-card__icon"><i class="bx bx-message-rounded-dots" aria-hidden="true"></i></span>
                        <span><small>Canales oficiales</small><strong>Contacto y redes</strong><span>{{ $business->phone ?: 'Conoce cómo encontrarnos' }}</span></span>
                        <i class="bx bx-right-arrow-alt" aria-hidden="true"></i>
                    </a>
                </div>
            </div>
        </section>
    </main>

    <x-public-menu.footer :business="$business" />

    <dialog class="product-modal" id="product-detail-modal" aria-labelledby="product-modal-title">
        <div class="product-modal__shell">
            <button type="button" class="product-modal__close" data-modal-close aria-label="Cerrar detalle del producto"><i class="bx bx-x" aria-hidden="true"></i></button>
            <div class="product-modal__layout">
                <div class="product-modal__media">
                    <img src="" alt="" width="720" height="640" data-modal-image>
                    <span class="product-modal__placeholder" data-modal-placeholder aria-hidden="true"><i class="bx bx-bowl-rice"></i></span>
                    <span class="product-modal__category" data-modal-category></span>
                </div>
                <div class="product-modal__content">
                    <div class="product-modal__heading">
                        <span class="menu-kicker">Detalle del producto</span>
                        <h2 id="product-modal-title" data-modal-name></h2>
                        <strong data-modal-price></strong>
                        <p data-modal-description></p>
                    </div>
                    <div class="product-modal__limits" data-modal-limits></div>
                    <section class="product-modal__section" data-modal-ingredients-section hidden>
                        <div><i class="bx bx-leaf" aria-hidden="true"></i><span><h3>Ingredientes disponibles</h3><p data-modal-ingredients-help></p></span></div>
                        <ul data-modal-ingredients></ul>
                    </section>
                    <div class="product-modal__groups" data-modal-groups></div>
                    <div class="product-modal__note"><i class="bx bx-info-circle" aria-hidden="true"></i><p>Esta vista es informativa. Las opciones se muestran como referencia y no generan un pedido.</p></div>
                </div>
            </div>
            <footer class="product-modal__footer">
                <span><i class="bx bx-check-shield" aria-hidden="true"></i>Información del menú</span>
                <button type="button" data-modal-close>Cerrar detalle</button>
            </footer>
        </div>
    </dialog>
    <script src="{{ asset('assets/js/public-menu.js') }}?v={{ filemtime(public_path('assets/js/public-menu.js')) }}" defer></script>
</body>
</html>
