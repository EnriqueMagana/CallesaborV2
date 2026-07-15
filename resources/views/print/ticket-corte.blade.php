<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Corte {{ $cut->folio }}</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: 'Courier New', monospace; font-size: 12px; width: 80mm; padding: 8px; }
  h2 { font-size: 15px; text-align: center; margin-bottom: 2px; }
  .center { text-align: center; }
  hr { border: none; border-top: 1px dashed #000; margin: 6px 0; }
  .row { display: flex; justify-content: space-between; margin-bottom: 2px; }
  .bold { font-weight: bold; }
  .section { font-size: 10px; text-transform: uppercase; letter-spacing: .5px; margin: 6px 0 2px; }
  .indent { padding-left: 8px; }
  .info { font-size: 10px; color: #555; }
  .big { font-size: 14px; font-weight: bold; }
  @media print { body { width: auto; } }
</style>
</head>
<body>

<h2>CALLE SABOR</h2>
<div class="center" style="font-size:10px">Corte de caja</div>
<hr>

<div class="row"><span>Folio:</span><span class="bold">{{ $cut->folio }}</span></div>
<div class="row"><span>Caja:</span><span>{{ $cut->cashRegister->name }}</span></div>
<div class="row"><span>Apertura:</span><span>{{ $cut->cashRegister->opened_at->format('d/m/Y g:i A') }}</span></div>
<div class="row"><span>Cierre:</span><span>{{ $cut->generated_at->format('d/m/Y g:i A') }}</span></div>
<div class="row"><span>Cajero:</span><span>{{ $cut->generator->name }}</span></div>

<hr>

<div class="section">Ventas del turno</div>
<div class="row">
  <span></span>
  <span style="font-size:9px;text-decoration:underline">Efectivo / Tarjeta / Transfer.</span>
</div>

<div class="row indent">
  <span>Ventanilla</span>
  <span>${{ number_format($cut->v_efectivo,2) }} / ${{ number_format($cut->v_tarjeta,2) }} / ${{ number_format($cut->v_transfer,2) }}</span>
</div>
<div class="row indent">
  <span>Mesas</span>
  <span>${{ number_format($cut->m_efectivo,2) }} / ${{ number_format($cut->m_tarjeta,2) }} / ${{ number_format($cut->m_transfer,2) }}</span>
</div>
<div class="row indent">
  <span>Delivery</span>
  <span>${{ number_format($cut->d_efectivo,2) }} / ${{ number_format($cut->d_tarjeta,2) }} / ${{ number_format($cut->d_transfer,2) }}</span>
</div>

<hr>

<div class="section">Movimientos de efectivo</div>
<div class="row indent"><span>Fondo inicial</span><span>${{ number_format($cut->initial_amount,2) }}</span></div>
<div class="row indent"><span>+ Ventas efectivo</span><span>${{ number_format($cut->total_cash_in,2) }}</span></div>
<div class="row indent"><span>- Gastos efectivo</span><span>-${{ number_format($cut->total_expenses_cash,2) }}</span></div>
<hr>
<div class="row bold"><span>Efectivo esperado</span><span>${{ number_format($cut->expected_cash,2) }}</span></div>
<div class="row bold"><span>Efectivo declarado</span><span>${{ number_format($cut->declared_cash,2) }}</span></div>
<div class="row bold {{ $cut->difference >= 0 ? '' : '' }}">
  <span>Diferencia</span>
  <span>{{ $cut->difference >= 0 ? '+' : '' }}${{ number_format($cut->difference,2) }}</span>
</div>

@if($cut->cashRegister->closing_notes)
<hr>
<div class="section">Notas</div>
<div style="font-size:10px">{{ $cut->cashRegister->closing_notes }}</div>
@endif

<hr>
<div class="center info">Generado el {{ $cut->generated_at->format('d/m/Y g:i A') }}</div>

<script>window.onload=()=>window.print()</script>
</body>
</html>
