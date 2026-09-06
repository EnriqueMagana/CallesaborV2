(function () {
    'use strict';

    const controllers = new Map();

    const nextPaint = () => new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
    const timeout = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));

    class TicketPreviewController {
        constructor(root) {
            this.root = root;
            this.id = root.id;
            this.activeTab = root.dataset.initialTab || '';
            this.ready = new Map();
            this.loadTokens = new Map();
            this.lastFocusedElement = null;
            this.bind();
            this.activate(this.activeTab);
            this.frames().forEach((frame) => this.prepareFrame(frame));
            if (this.root.classList.contains('is-open')) {
                this.root.setAttribute('aria-hidden', 'false');
                document.documentElement.classList.add('ticket-preview-open');
            }
        }

        frames() {
            return Array.from(this.root.querySelectorAll('[data-ticket-frame]'));
        }

        bind() {
            this.root.querySelectorAll('[data-ticket-tab]').forEach((button) => {
                button.addEventListener('click', () => this.activate(button.dataset.ticketTab));
            });
            this.root.querySelectorAll('[data-ticket-preview-close]').forEach((button) => {
                button.addEventListener('click', () => this.close(false));
            });
            this.root.querySelector('[data-ticket-preview-print]')?.addEventListener('click', () => this.print());
            this.root.addEventListener('click', (event) => {
                if (event.target === this.root) this.requestClose();
            });
            this.root.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') this.requestClose();
            });
        }

        requestClose() {
            const closeButton = this.root.querySelector('[data-ticket-preview-close]');
            if (closeButton) closeButton.click();
            else this.close();
        }

        open(options) {
            const config = options || {};
            this.lastFocusedElement = document.activeElement;
            if (config.title) this.root.querySelector('[data-ticket-preview-title]').textContent = config.title;

            Object.entries(config.frames || {}).forEach(([key, html]) => this.setFrameContent(key, html));
            this.activate(config.activeTab || this.activeTab || this.root.dataset.initialTab);
            this.root.classList.add('is-open');
            this.root.setAttribute('aria-hidden', 'false');
            document.documentElement.classList.add('ticket-preview-open');
            this.root.querySelector('[data-ticket-preview-close]')?.focus({ preventScroll: true });
        }

        close(restoreFocus = true) {
            this.root.classList.remove('is-open');
            this.root.setAttribute('aria-hidden', 'true');
            if (!document.querySelector('[data-ticket-preview-modal].is-open')) {
                document.documentElement.classList.remove('ticket-preview-open');
            }
            if (restoreFocus && this.lastFocusedElement?.isConnected) {
                this.lastFocusedElement.focus({ preventScroll: true });
            }
        }

        activate(key) {
            if (!key) return;
            this.activeTab = key;
            this.root.querySelectorAll('[data-ticket-tab]').forEach((button) => {
                const selected = button.dataset.ticketTab === key;
                button.classList.toggle('is-active', selected);
                button.setAttribute('aria-selected', selected ? 'true' : 'false');
                button.tabIndex = selected ? 0 : -1;
            });
            this.root.querySelectorAll('[data-ticket-pane]').forEach((pane) => {
                pane.classList.toggle('is-active', pane.dataset.ticketPane === key);
            });
            this.syncPrintButton();
        }

        setFrameContent(key, html) {
            const frame = this.root.querySelector(`[data-ticket-frame="${CSS.escape(key)}"]`);
            if (!frame) return;
            this.markLoading(key);
            frame.srcdoc = html || '<!doctype html><html><body></body></html>';
        }

        prepareFrame(frame) {
            const key = frame.dataset.ticketFrame;
            frame.addEventListener('load', () => this.waitUntilRendered(frame));
            this.markLoading(key);
            if (frame.contentDocument?.readyState === 'complete') this.waitUntilRendered(frame);
        }

        markLoading(key) {
            const token = (this.loadTokens.get(key) || 0) + 1;
            this.loadTokens.set(key, token);
            this.ready.set(key, false);
            const shell = this.root.querySelector(`[data-ticket-frame-shell="${CSS.escape(key)}"]`);
            shell?.classList.remove('is-ready');
            shell?.setAttribute('aria-busy', 'true');
            this.syncPrintButton();
            return token;
        }

        async waitUntilRendered(frame) {
            const key = frame.dataset.ticketFrame;
            const token = this.loadTokens.get(key) || this.markLoading(key);
            const documentRef = frame.contentDocument;
            if (!documentRef) return;

            const imageTasks = Array.from(documentRef.images).map((image) => {
                if (image.complete) return image.decode?.().catch(() => undefined) || Promise.resolve();
                return new Promise((resolve) => {
                    image.addEventListener('load', resolve, { once: true });
                    image.addEventListener('error', resolve, { once: true });
                });
            });
            const fontTask = documentRef.fonts?.ready || Promise.resolve();

            await Promise.race([Promise.allSettled([fontTask, ...imageTasks]), timeout(8000)]);
            await nextPaint();
            if (this.loadTokens.get(key) !== token) return;

            this.ready.set(key, true);
            const shell = this.root.querySelector(`[data-ticket-frame-shell="${CSS.escape(key)}"]`);
            shell?.classList.add('is-ready');
            shell?.setAttribute('aria-busy', 'false');
            this.syncPrintButton();
        }

        syncPrintButton() {
            const button = this.root.querySelector('[data-ticket-preview-print]');
            if (button) button.disabled = !this.ready.get(this.activeTab);
        }

        print() {
            if (!this.ready.get(this.activeTab)) return;
            const frame = this.root.querySelector(`[data-ticket-frame="${CSS.escape(this.activeTab)}"]`);
            try {
                frame?.contentWindow?.focus();
                frame?.contentWindow?.print();
            } catch (error) {
                console.error('No fue posible abrir la impresi\u00f3n del ticket.', error);
            }
        }
    }

    function initialize(root) {
        if (!(root instanceof Element) || !root.matches('[data-ticket-preview-modal]')) return null;
        const existing = controllers.get(root.id);
        if (existing?.root === root) return existing;
        const controller = new TicketPreviewController(root);
        controllers.set(root.id, controller);
        return controller;
    }

    function scan(scope) {
        if (scope instanceof Element && scope.matches('[data-ticket-preview-modal]')) initialize(scope);
        scope.querySelectorAll?.('[data-ticket-preview-modal]').forEach(initialize);
    }

    window.TicketPreviewModal = {
        open(id, options) {
            const root = document.getElementById(id);
            return (root && initialize(root))?.open(options);
        },
        close(id) {
            controllers.get(id)?.close();
        },
        activate(id, tab) {
            controllers.get(id)?.activate(tab);
        },
        print(id) {
            controllers.get(id)?.print();
        },
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => scan(document), { once: true });
    else scan(document);

    new MutationObserver((mutations) => {
        mutations.forEach((mutation) => mutation.addedNodes.forEach((node) => {
            if (node instanceof Element) scan(node);
        }));
    }).observe(document.documentElement, { childList: true, subtree: true });
})();
