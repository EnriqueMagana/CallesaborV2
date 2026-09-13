<div class="sales-audit-order-detail">
    <div class="sales-audit-order-detail__facts">
        <div><small>Caja / turno</small><strong>{{ $order->cashRegister?->name ?? 'Sin caja asociada' }}</strong><span>{{ $order->cashRegister?->is_open ? 'Turno abierto' : 'Turno cerrado' }}</span></div>
        <div><small>Responsable</small><strong>{{ $order->seller?->name ?? 'Sin responsable' }}</strong><span>{{ \App\Support\BusinessTime::format($order->created_at, 'd/m/Y g:i:s A') }}</span></div>
        <div><small>Cobro / cierre</small><strong>{{ \App\Support\BusinessTime::format($order->paid_at ?? $order->accounted_at, 'd/m/Y g:i:s A', 'Sin cierre') }}</strong><span>{{ $this->durationLabel($order) }} de ciclo</span></div>
        @if($order->type === 'mesa')
            <div><small>Servicio de mesa</small><strong>{{ $order->mesaService?->service_label ?? $order->mesa?->display_name ?? $order->table_identifier ?? 'Sin identificar' }}</strong><span>{{ \App\Support\BusinessTime::format($order->mesaService?->opened_at, 'g:i A', 'Sin apertura') }} → {{ \App\Support\BusinessTime::format($order->mesaService?->closed_at, 'g:i A', 'En curso') }}</span></div>
        @elseif($order->type === 'delivery')
            <div><small>Entrega</small><strong>{{ $order->deliveryAssignment?->driver?->name ?? 'Sin repartidor' }}</strong><span>{{ $order->delivery_method_label }} · {{ \App\Support\BusinessTime::format($order->deliveryAssignment?->delivered_at, 'g:i A', 'Sin entrega') }}</span></div>
        @else
            <div><small>Origen</small><strong>{{ $order->type_label }}</strong><span>{{ $order->table_identifier ?: 'Venta directa' }}</span></div>
        @endif
        @if($this->canViewFinancials)
            <div><small>Pagos</small><strong>{{ $order->payments->map(fn($payment) => $payment->method_label.' $'.number_format($payment->amount, 2))->join(' · ') ?: 'Sin pagos registrados' }}</strong><span>Subtotal &#36;{{ number_format($order->subtotal, 2) }} · Descuento &#36;{{ number_format($discountTotal, 2) }}</span></div>
        @endif
        @if($order->status === 'cancelada')
            <div class="is-danger"><small>Cancelación</small><strong>{{ $order->cancelledBy?->name ?? 'Usuario no disponible' }}</strong><span>{{ $order->cancellation_reason ?: 'Sin motivo registrado' }}</span></div>
        @endif
    </div>
    <div class="sales-audit-order-detail__items">
        <h3>Partidas del pedido</h3>
        <div class="sales-audit-item-list">
            @forelse($order->items as $item)
                <div class="{{ $item->is_cancelled ? 'is-cancelled' : '' }}"><span><strong>{{ $item->quantity }} × {{ $item->product_name }}</strong><small>{{ $item->is_cancelled ? 'Partida cancelada' : ($item->notes ?: 'Sin notas') }}</small></span>@if($this->canViewFinancials)<b>&#36;{{ number_format((float) $item->subtotal - (float) $item->promotion_discount - (float) $item->discount_amount, 2) }}</b>@endif</div>
            @empty
                <p>Esta orden no tiene partidas registradas.</p>
            @endforelse
        </div>
    </div>
    @can('ver ordenes')
        <a href="{{ route('app.ordenes.show', $order) }}" class="btn btn-sm btn-outline-primary"><i class="bx bx-show" aria-hidden="true"></i> Abrir orden completa</a>
    @endcan
</div>
