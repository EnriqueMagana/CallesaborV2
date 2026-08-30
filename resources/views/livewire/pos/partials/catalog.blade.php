<div class="pos-catalog"
     x-data="{
        category: null,
        mode: $wire.entangle('catalogMode'),
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

    <div class="cat-tabs" role="tablist" aria-label="Categorías del menú" x-show="mode === 'products'" x-cloak>
        <button type="button" @click="category = null" :class="{ 'active': category === null }"
                class="cat-tab" role="tab" :aria-selected="category === null">Todos</button>
        @foreach($this->allCategories as $cat)
            <button type="button" @click="category = {{ $cat->id }}"
                    :class="{ 'active': category === {{ $cat->id }} }"
                    class="cat-tab" role="tab" :aria-selected="category === {{ $cat->id }}">
                @if($cat->icon)<i class="bx {{ $cat->icon }}" aria-hidden="true"></i>@endif
                {{ $cat->name }}
            </button>
        @endforeach
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
                    <button type="button" wire:click="selectPromotionFromCatalog({{ $promotion->id }})"
                            wire:loading.attr="disabled" wire:target="selectPromotionFromCatalog({{ $promotion->id }})"
                            wire:key="pos-promotion-{{ $promotion->id }}" x-show="matches(@js($promotion->name.' '.$promotionDescription), null, catalogQuery)" x-cloak
                            class="prod-card pos-promotion-card"
                            aria-label="{{ $isAutomatic ? 'Agregar producto para' : 'Configurar' }} {{ $promotion->name }}, {{ $promotion->pricingRuleLabel() ?: 'precio $'.number_format($promotion->price, 2) }}">
                        <span class="prod-img pos-promotion-card__image">
                            @if($promotionImage)
                                <img src="{{ Storage::url($promotionImage) }}" alt="{{ $promotion->name }}" width="320" height="216" loading="lazy" decoding="async">
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

    <div class="catalog-grid" x-show="mode === 'products'" x-cloak>
        @php $cartProductIds = collect($cart)->groupBy('product_id')->map->sum('quantity'); @endphp

        @foreach($this->categoriesWithProducts as $category)
            @foreach($category->products as $product)
                @php
                    $inCart = isset($cartProductIds[$product->id]);
                    $cartQty = $cartProductIds[$product->id] ?? 0;
                    $hasOptions = $product->is_customizable || $product->addon_groups_count > 0 || $product->ingredients_count > 0;
                @endphp
                <button type="button"
                     x-show="matches(@js($product->name), {{ $category->id }}, catalogQuery)" x-cloak
                     wire:click="openCustomizeModal({{ $product->id }})"
                     wire:loading.attr="disabled" wire:target="openCustomizeModal({{ $product->id }})"
                     wire:key="pos-product-{{ $product->id }}"
                     class="prod-card {{ $inCart ? 'in-cart' : '' }}"
                     aria-label="{{ $hasOptions ? 'Personalizar' : 'Agregar' }} {{ $product->name }} por ${{ number_format($product->price, 2) }}{{ $inCart ? ', '.$cartQty.' en el pedido' : '' }}">
                    <div class="prod-img">
                        @if($product->image)
                            <img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }}"
                                 width="320" height="216" loading="lazy" decoding="async">
                        @else
                            <i class="bx bx-dish no-img" aria-hidden="true"></i>
                        @endif
                        @if($inCart)<span class="prod-badge-qty">{{ $cartQty }}</span>@endif
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
                $inCart = isset($cartProductIds[$product->id]);
                $cartQty = $cartProductIds[$product->id] ?? 0;
                $hasOptions = $product->is_customizable || $product->addon_groups_count > 0 || $product->ingredients_count > 0;
            @endphp
            <button type="button"
                 x-show="matches(@js($product->name), null, catalogQuery)" x-cloak
                 wire:click="openCustomizeModal({{ $product->id }})"
                 wire:loading.attr="disabled" wire:target="openCustomizeModal({{ $product->id }})"
                 wire:key="pos-product-{{ $product->id }}"
                 class="prod-card {{ $inCart ? 'in-cart' : '' }}"
                 aria-label="{{ $hasOptions ? 'Personalizar' : 'Agregar' }} {{ $product->name }} por ${{ number_format($product->price, 2) }}{{ $inCart ? ', '.$cartQty.' en el pedido' : '' }}">
                <div class="prod-img">
                    @if($product->image)
                        <img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }}"
                             width="320" height="216" loading="lazy" decoding="async">
                    @else
                        <i class="bx bx-dish no-img" aria-hidden="true"></i>
                    @endif
                    @if($inCart)<span class="prod-badge-qty">{{ $cartQty }}</span>@endif
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
