<nav class="pos-toolbar-bottom" aria-label="Áreas del punto de venta">
    @can('cerrar ordenes')
    <button type="button" class="tb-btn tb-btn--window" :class="panels.pickup ? 'is-active' : ''"
        @click="panels.pickup = true; $wire.$refresh()" aria-label="Abrir pedidos no pagados">
        <span class="tb-btn__icon"><i class="bx bx-receipt"></i></span>
        <span class="tb-btn__copy"><strong>Por cobrar</strong><small>Ventanilla y recoger</small></span>
        @if ($this->pickupOrders->count() > 0)<span class="tb-btn__badge">{{ $this->pickupOrders->count() }}</span>@endif
    </button>
    @endcan

    @can('cobrar mesas')
    <button type="button" class="tb-btn tb-btn--tables" :class="panels.mesas ? 'is-active' : ''"
        @click="panels.mesas = true; $wire.$refresh()" aria-label="Abrir cobro de mesas">
        <span class="tb-btn__icon"><i class="bx bx-table"></i></span>
        <span class="tb-btn__copy"><strong>Cobrar mesas</strong><small>Cuentas cerradas</small></span>
        @if ($this->mesasPendientes->count() > 0)<span class="tb-btn__badge">{{ $this->mesasPendientes->count() }}</span>@endif
    </button>

    <button type="button" class="tb-btn tb-btn--tracking" :class="panels.tracking ? 'is-active' : ''"
        @click="panels.tracking = true; $wire.openTableTracking()" aria-label="Abrir seguimiento operativo de mesas">
        <span class="tb-btn__icon"><i class="bx bx-radar"></i></span>
        <span class="tb-btn__copy"><strong>Comandas</strong><small>Cocina de mesas</small></span>
    </button>
    @endcan

    @can('reimprimir tickets')
    <button type="button" class="tb-btn tb-btn--reprint" :class="panels.reprint ? 'is-active' : ''"
        @click="panels.reprint = true; $wire.$refresh()" aria-label="Abrir reimpresión de tickets">
        <span class="tb-btn__icon"><i class="bx bx-printer"></i></span>
        <span class="tb-btn__copy"><strong>Reimprimir</strong><small>Cocina y cliente</small></span>
    </button>
    @endcan
</nav>
