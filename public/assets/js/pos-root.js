/*
 * Estado raíz del punto de venta.
 *
 * Esto vivía como un `x-data="{ ... }"` en línea dentro de la plantilla. El
 * problema era de peso: son 6.3 KB que Livewire volvía a mandar en CADA
 * respuesta —el 12 % del payload por click— aunque el código nunca cambia.
 *
 * Aquí se carga una vez, el navegador lo cachea, y la plantilla solo pasa el
 * único dato que sí depende del servidor: las cantidades del carrito.
 */
document.addEventListener("alpine:init", () => {
    Alpine.data("posRoot", (initialCartQuantities = {}) => ({
        showCart: false,
        showSaved: false,
        showMore: false,
        searchExpanded: false,
        isDesktop: window.matchMedia('(min-width: 1025px)').matches,
        catalogQuery: '',
        overlayTrigger: null,
        panels: { tables: false, pickup: false, delivery: false, balances: false, orders: false, reprint: false, kitchen: false },
        cartQuantities: initialCartQuantities,
        cartQtyFor(productId) {
            return this.cartQuantities[productId] ?? 0;
        },
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
            const items = Array.from(container.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])'))
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
                F11: '[data-pos-more]'
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
    }));
});
