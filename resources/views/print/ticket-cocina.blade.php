<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Ticket Cocina #{{ $order->id }}</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: 'Courier New', monospace; font-size: 12px; width: 80mm; padding: 8px; }
  h2 { font-size: 16px; text-align: center; margin-bottom: 4px; }
  .center { text-align: center; }
  hr { border: none; border-top: 1px dashed #000; margin: 6px 0; }
  .item { margin-bottom: 6px; }
  .item-name { font-weight: bold; font-size: 13px; }
  .item-qty { float: right; font-weight: bold; font-size: 15px; }
  .sub { padding-left: 8px; font-size: 11px; }
  .area-title { font-size: 14px; font-weight: bold; text-align: center; background: #000; color: #fff; padding: 3px; margin-bottom: 6px; }
  @media print { body { width: auto; } }
</style>
</head>
<body>

@if(isset($printArea))
<div class="area-title">{{ strtoupper($printArea) }}</div>
@endif

<h2>COMANDA #{{ $order->id }}</h2>
<div class="center">{{ now()->format('d/m/Y H:i') }}</div>

<hr>

<div>
  @if($order->type === 'mesa' && $order->table_identifier)
  <strong>Mesa:</strong> {{ $order->table_identifier }}<br>
  @elseif($order->type === 'delivery')
  <strong>DELIVERY</strong><br>
  @elseif($order->type === 'pick_up')
  <strong>PICK-UP</strong>
  @if($order->customer_name) — {{ $order->customer_name }} @endif<br>
  @endif
  @if($order->notes) <em>Nota: {{ $order->notes }}</em><br> @endif
</div>

<hr>

@foreach($order->items->where('is_cancelled', false) as $item)
<div class="item">
  <span class="item-qty">x{{ $item->quantity }}</span>
  <span class="item-name">{{ strtoupper($item->product_name) }}</span>
  @foreach($item->addons as $addon)
  <div class="sub">+ {{ $addon->addon_name }}</div>
  @endforeach
  @foreach($item->ingredients as $ing)
  <div class="sub">• {{ $ing->ingredient_name }}@if($ing->quantity > 1) x{{ $ing->quantity }}@endif</div>
  @endforeach
  @if($item->notes)
  <div class="sub"><em>* {{ $item->notes }}</em></div>
  @endif
</div>
@endforeach

<hr>
<div class="center" style="font-size:10px;">— FIN DE COMANDA —</div>

<script>window.print();</script>
</body>
</html>
