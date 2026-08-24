<div class="pos-catalog"
     x-data="{
        query: '',
        category: null,
        normalize(value) {
            return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        },
        matches(name, categoryId) {
            const categoryMatches = this.category === null || Number(this.category) === Number(categoryId);
            return categoryMatches && this.normalize(name).includes(this.normalize(this.query.trim()));
        }
     }">
    <div class="catalog-search-bar">
        <div class="search-wrap">
            <i class="bx bx-search si-icon" aria-hidden="true"></i>
            <input type="search" x-model.debounce.120ms="query" aria-label="Buscar platillo"
                   class="pos-input" placeholder="Buscar platillo..." autocomplete="off">
            <button type="button" class="pos-search-clear" x-show="query" x-cloak
                    @click="query = ''" aria-label="Limpiar búsqueda">
                <i class="bx bx-x"></i>
            </button>
        </div>
    </div>

    <div class="cat-tabs" role="tablist" aria-label="Categorías del menú">
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

    <div class="catalog-grid">
        @php $cartProductIds = collect($cart)->groupBy('product_id')->map->sum('quantity'); @endphp

        @foreach($this->categoriesWithProducts as $category)
            @foreach($category->products as $product)
                @php
                    $inCart = isset($cartProductIds[$product->id]);
                    $cartQty = $cartProductIds[$product->id] ?? 0;
                    $hasOptions = $product->is_customizable || $product->addon_groups_count > 0 || $product->ingredients_count > 0;
                @endphp
                <button type="button"
                     x-show="matches(@js($product->name), {{ $category->id }})" x-cloak
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
                 x-show="matches(@js($product->name), null)" x-cloak
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
