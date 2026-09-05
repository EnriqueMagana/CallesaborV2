<div class="orders-row-actions">
    @php
        $user = auth()->user();
        $activeUnpaid = in_array($order->status, ['pendiente', 'en_preparacion', 'lista'], true) && $order->payments->isEmpty();
        $paid = $order->status === 'pagada' && $order->payments->isNotEmpty();
        $canRequestOrderChange = (($activeUnpaid || $paid) && ($user?->can('solicitar cancelacion de ordenes') || $user?->can('solicitar modificacion de ordenes')))
            || ($order->type === 'delivery' && $paid && $order->payments->count() === 1 && $order->refunds->isEmpty() && $user?->can('solicitar cambio de metodo de pago'))
            || ($order->type === 'delivery' && in_array($order->status, ['pendiente', 'en_preparacion', 'lista', 'pagada'], true) && $order->deliveryAssignment?->status !== 'entregado' && $user?->can('solicitar cambio de direccion'));
    @endphp
    <a href="{{ route('app.ordenes.show', $order) }}" class="orders-action orders-action--view"
        aria-label="Ver detalle de la orden {{ $order->display_folio }}" title="Ver detalle">
        <i class="bx bx-show" aria-hidden="true"></i><span>Ver</span>
    </a>

    @if($order->changeRequests->isNotEmpty())
        <span class="orders-request-pending" title="Solicitud en revisión"><i class="bx bx-time-five"></i>En revisión</span>
    @elseif(!in_array($order->status, ['cancelada', 'entregada'], true))
        @if($canRequestOrderChange)
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
