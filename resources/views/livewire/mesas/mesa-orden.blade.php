<div class="mo-page" x-data="{ cartOpen: $wire.entangle('showCartDrawer') }">
    @once
        <link rel="stylesheet" href="{{ asset('assets/css/mesas.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/css/mesa-orden.css') }}">
    @endonce

    @if(session('orderSent'))
        <div class="mo-toast mo-toast--success" x-data="{show:true}" x-show="show"
             x-init="setTimeout(()=>show=false,3500)" x-transition:leave="mo-toast-leave">
            <i class="bx bx-check-circle"></i> {{ session('orderSent') }}
        </div>
    @endif

    {{-- ══ HEADER ══ --}}
    <div class="mo-header">
        <div class="mo-header-left">
            <button type="button" class="mo-back-btn" wire:click="goBack" aria-label="Volver a mesas">
                <i class="bx bx-arrow-back"></i>
            </button>
            <div class="mo-mesa-info">
                <div class="mo-mesa-badge mo-mesa-badge--{{ $this->mesa->status }}">
                    <i class="bx {{ $this->mesa->status_icon }}"></i>
                    {{ $this->mesa->status_label }}
                </div>
                <h5 class="mo-mesa-title">Mesa {{ $this->mesa->number }}
                    @if($this->mesa->name) · <span class="fw-normal text-muted">{{ $this->mesa->name }}</span> @endif
                </h5>
            </div>
        </div>
        @if($this->mesa->status === 'ocupada')
            <button type="button" class="btn btn-outline-warning btn-sm mo-close-table-btn" wire:click="closeMesa" wire:loading.attr="disabled">
                <i class="bx bx-receipt me-1"></i>
                <span wire:loading.remove wire:target="closeMesa">Cerrar cuenta</span>
                <span wire:loading wire:target="closeMesa">Cerrando…</span>
            </button>
        @endif
    </div>

    {{-- ══ CATALOG — full width ══ --}}
    <div class="mo-catalog">
        <div class="mo-catalog-toolbar">
            <div class="mo-search-wrap">
                <i class="bx bx-search mo-search-icon"></i>
                <input type="text" class="mo-search-input"
                       wire:model.live.debounce.250ms="search"
                       placeholder="Buscar producto…">
                @if($search)
                    <button type="button" class="mo-search-clear" wire:click="$set('search','')" aria-label="Limpiar búsqueda">
                        <i class="bx bx-x"></i>
                    </button>
                @endif
            </div>
            <div class="mo-cat-pills">
                <button type="button" class="mo-cat-pill {{ $categoryFilter === null ? 'active' : '' }}"
                        wire:click="$set('categoryFilter', null)">Todos</button>
                @foreach($this->categories as $cat)
                    <button type="button" class="mo-cat-pill {{ $categoryFilter === $cat->id ? 'active' : '' }}"
                            wire:click="$set('categoryFilter', {{ $cat->id }})"
                            >
                        @if($cat->icon)<i class="bx {{ $cat->icon }} me-1"></i>@endif
                        {{ $cat->name }}
                    </button>
                @endforeach
            </div>
        </div>

        <div wire:loading.class="mo-loading" class="mo-products-wrap">
            <div wire:loading class="mo-loading-overlay">
                <div class="spinner-border spinner-border-sm text-primary"></div>
            </div>

            @forelse($this->filteredProducts as $category)
                <div class="mo-cat-group">
                    <div class="mo-cat-label">
                        @if($category->icon)<i class="bx {{ $category->icon }}"></i>@endif
                        {{ $category->name }}
                    </div>
                    <div class="mo-products-grid">
                        @foreach($category->products as $product)
                        @php $inCart = collect($cart)->where('product_id', $product->id)->sum('qty'); @endphp
                        <button type="button" class="mo-product-card {{ $inCart > 0 ? 'in-cart' : '' }}"
                                wire:click="openCustomize({{ $product->id }})"
                                wire:key="prod-{{ $product->id }}">
                            @if($inCart > 0)
                                <span class="mo-product-qty-badge">{{ $inCart }}</span>
                            @endif
                            @if($product->image)
                                <img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }}" class="mo-product-img">
                            @else
                                <div class="mo-product-placeholder"><i class="bx bx-food-menu"></i></div>
                            @endif
                            <div class="mo-product-name">{{ $product->name }}</div>
                            <div class="mo-product-price">${{ number_format($product->price, 2) }}</div>
                            @if($product->is_customizable || $product->addonGroups->count() || $product->ingredients->count())
                                <div class="mo-product-custom-hint"><i class="bx bx-customize"></i><span>Personalizar</span></div>
                            @endif
                        </button>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="mo-empty">
                    <i class="bx bx-search-alt"></i>
                    <p>Sin productos encontrados</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- ══ BACKDROP ══ --}}
    <div class="mo-cart-backdrop" :class="{ 'show': cartOpen }" @click="cartOpen = false" x-cloak></div>

    {{-- ══ BOTTOM CART CONTAINER ══ --}}
    <div class="mo-cart-container">

        {{-- CART DRAWER (slides up) --}}
        <div class="mo-cart-drawer" :class="{ 'open': cartOpen }" x-cloak>
            <div class="mo-cart-drawer-handle"></div>

            <div class="mo-cart-header">
                <span><i class="bx bx-cart me-1"></i> Orden nueva
                    @if(!empty($cart))
                        <span class="mo-cart-count ms-1">{{ $this->cartCount }}</span>
                    @endif
                </span>
                <button type="button" class="mo-cart-close" @click="cartOpen = false" aria-label="Cerrar carrito">
                    <i class="bx bx-x"></i>
                </button>
            </div>

            @error('cart')
                <div class="alert alert-warning py-2 mx-3 mb-0 mt-2">
                    <small><i class="bx bx-error me-1"></i>{{ $message }}</small>
                </div>
            @enderror

            <div class="mo-cart-items">
                @forelse($cart as $line)
                <div class="mo-cart-item" wire:key="cart-{{ $line['cart_id'] }}">
                    <div class="mo-cart-item-name">{{ $line['name'] }}</div>
                    @if(!empty($line['addons']))
                        <div class="mo-cart-item-mods">
                            @foreach($line['addons'] as $a)
                                <span class="mo-cart-mod-chip">+ {{ $a['addon_name'] }}</span>
                            @endforeach
                        </div>
                    @endif
                    @if(!empty($line['ingredients']))
                        <div class="mo-cart-item-mods">
                            @foreach($line['ingredients'] as $i)
                                <span class="mo-cart-mod-chip mo-cart-mod-chip--ing">{{ $i['ingredient_name'] }}@if($i['quantity']>1)×{{ $i['quantity'] }}@endif</span>
                            @endforeach
                        </div>
                    @endif
                    <div class="mo-cart-item-controls">
                        <button type="button" class="mo-qty-btn" wire:click="decrementQty('{{ $line['cart_id'] }}')" aria-label="Reducir {{ $line['name'] }}">
                            <i class="bx bx-minus"></i>
                        </button>
                        <span class="mo-qty-val">{{ $line['qty'] }}</span>
                        <button type="button" class="mo-qty-btn" wire:click="incrementQty('{{ $line['cart_id'] }}')" aria-label="Aumentar {{ $line['name'] }}">
                            <i class="bx bx-plus"></i>
                        </button>
                        <span class="mo-cart-item-price">${{ number_format($line['unit_total'] * $line['qty'], 2) }}</span>
                        <button type="button" class="mo-cart-edit" wire:click="editCartItem('{{ $line['cart_id'] }}')" title="Editar" aria-label="Editar {{ $line['name'] }}">
                            <i class="bx bx-edit"></i>
                        </button>
                        <button type="button" class="mo-cart-remove" wire:click="removeFromCart('{{ $line['cart_id'] }}')" title="Eliminar" aria-label="Eliminar {{ $line['name'] }}">
                            <i class="bx bx-x"></i>
                        </button>
                    </div>
                </div>
                @empty
                <div class="mo-cart-empty">
                    <i class="bx bx-cart-alt"></i>
                    <p>Toca un producto para agregarlo</p>
                </div>
                @endforelse
            </div>

            @if(!empty($cart))
            <div class="mo-cart-footer">
                <input type="text" class="form-control form-control-sm"
                       wire:model="orderNotes" placeholder="Nota para cocina (opcional)">
                <div class="mo-cart-total">
                    <span>Total</span>
                    <strong>${{ number_format($this->cartTotal, 2) }}</strong>
                </div>
                <button class="mo-send-btn" wire:click="placeOrder"
                        wire:loading.attr="disabled" wire:target="placeOrder">
                    <span wire:loading.remove wire:target="placeOrder">
                        <i class="bx bx-send"></i> Mandar a cocina
                    </span>
                    <span wire:loading wire:target="placeOrder">
                        <span class="spinner-border spinner-border-sm"></span> Enviando…
                    </span>
                </button>
            </div>
            @endif
        </div>

        {{-- BOTTOM BAR (always visible) --}}
        <button type="button" class="mo-bottom-bar" @click="cartOpen = !cartOpen" aria-label="Abrir resumen del pedido">
            <div class="mo-bb-icon-wrap">
                <i class="bx bx-cart"></i>
                @if($this->cartCount > 0)
                    <span class="mo-bb-badge">{{ $this->cartCount }}</span>
                @endif
            </div>
            <span class="mo-bb-label">
                @if($this->cartCount > 0)
                    {{ $this->cartCount }} {{ $this->cartCount === 1 ? 'item' : 'items' }}
                @else
                    Sin items en el carrito
                @endif
            </span>
            @if($this->cartCount > 0)
                <span class="mo-bb-total">${{ number_format($this->cartTotal, 2) }}</span>
            @endif
            <i class="bx bx-chevron-up mo-bb-chevron" :class="{ 'is-open': cartOpen }"></i>
        </button>
    </div>

    {{-- ══ CUSTOMIZE MODAL ══ --}}
    @if($showCustomize && $this->customizingProductModel)
    @php
        $prod      = $this->customizingProductModel;
        $maxIngs   = (int)($prod->max_ingredients ?? 0);
        $totalIngs = array_sum($selectedIngredients ?? []);
    @endphp
    <div class="mo-modal-backdrop" wire:click.self="$set('showCustomize', false)">
        <div class="mo-modal" role="dialog" aria-modal="true" aria-labelledby="mo-customize-title">
            <div class="mo-modal-header">
                <div>
                    <h6 id="mo-customize-title" class="mo-modal-title">{{ $prod->name }}</h6>
                    <span class="text-muted small">${{ number_format($prod->price, 2) }} base · {{ $prod->max_addons ? 'Máx. '.$prod->max_addons.' complementos' : 'Complementos configurables' }}</span>
                </div>
                <button type="button" class="mo-modal-close" wire:click="$set('showCustomize', false)" aria-label="Cerrar personalización">
                    <i class="bx bx-x"></i>
                </button>
            </div>
            <div class="mo-modal-body">

                {{-- Addon groups --}}
                @foreach($prod->addonGroups as $group)
                @php
                    $groupSelected = collect($group->addons)->filter(fn($a) => isset($selectedAddons[$a->id]))->count();
                    $maxSelections = (int)$group->max_selections;
                    $maxReached    = $maxSelections > 0 && $groupSelected >= $maxSelections;
                @endphp
                <div class="mo-addon-group">
                    <div class="mo-addon-group-label">
                        <span>{{ $group->name }}</span>
                        <small class="mo-addon-rule">{{ $group->is_required ? 'Obligatorio' : 'Opcional' }} · Mín. {{ $group->is_required ? max(1, (int)$group->min_selections) : (int)$group->min_selections }} · Máx. {{ (int)$group->max_selections > 0 ? (int)$group->max_selections : 'sin límite' }}</small>
                        @if($group->is_required)
                            <span class="mo-required-badge">Requerido</span>
                        @endif
                        @if($maxSelections > 0)
                            <span class="mo-addon-count-hint {{ $maxReached ? 'at-max' : '' }}">
                                {{ $groupSelected }}/{{ $maxSelections }}
                            </span>
                        @endif
                    </div>
                    @error('addons_'.$group->id)
                        <small class="text-danger d-block mb-1">{{ $message }}</small>
                    @enderror
                    <div class="mo-addon-options">
                        @foreach($group->addons as $addon)
                        @php $isSelected = isset($selectedAddons[$addon->id]); @endphp
                        <button type="button" class="mo-addon-option {{ $isSelected ? 'selected' : '' }} {{ !$isSelected && $maxReached ? 'mo-addon-disabled' : '' }}"
                                wire:click="toggleAddon({{ $addon->id }})"
                                @if(!$isSelected && $maxReached) disabled @endif>
                            <span class="mo-addon-check"><i class="bx bx-check"></i></span>
                            <span class="mo-addon-name">{{ $addon->name }}</span>
                            @if($addon->extra_price > 0)
                                <span class="mo-addon-price">+${{ number_format($addon->extra_price, 2) }}</span>
                            @else
                                <span class="mo-addon-price mo-addon-price--free">Sin costo</span>
                            @endif
                        </button>
                        @endforeach
                    </div>
                </div>
                @endforeach
                @error('addons_general')<small class="text-danger d-block mb-2">{{ $message }}</small>@enderror

                {{-- Ingredients --}}
                @if($prod->ingredients->isNotEmpty())
                <div class="mo-addon-group">
                    <div class="mo-addon-group-label">
                        <span>Ingredientes</span>
                        <small class="mo-addon-rule">Mín. {{ $prod->min_ingredients ?? 0 }} · Máx. {{ $maxIngs > 0 ? $maxIngs : 'sin límite' }}</small>
                        @if($maxIngs > 0)
                            <span class="mo-addon-count-hint {{ $totalIngs >= $maxIngs ? 'at-max' : '' }}">
                                {{ $totalIngs }}/{{ $maxIngs }}
                            </span>
                        @endif
                    </div>
                    @if($maxIngs > 0 && $totalIngs >= $maxIngs)
                        <div class="mo-ing-max-hint">
                            <i class="bx bx-info-circle me-1"></i>Máximo {{ $maxIngs }} ingrediente(s) alcanzado
                        </div>
                    @endif
                    @error('ingredients')<small class="text-danger d-block mb-1">{{ $message }}</small>@enderror
                    <div class="mo-addon-options">
                        @foreach($prod->ingredients as $ing)
                        @php
                            $ingQty      = $selectedIngredients[$ing->id] ?? 0;
                            $canAddMore  = $maxIngs === 0 || $totalIngs < $maxIngs;
                        @endphp
                        <div class="mo-ingredient-row">
                            <span class="mo-addon-name">
                                {{ $ing->name }}
                                @if($ing->extra_price > 0)
                                    <small class="text-muted">+${{ number_format($ing->extra_price, 2) }}/u</small>
                                @else
                                    <small class="text-muted">Sin costo</small>
                                @endif
                            </span>
                            <div class="mo-ing-controls">
                                <button type="button" class="mo-qty-btn"
                                        wire:click="setIngredientQty({{ $ing->id }}, {{ max(0, $ingQty - 1) }})"
                                        @if($ingQty === 0) disabled @endif>
                                    <i class="bx bx-minus"></i>
                                </button>
                                <span class="mo-qty-val">{{ $ingQty }}</span>
                                <button type="button" class="mo-qty-btn"
                                        wire:click="setIngredientQty({{ $ing->id }}, {{ $ingQty + 1 }})"
                                        @if(!$canAddMore) disabled @endif>
                                    <i class="bx bx-plus"></i>
                                </button>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Quantity --}}
                <div class="mo-addon-group">
                    <div class="mo-addon-group-label"><span>Cantidad</span><small class="mo-addon-rule">Máximo 99 por producto</small></div>
                    <div class="mo-ing-controls">
                        <button type="button" class="mo-qty-btn" wire:click="$set('itemQty', {{ max(1, $itemQty - 1) }})" aria-label="Reducir cantidad">
                            <i class="bx bx-minus"></i>
                        </button>
                        <span class="mo-qty-val">{{ $itemQty }}</span>
                        <button type="button" class="mo-qty-btn" wire:click="$set('itemQty', {{ min(99, $itemQty + 1) }})" aria-label="Aumentar cantidad">
                            <i class="bx bx-plus"></i>
                        </button>
                    </div>
                </div>

                {{-- Notes --}}
                <div class="mo-addon-group">
                    <div class="mo-addon-group-label"><span>Nota (opcional)</span><small class="mo-addon-rule">Visible para cocina</small></div>
                    <input type="text" class="form-control form-control-sm"
                           wire:model="itemNotes" placeholder="Sin cebolla, bien cocido…">
                </div>
                @error('itemQty')<small class="text-danger d-block mb-2">{{ $message }}</small>@enderror

            </div>
            <div class="mo-modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="$set('showCustomize', false)">Cancelar</button>
                <button type="button" class="mo-send-btn mo-confirm-btn" wire:click="confirmCustomize">
                    {{ $editingCartId ? 'Actualizar' : 'Agregar al carrito' }}
                </button>
            </div>
        </div>
    </div>
    @endif

</div>
