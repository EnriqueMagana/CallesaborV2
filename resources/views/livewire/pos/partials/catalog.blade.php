<div class="pos-catalog">
    {{-- Búsqueda --}}
    <div class="catalog-search-bar">
        <div class="search-wrap">
            <i class="bx bx-search si-icon"></i>
            <input type="text" wire:model.live.debounce.250ms="productSearch"
                   class="pos-input" placeholder="Buscar platillo…" style="padding-left:32px">
        </div>
    </div>

    {{-- Categorías --}}
    <div class="cat-tabs">
        <button wire:click="selectCategory(null)"
                class="cat-tab {{ !$selectedCategoryId ? 'active' : '' }}">Todos</button>
        @foreach($this->allCategories as $cat)
            <button wire:click="selectCategory({{ $cat->id }})"
                    class="cat-tab {{ $selectedCategoryId === $cat->id ? 'active' : '' }}">
                {{ $cat->name }}
            </button>
        @endforeach
    </div>

    {{-- Grid de productos --}}
    <div class="catalog-grid">
        @php $cartProductIds = collect($cart)->pluck('product_id')->countBy(); @endphp

        @foreach($this->categoriesWithProducts as $category)
            @foreach($category->products as $product)
                @php
                    $inCart    = isset($cartProductIds[$product->id]);
                    $cartQty   = $cartProductIds[$product->id] ?? 0;
                    $hasAddons = $product->addonGroups->count() > 0 || $product->ingredients->count() > 0;
                @endphp
                <div wire:click="openCustomizeModal({{ $product->id }})"
                     class="prod-card {{ $inCart ? 'in-cart' : '' }}">
                    <div class="prod-img">
                        @if($product->image)
                            <img src="{{ asset('storage/'.$product->image) }}" alt="">
                        @else
                            <i class="bx bx-dish no-img"></i>
                        @endif
                        @if($inCart) <div class="prod-badge-qty">{{ $cartQty }}</div> @endif
                        @if($hasAddons) <div class="prod-badge-addon">+OPC</div> @endif
                    </div>
                    <div class="prod-info">
                        <div class="prod-name">{{ $product->name }}</div>
                        <div class="prod-price">${{ number_format($product->price, 2) }}</div>
                    </div>
                </div>
            @endforeach
        @endforeach

        @foreach($this->productsWithoutCategory as $product)
            @php
                $inCart    = isset($cartProductIds[$product->id]);
                $cartQty   = $cartProductIds[$product->id] ?? 0;
                $hasAddons = $product->addonGroups->count() > 0 || $product->ingredients->count() > 0;
            @endphp
            <div wire:click="openCustomizeModal({{ $product->id }})"
                 class="prod-card {{ $inCart ? 'in-cart' : '' }}">
                <div class="prod-img">
                    @if($product->image)
                        <img src="{{ asset('storage/'.$product->image) }}" alt="">
                    @else
                        <i class="bx bx-dish no-img"></i>
                    @endif
                    @if($inCart) <div class="prod-badge-qty">{{ $cartQty }}</div> @endif
                    @if($hasAddons) <div class="prod-badge-addon">+OPC</div> @endif
                </div>
                <div class="prod-info">
                    <div class="prod-name">{{ $product->name }}</div>
                    <div class="prod-price">${{ number_format($product->price, 2) }}</div>
                </div>
            </div>
        @endforeach

        @if($this->categoriesWithProducts->isEmpty() && $this->productsWithoutCategory->isEmpty())
            <div style="grid-column:1/-1;text-align:center;padding:48px 20px;color:var(--pos-muted)">
                <i class="bx bx-food-menu" style="font-size:3rem;display:block;margin-bottom:8px;opacity:.3"></i>
                <div style="font-size:.85rem">No hay productos disponibles</div>
            </div>
        @endif
    </div>
</div>
