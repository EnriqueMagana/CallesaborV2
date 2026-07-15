<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Delivery #{{ $order->id }}</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: 'Courier New', monospace; font-size: 12px; width: 80mm; padding: 8px; }
  h2 { font-size: 15px; text-align: center; margin-bottom: 2px; }
  .center { text-align: center; }
  hr { border: none; border-top: 1px dashed #000; margin: 6px 0; }
  .row { display: flex; justify-content: space-between; margin-bottom: 2px; }
  .item-name { font-weight: bold; }
  .sub { padding-left: 8px; font-size: 11px; }
  .total-row { font-size: 14px; font-weight: bold; }
  .delivery-box { border: 2px solid #000; padding: 5px; margin-bottom: 6px; }
  .delivery-box h3 { font-size: 13px; margin-bottom: 3px; }
  @media print { body { width: auto; } }
</style>
</head>
<body>

<h2>CALLE SABOR</h2>
<div class="center" style="font-size:11px; font-weight:bold;">🏍 DELIVERY</div>
<hr>

<div class="row"><span>Pedido:</span><span>#{{ $order->id }}</span></div>
<div class="row"><span>Fecha:</span><span>{{ $order->created_at->format('d/m/Y H:i') }}</span></div>

<hr>

<div class="delivery-box">
  <h3>DATOS DE ENTREGA</h3>
  <div><strong>Cliente:</strong> {{ $order->display_name }}</div>
  @if($order->customer_phone)
  <div><strong>Tel:</strong> {{ $order->customer_phone }}</div>
  @endif
  @if($order->customerRelation?->address ?? $order->customer?->address)
  <div><strong>Dirección:</strong> {{ $order->customer?->address }}</div>
  @endif
  @if($order->customer?->references)
  <div><strong>Ref:</strong> {{ $order->customer->references }}</div>
  @endif
  <div><strong>Cobro:</strong> {{ $order->delivery_method_label }}</div>
</div>

@foreach($order->items->where('is_cancelled', false) as $item)
<div>
  <div class="row">
    <span class="item-name">{{ $item->product_name }} x{{ $item->quantity }}</span>
    <span>${{ number_format($item->subtotal, 2) }}</span>
  </div>
  @foreach($item->addons as $addon)
  <div class="sub row">
    <span>+ {{ $addon->addon_name }}</span>
    @if($addon->extra_price > 0)<span>+${{ number_format($addon->extra_price,2) }}</span>@endif
  </div>
  @endforeach
  @foreach($item->ingredients as $ing)
  <div class="sub row">
    <span>• {{ $ing->ingredient_name }}@if($ing->quantity > 1) x{{ $ing->quantity }}@endif</span>
    @if($ing->extra_price > 0)<span>+${{ number_format($ing->extra_price,2) }}</span>@endif
  </div>
  @endforeach
  @if($item->notes)<div class="sub"><em>* {{ $item->notes }}</em></div>@endif
</div>
@endforeach

<hr>
<div class="row total-row"><span>TOTAL</span><span>${{ number_format($order->total, 2) }}</span></div>
<hr>
<div class="center" style="font-size:10px;">¡Buen provecho!</div>

<script>window.print();</script>
</body>
</html>
