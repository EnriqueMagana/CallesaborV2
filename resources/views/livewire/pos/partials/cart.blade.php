<div class="cart-panel">
    <div class="cart-header">
        <i class="bx bx-receipt" data-ui="xui-o6nayf"></i>
        <h5>Pedido actual</h5>
        @if(!empty($cart))
            <span class="cart-badge">{{ $this->cartCount }}</span>
            <button type="button" class="btn-remove" wire:click="confirmClearCart" aria-label="Vaciar pedido"
                    data-ui="xui-pspzi">
                <i class="bx bx-trash"></i>
            </button>
        @endif
    </div>

    <div class="cart-items">
        @forelse($cart as $item)
            <div class="cart-item" wire:key="pos-cart-{{ $item['cart_id'] }}">
                @if(!empty($item['product_image']))
                    <img class="cart-item-image" src="{{ Storage::url($item['product_image']) }}"
                         alt="" width="52" height="52" loading="lazy" decoding="async">
                @else
                    <span class="cart-item-image is-empty" aria-hidden="true"><i class="bx bx-food-menu"></i></span>
                @endif
                <div class="cart-qty-controls">
                    <button type="button" class="qty-btn" wire:click="decrementCartItem('{{ $item['cart_id'] }}')" aria-label="Reducir cantidad de {{ $item['product_name'] }}">
                        <i class="bx bx-minus"></i>
                    </button>
                    <span class="qty-val">{{ $item['quantity'] }}</span>
                    <button type="button" class="qty-btn" data-ui="xui-gv08wd" aria-label="Aumentar cantidad de {{ $item['product_name'] }}"
                            wire:click="incrementCartItem('{{ $item['cart_id'] }}')">
                        <i class="bx bx-plus"></i>
                    </button>
                </div>
                <div class="cart-item-detail">
                    <div class="cart-item-name">{{ $item['product_name'] }}</div>
                    @foreach($item['addons'] as $a)
                        <div class="cart-item-addon">
                            <i class="bx bx-plus-circle" data-ui="xui-1l9zni9"></i>
                            {{ $a['addon_name'] }}@if($a['extra_price']>0) <span data-ui="xui-n83t44">(+${{ number_format($a['extra_price'],2) }})</span>@endif
                        </div>
                    @endforeach
                    @foreach($item['ingredients'] as $i)
                        <div data-ui="xui-17weutf">x {{ $i['ingredient_name'] }}@if($i['quantity']>1) x{{ $i['quantity'] }}@endif</div>
                    @endforeach
                    @if($item['notes'])
                        <div class="cart-item-note"><i class="bx bx-note" data-ui="xui-1l9zni9"></i> {{ $item['notes'] }}</div>
                    @endif
                </div>
                <div class="cart-item-right">
                    <span class="cart-item-price">${{ number_format($item['subtotal'],2) }}</span>
                    <div data-ui="xui-aqd7gy">
                        <button type="button" class="btn-remove" wire:click="editCartItem('{{ $item['cart_id'] }}')" title="Editar" aria-label="Editar {{ $item['product_name'] }}" data-ui="xui-n83t44">
                            <i class="bx bx-edit" data-ui="xui-1ml6c8v"></i>
                        </button>
                        <button type="button" class="btn-remove" wire:click="removeCartItem('{{ $item['cart_id'] }}')" title="Quitar" aria-label="Quitar {{ $item['product_name'] }}">
                            <i class="bx bx-x"></i>
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="cart-empty">
                <i class="bx bx-cart"></i>
                <p>Selecciona platillos del catalogo</p>
            </div>
        @endforelse
    </div>

    <div class="cart-footer">
        @if(!empty($cart))
            <div class="cart-note-bar">
                <i class="bx bx-message-dots"></i>
                <input type="text" wire:model.blur="orderNotes" maxlength="500" class="pos-input"
                       data-ui="xui-1s0sgt3" placeholder="Nota general para cocina...">
            </div>
            <div class="cart-totals">
                <div class="total-row main">
                    <span>Total</span>
                    <span>${{ number_format($this->cartTotal,2) }}</span>
                </div>
            </div>
            <div class="cart-cta">
                <button class="pos-btn pos-btn-secondary cart-cta-save" title="Guardar cotización"
                        wire:click="$set('showSaveQuotationModal',true)">
                    <i class="bx bx-save"></i>
                </button>
                <button class="btn-checkout cart-cta-checkout" wire:click="openCheckoutModal" wire:loading.attr="disabled" wire:target="openCheckoutModal">
                    <span wire:loading wire:target="openCheckoutModal" class="pos-btn-spinner"></span>
                    <i wire:loading.remove wire:target="openCheckoutModal" class="bx bx-credit-card"></i>
                    Cobrar pedido
                </button>
            </div>
        @else
            <div class="cart-cta">
                <div class="btn-checkout-ghost">
                    <i class="bx bx-cart me-1"></i>Agrega platillos para continuar
                </div>
            </div>
        @endif
    </div>
</div>
