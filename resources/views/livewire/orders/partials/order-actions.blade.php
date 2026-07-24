<div class="orders-row-actions">
    <a href="{{ route('app.ordenes.show', $order) }}" class="orders-action orders-action--view"
        aria-label="Ver detalle de la orden {{ $order->display_folio }}" title="Ver detalle">
        <i class="bx bx-show" aria-hidden="true"></i><span>Ver</span>
    </a>
    @if($order->status !== 'cancelada' && $order->status !== 'pagada')
        @can('editar ordenes')
            <button type="button" class="orders-action" wire:click="openStatusModal({{ $order->id }})"
                aria-label="Cambiar estado de la orden {{ $order->display_folio }}" title="Cambiar estado">
                <i class="bx bx-transfer" aria-hidden="true"></i><span>Estado</span>
            </button>
        @endcan
        @can('cancelar ordenes')
            <button type="button" class="orders-action orders-action--warning" wire:click="openCancelModal({{ $order->id }})"
                aria-label="Cancelar la orden {{ $order->display_folio }}" title="Cancelar orden">
                <i class="bx bx-x-circle" aria-hidden="true"></i><span>Cancelar</span>
            </button>
        @endcan
    @endif
    @can('eliminar ordenes')
        <button type="button" class="orders-action orders-action--danger" wire:click="confirmDeleteOrder({{ $order->id }})"
            aria-label="Eliminar la orden {{ $order->display_folio }}" title="Eliminar orden">
            <i class="bx bx-trash" aria-hidden="true"></i><span>Eliminar</span>
        </button>
    @endcan
</div>
