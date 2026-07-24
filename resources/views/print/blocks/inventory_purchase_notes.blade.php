@if(!empty($payload['notes']))
    <hr>
    <section class="ticket-purchase-notes">
        <strong class="ticket-section-title">INDICACIONES</strong>
        <p>{{ $payload['notes'] }}</p>
    </section>
@endif
