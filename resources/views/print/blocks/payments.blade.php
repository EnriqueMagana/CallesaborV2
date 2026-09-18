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
    @if(!empty($payload['refunds']))
        @foreach($payload['refunds'] as $refund)
            @foreach($refund['allocations'] as $method => $amount)
                <div class="ticket-row"><span>Reembolso · {{ $method }}</span><strong>-${{ number_format($amount, 2) }}</strong></div>
            @endforeach
            @if(!empty($refund['reference']))<div class="ticket-row"><small>Referencia {{ $refund['reference'] }}</small><span></span></div>@endif
        @endforeach
        <div class="ticket-row"><strong>Pago neto</strong><strong>${{ number_format($payload['net_paid'] ?? 0, 2) }}</strong></div>
    @endif
    @if(($payload['balance'] ?? 0) > 0.009)
        <div class="ticket-row"><strong>{{ $payload['balance_label'] ?? 'Saldo pendiente' }}</strong><strong>${{ number_format($payload['balance'], 2) }}</strong></div>
    @endif
</section>
<hr>
@elseif(($payload['balance_label'] ?? null) === 'Por cobrar contra entrega' && ($payload['balance'] ?? 0) > 0.009)
{{-- Contra entrega sin cobros aún: el repartidor necesita ver el importe exacto. --}}
<section class="ticket-payments">
    <div class="ticket-row"><strong>{{ $payload['balance_label'] }}</strong><strong>${{ number_format($payload['balance'], 2) }}</strong></div>
</section>
<hr>
@endif
