@if(!empty($payload['cut']))
@php $cut = $payload['cut']; @endphp
<section class="ticket-cut">
    <strong class="ticket-section-title">MOVIMIENTOS DE EFECTIVO</strong>
    <div class="ticket-row"><span>Fondo inicial</span><span>${{ number_format($cut['initial_amount'], 2) }}</span></div>
    <div class="ticket-row"><span>+ Ventas en efectivo</span><span>${{ number_format($cut['cash_sales'], 2) }}</span></div>
    <div class="ticket-row"><span>+ Ingresos adicionales</span><span>${{ number_format($cut['cash_incomes'] ?? 0, 2) }}</span></div>
    <div class="ticket-row"><span>− Gastos en efectivo</span><span>−${{ number_format($cut['cash_expenses'], 2) }}</span></div>
</section>
<hr>
@endif
