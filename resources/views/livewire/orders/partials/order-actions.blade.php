<div class="orders-row-actions">
    <a href="{{ route('app.ordenes.show', $order) }}" class="orders-action orders-action--view"
        aria-label="Ver detalle de la orden {{ $order->display_folio }}" title="Ver detalle">
        <i class="bx bx-show" aria-hidden="true"></i><span>Ver</span>
    </a>

    @if($order->changeRequests->isNotEmpty())
        <span class="orders-request-pending" title="Solicitud en revisión"><i class="bx bx-time-five"></i>En revisión</span>
    @elseif(!in_array($order->status, ['cancelada', 'entregada'], true))
        @if(auth()->user()?->can('solicitar modificacion de ordenes') || auth()->user()?->can('solicitar cancelacion de ordenes'))
            <a class="orders-action orders-action--warning" href="{{ route('app.ordenes.solicitud', ['order' => $order, 'source' => 'list']) }}"
                aria-label="Iniciar solicitud de cambio para la orden {{ $order->display_folio }}" title="Solicitar cambio">
                <i class="bx bx-git-compare" aria-hidden="true"></i><span>Solicitar cambio</span>
            </a>
        @endif
    @endif

    @if(!in_array($order->status, ['cancelada', 'pagada'], true))
        @can('editar ordenes')
            <button type="button" class="orders-action" wire:click="openStatusModal({{ $order->id }})"
                aria-label="Cambiar estado de la orden {{ $order->display_folio }}" title="Cambiar estado">
                <i class="bx bx-transfer" aria-hidden="true"></i><span>Estado</span>
            </button>
        @endcan
    @endif
</div>
