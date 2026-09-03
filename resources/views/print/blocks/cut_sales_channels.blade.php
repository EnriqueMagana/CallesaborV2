@if(!empty($payload['cut']['channels']))
<section class="ticket-cut">
    <strong class="ticket-section-title">VENTAS POR CANAL</strong>
    @foreach($payload['cut']['channels'] as $channel)
        <div class="ticket-cut-channel">
            <div class="ticket-row ticket-cut-channel-total">
                <strong>{{ $channel['label'] }}</strong>
                <strong>${{ number_format($channel['total'], 2) }}</strong>
            </div>
            <div class="ticket-cut-breakdown" aria-label="Formas de pago de {{ $channel['label'] }}">
                <div class="ticket-row ticket-cut-method-row">
                    <span>Efectivo</span>
                    <span>${{ number_format($channel['cash'], 2) }}</span>
                </div>
                <div class="ticket-row ticket-cut-method-row">
                    <span>Tarjeta</span>
                    <span>${{ number_format($channel['card'], 2) }}</span>
                </div>
                <div class="ticket-row ticket-cut-method-row">
                    <span>Transferencia</span>
                    <span>${{ number_format($channel['transfer'], 2) }}</span>
                </div>
            </div>
        </div>
    @endforeach
    <div class="ticket-row ticket-cut-subtotal"><strong>Total de ventas</strong><strong>${{ number_format($payload['cut']['sales_total'], 2) }}</strong></div>
</section>
<hr>
@endif
