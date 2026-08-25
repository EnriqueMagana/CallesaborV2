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
        <livewire:layout.notification-center placement="pos" />
        <a href="{{ route('app.dashboard') }}" class="btn-header-action">
            <i class="bx bx-home-alt"></i>
            <span>Dashboard</span>
        </a>
        @canany(['crear ordenes', 'editar ordenes'])
            <button type="button" class="btn-header-action btn-header-saved"
                @click="showSaved = true; $wire.$set('showQuotationsModal', true)"
                data-pos-saved aria-keyshortcuts="F4"
                aria-label="Abrir pedidos guardados para retomarlos" title="Retomar pedidos guardados (F4)">
                <i class="bx bx-bookmark"></i>
                <span>Guardados</span>
                <kbd class="pos-control-shortcut" aria-hidden="true">F4</kbd>
            </button>
        @endcanany
        <div data-ui="xui-h2y0md">
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
