{{-- Punto de Venta --}}
<div x-data="{
    showCart: false,
    showSaved: false,
    panels: { mesas: false, pickup: false, delivery: false, orders: false, reprint: false, kitchen: false }
}" class="pos-root">

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
        @include('livewire.pos.partials.catalog')
        @include('livewire.pos.partials.toolbar')
    </div>

    {{-- Carrito fijo desktop --}}
    <div class="pos-cart-fixed">
        @include('livewire.pos.partials.cart')
    </div>
</div>

{{-- Carrito overlay mobile --}}
<div class="cart-overlay" :class="showCart ? 'show' : ''" @click="showCart = false">
    <div @click.stop>
        @include('livewire.pos.partials.cart')
    </div>
</div>

{{-- Panels laterales --}}
@include('livewire.pos.partials.panels.mesas')
@include('livewire.pos.partials.panels.pickup')
@include('livewire.pos.partials.panels.kitchen')
@include('livewire.pos.partials.panels.reprint')

{{-- Modals --}}
@include('livewire.pos.partials.modals.customize')
@include('livewire.pos.partials.modals.checkout')
@include('livewire.pos.partials.modals.expense')
@include('livewire.pos.partials.modals.quotations')
@include('livewire.pos.partials.modals.cash-register')
@include('livewire.pos.partials.modals.new-customer')
@include('livewire.pos.partials.modals.order-success')
@include('livewire.pos.partials.modals.pickup-pay')
@include('livewire.pos.partials.modals.convert-delivery')
@include('livewire.pos.partials.modals.mesa-pay')
@include('livewire.pos.partials.modals.ticket')

@endif{{-- /activeCashRegister --}}

<script>
window._posTicketTab = 'cliente';

document.addEventListener('livewire:init', function () {
    Livewire.on('pos-reprint-show', ({ html_cliente, html_cocina }) => {
        document.getElementById('iframe-cliente').srcdoc = html_cliente || '';
        document.getElementById('iframe-cocina').srcdoc  = html_cocina  || '';
        posTicketTab('cliente');
        document.getElementById('posTicketModal').classList.add('is-open');
    });

    Livewire.on('pos-reprint-show-cocina', ({ html_cliente, html_cocina }) => {
        document.getElementById('iframe-cliente').srcdoc = html_cliente || '';
        document.getElementById('iframe-cocina').srcdoc  = html_cocina  || '';
        posTicketTab('cocina');
        document.getElementById('posTicketModal').classList.add('is-open');
    });
});

window.posTicketTab = function (tab) {
    window._posTicketTab = tab;
    document.getElementById('pane-cliente').classList.toggle('is-hidden', tab !== 'cliente');
    document.getElementById('pane-cocina').classList.toggle('is-hidden', tab !== 'cocina');
    document.getElementById('tab-cliente').className = 'pos-btn pos-btn-sm ' + (tab === 'cliente' ? 'pos-btn-primary' : 'pos-btn-secondary');
    document.getElementById('tab-cocina').className  = 'pos-btn pos-btn-sm ' + (tab === 'cocina'  ? 'pos-btn-primary' : 'pos-btn-secondary');
};

window.posTicketClose = function () {
    document.getElementById('posTicketModal').classList.remove('is-open');
};

window.posTicketPrint = function () {
    const id = window._posTicketTab === 'cocina' ? 'iframe-cocina' : 'iframe-cliente';
    try { document.getElementById(id).contentWindow.print(); } catch(e) {}
};
</script>

</div>{{-- /pos-root --}}
