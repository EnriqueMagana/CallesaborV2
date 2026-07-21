@if(!empty($payload['cut']['notes']))
<section class="ticket-cut ticket-cut-notes">
    <strong class="ticket-section-title">NOTAS DEL CIERRE</strong>
    <p>{{ $payload['cut']['notes'] }}</p>
</section>
<hr>
@endif
