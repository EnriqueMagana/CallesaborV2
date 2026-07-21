@if(!empty($payload['cut']))
@php $cut = $payload['cut']; @endphp
<section class="ticket-cut ticket-cut-meta">
    <div class="ticket-row"><span>Folio</span><strong>{{ $cut['folio'] }}</strong></div>
    <div class="ticket-row"><span>Caja</span><strong>{{ $cut['register'] }}</strong></div>
    <div class="ticket-row"><span>Apertura</span><span>{{ $cut['opened_at'] }}</span></div>
    <div class="ticket-row"><span>Cierre</span><span>{{ $cut['closed_at'] }}</span></div>
    <div class="ticket-row"><span>Responsable</span><span>{{ $cut['cashier'] }}</span></div>
</section>
<hr>
@endif
