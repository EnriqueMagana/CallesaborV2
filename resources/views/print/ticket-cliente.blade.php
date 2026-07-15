<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Cuenta #{{ $order->id }}</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: 'Courier New', monospace; font-size: 12px; width: 80mm; padding: 8px; }
  h2 { font-size: 15px; text-align: center; margin-bottom: 2px; }
  .center { text-align: center; }
  hr { border: none; border-top: 1px dashed #000; margin: 6px 0; }
  .row { display: flex; justify-content: space-between; margin-bottom: 2px; }
  .item-name { font-weight: bold; }
  .sub { padding-left: 8px; font-size: 11px; color: #333; }
  .total-row { font-size: 15px; font-weight: bold; }
  @media print { body { width: auto; } }
</style>
</head>
<body>

<h2>CALLE SABOR</h2>
<div class="center" style="font-size:11px;">CUENTA DEL CLIENTE</div>
<hr>

<div class="row"><span>Mesa:</span><span>{{ $order->table_identifier ?? '—' }}</span></div>
@if($order->display_name !== 'Cliente general')
<div class="row"><span>Cliente:</span><span>{{ $order->display_name }}</span></div>
@endif
<div class="row"><span>Fecha:</span><span>{{ now()->format('d/m/Y H:i') }}</span></div>

<hr>

@foreach($order->items->where('is_cancelled', false) as $item)
<div>
  <div class="row">
    <span class="item-name">{{ $item->product_name }} x{{ $item->quantity }}</span>
    <span>${{ number_format($item->subtotal, 2) }}</span>
  </div>
  @foreach($item->addons as $addon)
  <div class="sub row">
    <span>+ {{ $addon->addon_name }}</span>
    @if($addon->extra_price > 0)<span>+${{ number_format($addon->extra_price * $addon->quantity,2) }}</span>@endif
  </div>
  @endforeach
  @foreach($item->ingredients as $ing)
  <div class="sub row">
    <span>• {{ $ing->ingredient_name }}@if($ing->quantity > 1) x{{ $ing->quantity }}@endif</span>
    @if($ing->extra_price > 0)<span>+${{ number_format($ing->extra_price * $ing->quantity,2) }}</span>@endif
  </div>
  @endforeach
</div>
@endforeach

<hr>
<div class="row total-row"><span>TOTAL A PAGAR</span><span>${{ number_format($order->total, 2) }}</span></div>
<hr>
<div class="center" style="font-size:10px;">Por favor pase a caja para realizar su pago.</div>
<div class="center" style="font-size:10px; margin-top:4px;">¡Gracias por visitarnos!</div>

<script>window.print();</script>
</body>
</html>
