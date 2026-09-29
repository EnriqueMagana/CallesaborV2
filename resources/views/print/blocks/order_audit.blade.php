@if(!empty($payload['order_audits']))
<section class="ticket-order-audit">
    <div class="ticket-row"><strong>AUDITORÍA DE MODIFICACIONES</strong><span></span></div>
    @foreach($payload['order_audits'] as $audit)
        <div class="ticket-audit-entry">
            <div class="ticket-row"><strong>{{ $audit['type'] }}</strong><span>{{ $audit['approved_at'] }}</span></div>
            <div class="ticket-row"><span>Motivo</span><span>{{ $audit['reason'] }}</span></div>
            <div class="ticket-row"><span>Solicitó</span><span>{{ $audit['requested_by'] ?: 'Sin registro' }}</span></div>
            <div class="ticket-row"><span>Aprobó</span><span>{{ $audit['approved_by'] ?: 'Sin registro' }}</span></div>
            <div class="ticket-row"><span>Total anterior</span><span>${{ number_format($audit['original_total'], 2) }}</span></div>
            <div class="ticket-row"><span>Total actualizado</span><strong>${{ number_format($audit['proposed_total'], 2) }}</strong></div>
            @foreach($audit['items'] as $item)
                <div class="ticket-audit-item">
                    <strong>{{ $item['action_label'] }} · {{ $item['product'] }}</strong>
                    <small>{{ $item['from_quantity'] }} → {{ $item['to_quantity'] }} · ${{ number_format($item['before_subtotal'], 2) }} → ${{ number_format($item['after_subtotal'], 2) }}</small>
                </div>
            @endforeach
            @if($audit['pending_balance'] > 0.009)
                <div class="ticket-row"><span>Diferencia generada</span><strong>${{ number_format($audit['pending_balance'], 2) }}</strong></div>
            @endif
        </div>
    @endforeach
</section>
<hr>
@endif
