{{-- Punto de Venta --}}
<div x-data="{
    showCart: false,
    showSaved: false,
    panels: { tables: false, pickup: false, delivery: false, orders: false, reprint: false, kitchen: false },
    visibleShortcutTarget(selector) {
        return Array.from(document.querySelectorAll(selector))
            .find(element => element.offsetParent !== null && !element.disabled);
    },
    hasBlockingLayer() {
        return Boolean(document.querySelector('.pos-modal-wrap.is-open, .pos-modal-wrap.show, .pos-overlay-panel.show, dialog[open]'));
    },
    handleKeyboardShortcut(event) {
        if (!window.matchMedia('(min-width: 1025px)').matches || event.repeat || event.ctrlKey || event.altKey || event.metaKey) return;

        const shortcuts = {
            F2: '[data-pos-checkout]',
            F4: '[data-pos-saved]',
            F5: '[data-pos-save-cart]',
            F6: '[data-pos-panel=pickup]',
            F7: '[data-pos-panel=tables]',
            F8: '[data-pos-panel=delivery]',
            F9: '[data-pos-panel=reprint]',
            F11: '[data-pos-operations]'
        };
        const key = event.key.toUpperCase();
        const isSearchShortcut = key === 'F3' || key === 'F10';
        if (!isSearchShortcut && !shortcuts[key]) return;

        event.preventDefault();
        if (this.hasBlockingLayer()) return;

        if (isSearchShortcut) {
            const search = this.visibleShortcutTarget('[data-pos-catalog-search]');
            if (!search) return;
            search.focus({ preventScroll: false });
            search.select();
            return;
        }

        const focused = document.activeElement;
        if (focused && (focused.matches('input, textarea, select, [contenteditable=true]') || focused.closest('[role=dialog]'))) return;

        const target = this.visibleShortcutTarget(shortcuts[key]);
        if (!target) return;
        target.focus({ preventScroll: true });
        target.click();
    }
}" @keydown.window="handleKeyboardShortcut($event)" class="pos-root">

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
@include('livewire.pos.partials.panels.table-services')
@include('livewire.pos.partials.panels.pickup')
@include('livewire.pos.partials.panels.delivery')
@include('livewire.pos.partials.panels.kitchen')
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
@include('livewire.pos.partials.modals.mesa-pay')
@include('livewire.pos.partials.modals.ticket')

@endif{{-- /activeCashRegister --}}

<script>
window._posTicketTab = 'cliente';

window.bindPosTicketEvents = function () {
    if (!window.Livewire || window._posTicketEventsBound) return;
    window._posTicketEventsBound = true;

    Livewire.on('pos-reprint-show', ({ html_cliente, html_cocina }) => {
        const clientFrame = document.getElementById('iframe-cliente');
        const kitchenFrame = document.getElementById('iframe-cocina');
        const modal = document.getElementById('posTicketModal');
        if (!clientFrame || !kitchenFrame || !modal) return;
        clientFrame.srcdoc = html_cliente || '';
        kitchenFrame.srcdoc = html_cocina || '';
        posTicketTab('cliente');
        modal.classList.add('is-open');
    });

    Livewire.on('pos-reprint-show-cocina', ({ html_cliente, html_cocina }) => {
        const clientFrame = document.getElementById('iframe-cliente');
        const kitchenFrame = document.getElementById('iframe-cocina');
        const modal = document.getElementById('posTicketModal');
        if (!clientFrame || !kitchenFrame || !modal) return;
        clientFrame.srcdoc = html_cliente || '';
        kitchenFrame.srcdoc = html_cocina || '';
        posTicketTab('cocina');
        modal.classList.add('is-open');
    });
};

if (window.Livewire) {
    window.bindPosTicketEvents();
} else {
    document.addEventListener('livewire:init', window.bindPosTicketEvents, { once: true });
}

window.posTicketTab = function (tab) {
    window._posTicketTab = tab;
    const clientPane = document.getElementById('pane-cliente');
    const kitchenPane = document.getElementById('pane-cocina');
    const clientTab = document.getElementById('tab-cliente');
    const kitchenTab = document.getElementById('tab-cocina');
    if (!clientPane || !kitchenPane || !clientTab || !kitchenTab) return;
    clientPane.classList.toggle('is-hidden', tab !== 'cliente');
    kitchenPane.classList.toggle('is-hidden', tab !== 'cocina');
    clientTab.className = 'pos-btn pos-btn-sm ' + (tab === 'cliente' ? 'pos-btn-primary' : 'pos-btn-secondary');
    kitchenTab.className = 'pos-btn pos-btn-sm ' + (tab === 'cocina' ? 'pos-btn-primary' : 'pos-btn-secondary');
};

window.posTicketClose = function () {
    const modal = document.getElementById('posTicketModal');
    if (modal) modal.classList.remove('is-open');
};

window.posTicketPrint = function () {
    const id = window._posTicketTab === 'cocina' ? 'iframe-cocina' : 'iframe-cliente';
    try { document.getElementById(id).contentWindow.print(); } catch(e) {}
};
</script>

</div>{{-- /pos-root --}}
