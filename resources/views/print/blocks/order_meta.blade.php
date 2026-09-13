<section class="ticket-meta">
    @if(!empty($payload['is_cancelled']))<div class="ticket-row"><strong>ESTADO</strong><strong>ORDEN CANCELADA</strong></div>@endif
    @if(!empty($payload['folio']))<div class="ticket-row"><span>Folio</span><strong>#{{ $payload['folio'] }}</strong></div>@endif
    @if(!empty($payload['date']))<div class="ticket-row"><span>Fecha</span><span>{{ $payload['date'] }}</span></div>@endif
    @if(!empty($payload['table']))<div class="ticket-row"><span>Mesa</span><strong>{{ $payload['table'] }}</strong></div>@endif
    @if(!empty($payload['area']))<div class="ticket-row"><span>Área</span><span>{{ $payload['area'] }}</span></div>@endif
    @if(!empty($payload['customer']) && $payload['customer'] !== 'Cliente general')<div class="ticket-row"><span>Cliente</span><span>{{ $payload['customer'] }}</span></div>@endif
    @if(!empty($payload['served_by']))<div class="ticket-row"><span>Atendió</span><span>{{ $payload['served_by'] }}</span></div>@endif
    @if(!empty($payload['cashier']))<div class="ticket-row"><span>Cajero</span><span>{{ $payload['cashier'] }}</span></div>@endif
    @if(!empty($payload['cancellation_reason']))<div class="ticket-note"><strong>Motivo de cancelación:</strong> {{ $payload['cancellation_reason'] }}</div>@endif
    @if(!empty($payload['cancelled_at']))<div class="ticket-row"><span>Cancelada</span><span>{{ $payload['cancelled_at'] }}</span></div>@endif
    @if(!empty($payload['cancelled_by']))<div class="ticket-row"><span>Autorizó</span><span>{{ $payload['cancelled_by'] }}</span></div>@endif
    @if(!empty($payload['notes']))<div class="ticket-note">Nota: {{ $payload['notes'] }}</div>@endif
</section>
<hr>
