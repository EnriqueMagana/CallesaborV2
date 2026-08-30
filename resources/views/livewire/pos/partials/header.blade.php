<div class="pos-header">
    <div class="pos-header-left">
        <div class="pos-logo">
            <img class="pos-logo-img"
                src="{{ $businessSettings?->logo_path ? Storage::url($businessSettings->logo_path) : asset('assets/img/favicon/favicon.ico') }}"
                alt="Logo de {{ $businessSettings?->business_name ?? config('app.name') }}" width="42" height="42"
                onerror="this.hidden=true;this.nextElementSibling.hidden=false">
            <i class="bx bx-restaurant" data-ui="xui-1v6ktfg" hidden></i>
        </div>
        <div class="pos-header-info">
            <span class="brand-name">{{ $businessSettings?->platform_name ?? config('app.name') }}</span>
            <span class="brand-sub">Punto de Venta</span>
        </div>
    </div>
    <div class="pos-header-right">
        <div class="pos-header-search" :class="{ 'is-expanded': searchExpanded, 'has-query': catalogQuery.trim() }"
            @click.outside="if (searchExpanded) closeCatalogSearch(false)">
            <button type="button" class="pos-header-search__trigger" x-ref="catalogSearchButton"
                @click="openCatalogSearch(false)" :aria-expanded="searchExpanded.toString()"
                aria-controls="pos-header-catalog-search" aria-label="Buscar en el catálogo"
                title="Buscar platillo (F3 o F10)">
                <i class="bx bx-search" aria-hidden="true"></i>
                <span class="visually-hidden">Buscar</span>
                <span class="pos-header-search__status" x-show="catalogQuery.trim()" x-cloak aria-hidden="true"></span>
            </button>
            <div id="pos-header-catalog-search" class="pos-header-search__field"
                :aria-hidden="(!searchExpanded).toString()">
                <i class="bx bx-search" aria-hidden="true"></i>
                <label for="pos-catalog-search" class="visually-hidden">Buscar platillo o promoción</label>
                <input id="pos-catalog-search" type="search" x-ref="catalogSearch"
                    x-model.debounce.160ms="catalogQuery"
                    @keydown.escape.stop="closeCatalogSearch(false)"
                    :tabindex="searchExpanded ? 0 : -1"
                    class="pos-header-search__input" placeholder="Buscar platillo..." autocomplete="off"
                    data-pos-catalog-search aria-keyshortcuts="F3 F10">
                <button type="button" class="pos-header-search__close"
                    @click="closeCatalogSearch(true)" :tabindex="searchExpanded ? 0 : -1"
                    aria-label="Limpiar búsqueda y cerrar">
                    <i class="bx bx-x" aria-hidden="true"></i>
                </button>
            </div>
        </div>
        <livewire:layout.notification-center placement="pos" />
        <a href="{{ route('app.dashboard') }}" class="btn-header-action">
            <i class="bx bx-home-alt"></i>
            <span>Dashboard</span>
        </a>
        @canany(['crear ordenes', 'editar ordenes'])
            <button type="button" class="btn-header-action btn-header-saved"
                @click="closeAllPanels(); showCart = false; showMore = false; showSaved = true; $wire.openSavedOrdersModal()"
                data-pos-saved aria-keyshortcuts="F4"
                aria-label="Abrir pedidos guardados para retomarlos" title="Retomar pedidos guardados (F4)">
                <i class="bx bx-bookmark"></i>
                <span>Guardados</span>
                <kbd class="pos-control-shortcut" aria-hidden="true">F4</kbd>
            </button>
        @endcanany
        <div class="pos-register-status" data-ui="xui-h2y0md">
            <i class="bx bx-lock-open-alt"></i>
            <span>{{ $this->activeCashRegister->name }}</span>
        </div>
        <button type="button" class="btn-cart-toggle" @click="showCart = !showCart" :class="showCart ? 'active' : ''"
            aria-label="Abrir carrito" :aria-expanded="showCart.toString()">
            <i class="bx bx-cart"></i>
            @if (!empty($cart))
                <span class="cart-count">{{ $this->cartCount }}</span>
            @endif
            <span>Carrito</span>
        </button>
    </div>
</div>
