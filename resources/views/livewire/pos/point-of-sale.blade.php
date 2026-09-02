{{-- Punto de Venta --}}
<div x-data="{
    showCart: false,
    showSaved: false,
    showMore: false,
    searchExpanded: false,
    isDesktop: window.matchMedia('(min-width: 1025px)').matches,
    catalogQuery: '',
    overlayTrigger: null,
    panels: { tables: false, pickup: false, delivery: false, orders: false, reprint: false, kitchen: false },
    init() {
        this.$watch('showCart', () => this.syncOverlayLock());
        this.$watch('showMore', () => this.syncOverlayLock());
        this.syncSearchBreakpoint();
    },
    syncOverlayLock() {
        document.documentElement.classList.toggle('pos-overlay-open', this.showCart || this.showMore);
    },
    syncSearchBreakpoint() {
        const wasDesktop = this.isDesktop;
        this.isDesktop = window.matchMedia('(min-width: 1025px)').matches;

        if (this.isDesktop) {
            this.searchExpanded = true;
        } else if (wasDesktop) {
            this.searchExpanded = false;
        }
    },
    isCatalogSearchExpanded() {
        return this.isDesktop || this.searchExpanded;
    },
    collapseCatalogSearch() {
        if (!this.isDesktop) this.searchExpanded = false;
    },
    openCatalogSearch(selectContents = false) {
        this.searchExpanded = true;
        this.$nextTick(() => {
            const input = this.$refs.catalogSearch;
            if (!input) return;
            input.focus({ preventScroll: true });
            if (selectContents) input.select();
        });
    },
    closeCatalogSearch(clearQuery = false) {
        if (clearQuery) this.catalogQuery = '';
        if (this.isDesktop) {
            this.searchExpanded = true;
            this.$nextTick(() => this.$refs.catalogSearch?.focus({ preventScroll: true }));
            return;
        }
        this.searchExpanded = false;
        this.$nextTick(() => this.$refs.catalogSearchButton?.focus({ preventScroll: true }));
    },
    closeAllPanels() {
        const hadOpenPanel = Object.values(this.panels).some(Boolean);
        Object.keys(this.panels).forEach(panel => this.panels[panel] = false);
        return hadOpenPanel;
    },
    showOnlyPanel(panel) {
        this.showMore = false;
        this.showCart = false;
        this.collapseCatalogSearch();
        this.closeAllPanels();
        this.panels[panel] = true;
    },
    openMore(trigger) {
        const hadOpenPanel = this.closeAllPanels();
        this.showCart = false;
        this.collapseCatalogSearch();
        this.overlayTrigger = trigger;
        this.showMore = true;
        if (hadOpenPanel) this.$wire.closeOperationalPanels();
        this.$nextTick(() => this.$refs.moreClose?.focus({ preventScroll: true }));
    },
    closeMore(restoreFocus = true) {
        this.showMore = false;
        if (restoreFocus) {
            this.$nextTick(() => this.overlayTrigger?.focus({ preventScroll: true }));
        }
    },
    toggleCart() {
        const hadOpenPanel = this.closeAllPanels();
        this.showMore = false;
        this.collapseCatalogSearch();
        this.showCart = !this.showCart;
        if (hadOpenPanel) this.$wire.closeOperationalPanels();
    },
    closeTransientLayers() {
        if (this.showMore) return this.closeMore();
        if (this.showCart) {
            this.showCart = false;
            return;
        }
        if (!this.isDesktop && this.searchExpanded) this.closeCatalogSearch(false);
        else if (this.closeAllPanels()) this.$wire.closeOperationalPanels();
    },
    trapFocus(event, container) {
        const items = Array.from(container.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex=&quot;-1&quot;])'))
            .filter(element => element.offsetParent !== null);
        if (!items.length) return;
        const first = items[0];
        const last = items[items.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    },
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
            F6: '[data-pos-panel=pickup]',
            F7: '[data-pos-panel=tables]',
            F8: '[data-pos-panel=delivery]',
            F9: '[data-pos-panel=reprint]',
            F11: '[data-pos-operations]'
        };
        const key = event.key.toUpperCase();
        const isSearchShortcut = key === 'F3' || key === 'F10';
        const isCheckoutShortcut = key === 'F2';
        const isDraftShortcut = key === 'F5';
        if (!isSearchShortcut && !isDraftShortcut && !shortcuts[key]) return;

        event.preventDefault();
        if (isDraftShortcut) {
            const saveDraft = this.visibleShortcutTarget('[data-pos-save-draft]');
            if (saveDraft) saveDraft.click();
            return;
        }

        if (isCheckoutShortcut) {
            const submitOrder = this.visibleShortcutTarget('[data-pos-submit-order]');
            if (submitOrder) {
                submitOrder.focus({ preventScroll: true });
                submitOrder.click();
                return;
            }
        }

        if (this.hasBlockingLayer()) return;

        if (isSearchShortcut) {
            this.openCatalogSearch(true);
            return;
        }

        const focused = document.activeElement;
        if (focused && (focused.matches('input, textarea, select, [contenteditable=true]') || focused.closest('[role=dialog]'))) return;

        const target = this.visibleShortcutTarget(shortcuts[key]);
        if (!target) return;
        target.focus({ preventScroll: true });
        target.click();
    }
}" @resize.window.debounce.150ms="syncSearchBreakpoint()"
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
        @include('livewire.pos.partials.catalog')
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
