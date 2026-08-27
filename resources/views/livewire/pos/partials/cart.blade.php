@php
    $cartLineCount = count($cart);
    $cartUnitCount = $this->cartCount;
@endphp

<section class="cart-panel" aria-label="Pedido actual">
    <header class="cart-header">
        <div class="cart-header__identity">
            <span class="cart-header__icon" aria-hidden="true"><i class="bx bx-receipt"></i></span>
            <div>
                <h5>Pedido actual</h5>
                <p class="cart-header__meta" aria-live="polite">
                    @if ($cartLineCount > 0)
                        {{ $cartLineCount }} {{ $cartLineCount === 1 ? 'producto' : 'productos' }}
                        <span aria-hidden="true">&middot;</span>
                        {{ $cartUnitCount }} {{ $cartUnitCount === 1 ? 'unidad' : 'unidades' }}
                    @else
                        Listo para comenzar
                    @endif
                </p>
            </div>
        </div>

        <div class="cart-header__actions">
            @if ($cartLineCount > 0)
                <button type="button" class="cart-clear-btn" wire:click="confirmClearCart"
                    aria-label="Vaciar todo el pedido">
                    <i class="bx bx-trash" aria-hidden="true"></i><span>Vaciar</span>
                </button>
            @endif
            <button type="button" class="cart-mobile-close" @click="showCart = false" aria-label="Cerrar carrito">
                <i class="bx bx-x" aria-hidden="true"></i>
            </button>
        </div>
    </header>

    <div class="cart-items" role="list" aria-label="Productos del pedido">
        @forelse($cart as $item)
            <article class="cart-item" role="listitem" wire:key="pos-cart-{{ $item['cart_id'] }}">
                @if (!empty($item['product_image']))
                    <img class="cart-item-image" src="{{ Storage::url($item['product_image']) }}" alt=""
                        width="56" height="56" loading="lazy" decoding="async">
                @else
                    <span class="cart-item-image is-empty" aria-hidden="true"><i class="bx bx-food-menu"></i></span>
                @endif

                <div class="cart-item-detail">
                    <div class="cart-item__heading">
                        <div class="cart-item-name">{{ $item['product_name'] }}</div>
                        <span class="cart-item-price">${{ number_format($item['subtotal'], 2) }}</span>
                    </div>

                    @if (!empty($item['promotion_selections']) || !empty($item['addons']) || !empty($item['ingredients']) || !empty($item['notes']))
                        <div class="cart-item-options" aria-label="Personalización">
                            @foreach($item['promotion_selections'] ?? [] as $group)
                                <div class="cart-item-promotion-group"><strong>{{ $group['group_name'] }}</strong>@foreach($group['items'] as $selected)<span><i class="bx bx-check"></i>{{ $selected['quantity'] }}× {{ $selected['product_name'] }}</span>@endforeach</div>
                            @endforeach
                            @foreach ($item['addons'] as $addon)
                                <div class="cart-item-addon">
                                    <i class="bx bx-plus-circle" aria-hidden="true"></i>
                                    <span>{{ $addon['addon_name'] }}</span>
                                    @if ($addon['extra_price'] > 0)
                                        <span
                                            class="cart-item-option-price">+${{ number_format($addon['extra_price'], 2) }}</span>
                                    @endif
                                </div>
                            @endforeach
                            @foreach ($item['ingredients'] as $ingredient)
                                <div class="cart-item-ingredient">
                                    <i class="bx bx-check" aria-hidden="true"></i>
                                    <span>{{ $ingredient['ingredient_name'] }}</span>
                                    @if ($ingredient['quantity'] > 1)
                                        <span>&times;{{ $ingredient['quantity'] }}</span>
                                    @endif
                                </div>
                            @endforeach
                            @if ($item['notes'])
                                <div class="cart-item-note">
                                    <i class="bx bx-message-rounded-detail" aria-hidden="true"></i>
                                    <span>{{ $item['notes'] }}</span>
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="cart-item__footer">
                        <div class="cart-qty-controls" aria-label="Cantidad de {{ $item['product_name'] }}">
                            <button type="button" class="qty-btn"
                                wire:click="decrementCartItem('{{ $item['cart_id'] }}')"
                                aria-label="Reducir cantidad de {{ $item['product_name'] }}">
                                <i class="bx bx-minus" aria-hidden="true"></i>
                            </button>
                            <span class="qty-val"
                                aria-label="Cantidad {{ $item['quantity'] }}">{{ $item['quantity'] }}</span>
                            <button type="button" class="qty-btn"
                                wire:click="incrementCartItem('{{ $item['cart_id'] }}')"
                                aria-label="Aumentar cantidad de {{ $item['product_name'] }}">
                                <i class="bx bx-plus" aria-hidden="true"></i>
                            </button>
                        </div>

                        <div class="cart-item-actions">
                            @if(empty($item['promotion_id']))<button type="button" class="cart-item-action"
                                wire:click="editCartItem('{{ $item['cart_id'] }}')" title="Personalizar"
                                aria-label="Personalizar {{ $item['product_name'] }}">
                                <i class="bx bx-slider-alt" aria-hidden="true"></i><span>Editar</span>
                            </button>@endif
                            <button type="button" class="cart-item-action is-danger"
                                wire:click="removeCartItem('{{ $item['cart_id'] }}')" title="Quitar"
                                aria-label="Quitar {{ $item['product_name'] }}">
                                <i class="bx bx-trash" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </article>
        @empty
            <div class="cart-empty">
                <span class="cart-empty__icon" aria-hidden="true"><i class="bx bx-basket"></i></span>
                <strong>Tu pedido está vacío</strong>
                <p>Selecciona platillos del catálogo para comenzar la orden.</p>
            </div>
        @endforelse
    </div>

    <footer class="cart-footer">
        @if ($cartLineCount > 0)
            <label class="cart-note-bar">
                <span class="cart-note-bar__icon" aria-hidden="true"><i class="bx bx-message-dots"></i></span>
                <span class="cart-note-bar__content">
                    <span class="cart-note-bar__label">Nota para cocina</span>
                    <input type="text" wire:model.blur="orderNotes" maxlength="500" class="pos-input"
                        placeholder="Ej. entregar todo junto">
                </span>
            </label>

            <div class="cart-totals" aria-label="Resumen del pedido">
                <div class="cart-summary-meta">
                    <span>{{ $cartUnitCount }} {{ $cartUnitCount === 1 ? 'artículo' : 'artículos' }}</span>
                    <span>Total a cobrar</span>
                </div>
                <div class="total-row main">
                    <span>Total</span>
                    <span>${{ number_format($this->cartTotal, 2) }}</span>
                </div>
            </div>

            <div class="cart-cta">
                <button type="button" class="pos-btn pos-btn-secondary cart-cta-save" data-pos-save-cart
                    aria-keyshortcuts="F5" aria-label="Guardar pedido" title="Guardar pedido (F5)"
                    wire:click="$set('showSaveQuotationModal',true)">
                    <i class="bx bx-save" aria-hidden="true"></i><span>Guardar</span>
                    <kbd class="pos-control-shortcut" aria-hidden="true">F5</kbd>
                </button>
                <button type="button" class="btn-checkout cart-cta-checkout" data-pos-checkout
                    aria-keyshortcuts="F2" title="Cobrar pedido (F2)" wire:click="openCheckoutModal"
                    wire:loading.attr="disabled" wire:target="openCheckoutModal">
                    <span wire:loading wire:target="openCheckoutModal" class="pos-btn-spinner"></span>
                    <i wire:loading.remove wire:target="openCheckoutModal" class="bx bx-credit-card"
                        aria-hidden="true"></i>
                    <span>Cobrar pedido</span><kbd class="pos-shortcut-hint" aria-hidden="true">F2</kbd>
                </button>
            </div>
        @else
            <div class="cart-empty-hint" aria-hidden="true">
                <i class="bx bx-info-circle"></i>Agrega al menos un producto para continuar
            </div>
        @endif
    </footer>
</section>
