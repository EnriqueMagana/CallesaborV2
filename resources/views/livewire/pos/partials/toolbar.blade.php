<nav class="pos-toolbar-bottom" aria-label="Áreas del punto de venta">
    @can('cerrar ordenes')
    <button type="button" class="tb-btn tb-btn--window" :class="panels.pickup ? 'is-active' : ''"
        @click="panels.pickup = true; $wire.$refresh()" aria-label="Abrir pedidos no pagados">
        <span class="tb-btn__icon"><i class="bx bx-receipt"></i></span>
        <span class="tb-btn__copy"><strong>Por cobrar</strong><small>Ventanilla y recoger</small></span>
        @if ($this->toolbarPendingCounts['pickup'] > 0)
            <span class="tb-btn__badge" aria-label="{{ $this->toolbarPendingCounts['pickup'] }} pedidos pendientes">{{ $this->toolbarPendingCounts['pickup'] }}</span>
        @endif
    </button>
    @endcan

    @canany(['cobrar mesas', 'editar ordenes', 'reimprimir tickets'])
    <button type="button" class="tb-btn tb-btn--workspace" :class="panels.tables ? 'is-active' : ''"
        @click="panels.tables = true; $wire.openTableWorkspace()" aria-label="Abrir mesas y comandas">
        <span class="tb-btn__icon"><i class="bx bx-dish"></i></span>
        <span class="tb-btn__copy"><strong>Mesas y comandas</strong><small>Seguimiento y cobro</small></span>
        @if ($this->toolbarPendingCounts['tables'] > 0)
            <span class="tb-btn__badge" aria-label="{{ $this->toolbarPendingCounts['tables'] }} servicios de mesa pendientes">{{ $this->toolbarPendingCounts['tables'] }}</span>
        @endif
    </button>
    @endcanany

    @can('cerrar ordenes')
    <button type="button" class="tb-btn tb-btn--delivery" :class="panels.delivery ? 'is-active' : ''"
        @click="panels.delivery = true; $wire.openDeliveryPanel()" aria-label="Abrir pedidos para entrega a domicilio">
        <span class="tb-btn__icon"><i class="bx bx-cycling"></i></span>
        <span class="tb-btn__copy"><strong>Delivery</strong><small>Contra entrega</small></span>
        @if ($this->toolbarPendingCounts['delivery'] > 0)
            <span class="tb-btn__badge" aria-label="{{ $this->toolbarPendingCounts['delivery'] }} entregas pendientes">{{ $this->toolbarPendingCounts['delivery'] }}</span>
        @endif
    </button>
    @endcan

    @can('reimprimir tickets')
    <button type="button" class="tb-btn tb-btn--reprint" :class="panels.reprint ? 'is-active' : ''"
        @click="panels.reprint = true; $wire.$refresh()" aria-label="Abrir reimpresión de tickets">
        <span class="tb-btn__icon"><i class="bx bx-printer"></i></span>
        <span class="tb-btn__copy"><strong>Reimprimir</strong><small>Cocina y cliente</small></span>
    </button>
    @endcan
    @canany(['registrar movimientos de caja', 'registrar gastos', 'registrar salida de insumos', 'ajustar inventario'])
    <button type="button" class="tb-btn tb-btn--operations"
        wire:click="openOperationsModal('{{ auth()->user()->can('registrar movimientos de caja') || auth()->user()->can('registrar gastos') ? 'expense' : 'inventory_out' }}')"
        aria-label="Registrar movimientos de caja o salida de insumos">
        <span class="tb-btn__icon"><i class="bx bx-transfer-alt"></i></span>
        <span class="tb-btn__copy"><strong>Movimientos</strong><small>Caja e insumos</small></span>
    </button>
    @endcanany
</nav>
