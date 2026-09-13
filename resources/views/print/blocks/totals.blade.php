@if(($payload['refund_total'] ?? 0) > 0.009)
    <section class="ticket-total">
        <div class="ticket-row"><span>Total original</span><span>${{ number_format($payload['original_total'] ?? 0, 2) }}</span></div>
        <div class="ticket-row"><strong>REEMBOLSO</strong><strong>-${{ number_format($payload['refund_total'], 2) }}</strong></div>
        <div class="ticket-row"><strong>TOTAL VIGENTE</strong><strong>${{ number_format($payload['total'] ?? 0, 2) }}</strong></div>
    </section>
@else
    <section class="ticket-total ticket-row"><strong>TOTAL</strong><strong>${{ number_format($payload['total'] ?? 0, 2) }}</strong></section>
@endif
<hr>
