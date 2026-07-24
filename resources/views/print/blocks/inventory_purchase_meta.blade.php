<section class="ticket-purchase-meta">
    <div class="ticket-purchase-folio">
        <span>FOLIO DE COMPRA Y RECEPCIÓN</span>
        <strong>{{ $payload['folio'] ?? 'Sin folio' }}</strong>
    </div>
    <div class="ticket-row"><span>Preparó</span><strong>{{ $payload['requested_by'] ?? 'Sin asignar' }}</strong></div>
    <div class="ticket-row"><span>Fecha</span><span>{{ $payload['date'] ?? '' }}</span></div>
    <div class="ticket-row"><span>Partidas</span><span>{{ count($payload['items'] ?? []) }}</span></div>
    @if(($payload['status'] ?? 'pending') === 'received')
        <div class="ticket-row"><span>Recibió</span><strong>{{ $payload['received_by'] ?? 'Sin asignar' }}</strong></div>
        <div class="ticket-row"><span>Recepción</span><span>{{ $payload['received_at'] ?? '' }}</span></div>
    @endif
</section>
<hr>
