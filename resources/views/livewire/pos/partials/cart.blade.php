<div class="cart-panel">
    <div class="cart-header">
        <i class="bx bx-receipt" style="color:var(--pos-accent);font-size:1.1rem"></i>
        <h5>Pedido actual</h5>
        @if(!empty($cart))
            <span class="cart-badge">{{ $this->cartCount }}</span>
            <button class="btn-remove" wire:click="confirmClearCart"
                    style="font-size:.8rem;padding:3px 7px;border:1px solid var(--pos-border);border-radius:6px;color:var(--pos-muted)">
                <i class="bx bx-trash"></i>
            </button>
        @endif
    </div>

    <div class="cart-items">
        @forelse($cart as $item)
            <div class="cart-item">
                <div class="cart-qty-controls">
                    <button class="qty-btn" wire:click="decrementCartItem('{{ $item['cart_id'] }}')">
                        <i class="bx bx-minus"></i>
                    </button>
                    <span class="qty-val">{{ $item['quantity'] }}</span>
                    <button class="qty-btn" style="border-color:var(--pos-accent);color:var(--pos-accent)"
                            wire:click="incrementCartItem('{{ $item['cart_id'] }}')">
                        <i class="bx bx-plus"></i>
                    </button>
                </div>
                <div class="cart-item-detail">
                    <div class="cart-item-name">{{ $item['product_name'] }}</div>
                    @foreach($item['addons'] as $a)
                        <div class="cart-item-addon">
                            <i class="bx bx-plus-circle" style="font-size:.65rem"></i>
                            {{ $a['addon_name'] }}@if($a['extra_price']>0) <span style="color:var(--pos-muted)">(+${{ number_format($a['extra_price'],2) }})</span>@endif
                        </div>
                    @endforeach
                    @foreach($item['ingredients'] as $i)
                        <div style="font-size:.72rem;color:var(--pos-muted)">x {{ $i['ingredient_name'] }}@if($i['quantity']>1) x{{ $i['quantity'] }}@endif</div>
                    @endforeach
                    @if($item['notes'])
                        <div class="cart-item-note"><i class="bx bx-note" style="font-size:.65rem"></i> {{ $item['notes'] }}</div>
                    @endif
                </div>
                <div class="cart-item-right">
                    <span class="cart-item-price">${{ number_format($item['subtotal'],2) }}</span>
                    <div style="display:flex;gap:3px">
                        <button class="btn-remove" wire:click="editCartItem('{{ $item['cart_id'] }}')" title="Editar" style="color:var(--pos-muted)">
                            <i class="bx bx-edit" style="font-size:.85rem"></i>
                        </button>
                        <button class="btn-remove" wire:click="removeCartItem('{{ $item['cart_id'] }}')" title="Quitar">
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
                <input type="text" wire:model.live.debounce.400ms="orderNotes" class="pos-input"
                       style="flex:1;background:var(--pos-bg);padding:6px 10px" placeholder="Nota general...">
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
