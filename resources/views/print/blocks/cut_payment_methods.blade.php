@if(!empty($payload['cut']['payment_methods']))
<section class="ticket-cut">
    <strong class="ticket-section-title">RESUMEN POR FORMA DE PAGO</strong>
    @foreach($payload['cut']['payment_methods'] as $method)
        <div class="ticket-row"><span>{{ $method['label'] }}</span><span>${{ number_format($method['amount'], 2) }}</span></div>
    @endforeach
</section>
<hr>
@endif
