<nav class="pos-toolbar-bottom" aria-label="Áreas del punto de venta">
    @can('cerrar ordenes')
    <button type="button" class="tb-btn tb-btn--window" :class="panels.pickup ? 'is-active' : ''"
        @click="showOnlyPanel('pickup'); $wire.openPickupPanel()" data-pos-panel="pickup" aria-keyshortcuts="F6"
        aria-label="Abrir pedidos no pagados" title="Abrir pedidos por cobrar (F6)">
        <span class="tb-btn__icon"><i class="bx bx-receipt"></i></span>
        <span class="tb-btn__copy"><strong>Por cobrar</strong><small>Ventanilla y recoger</small></span>
        @if ($this->toolbarPendingCounts['pickup'] > 0)
            <span class="tb-btn__badge" aria-label="{{ $this->toolbarPendingCounts['pickup'] }} pedidos pendientes">{{ $this->toolbarPendingCounts['pickup'] }}</span>
        @endif
        <kbd class="tb-btn__shortcut" aria-hidden="true">F6</kbd>
    </button>
    @endcan

    @canany(['cobrar mesas', 'editar ordenes', 'reimprimir tickets'])
    <button type="button" class="tb-btn tb-btn--workspace" :class="panels.tables ? 'is-active' : ''"
        @click="showOnlyPanel('tables'); $wire.openTableWorkspace()" data-pos-panel="tables" aria-keyshortcuts="F7"
        aria-label="Abrir mesas y comandas" title="Abrir mesas y comandas (F7)">
        <span class="tb-btn__icon"><i class="bx bx-dish"></i></span>
        <span class="tb-btn__copy"><strong>Mesas y comandas</strong><small>Seguimiento y cobro</small></span>
        @if ($this->toolbarPendingCounts['tables'] > 0)
            <span class="tb-btn__badge" aria-label="{{ $this->toolbarPendingCounts['tables'] }} servicios de mesa pendientes">{{ $this->toolbarPendingCounts['tables'] }}</span>
        @endif
        <kbd class="tb-btn__shortcut" aria-hidden="true">F7</kbd>
    </button>
    @endcanany

    @can('cerrar ordenes')
    <button type="button" class="tb-btn tb-btn--delivery" :class="panels.delivery ? 'is-active' : ''"
        @click="showOnlyPanel('delivery'); $wire.openDeliveryPanel()" data-pos-panel="delivery" aria-keyshortcuts="F8"
        aria-label="Abrir pedidos para entrega a domicilio" title="Abrir Delivery (F8)">
        <span class="tb-btn__icon"><i class="bx bx-cycling"></i></span>
        <span class="tb-btn__copy"><strong>Delivery</strong><small>Contra entrega</small></span>
        @if ($this->toolbarPendingCounts['delivery'] > 0)
            <span class="tb-btn__badge" aria-label="{{ $this->toolbarPendingCounts['delivery'] }} entregas nuevas sin enviar a cocina">{{ $this->toolbarPendingCounts['delivery'] }}</span>
        @endif
        <kbd class="tb-btn__shortcut" aria-hidden="true">F8</kbd>
    </button>
    @endcan

    @can('reimprimir tickets')
    <button type="button" class="tb-btn tb-btn--reprint" :class="panels.reprint ? 'is-active' : ''"
        @click="showOnlyPanel('reprint'); $wire.openReprintPanel()" data-pos-panel="reprint" aria-keyshortcuts="F9"
        aria-label="Abrir reimpresión de tickets" title="Abrir reimpresión de tickets (F9)">
        <span class="tb-btn__icon"><i class="bx bx-printer"></i></span>
        <span class="tb-btn__copy"><strong>Reimprimir</strong><small>Cocina y cliente</small></span>
        <kbd class="tb-btn__shortcut" aria-hidden="true">F9</kbd>
    </button>
    @endcan
    @canany(['registrar movimientos de caja', 'registrar gastos', 'registrar salida de insumos', 'ajustar inventario'])
    <button type="button" class="tb-btn tb-btn--operations"
        @click="closeAllPanels(); showCart = false; showMore = false"
        wire:click="openOperationsModal('{{ auth()->user()->can('registrar movimientos de caja') || auth()->user()->can('registrar gastos') ? 'expense' : 'inventory_out' }}')"
        data-pos-operations aria-keyshortcuts="F11"
        aria-label="Registrar movimientos de caja o salida de insumos" title="Abrir movimientos (F11)">
        <span class="tb-btn__icon"><i class="bx bx-transfer-alt"></i></span>
        <span class="tb-btn__copy"><strong>Movimientos</strong><small>Caja e insumos</small></span>
        <kbd class="tb-btn__shortcut" aria-hidden="true">F11</kbd>
    </button>
    @endcanany
</nav>
