<div class="mo-page" x-data="{ cartOpen: $wire.entangle('showCartDrawer') }">
    @once
        <link rel="stylesheet" href="{{ asset('assets/css/mesas.css') }}?v={{ filemtime(public_path('assets/css/mesas.css')) }}">
        <link rel="stylesheet" href="{{ asset('assets/css/mesa-orden.css') }}?v={{ filemtime(public_path('assets/css/mesa-orden.css')) }}">
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
            <button type="button" class="btn btn-outline-warning btn-sm mo-close-table-btn" wire:click="openCloseMesa" wire:loading.attr="disabled" wire:target="openCloseMesa">
                <i class="bx bx-receipt me-1"></i>
                <span wire:loading.remove wire:target="openCloseMesa">Cerrar cuenta</span>
                <span wire:loading wire:target="openCloseMesa">Abriendo…</span>
            </button>
        @endif
    </div>

    {{-- ══ CATALOG — full width ══ --}}
    <div class="mo-catalog">
        <div class="mo-catalog-toolbar">
            <div class="mo-search-wrap">
                <i class="bx bx-search mo-search-icon"></i>
                <input type="text" class="mo-search-input"
                       wire:model.live.debounce.450ms="search"
                       aria-label="Buscar productos"
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
                                <img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }}"
                                     class="mo-product-img" width="320" height="216" loading="lazy" decoding="async">
                            @else
                                <div class="mo-product-placeholder"><i class="bx bx-food-menu"></i></div>
                            @endif
                            <div class="mo-product-name">{{ $product->name }}</div>
                            <div class="mo-product-price">${{ number_format($product->price, 2) }}</div>
                            @if($product->is_customizable || $product->addon_groups_count || $product->ingredients_count)
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

            <header class="mo-cart-header">
                <div class="mo-cart-header__identity">
                    <span class="mo-cart-header__icon" aria-hidden="true"><i class="bx bx-receipt"></i></span>
                    <div>
                        <h2>Orden para mesa {{ $this->mesa->number }}</h2>
                        <p aria-live="polite">
                            @if(!empty($cart))
                                {{ count($cart) }} {{ count($cart) === 1 ? 'producto' : 'productos' }}
                                <span aria-hidden="true">&middot;</span>
                                {{ $this->cartCount }} {{ $this->cartCount === 1 ? 'unidad' : 'unidades' }}
                            @else
                                Lista para comenzar
                            @endif
                        </p>
                    </div>
                </div>
                <button type="button" class="mo-cart-close" @click="cartOpen = false" aria-label="Cerrar carrito">
                    <i class="bx bx-x" aria-hidden="true"></i>
                </button>
            </header>

            @error('cart')
                <div class="alert alert-warning py-2 mx-3 mb-0 mt-2">
                    <small><i class="bx bx-error me-1"></i>{{ $message }}</small>
                </div>
            @enderror

            <div class="mo-cart-items" role="list" aria-label="Productos de la nueva orden">
                @forelse($cart as $line)
                <article class="mo-cart-item" role="listitem" wire:key="cart-{{ $line['cart_id'] }}">
                    <div class="mo-cart-item-main">
                        @if(!empty($line['image']))
                            <img class="mo-cart-item-image" src="{{ Storage::url($line['image']) }}"
                                 alt="" width="48" height="48" loading="lazy" decoding="async">
                        @else
                            <span class="mo-cart-item-image mo-cart-item-image--empty" aria-hidden="true">
                                <i class="bx bx-food-menu"></i>
                            </span>
                        @endif
                        <div class="mo-cart-item-copy">
                            <div class="mo-cart-item-heading">
                                <div class="mo-cart-item-name">{{ $line['name'] }}</div>
                                <strong class="mo-cart-item-price">${{ number_format($line['unit_total'] * $line['qty'], 2) }}</strong>
                            </div>
                            @if(!empty($line['addons']))
                                <div class="mo-cart-item-mods">
                                    @foreach($line['addons'] as $a)
                                        <span class="mo-cart-mod-chip"><i class="bx bx-plus-circle" aria-hidden="true"></i>{{ $a['addon_name'] }}</span>
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
                            @if(!empty($line['notes']))
                                <div class="mo-cart-item-note">
                                    <i class="bx bx-message-rounded-detail" aria-hidden="true"></i>
                                    <span>{{ $line['notes'] }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="mo-cart-item-controls">
                        <div class="mo-cart-stepper" aria-label="Cantidad de {{ $line['name'] }}">
                        <button type="button" class="mo-qty-btn" wire:click="decrementQty('{{ $line['cart_id'] }}')" aria-label="Reducir {{ $line['name'] }}">
                            <i class="bx bx-minus" aria-hidden="true"></i>
                        </button>
                        <span class="mo-qty-val" aria-label="Cantidad {{ $line['qty'] }}">{{ $line['qty'] }}</span>
                        <button type="button" class="mo-qty-btn" wire:click="incrementQty('{{ $line['cart_id'] }}')" aria-label="Aumentar {{ $line['name'] }}">
                            <i class="bx bx-plus" aria-hidden="true"></i>
                        </button>
                        </div>
                        <div class="mo-cart-item-actions">
                        <button type="button" class="mo-cart-edit" wire:click="editCartItem('{{ $line['cart_id'] }}')" title="Personalizar" aria-label="Personalizar {{ $line['name'] }}">
                            <i class="bx bx-slider-alt" aria-hidden="true"></i><span>Editar</span>
                        </button>
                        <button type="button" class="mo-cart-remove" wire:click="removeFromCart('{{ $line['cart_id'] }}')" title="Eliminar" aria-label="Eliminar {{ $line['name'] }}">
                            <i class="bx bx-trash" aria-hidden="true"></i>
                        </button>
                        </div>
                    </div>
                </article>
                @empty
                <div class="mo-cart-empty">
                    <span class="mo-cart-empty__icon" aria-hidden="true"><i class="bx bx-basket"></i></span>
                    <strong>La orden está vacía</strong>
                    <p>Toca un producto del catálogo para comenzar.</p>
                </div>
                @endforelse
            </div>

            @if(!empty($cart))
            <div class="mo-cart-footer">
                <label class="mo-order-note">
                    <span class="mo-order-note__icon" aria-hidden="true"><i class="bx bx-message-dots"></i></span>
                    <span class="mo-order-note__content">
                        <span class="mo-order-note__label">Nota general para cocina</span>
                        <input type="text" wire:model="orderNotes" maxlength="500"
                               placeholder="Ej. entregar todo junto">
                    </span>
                </label>
                <div class="mo-cart-summary" aria-label="Resumen de la orden">
                    <div class="mo-cart-summary__meta">
                        <span>{{ $this->cartCount }} {{ $this->cartCount === 1 ? 'artículo' : 'artículos' }}</span>
                        <span>Total estimado</span>
                    </div>
                    <div class="mo-cart-total">
                        <span>Total</span>
                        <strong>${{ number_format($this->cartTotal, 2) }}</strong>
                    </div>
                </div>
                <button type="button" class="mo-send-btn" wire:click="placeOrder"
                        wire:loading.attr="disabled" wire:target="placeOrder">
                    <span wire:loading.remove wire:target="placeOrder">
                        <i class="bx bx-send" aria-hidden="true"></i> Enviar orden a cocina
                    </span>
                    <span wire:loading wire:target="placeOrder">
                        <span class="spinner-border spinner-border-sm"></span> Enviando…
                    </span>
                </button>
            </div>
            @endif
        </div>

        {{-- BOTTOM BAR (always visible) --}}
        <button type="button" class="mo-bottom-bar" @click="cartOpen = !cartOpen"
                :aria-expanded="cartOpen ? 'true' : 'false'" aria-label="Abrir resumen del pedido">
            <div class="mo-bb-icon-wrap">
                <i class="bx bx-cart"></i>
                @if($this->cartCount > 0)
                    <span class="mo-bb-badge">{{ $this->cartCount }}</span>
                @endif
            </div>
            <span class="mo-bb-label">
                @if($this->cartCount > 0)
                    <strong>Ver orden</strong>
                    <small>{{ $this->cartCount }} {{ $this->cartCount === 1 ? 'artículo' : 'artículos' }}</small>
                @else
                    <strong>Nueva orden</strong>
                    <small>Agrega productos del catálogo</small>
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
    <div class="mo-modal-backdrop" wire:click.self="closeCustomize"
         x-data="{
            addons: @js(collect($selectedAddons)->filter()->keys()->map(fn($id) => (int) $id)->values()),
            ingredients: @js((object) $selectedIngredients),
            qty: @js($itemQty),
            notes: @js($itemNotes),
            clientError: '',
            submitting: false,
            hasAddon(id) { return this.addons.includes(Number(id)); },
            groupCount(ids) { return ids.filter(id => this.hasAddon(id)).length; },
            totalAddons() { return this.addons.length; },
            totalIngredients() {
                return Object.values(this.ingredients).reduce((sum, value) => sum + Number(value || 0), 0);
            },
            toggleAddon(id, groupIds, minimum, maximum, productMaximum) {
                id = Number(id);
                this.clientError = '';
                if (this.hasAddon(id)) {
                    if (this.groupCount(groupIds) <= minimum) {
                        this.clientError = `Debes mantener al menos ${minimum} opción(es) en este grupo.`;
                        return;
                    }
                    this.addons = this.addons.filter(value => value !== id);
                    return;
                }
                if (maximum === 1) {
                    if (this.groupCount(groupIds) === 0 && productMaximum > 0 && this.totalAddons() >= productMaximum) {
                        this.clientError = `Este producto permite máximo ${productMaximum} complemento(s).`;
                        return;
                    }
                    this.addons = this.addons.filter(value => !groupIds.includes(value));
                    this.addons.push(id);
                    return;
                }
                if (maximum > 0 && this.groupCount(groupIds) >= maximum) {
                    this.clientError = `Este grupo permite máximo ${maximum} opción(es).`;
                    return;
                }
                if (productMaximum > 0 && this.totalAddons() >= productMaximum) {
                    this.clientError = `Este producto permite máximo ${productMaximum} complemento(s).`;
                    return;
                }
                this.addons.push(id);
            },
            changeIngredient(id, delta, maximum) {
                id = Number(id);
                this.clientError = '';
                const current = Number(this.ingredients[id] || 0);
                if (delta > 0 && maximum > 0 && this.totalIngredients() >= maximum) {
                    this.clientError = `Este producto permite máximo ${maximum} ingrediente(s).`;
                    return;
                }
                const next = Math.max(0, current + delta);
                if (next === 0) delete this.ingredients[id];
                else this.ingredients[id] = next;
            },
            submit(wire) {
                this.submitting = true;
                wire.confirmCustomize(this.addons, this.ingredients, this.qty, this.notes)
                    .finally(() => this.submitting = false);
            }
         }"
         x-on:keydown.escape.window="$wire.closeCustomize()">
        <div class="mo-modal" role="dialog" aria-modal="true" aria-labelledby="mo-customize-title">
            <div class="mo-modal-header">
                <div class="mo-modal-product">
                    @if($prod->image)
                        <img src="{{ Storage::url($prod->image) }}" alt="" class="mo-modal-product-image"
                             width="64" height="64" decoding="async">
                    @else
                        <span class="mo-modal-product-image mo-modal-product-image--empty" aria-hidden="true">
                            <i class="bx bx-food-menu"></i>
                        </span>
                    @endif
                    <div>
                        <h2 id="mo-customize-title" class="mo-modal-title">{{ $prod->name }}</h2>
                        <span class="mo-modal-subtitle">${{ number_format($prod->price, 2) }} base · {{ $prod->max_addons ? 'Máx. '.$prod->max_addons.' complementos' : 'Complementos configurables' }}</span>
                    </div>
                </div>
                <button type="button" class="mo-modal-close" wire:click="closeCustomize" aria-label="Cerrar personalización">
                    <i class="bx bx-x"></i>
                </button>
            </div>
            <div class="mo-modal-body">
                <div class="mo-client-alert" x-show="clientError" x-cloak role="alert">
                    <i class="bx bx-info-circle" aria-hidden="true"></i>
                    <span x-text="clientError"></span>
                </div>

                {{-- Addon groups --}}
                @foreach($prod->addonGroups as $group)
                @php
                    $groupIds = $group->addons->pluck('id')->map(fn($id) => (int) $id)->values();
                    $minimum = $group->is_required
                        ? max(1, (int) $group->min_selections)
                        : (int) $group->min_selections;
                    $configuredMaximum = (int) $group->max_selections;
                    $maximum = $configuredMaximum > 0
                        ? max($minimum, min($configuredMaximum, $group->addons->count()))
                        : $group->addons->count();
                @endphp
                <div class="mo-addon-group">
                    <div class="mo-addon-group-label">
                        <span>{{ $group->name }}</span>
                        <small class="mo-addon-rule">{{ $group->is_required ? 'Obligatorio' : 'Opcional' }} · Mín. {{ $minimum }} · Máx. {{ $maximum }}</small>
                        @if($group->is_required)
                            <span class="mo-required-badge">Requerido</span>
                        @endif
                        <span class="mo-addon-count-hint"
                              :class="{ 'at-max': groupCount(@js($groupIds)) >= {{ $maximum }} }">
                            <span x-text="groupCount(@js($groupIds))"></span>/{{ $maximum }}
                        </span>
                    </div>
                    @error('addons_'.$group->id)
                        <small class="text-danger d-block mb-1">{{ $message }}</small>
                    @enderror
                    <div class="mo-addon-options">
                        @foreach($group->addons as $addon)
                        <button type="button" class="mo-addon-option"
                                x-on:click="toggleAddon({{ $addon->id }}, @js($groupIds), {{ $minimum }}, {{ $maximum }}, {{ (int) $prod->max_addons }})"
                                :class="{ 'selected': hasAddon({{ $addon->id }}), 'mo-addon-disabled': !hasAddon({{ $addon->id }}) && (({{ $maximum }} > 1 && groupCount(@js($groupIds)) >= {{ $maximum }}) || ({{ (int) $prod->max_addons }} > 0 && totalAddons() >= {{ (int) $prod->max_addons }} && !({{ $maximum }} === 1 && groupCount(@js($groupIds)) > 0))) }"
                                :aria-pressed="hasAddon({{ $addon->id }}) ? 'true' : 'false'"
                                :disabled="!hasAddon({{ $addon->id }}) && (({{ $maximum }} > 1 && groupCount(@js($groupIds)) >= {{ $maximum }}) || ({{ (int) $prod->max_addons }} > 0 && totalAddons() >= {{ (int) $prod->max_addons }} && !({{ $maximum }} === 1 && groupCount(@js($groupIds)) > 0)))">
                            @if($addon->image)
                                <img src="{{ Storage::url($addon->image) }}" alt="" class="mo-option-image"
                                     width="48" height="48" loading="lazy" decoding="async">
                            @else
                                <span class="mo-option-image mo-option-image--empty" aria-hidden="true">
                                    <i class="bx bx-plus-circle"></i>
                                </span>
                            @endif
                            <span class="mo-addon-check"><i class="bx bx-check"></i></span>
                            <span class="mo-addon-copy">
                                <span class="mo-addon-name">{{ $addon->name }}</span>
                                @if($addon->description)
                                    <small>{{ $addon->description }}</small>
                                @endif
                            </span>
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
                            <span class="mo-addon-count-hint" :class="{ 'at-max': totalIngredients() >= {{ $maxIngs }} }">
                                <span x-text="totalIngredients()"></span>/{{ $maxIngs }}
                            </span>
                        @endif
                    </div>
                    @if($maxIngs > 0)
                        <div class="mo-ing-max-hint" x-show="totalIngredients() >= {{ $maxIngs }}" x-cloak>
                            <i class="bx bx-info-circle me-1"></i>Máximo de {{ $maxIngs }} ingrediente(s) alcanzado
                        </div>
                    @endif
                    @error('ingredients')<small class="text-danger d-block mb-1">{{ $message }}</small>@enderror
                    <div class="mo-addon-options">
                        @foreach($prod->ingredients as $ing)
                        <div class="mo-ingredient-row">
                            @if($ing->image)
                                <img src="{{ Storage::url($ing->image) }}" alt="" class="mo-option-image"
                                     width="48" height="48" loading="lazy" decoding="async">
                            @else
                                <span class="mo-option-image mo-option-image--empty" aria-hidden="true">
                                    <i class="bx bx-list-ul"></i>
                                </span>
                            @endif
                            <span class="mo-addon-copy">
                                <span class="mo-addon-name">{{ $ing->name }}</span>
                                @if($ing->description)
                                    <small>{{ $ing->description }}</small>
                                @endif
                                @if($ing->extra_price > 0)
                                    <small>+${{ number_format($ing->extra_price, 2) }}/unidad</small>
                                @else
                                    <small>Sin costo</small>
                                @endif
                            </span>
                            <div class="mo-ing-controls">
                                <button type="button" class="mo-qty-btn"
                                        x-on:click="changeIngredient({{ $ing->id }}, -1, {{ $maxIngs }})"
                                        :disabled="Number(ingredients[{{ $ing->id }}] || 0) === 0"
                                        aria-label="Quitar {{ $ing->name }}">
                                    <i class="bx bx-minus"></i>
                                </button>
                                <span class="mo-qty-val" x-text="Number(ingredients[{{ $ing->id }}] || 0)"></span>
                                <button type="button" class="mo-qty-btn"
                                        x-on:click="changeIngredient({{ $ing->id }}, 1, {{ $maxIngs }})"
                                        :disabled="{{ $maxIngs }} > 0 && totalIngredients() >= {{ $maxIngs }}"
                                        aria-label="Agregar {{ $ing->name }}">
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
                        <button type="button" class="mo-qty-btn" x-on:click="qty = Math.max(1, qty - 1)"
                                :disabled="qty <= 1" aria-label="Reducir cantidad">
                            <i class="bx bx-minus"></i>
                        </button>
                        <span class="mo-qty-val" x-text="qty"></span>
                        <button type="button" class="mo-qty-btn" x-on:click="qty = Math.min(99, qty + 1)"
                                :disabled="qty >= 99" aria-label="Aumentar cantidad">
                            <i class="bx bx-plus"></i>
                        </button>
                    </div>
                </div>

                {{-- Notes --}}
                <div class="mo-addon-group">
                    <div class="mo-addon-group-label"><span>Nota (opcional)</span><small class="mo-addon-rule">Visible para cocina</small></div>
                    <label for="mo-item-notes" class="visually-hidden">Nota para cocina</label>
                    <textarea id="mo-item-notes" class="form-control" rows="2" maxlength="500"
                              x-model="notes" placeholder="Ej. sin cebolla, bien cocido…"></textarea>
                </div>
                @error('itemQty')<small class="text-danger d-block mb-2">{{ $message }}</small>@enderror
                @error('itemNotes')<small class="text-danger d-block mb-2">{{ $message }}</small>@enderror

            </div>
            <div class="mo-modal-footer">
                <button type="button" class="btn btn-outline-secondary" wire:click="closeCustomize">Cancelar</button>
                <button type="button" class="mo-send-btn mo-confirm-btn" x-on:click="submit($wire)"
                        :disabled="submitting">
                    <span x-show="!submitting">
                        <i class="bx bx-cart" aria-hidden="true"></i>
                        {{ $editingCartId ? 'Actualizar producto' : 'Agregar al pedido' }}
                    </span>
                    <span x-show="submitting" x-cloak>
                        <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                        Guardando…
                    </span>
                </button>
            </div>
        </div>
    </div>
    @endif

    @if($showCloseModal)
    <div class="mesas-modal-backdrop" wire:click.self="closeCloseModal"
         x-data x-init="$nextTick(() => $refs.orderCloseCancel.focus())"
         @keydown.escape.window="$wire.closeCloseModal()">
        <section class="mesas-modal mesas-modal--close-choice" role="dialog" aria-modal="true"
                 aria-labelledby="order-close-title" aria-describedby="order-close-description">
            <div class="mesas-modal-header">
                <div>
                    <span class="mesas-modal-eyebrow">Enviar cuenta a caja</span>
                    <h5 id="order-close-title">Cerrar {{ $this->mesa->display_name }}</h5>
                </div>
                <button type="button" class="mesas-modal-close" wire:click="closeCloseModal" aria-label="Cancelar cierre de mesa"><i class="bx bx-x" aria-hidden="true"></i></button>
            </div>
            <div class="mesas-modal-body">
                <p id="order-close-description" class="text-muted mb-3">Elige cómo se cobrará. Para agregar más pedidos después tendrás que reabrir la mesa.</p>
                <div class="mesas-close-options">
                    <button type="button" class="mesas-close-option" wire:click="confirmCloseMesa('full')" wire:loading.attr="disabled" wire:target="confirmCloseMesa">
                        <span class="mesas-close-option__icon" aria-hidden="true"><i class="bx bx-receipt"></i></span>
                        <span><strong>Cuenta completa</strong><small>Cobrar todo junto en el POS.</small></span>
                        <i class="bx bx-chevron-right" aria-hidden="true"></i>
                    </button>
                    @can('dividir mesas')
                    <button type="button" class="mesas-close-option mesas-close-option--split" wire:click="confirmCloseMesa('split')" wire:loading.attr="disabled" wire:target="confirmCloseMesa">
                        <span class="mesas-close-option__icon" aria-hidden="true"><i class="bx bx-git-branch"></i></span>
                        <span><strong>Dividir cuenta</strong><small>Asignar productos o partes a subcuentas.</small></span>
                        <i class="bx bx-chevron-right" aria-hidden="true"></i>
                    </button>
                    @endcan
                </div>
            </div>
            <div class="mesas-modal-actions">
                <button type="button" class="btn btn-outline-secondary" wire:click="closeCloseModal" x-ref="orderCloseCancel">Cancelar</button>
            </div>
        </section>
    </div>
    @endif

</div>
