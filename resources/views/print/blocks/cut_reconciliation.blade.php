@if(!empty($payload['cut']))
@php $cut = $payload['cut']; @endphp
<section class="ticket-cut ticket-cut-reconciliation">
    <strong class="ticket-section-title">CONCILIACIÓN DE CAJA</strong>
    <div class="ticket-row"><strong>Efectivo esperado</strong><strong>${{ number_format($cut['expected_cash'], 2) }}</strong></div>
    <div class="ticket-row"><span>Efectivo declarado</span><span>${{ number_format($cut['declared_cash'], 2) }}</span></div>
    <div class="ticket-row ticket-cut-difference"><strong>Diferencia</strong><strong>{{ $cut['difference'] >= 0 ? '+' : '−' }}${{ number_format(abs($cut['difference']), 2) }}</strong></div>
</section>
<hr>
@endif
