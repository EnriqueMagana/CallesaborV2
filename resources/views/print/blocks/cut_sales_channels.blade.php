@if(!empty($payload['cut']['channels']))
<section class="ticket-cut">
    <strong class="ticket-section-title">VENTAS POR CANAL</strong>
    @foreach($payload['cut']['channels'] as $channel)
        <div class="ticket-cut-channel">
            <div class="ticket-row"><strong>{{ $channel['label'] }}</strong><strong>${{ number_format($channel['total'], 2) }}</strong></div>
            <div class="ticket-cut-breakdown">Efectivo ${{ number_format($channel['cash'], 2) }} · Tarjeta ${{ number_format($channel['card'], 2) }} · Transfer. ${{ number_format($channel['transfer'], 2) }}</div>
        </div>
    @endforeach
    <div class="ticket-row ticket-cut-subtotal"><strong>Total de ventas</strong><strong>${{ number_format($payload['cut']['sales_total'], 2) }}</strong></div>
</section>
<hr>
@endif
