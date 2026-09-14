{{-- Punto de Venta --}}
{{-- El estado raíz vive en `assets/js/pos-root.js`: son 6.3 KB que Livewire
     reenviaba en cada respuesta sin que cambiaran nunca. --}}
<div x-data="posRoot(@js($this->cartProductQuantities))"
    @resize.window.debounce.150ms="syncSearchBreakpoint()"
    @pos-cart-quantities.window="cartQuantities = $event.detail.quantities ?? {}"
    @keydown.window="handleKeyboardShortcut($event)" @keydown.escape.window="closeTransientLayers()" class="pos-root">

{{-- Toast --}}
<div x-data="{ show: false, msg: '', type: 'success' }"
     x-on:notify.window="msg=$event.detail.message; type=$event.detail.type??'success'; show=true; setTimeout(()=>show=false,3500)"
     x-show="show" x-transition class="pos-toast-wrap">
    <div class="pos-toast" :class="type">
        <i class="bx" :class="type==='success'?'bx-check-circle':'bx-error-circle'"></i>
        <span class="pos-toast-msg" x-text="msg"></span>
    </div>
</div>

{{-- Gate: sin caja activa --}}
@if(!$this->activeCashRegister)
<div class="pos-gate">
    <div class="pos-gate-card">
        <div class="pos-gate-icon">
            <i class="bx bx-lock-alt"></i>
        </div>
        <h5>Sin caja activa</h5>
        <p>Abre una caja para comenzar a usar el Punto de Venta.</p>
        <a href="{{ route('app.caja') }}" class="pos-btn pos-btn-primary pos-btn-block">
            <i class="bx bx-door-open"></i> Ir a Caja
        </a>
    </div>
</div>

@else

{{-- Header --}}
@include('livewire.pos.partials.header')

{{-- Main layout --}}
<div class="pos-main-layout">
    <div class="pos-body-with-toolbar">
        {{-- Componente hijo: tiene su propio ciclo de render, así que el carrito
             deja de reconstruir los 86 KB del catálogo en cada click. --}}
        <livewire:pos.catalog :order-type="$orderType" wire:key="pos-catalog" />
        @include('livewire.pos.partials.toolbar')
    </div>

    {{-- Carrito fijo desktop --}}
    <div class="pos-cart-fixed">
        @include('livewire.pos.partials.cart')
    </div>
</div>

@include('livewire.pos.partials.mobile-navigation')
@include('livewire.pos.partials.more-menu')

{{-- Carrito overlay mobile --}}
<div id="pos-mobile-cart" class="cart-overlay" :class="showCart ? 'show' : ''" @click="showCart = false">
    <div @click.stop>
        @include('livewire.pos.partials.cart')
    </div>
</div>

{{-- Panels laterales --}}
@include('livewire.pos.partials.panels.table-services')
@include('livewire.pos.partials.panels.pickup')
@include('livewire.pos.partials.panels.delivery')
<livewire:pos.panels.kitchen-panel wire:key="pos-panel-kitchen" />
@include('livewire.pos.partials.panels.reprint')

{{-- Modals --}}
@include('livewire.pos.partials.modals.customize')
@include('livewire.pos.partials.modals.promotion')
@include('livewire.pos.partials.modals.checkout')
@include('livewire.pos.partials.modals.expense')
@include('livewire.pos.partials.modals.quotations')
@include('livewire.pos.partials.modals.cash-register')
@include('livewire.pos.partials.modals.new-customer')
@include('livewire.pos.partials.modals.order-success')
@include('livewire.pos.partials.modals.pickup-pay')
@include('livewire.pos.partials.modals.convert-delivery')
@include('livewire.pos.partials.modals.order-data')
@include('livewire.pos.partials.modals.delivery-dispatch')
@include('livewire.pos.partials.modals.mesa-pay')
@include('livewire.pos.partials.modals.ticket')

@endif{{-- /activeCashRegister --}}

<script>
window.bindPosTicketEvents = function () {
    if (!window.Livewire || window._posTicketEventsBound) return;
    window._posTicketEventsBound = true;

    Livewire.on('pos-reprint-show', ({ html_cliente, html_cocina }) => {
        window.TicketPreviewModal?.open('posTicketModal', {
            activeTab: 'cliente',
            frames: { cliente: html_cliente || '', cocina: html_cocina || '' },
        });
    });

    Livewire.on('pos-reprint-show-cocina', ({ html_cliente, html_cocina }) => {
        window.TicketPreviewModal?.open('posTicketModal', {
            activeTab: 'cocina',
            frames: { cliente: html_cliente || '', cocina: html_cocina || '' },
        });
    });
};

if (window.Livewire) {
    window.bindPosTicketEvents();
} else {
    document.addEventListener('livewire:init', window.bindPosTicketEvents, { once: true });
}

window.posTicketTab = function (tab) {
    window.TicketPreviewModal?.activate('posTicketModal', tab);
};

window.posTicketClose = function () {
    window.TicketPreviewModal?.close('posTicketModal');
};

window.posTicketPrint = function () {
    window.TicketPreviewModal?.print('posTicketModal');
};
</script>

</div>{{-- /pos-root --}}
