<div class="pos-catalog">
    {{-- Búsqueda --}}
    <div class="catalog-search-bar">
        <div class="search-wrap">
            <i class="bx bx-search si-icon"></i>
            <input type="text" wire:model.live.debounce.250ms="productSearch"
                   class="pos-input" placeholder="Buscar platillo…" data-ui="xui-zoq5gj">
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
            <div data-ui="xui-12u9f6v">
                <i class="bx bx-food-menu" data-ui="xui-u3h3fd"></i>
                <div data-ui="xui-1ml6c8v">No hay productos disponibles</div>
            </div>
        @endif
    </div>
</div>
