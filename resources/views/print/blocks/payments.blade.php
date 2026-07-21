@if(!empty($payload['payments']))
<section class="ticket-payments">
    @foreach($payload['payments'] as $payment)
        <div class="ticket-row"><span>{{ $payment['label'] }}</span><span>${{ number_format($payment['amount'], 2) }}</span></div>
        @if(($payment['change'] ?? 0) > 0)<div class="ticket-row"><span>Cambio</span><span>${{ number_format($payment['change'], 2) }}</span></div>@endif
    @endforeach
</section>
<hr>
@endif
