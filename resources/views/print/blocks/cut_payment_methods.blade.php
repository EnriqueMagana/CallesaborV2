@if(!empty($payload['cut']['payment_methods']))
<section class="ticket-cut">
    <strong class="ticket-section-title">RESUMEN POR FORMA DE PAGO</strong>
    @foreach($payload['cut']['payment_methods'] as $method)
        <div class="ticket-row ticket-cut-payment-row">
            <span>{{ $method['label'] }}</span>
            <strong>${{ number_format($method['amount'], 2) }}</strong>
        </div>
    @endforeach
</section>
<hr>
@endif
