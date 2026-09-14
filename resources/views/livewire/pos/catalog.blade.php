<div class="pos-catalog"
     @pos-catalog-settled.window="pendingId = null"
     x-data="{
        category: null,
        mode: $wire.entangle('catalogMode'),
        pendingId: null,
        normalize(value) {
            return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        },
        matches(name, categoryId, query) {
            const categoryMatches = this.category === null || Number(this.category) === Number(categoryId);
            return categoryMatches && this.normalize(name).includes(this.normalize(String(query || '').trim()));
        }
     }">
    <div class="pos-catalog-switcher" role="tablist" aria-label="Contenido del catálogo">
        <button type="button" class="pos-catalog-switcher__tab" @click="mode = 'products'; category = null"
                :class="{ 'is-active': mode === 'products' }" :aria-selected="mode === 'products'" role="tab">
            <i class="bx bx-food-menu" aria-hidden="true"></i><span>Productos</span>
        </button>
        @if($this->activePromotions->isNotEmpty())
            <button type="button" class="pos-catalog-switcher__tab" @click="mode = 'promotions'; category = null"
                    :class="{ 'is-active': mode === 'promotions' }" :aria-selected="mode === 'promotions'" role="tab">
                <i class="bx bx-purchase-tag-alt" aria-hidden="true"></i><span>Promociones</span><b>{{ $this->activePromotions->count() }}</b>
            </button>
        @endif
    </div>

    <div class="pos-category-navigation" x-show="mode === 'products'" x-cloak
         x-data="{
            canScrollBack: false,
            canScrollForward: false,
            observer: null,
            init() {
                this.$nextTick(() => {
                    this.updateScrollState();
                    this.observer = new ResizeObserver(() => this.updateScrollState());
                    this.observer.observe(this.$refs.categoryRail);
                });
            },
            destroy() { this.observer?.disconnect(); },
            updateScrollState() {
                const rail = this.$refs.categoryRail;
                if (!rail) return;
                this.canScrollBack = rail.scrollLeft > 3;
                this.canScrollForward = rail.scrollLeft + rail.clientWidth < rail.scrollWidth - 3;
            },
            scrollCategories(direction) {
                const rail = this.$refs.categoryRail;
                rail?.scrollBy({ left: direction * Math.max(220, rail.clientWidth * .72), behavior: 'smooth' });
            },
            handleCategoryWheel(event) {
                const rail = this.$refs.categoryRail;
                if (!rail || rail.scrollWidth <= rail.clientWidth || Math.abs(event.deltaX) > Math.abs(event.deltaY)) return;
                event.preventDefault();
                rail.scrollLeft += event.deltaY;
            },
            revealCategory(button) {
                this.$nextTick(() => button.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' }));
            }
         }">
        <button type="button" class="pos-category-navigation__control is-back"
                x-show="canScrollBack" x-transition.opacity @click="scrollCategories(-1)"
                aria-label="Ver categorías anteriores" title="Categorías anteriores">
            <i class="bx bx-chevron-left" aria-hidden="true"></i>
        </button>
        <div class="cat-tabs" x-ref="categoryRail" role="tablist" aria-label="Categorías del menú"
             @scroll.passive="updateScrollState" @wheel="handleCategoryWheel($event)">
            <button type="button" @click="category = null; revealCategory($el)" :class="{ 'active': category === null }"
                    class="cat-tab" role="tab" :aria-selected="category === null">Todos</button>
            @foreach($this->allCategories as $cat)
                <button type="button" @click="category = {{ $cat->id }}; revealCategory($el)"
                        :class="{ 'active': category === {{ $cat->id }} }"
                        class="cat-tab" role="tab" :aria-selected="category === {{ $cat->id }}">
                    @if($cat->icon)<i class="bx {{ $cat->icon }}" aria-hidden="true"></i>@endif
                    {{ $cat->name }}
                </button>
            @endforeach
        </div>
        <button type="button" class="pos-category-navigation__control is-forward"
                x-show="canScrollForward" x-transition.opacity @click="scrollCategories(1)"
                aria-label="Ver más categorías" title="Más categorías">
            <i class="bx bx-chevron-right" aria-hidden="true"></i>
        </button>
    </div>

    @if($this->activePromotions->isNotEmpty())
        <section class="pos-promotion-catalog" aria-labelledby="pos-promotions-title" x-show="mode === 'promotions'" x-cloak>
            <header class="pos-promotion-catalog__header">
                <div><span>Beneficios disponibles</span><h2 id="pos-promotions-title">Promociones</h2></div>
                <small>Las ofertas automáticas se calculan en el carrito; los combos permiten elegir sus productos.</small>
            </header>
            <div class="catalog-grid pos-promotion-grid">
                @foreach($this->activePromotions as $promotion)
                    @php
                        $isAutomatic = $promotion->hasAutomaticPricingRule();
                        $promotionImage = $promotion->image ?: $promotion->primaryProduct?->image;
                        $promotionBenefit = $isAutomatic ? $promotion->pricingRuleShortLabel() : '$'.number_format($promotion->price, 2);
                        $promotionDescription = $promotion->short_description
                            ?: ($isAutomatic ? 'Se aplica automáticamente al completar las cantidades requeridas.' : 'Configura los productos incluidos en este combo.');
                    @endphp
                    <button type="button" wire:click="$parent.selectPromotionFromCatalog({{ $promotion->id }})"
                            @click="pendingId = {{ $promotion->id }}" :disabled="pendingId === {{ $promotion->id }}"
                            wire:key="pos-promotion-{{ $promotion->id }}" x-show="matches(@js($promotion->name.' '.$promotionDescription), null, catalogQuery)" x-cloak
                            class="prod-card pos-promotion-card"
                            aria-label="{{ $isAutomatic ? 'Agregar producto para' : 'Configurar' }} {{ $promotion->name }}, {{ $promotion->pricingRuleLabel() ?: 'precio $'.number_format($promotion->price, 2) }}">
                        <span class="prod-img pos-promotion-card__image {{ $promotionImage ? 'pos-product-image-shell' : '' }}"
                              @if($promotionImage) x-data="posProductImage"
                              :class="{ 'is-image-pending': state === 'waiting' || state === 'loading' || state === 'decoding', 'is-image-ready': state === 'ready', 'is-image-error': state === 'error' }" @endif>
                            @if($promotionImage)
                                <img x-ref="image" src="{{ Storage::url($promotionImage) }}" data-src="{{ Storage::url($promotionImage) }}"
                                     alt="" width="320" height="216" loading="lazy" decoding="async">
                                <i class="bx bx-purchase-tag-alt no-img pos-product-image-fallback" x-show="state === 'error'" x-cloak aria-hidden="true"></i>
                            @else
                                <i class="bx bx-purchase-tag-alt no-img" aria-hidden="true"></i>
                            @endif
                            <span class="pos-promotion-card__badge"><i class="bx {{ $isAutomatic ? 'bx-bolt-circle' : 'bx-selection' }}" aria-hidden="true"></i>{{ $isAutomatic ? 'Automática' : 'Configurable' }}</span>
                        </span>
                        <span class="prod-info pos-promotion-card__info">
                            <span class="prod-name">{{ $promotion->name }}</span>
                            <span class="pos-promotion-card__description">{{ $promotionDescription }}</span>
                            <span class="pos-promotion-card__availability">{{ $promotion->fulfillmentSummary() }}</span>
                            <span class="prod-card-footer">
                                <strong class="prod-price">{{ $promotionBenefit }}</strong>
                                <span class="prod-card-cta" aria-hidden="true"><i class="bx {{ $isAutomatic ? 'bx-plus' : 'bx-slider-alt' }}"></i></span>
                            </span>
                        </span>
                    </button>
                @endforeach
            </div>
        </section>
    @endif

    {{-- El distintivo "en el pedido" lo resuelve Alpine con `cartQtyFor()`, que
         vive en la raiz del POS. Asi el catalogo no depende del carrito y deja
         de reconstruirse en cada click. --}}
    <div class="catalog-grid" x-show="mode === 'products'" x-cloak>
        @foreach($this->categoriesWithProducts as $category)
            @foreach($category->products as $product)
                @php
                    $hasOptions = $product->is_customizable || $product->addon_groups_count > 0 || $product->ingredients_count > 0;
                    $productLabel = ($hasOptions ? 'Personalizar' : 'Agregar').' '.$product->name.' por $'.number_format($product->price, 2);
                @endphp
                <button type="button"
                     x-show="matches(@js($product->name), {{ $category->id }}, catalogQuery)" x-cloak
                     wire:click="$parent.openCustomizeModal({{ $product->id }})"
                     @click="pendingId = {{ $product->id }}" :disabled="pendingId === {{ $product->id }}"
                     wire:key="pos-product-{{ $product->id }}"
                     class="prod-card"
                     :class="{ 'in-cart': cartQtyFor({{ $product->id }}) > 0 }"
                     data-label="{{ $productLabel }}" aria-label="{{ $productLabel }}"
                     :aria-label="cartQtyFor({{ $product->id }}) > 0 ? $el.dataset.label + ', ' + cartQtyFor({{ $product->id }}) + ' en el pedido' : $el.dataset.label">
                    <div class="prod-img {{ $product->image ? 'pos-product-image-shell' : '' }}"
                         @if($product->image) x-data="posProductImage"
                         :class="{ 'is-image-pending': state === 'waiting' || state === 'loading' || state === 'decoding', 'is-image-ready': state === 'ready', 'is-image-error': state === 'error' }" @endif>
                        @if($product->image)
                            <img x-ref="image" src="{{ Storage::url($product->image) }}" data-src="{{ Storage::url($product->image) }}"
                                 alt="" width="320" height="216" loading="lazy" decoding="async">
                            <i class="bx bx-dish no-img pos-product-image-fallback" x-show="state === 'error'" x-cloak aria-hidden="true"></i>
                        @else
                            <i class="bx bx-dish no-img" aria-hidden="true"></i>
                        @endif
                        <span class="prod-badge-qty" x-show="cartQtyFor({{ $product->id }}) > 0" x-cloak
                              x-text="cartQtyFor({{ $product->id }})"></span>
                        @if($hasOptions)<span class="prod-badge-addon"><i class="bx bx-customize" aria-hidden="true"></i> Personalizar</span>@endif
                    </div>
                    <div class="prod-info">
                        <span class="prod-name">{{ $product->name }}</span>
                        <span class="prod-card-footer">
                            <strong class="prod-price">${{ number_format($product->price, 2) }}</strong>
                            <span class="prod-card-cta" aria-hidden="true">
                                <i class="bx {{ $hasOptions ? 'bx-slider-alt' : 'bx-plus' }}"></i>
                            </span>
                        </span>
                    </div>
                </button>
            @endforeach
        @endforeach

        @foreach($this->productsWithoutCategory as $product)
            @php
                $hasOptions = $product->is_customizable || $product->addon_groups_count > 0 || $product->ingredients_count > 0;
                $productLabel = ($hasOptions ? 'Personalizar' : 'Agregar').' '.$product->name.' por $'.number_format($product->price, 2);
            @endphp
            <button type="button"
                 x-show="matches(@js($product->name), null, catalogQuery)" x-cloak
                 wire:click="$parent.openCustomizeModal({{ $product->id }})"
                     @click="pendingId = {{ $product->id }}" :disabled="pendingId === {{ $product->id }}"
                 wire:key="pos-product-{{ $product->id }}"
                 class="prod-card"
                 :class="{ 'in-cart': cartQtyFor({{ $product->id }}) > 0 }"
                 data-label="{{ $productLabel }}" aria-label="{{ $productLabel }}"
                 :aria-label="cartQtyFor({{ $product->id }}) > 0 ? $el.dataset.label + ', ' + cartQtyFor({{ $product->id }}) + ' en el pedido' : $el.dataset.label">
                <div class="prod-img {{ $product->image ? 'pos-product-image-shell' : '' }}"
                     @if($product->image) x-data="posProductImage"
                     :class="{ 'is-image-pending': state === 'waiting' || state === 'loading' || state === 'decoding', 'is-image-ready': state === 'ready', 'is-image-error': state === 'error' }" @endif>
                    @if($product->image)
                        <img x-ref="image" src="{{ Storage::url($product->image) }}" data-src="{{ Storage::url($product->image) }}"
                             alt="" width="320" height="216" loading="lazy" decoding="async">
                        <i class="bx bx-dish no-img pos-product-image-fallback" x-show="state === 'error'" x-cloak aria-hidden="true"></i>
                    @else
                        <i class="bx bx-dish no-img" aria-hidden="true"></i>
                    @endif
                    <span class="prod-badge-qty" x-show="cartQtyFor({{ $product->id }}) > 0" x-cloak
                          x-text="cartQtyFor({{ $product->id }})"></span>
                    @if($hasOptions)<span class="prod-badge-addon"><i class="bx bx-customize" aria-hidden="true"></i> Personalizar</span>@endif
                </div>
                <div class="prod-info">
                    <span class="prod-name">{{ $product->name }}</span>
                    <span class="prod-card-footer">
                        <strong class="prod-price">${{ number_format($product->price, 2) }}</strong>
                        <span class="prod-card-cta" aria-hidden="true">
                            <i class="bx {{ $hasOptions ? 'bx-slider-alt' : 'bx-plus' }}"></i>
                        </span>
                    </span>
                </div>
            </button>
        @endforeach

        @if($this->categoriesWithProducts->sum(fn($category) => $category->products->count()) === 0 && $this->productsWithoutCategory->isEmpty())
            <div class="pos-catalog-empty">
                <i class="bx bx-food-menu"></i>
                <strong>No hay productos disponibles</strong>
                <span>Activa productos desde la administración del menú.</span>
            </div>
        @endif
    </div>
</div>
