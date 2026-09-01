@if(!empty($payload['payments']))
<section class="ticket-payments">
    <div class="ticket-row"><strong>FORMAS DE PAGO</strong><span></span></div>
    @foreach($payload['payments'] as $payment)
        <div class="ticket-row"><span>{{ $payment['label'] }}</span><span>${{ number_format($payment['amount'], 2) }}</span></div>
        @if(!empty($payment['card_last4']))<div class="ticket-row"><small>Terminación {{ $payment['card_last4'] }}</small><span></span></div>@endif
        @if(!empty($payment['reference']))<div class="ticket-row"><small>Referencia {{ $payment['reference'] }}</small><span></span></div>@endif
        @if(($payment['change'] ?? 0) > 0)<div class="ticket-row"><span>Cambio</span><span>${{ number_format($payment['change'], 2) }}</span></div>@endif
    @endforeach
    <div class="ticket-row"><strong>Total pagado</strong><strong>${{ number_format($payload['paid_total'] ?? collect($payload['payments'])->sum('amount'), 2) }}</strong></div>
    @if(($payload['balance'] ?? 0) > 0.009)
        <div class="ticket-row"><strong>Saldo pendiente</strong><strong>${{ number_format($payload['balance'], 2) }}</strong></div>
    @endif
</section>
<hr>
@endif
