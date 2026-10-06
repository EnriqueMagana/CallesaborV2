<nav class="pos-mobile-nav" aria-label="Navegación principal del punto de venta">
    @can('ver pedidos en punto de venta')
        <button type="button" class="pos-mobile-nav__item is-pickup"
            :class="panels.pickup ? 'is-active' : ''"
            @click="showOnlyPanel('pickup'); $wire.openPickupPanel()"
            :aria-current="panels.pickup ? 'page' : null"
            data-pos-panel="pickup" aria-label="Abrir pedidos por cobrar">
            <span class="pos-mobile-nav__icon"><i class="bx bx-receipt" aria-hidden="true"></i></span>
            <span>Por cobrar</span>
            <strong class="pos-mobile-nav__badge" x-show="pendingCounts.pickup > 0" x-cloak
                aria-label="{{ $this->toolbarPendingCounts['pickup'] }} pedidos pendientes"
                :aria-label="`${pendingCounts.pickup} pedidos pendientes`"
                x-text="pendingCounts.pickup">{{ $this->toolbarPendingCounts['pickup'] }}</strong>
        </button>
    @endcan

    @canany(['cobrar mesas', 'editar ordenes', 'reimprimir tickets'])
        <button type="button" class="pos-mobile-nav__item is-tables"
            :class="panels.tables ? 'is-active' : ''"
            @click="showOnlyPanel('tables'); $wire.openTableWorkspace()"
            :aria-current="panels.tables ? 'page' : null"
            data-pos-panel="tables" aria-label="Abrir mesas y comandas">
            <span class="pos-mobile-nav__icon"><i class="bx bx-dish" aria-hidden="true"></i></span>
            <span>Mesas</span>
            <strong class="pos-mobile-nav__badge" x-show="pendingCounts.tables > 0" x-cloak
                aria-label="{{ $this->toolbarPendingCounts['tables'] }} servicios de mesa pendientes"
                :aria-label="`${pendingCounts.tables} servicios de mesa pendientes`"
                x-text="pendingCounts.tables">{{ $this->toolbarPendingCounts['tables'] }}</strong>
        </button>
    @endcanany

    <button type="button" class="pos-mobile-nav__item pos-mobile-nav__cart is-cart"
        :class="showCart ? 'is-active' : ''" @click="toggleCart()"
        :aria-expanded="showCart.toString()" aria-controls="pos-mobile-cart"
        aria-label="Abrir carrito, {{ $this->cartCount }} productos">
        <span class="pos-mobile-nav__cart-circle"><i class="bx bx-cart" aria-hidden="true"></i></span>
        <span class="visually-hidden">Carrito</span>
        @if ($this->cartCount > 0)
            <strong class="pos-mobile-nav__badge pos-mobile-nav__cart-badge" aria-hidden="true">{{ $this->cartCount }}</strong>
        @endif
    </button>

    @can('ver pedidos en punto de venta')
        <button type="button" class="pos-mobile-nav__item is-delivery"
            :class="panels.delivery ? 'is-active' : ''"
            @click="showOnlyPanel('delivery'); $wire.openDeliveryPanel()"
            :aria-current="panels.delivery ? 'page' : null"
            data-pos-panel="delivery" aria-label="Abrir pedidos para entrega">
            <span class="pos-mobile-nav__icon"><i class="bx bx-cycling" aria-hidden="true"></i></span>
            <span>Pedidos</span>
            <strong class="pos-mobile-nav__badge" x-show="pendingCounts.delivery > 0" x-cloak
                aria-label="{{ $this->toolbarPendingCounts['delivery'] }} entregas pendientes"
                :aria-label="`${pendingCounts.delivery} entregas pendientes`"
                x-text="pendingCounts.delivery">{{ $this->toolbarPendingCounts['delivery'] }}</strong>
        </button>
    @endcan

    <button type="button" class="pos-mobile-nav__item is-more"
        :class="showMore ? 'is-active' : ''"
        @click="showMore ? closeMore() : openMore($event.currentTarget)"
        :aria-expanded="showMore.toString()" aria-controls="pos-more-menu"
        aria-label="Abrir más opciones">
        <span class="pos-mobile-nav__icon"><i class="bx bx-dots-horizontal-rounded" aria-hidden="true"></i></span>
        <span>Más</span>
    </button>
</nav>
