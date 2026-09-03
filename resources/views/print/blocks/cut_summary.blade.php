@if(!empty($payload['cut']))
@php $cut = $payload['cut']; @endphp
<section class="ticket-cut">
    <div class="ticket-row"><span>Caja</span><strong>{{ $cut->cashRegister->name }}</strong></div>
    <div class="ticket-row"><span>Apertura</span><span>{{ $cut->cashRegister->opened_at->copy()->timezone(config('app.business_timezone'))->format('d/m/Y h:i A') }}</span></div>
    <div class="ticket-row"><span>Cierre</span><span>{{ $cut->generated_at->copy()->timezone(config('app.business_timezone'))->format('d/m/Y h:i A') }}</span></div>
    <div class="ticket-row"><span>Cajero</span><span>{{ $cut->generator->name }}</span></div>
    <hr>
    <strong>VENTAS DEL TURNO</strong>
    <div class="ticket-row"><span>Ventanilla</span><span>${{ number_format($cut->v_efectivo + $cut->v_tarjeta + $cut->v_transfer, 2) }}</span></div>
    <div class="ticket-row"><span>Mesas</span><span>${{ number_format($cut->m_efectivo + $cut->m_tarjeta + $cut->m_transfer, 2) }}</span></div>
    <div class="ticket-row"><span>Delivery</span><span>${{ number_format($cut->d_efectivo + $cut->d_tarjeta + $cut->d_transfer, 2) }}</span></div>
    <hr>
    <div class="ticket-row"><span>Fondo inicial</span><span>${{ number_format($cut->initial_amount, 2) }}</span></div>
    <div class="ticket-row"><span>Ventas efectivo</span><span>${{ number_format($cut->total_cash_in, 2) }}</span></div>
    <div class="ticket-row"><span>Ingresos adicionales</span><span>+${{ number_format($cut->total_cash_income ?? 0, 2) }}</span></div>
    <div class="ticket-row"><span>Gastos efectivo</span><span>-${{ number_format($cut->total_expenses_cash, 2) }}</span></div>
    <div class="ticket-row ticket-total"><strong>Efectivo esperado</strong><strong>${{ number_format($cut->expected_cash, 2) }}</strong></div>
    <div class="ticket-row"><span>Efectivo declarado</span><span>${{ number_format($cut->declared_cash, 2) }}</span></div>
    <div class="ticket-row"><strong>Diferencia</strong><strong>{{ $cut->difference >= 0 ? '+' : '' }}${{ number_format($cut->difference, 2) }}</strong></div>
</section>
<hr>
@endif
