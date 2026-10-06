<x-public-menu.site-layout :business="$business" :menu-settings="$menuSettings" :title="'Menú | ' . $business->business_name" :description="'Consulta el menú, horarios y datos de ' . $business->business_name . '.'" :styles="['assets/css/promotions-public.css', 'assets/css/online-ordering.css', 'assets/css/online-ordering-customizer.css']"
    font-url="https://fonts.googleapis.com/css2?family=Parisienne&family=Poppins:wght@400;500;600;700;800&display=swap">
    <a class="menu-skip-link" href="#menu">Saltar al menú</a>
    <x-public-menu.brand-header :business="$business" :menu-settings="$menuSettings" :opening-status="$openingStatus" action-label="Volver al inicio"
        :action-href="route('public.home')" action-icon="bx-left-arrow-alt" />

    <main>
        <section class="menu-discovery" id="menu" tabindex="-1" aria-labelledby="catalog-title" data-menu-analytics
            data-view-url="{{ route('public.menu.analytics.view') }}"
            data-product-url="{{ url('/menu/analitica/productos') }}">
            <div class="menu-container menu-discovery__intro">
                <div class="menu-discovery__heading">
                    <span class="menu-kicker">Menú del restaurante</span>
                    <h1>¿Qué se te antoja hoy?</h1>
                    <p>Explora {{ $totalProducts }} {{ $totalProducts === 1 ? 'platillo' : 'platillos' }} de
                        {{ $business->business_name }}.</p>
                </div>
                <form class="menu-search" role="search" id="menu-search-form">
                    <label for="menu-search-input"><span class="sr-only">Buscar platillos en el menú</span></label>
                    <i class="bx bx-search menu-search__icon" aria-hidden="true"></i>
                    <input id="menu-search-input" type="search" placeholder="Buscar platillo o ingrediente"
                        autocomplete="off" enterkeyhint="search" aria-describedby="menu-search-status">
                    <button class="menu-search__clear" type="button" id="menu-search-clear"
                        aria-label="Limpiar búsqueda" hidden><i class="bx bx-x" aria-hidden="true"></i></button>
                    <button class="menu-search__submit" type="submit" aria-label="Buscar en el menú"><i
                            class="bx bx-search" aria-hidden="true"></i></button>
                </form>
                <p class="menu-search__status" id="menu-search-status" aria-live="polite"></p>
            </div>

            @if (
                $menuSettings->show_categories ||
                    $promotions->isNotEmpty() ||
                    $discountCampaigns->isNotEmpty() ||
                    $newProductCampaigns->isNotEmpty())
                <nav @class([
                    'category-nav',
                    'category-nav--circles' => $menuSettings->category_style === 'circles',
                ]) aria-label="Categorías del menú" data-search-navigation>
                    <div class="menu-container category-nav__rail" data-category-rail>
                        <button class="category-nav__control category-nav__control--previous" type="button"
                            data-category-previous aria-label="Ver categorías anteriores"
                            aria-controls="category-nav-scroll" hidden>
                            <i class="bx bx-chevron-left" aria-hidden="true"></i>
                        </button>
                        <div class="category-nav__scroll" id="category-nav-scroll" data-category-scroll tabindex="0">
                            <a href="#catalog-title" class="category-nav__item is-active" data-category-all
                                aria-current="location">
                                <span class="category-nav__icon"><i class="bx bx-grid-alt"
                                        aria-hidden="true"></i></span>
                                <span><strong>Todo</strong><small>{{ $totalProducts }} opciones</small></span>
                            </a>
                            @if ($promotions->isNotEmpty())
                                <a href="#digital-promotions" class="category-nav__item"
                                    data-category-link="digital-promotions">
                                    <span class="category-nav__icon"><i class="bx bx-purchase-tag-alt"
                                            aria-hidden="true"></i></span>
                                    <span><strong>Promociones</strong><small>{{ $promotions->count() }}
                                            {{ $promotions->count() === 1 ? 'disponible' : 'disponibles' }}</small></span>
                                </a>
                            @endif
                            @if ($discountCampaigns->isNotEmpty())
                                <a href="#discount-products" class="category-nav__item"
                                    data-category-link="discount-products">
                                    <span class="category-nav__icon"><i class="bx bx-purchase-tag"
                                            aria-hidden="true"></i></span>
                                    <span><strong>Descuentos</strong><small>{{ $discountCampaigns->count() }}
                                            {{ $discountCampaigns->count() === 1 ? 'producto' : 'productos' }}</small></span>
                                </a>
                            @endif
                            @if ($newProductCampaigns->isNotEmpty())
                                <a href="#new-products" class="category-nav__item" data-category-link="new-products">
                                    <span class="category-nav__icon"><i class="bx bx-star"
                                            aria-hidden="true"></i></span>
                                    <span><strong>Nuevos productos</strong><small>{{ $newProductCampaigns->count() }}
                                            {{ $newProductCampaigns->count() === 1 ? 'novedad' : 'novedades' }}</small></span>
                                </a>
                            @endif
                            @if ($menuSettings->show_categories)
                                @foreach ($categories as $category)
                                    @php
                                        $categoryPreview = $category->products->first(
                                            fn($product) => filled($product->image),
                                        );
                                    @endphp
                                    <a href="#category-{{ $category->id }}" class="category-nav__item"
                                        data-category-link="category-{{ $category->id }}">
                                        <span @class([
                                            'category-nav__icon',
                                            'menu-image-shell is-image-loading' =>
                                                $menuSettings->category_style === 'circles' && $categoryPreview,
                                        ])
                                            @if ($menuSettings->category_style === 'circles' && $categoryPreview) data-menu-image-shell @endif
                                            style="--category-color: {{ $category->color ?: $business->primary_color }}">
                                            @if ($menuSettings->category_style === 'circles' && $categoryPreview)
                                                <img src="{{ Storage::url($categoryPreview->image) }}" alt=""
                                                    width="64" height="64" loading="lazy" decoding="async"
                                                    data-menu-image>
                                            @else
                                                <i class="bx {{ $category->icon ?: 'bx-food-menu' }}"
                                                    aria-hidden="true"></i>
                                            @endif
                                        </span>
                                        <span><strong>{{ $category->name }}</strong><small>{{ $category->products->count() }}
                                                {{ $category->products->count() === 1 ? 'opción' : 'opciones' }}</small></span>
                                    </a>
                                @endforeach
                                @if ($uncategorized->isNotEmpty())
                                    <a href="#category-other" class="category-nav__item"
                                        data-category-link="category-other">
                                        <span class="category-nav__icon"><i class="bx bx-dish"
                                                aria-hidden="true"></i></span>
                                        <span><strong>Otros</strong><small>{{ $uncategorized->count() }}
                                                opciones</small></span>
                                    </a>
                                @endif
                            @endif
                        </div>
                        <button class="category-nav__control category-nav__control--next" type="button"
                            data-category-next aria-label="Ver más categorías" aria-controls="category-nav-scroll"
                            hidden>
                            <i class="bx bx-chevron-right" aria-hidden="true"></i>
                        </button>
                    </div>
                </nav>
            @endif

            @if ($promotions->isNotEmpty())
                <section class="promotion-banners menu-container" id="digital-promotions"
                    aria-labelledby="digital-promotions-title" data-category-section data-promotion-carousel
                    data-autoplay-interval="4500" data-search-merchandising>
                    <div class="section-heading promotion-banners__heading">
                        <div><span class="menu-kicker">Beneficios por tiempo limitado</span>
                            <h2 id="digital-promotions-title">Promociones</h2>
                        </div>
                        <p>Más por menos con nuestros combos y promociones.</p>
                    </div>
                    <div class="promotion-banners__rail" data-promotion-rail tabindex="0"
                        aria-label="Carrusel de promociones">
                        @foreach ($promotions as $promotion)
                            @php
                                $digitalFulfillmentLabels = collect([
                                    'takeaway' => 'Para llevar',
                                    'delivery' => 'Entrega a domicilio',
                                ])->filter(
                                    fn($label, $fulfillment) => $promotion->appliesToFulfillment($fulfillment),
                                )->values()->all();
                                $digitalFulfillmentSummary = count($digitalFulfillmentLabels) === 2
                                    ? 'Disponible para llevar o entrega a domicilio'
                                    : ($promotion->appliesToFulfillment('delivery')
                                        ? 'Solo con entrega a domicilio'
                                        : 'Solo para llevar');
                                $modalPromotion = [
                                    'id' => (int) $promotion->id,
                                    'name' => $promotion->name,
                                    'summary' =>
                                        $promotion->short_description ?:
                                        \Illuminate\Support\Str::limit(
                                            $promotion->description ?: $promotion->name,
                                            160,
                                        ),
                                    'description' => $promotion->description,
                                    'offerCaption' => $promotion->publicOfferCaption(),
                                    'offerValue' => $promotion->publicOfferValue(),
                                    'priceValue' => (float) $promotion->price,
                                    'pricingExplanation' => $promotion->publicPricingExplanation(),
                                    'image' => $promotion->image ? Storage::url($promotion->image) : null,
                                    'badge' => $promotion->presentationLabel(),
                                    'icon' => $promotion->presentationIcon(),
                                    'days' => $promotion->scheduleSummary(),
                                    'validity' => $promotion->ends_on
                                        ? 'Válida hasta ' . $promotion->ends_on->translatedFormat('d M Y')
                                        : 'Sin fecha de finalización',
                                    'fulfillment' => $digitalFulfillmentLabels,
                                    'fulfillmentSummary' => $digitalFulfillmentSummary,
                                    'terms' => $promotion->terms_and_conditions,
                                    'groups' => $promotion->groups
                                        ->map(
                                            fn($group) => [
                                                'id' => (int) $group->id,
                                                'name' => $group->name,
                                                'minimum' => (int) $group->min_selections,
                                                'maximum' => (int) $group->max_selections,
                                                'rule' => $promotion->publicGroupRule($group),
                                                'products' => $group->products
                                                    ->map(
                                                        fn($product) => [
                                                            'id' => (int) $product->id,
                                                            'name' => $product->name,
                                                            'description' => $product->description,
                                                            'image' => $product->image
                                                                ? Storage::url($product->image)
                                                                : null,
                                                        ],
                                                    )
                                                    ->values(),
                                            ],
                                        )
                                        ->values(),
                                ];
                            @endphp
                            <article
                                class="promotion-banner is-{{ $promotion->presentation_type }} {{ $promotion->image ? 'has-image' : '' }}">
                                <button type="button" class="promotion-banner__trigger"
                                    aria-label="Ver detalles de {{ $promotion->name }}" aria-haspopup="dialog"
                                    data-promotion-detail="{{ json_encode($modalPromotion, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"></button>
                                <div class="promotion-banner__media"
                                    @if ($promotion->image) style="background-image: url('{{ Storage::url($promotion->image) }}')" @endif
                                    aria-hidden="true">
                                    @unless ($promotion->image)
                                        <span><i class="bx {{ $promotion->presentationIcon() }}"></i></span>
                                    @endunless
                                </div>
                                <div class="promotion-banner__overlay"><span><i
                                            class="bx {{ $promotion->presentationIcon() }}"></i>{{ $promotion->presentationLabel() }}</span>
                                    <h3>{{ $promotion->name }}</h3>
                                    <p>{{ $promotion->short_description }}</p><small
                                        class="promotion-banner__fulfillment"><i
                                            class="bx bx-map-pin"></i>{{ $digitalFulfillmentSummary }}</small>
                                    <div>
                                        <small>{{ $promotion->scheduleSummary() }}</small>
                                    </div>
                                </div>
                                <strong
                                    class="promotion-banner__price"><small>{{ $promotion->publicOfferCaption() }}</small><span>{{ $promotion->publicOfferValue() }}</span></strong>
                            </article>
                        @endforeach
                    </div>
                    <div class="promotion-banners__dots"
                        aria-label="{{ $promotions->count() }} {{ $promotions->count() === 1 ? 'promoción' : 'promociones' }}"
                        role="status">
                        @foreach ($promotions as $promotion)
                            <span data-promotion-dot data-index="{{ $loop->index }}"
                                @if ($loop->first) class="is-active" @endif aria-hidden="true"></span>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($discountCampaigns->isNotEmpty())
                <section class="discount-products menu-container" id="discount-products"
                    aria-labelledby="discount-products-title" data-category-section data-search-merchandising>
                    <div class="section-heading discount-products__heading">
                        <div><span class="menu-kicker">Precios especiales</span>
                            <h2 id="discount-products-title">Descuentos</h2>
                        </div>
                        <p>Ahorra en productos seleccionados por tiempo limitado.</p>
                    </div>
                    <div class="discount-products__rail" role="list" tabindex="0"
                        aria-label="Productos con descuento, carrusel horizontal">
                        @foreach ($discountCampaigns as $campaign)
                            @php
                                $discountProduct = $campaign->primaryProduct;
                                $originalPrice = (float) $discountProduct->price;
                                $discountPrice = (float) $campaign->price;
                                $discountPercent =
                                    $campaign->pricing_rule_type ===
                                    \App\Models\Promotion::PRICING_RULE_PERCENTAGE_DISCOUNT
                                        ? $campaign->normalizedPricingRule()['discount_percentage']
                                        : ($originalPrice > 0
                                            ? max(1, min(99, (int) round((1 - $discountPrice / $originalPrice) * 100)))
                                            : 0);
                                $discountDescription =
                                    $discountPercent > 0
                                        ? $discountPercent .
                                            '% de descuento. Antes $' .
                                            number_format($originalPrice, 2) .
                                            ' y ahora $' .
                                            number_format($discountPrice, 2) .
                                            '.'
                                        : 'Precio especial: antes $' .
                                            number_format($originalPrice, 2) .
                                            ' y ahora $' .
                                            number_format($discountPrice, 2) .
                                            '.';
                            @endphp
                            <x-public-menu.product-card :product="$discountProduct" :image-override="$discountProduct->image ?: $campaign->image" :title-override="$discountProduct->name"
                                :description-override="$discountProduct->description ?: $discountDescription" :price-override="$discountPrice" :original-price="$originalPrice" :discount-percent="$discountPercent"
                                class="discount-product-card" role="listitem" data-discount-product-item />
                            <span class="sr-only">{{ $discountDescription }}</span>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($newProductCampaigns->isNotEmpty())
                <section class="new-products menu-container" id="new-products" aria-labelledby="new-products-title"
                    data-category-section data-search-merchandising>
                    <div class="section-heading">
                        <div><span class="menu-kicker">Recién llegados</span>
                            <h2 id="new-products-title">Nuevos productos</h2>
                        </div>
                    </div>
                    <div class="new-products__rail" role="list" tabindex="0"
                        aria-label="Nuevos productos, carrusel horizontal">
                        @foreach ($newProductCampaigns as $campaign)
                            <x-public-menu.product-card :product="$campaign->primaryProduct" :image-override="$campaign->image" :title-override="$campaign->name"
                                :description-override="$campaign->short_description" :badge="$campaign->pricingRuleShortLabel()
                                    ? 'Nuevo · ' . $campaign->pricingRuleShortLabel()
                                    : 'Nuevo'" role="listitem" data-new-product-item />
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($featured->isNotEmpty())
                <section class="featured-menu menu-container" aria-labelledby="featured-title" data-featured-carousel
                    data-autoplay="true" data-interval="{{ ((int) $menuSettings->banner_interval_seconds) * 1000 }}"
                    data-search-merchandising>
                    <div class="section-heading">
                        <div><span class="menu-kicker">Recomendados</span>
                            <h2 id="featured-title">Favoritos de la casa</h2>
                        </div>
                        @if($featured->count() > 1)
                            <div class="featured-menu__controls" aria-label="Controles de favoritos">
                                <button type="button" data-featured-previous aria-label="Ver favorito anterior"><i class="bx bx-chevron-left"></i></button>
                                <span data-featured-status aria-live="polite">1 / {{ $featured->count() }}</span>
                                <button type="button" data-featured-pause aria-label="Pausar carrusel" aria-pressed="false"><i class="bx bx-pause"></i></button>
                                <button type="button" data-featured-next aria-label="Ver siguiente favorito"><i class="bx bx-chevron-right"></i></button>
                            </div>
                        @endif
                    </div>
                    <div class="featured-menu__rail" role="list" tabindex="0"
                        aria-label="Favoritos de la casa, carrusel horizontal">
                        @foreach ($featured as $product)
                            <x-public-menu.product-card :product="$product" :rank="$loop->iteration" featured role="listitem"
                                data-featured-item />
                        @endforeach
                    </div>
                </section>
            @endif

            <div class="menu-catalog menu-container">
                <div class="section-heading section-heading--catalog" data-catalog-heading>
                    <div><span class="menu-kicker">Carta completa</span>
                        <h2 id="catalog-title">Explora por categoría</h2>
                    </div>
                </div>
                <div class="menu-search-results-heading" data-search-results-heading hidden tabindex="-1">
                    <span class="menu-search-results-heading__icon" aria-hidden="true"><i
                            class="bx bx-search-alt"></i></span>
                    <div>
                        <span class="menu-kicker">Resultados en todo el menú</span>
                        <h2 id="menu-search-results-title">Platillos encontrados</h2>
                        <p>Mostramos cada platillo una sola vez. Las etiquetas indican si también es favorito, nuevo o
                            tiene una promoción.</p>
                    </div>
                </div>

                @forelse($categories as $category)
                    <section class="menu-category" id="category-{{ $category->id }}" data-menu-section>
                        <header class="menu-category__header">
                            <span class="menu-category__icon"
                                style="--category-color: {{ $category->color ?: $business->primary_color }}"><i
                                    class="bx {{ $category->icon ?: 'bx-food-menu' }}" aria-hidden="true"></i></span>
                            <div>
                                <h2>{{ $category->name }}</h2>
                                @if ($category->description)
                                    <p>{{ $category->description }}</p>
                                @endif
                            </div>
                            <span>{{ $category->products->count() }}
                                {{ $category->products->count() === 1 ? 'opción' : 'opciones' }}</span>
                        </header>
                        <div class="product-grid">
                            @foreach ($category->products as $product)
                                @php($productSearch = $searchMetadata->get($product->id, []))
                                <x-public-menu.product-card :product="$product" :search-badges="$productSearch['badges'] ?? []" :search-keywords="$productSearch['keywords'] ?? ''" />
                            @endforeach
                        </div>
                    </section>
                @empty
                    @if ($uncategorized->isEmpty())
                        <div class="menu-empty">
                            <i class="bx bx-food-menu" aria-hidden="true"></i>
                            <h2>Estamos preparando el menú</h2>
                            <p>Muy pronto encontrarás aquí todas nuestras especialidades.</p>
                        </div>
                    @endif
                @endforelse

                @if ($uncategorized->isNotEmpty())
                    <section class="menu-category" id="category-other" data-menu-section>
                        <header class="menu-category__header">
                            <span class="menu-category__icon"><i class="bx bx-dish" aria-hidden="true"></i></span>
                            <div>
                                <h2>Otros sabores</h2>
                                <p>Más opciones para disfrutar.</p>
                            </div>
                            <span>{{ $uncategorized->count() }}
                                {{ $uncategorized->count() === 1 ? 'opción' : 'opciones' }}</span>
                        </header>
                        <div class="product-grid">
                            @foreach ($uncategorized as $product)
                                @php($productSearch = $searchMetadata->get($product->id, []))
                                <x-public-menu.product-card :product="$product" :search-badges="$productSearch['badges'] ?? []" :search-keywords="$productSearch['keywords'] ?? ''" />
                            @endforeach
                        </div>
                    </section>
                @endif

                <div class="menu-no-results" id="menu-no-results" hidden>
                    <i class="bx bx-search-alt" aria-hidden="true"></i>
                    <h2>No encontramos coincidencias</h2>
                    <p>Prueba con otro nombre, ingrediente, promoción o categoría.</p>
                    <button type="button" data-search-empty-clear>Limpiar búsqueda y ver todo el menú</button>
                </div>
            </div>
        </section>

        <x-public-menu.quick-access />
    </main>

    <x-public-menu.footer :business="$business" :menu-settings="$menuSettings" />

    @if ($promotions->isNotEmpty())
        <dialog class="product-modal promotion-detail-modal" id="promotion-detail-modal"
            aria-labelledby="promotion-modal-title">
            <div class="product-modal__shell">
                <button type="button" class="product-modal__close" data-promotion-modal-close
                    aria-label="Cerrar detalle de la promoción"><i class="bx bx-x" aria-hidden="true"></i></button>
                <div class="product-modal__layout">
                    <div class="product-modal__media"><img src="" alt="" width="720"
                            height="640" data-promotion-modal-image><span class="product-modal__placeholder"
                            data-promotion-modal-placeholder aria-hidden="true"><i class="bx bx-gift"></i></span><span
                            class="product-modal__category" data-promotion-modal-badge></span></div>
                    <div class="product-modal__content">
                        <div class="product-modal__heading"><span class="menu-kicker">Detalle de la promoción</span>
                            <h2 id="promotion-modal-title" data-promotion-modal-name></h2><strong
                                data-promotion-modal-price></strong>
                            <p data-promotion-modal-summary></p>
                        </div>
                        <div class="promotion-detail-modal__pricing"><i class="bx bx-purchase-tag-alt"
                                aria-hidden="true"></i><span><small data-promotion-modal-offer-caption></small><strong
                                    data-promotion-modal-pricing></strong></span></div>
                        <div class="product-modal__limits">
                            <div class="product-modal__limit"><i class="bx bx-calendar-check"></i><span><small>Días
                                        válidos</small><strong data-promotion-modal-days></strong></span></div>
                            <div class="product-modal__limit"><i
                                    class="bx bx-time-five"></i><span><small>Vigencia</small><strong
                                        data-promotion-modal-validity></strong></span></div>
                            <div class="product-modal__limit"><i
                                    class="bx bx-map-pin"></i><span><small>Modalidades</small><strong
                                        data-promotion-modal-fulfillment></strong></span></div>
                        </div>
                        <p class="promotion-detail-modal__description" data-promotion-modal-description></p>
                        <div class="promotion-detail-modal__terms" data-promotion-modal-terms-wrap hidden><i
                                class="bx bx-info-circle"></i><span><small>Términos y condiciones</small><strong
                                    data-promotion-modal-terms></strong></span></div>
                        <div class="product-modal__groups" data-promotion-modal-groups></div>
                    </div>
                </div>
                <footer class="product-modal__footer"><span><i class="bx bx-check-shield" aria-hidden="true"></i>La disponibilidad y el beneficio se validan al realizar el pedido</span>
                    @if($onlineOrderingAvailable)<button type="button" class="online-add-button" data-online-add-promotion><i class="bx bx-cart"></i>Elegir combo</button>@endif
                    <button type="button" data-promotion-modal-close>Cerrar detalle</button></footer>
            </div>
        </dialog>
    @endif

    <dialog class="product-modal" id="product-detail-modal" aria-labelledby="product-modal-title">
        <div class="product-modal__shell">
            <button type="button" class="product-modal__close" data-modal-close
                aria-label="Cerrar detalle del producto"><i class="bx bx-x" aria-hidden="true"></i></button>
            <div class="product-modal__layout">
                <div class="product-modal__media">
                    <img src="" alt="" width="720" height="640" data-modal-image>
                    <span class="product-modal__placeholder" data-modal-placeholder aria-hidden="true"><i
                            class="bx bx-dish"></i></span>
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
                        <div><i class="bx bx-list-ul" aria-hidden="true"></i><span>
                                <h3>Ingredientes disponibles</h3>
                                <p data-modal-ingredients-help></p>
                            </span></div>
                        <ul data-modal-ingredients></ul>
                    </section>
                    <div class="product-modal__groups" data-modal-groups></div>

                </div>
            </div>
            <footer class="product-modal__footer">
                <span><i class="bx bx-check-shield" aria-hidden="true"></i>{{ $onlineOrderingAvailable ? 'Personaliza antes de agregar' : 'Esta vista es informativa' }}</span>
                @if($onlineOrderingAvailable)<button type="button" class="online-add-button" data-online-add-product><i class="bx bx-cart"></i>Agregar</button>@endif
                <button type="button" data-modal-close>Cerrar detalle</button>
            </footer>
        </div>
    </dialog>
    @if($onlineOrderingAvailable)
        <x-public-menu.online-ordering :business="$business" />
    @endif
    <x-slot:scripts>
        <script src="https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/gsap.min.js" async></script>
        <script src="{{ asset('assets/js/public-menu.js') }}?v={{ filemtime(public_path('assets/js/public-menu.js')) }}"
            defer></script>
        @if($onlineOrderingAvailable)<script src="{{ asset('assets/js/online-ordering.js') }}?v={{ filemtime(public_path('assets/js/online-ordering.js')) }}" defer></script>@endif
    </x-slot:scripts>
</x-public-menu.site-layout>
