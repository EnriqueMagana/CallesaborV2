@props(['order', 'canComplete' => false, 'canManageAll' => false])

@php
    $assignment = $order->deliveryAssignment;
    $canFinish = $canComplete
        && $assignment?->status === 'asignado'
        && ($assignment->driver_id === auth()->id() || $canManageAll);
    $discount = max(0, (float) $order->subtotal - (float) $order->total);
    $mapsUrl = 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($order->customer_address ?? '');
@endphp

<div class="delivery-modal-layer" role="presentation" wire:key="delivery-detail-{{ $order->id }}" x-data x-on:keydown.escape.window="$wire.closeOrder()" x-init="$nextTick(() => $refs.closeButton.focus())">
    <button type="button" class="delivery-modal-layer__backdrop" wire:click="closeOrder" aria-label="Cerrar detalle"></button>
    <section class="delivery-detail" role="dialog" aria-modal="true" aria-labelledby="delivery-detail-title" aria-describedby="delivery-detail-description" tabindex="-1">
        <header class="delivery-detail__header">
            <div>
                <span id="delivery-detail-description">Detalle de entrega</span>
                <h2 id="delivery-detail-title">Pedido #{{ $order->display_folio }}</h2>
            </div>
            <button x-ref="closeButton" type="button" class="delivery-icon-btn" wire:click="closeOrder" aria-label="Cerrar detalle"><i class="bx bx-x" aria-hidden="true"></i></button>
        </header>

        <div class="delivery-detail__body">
            <section class="delivery-detail__destination" aria-labelledby="delivery-address-title">
                <div class="delivery-detail__section-icon"><i class="bx bx-map"></i></div>
                <div>
                    <small id="delivery-address-title">Entregar en</small>
                    <h3>{{ $order->customer_address ?: 'Dirección no capturada' }}</h3>
                    @if($order->customer_references)<p>{{ $order->customer_references }}</p>@endif
                    <div class="delivery-detail__quick-actions">
                        @if($order->customer_phone)
                            <a class="delivery-mini-action" href="tel:{{ preg_replace('/\D+/', '', $order->customer_phone) }}"><i class="bx bx-phone"></i> Llamar</a>
                        @endif
                        @if($order->customer_address)
                            <a class="delivery-mini-action" href="{{ $mapsUrl }}" target="_blank" rel="noopener"><i class="bx bx-navigation"></i> Abrir mapa</a>
                        @endif
                    </div>
                </div>
            </section>

            <div class="delivery-detail__facts">
                <div><small>Cliente</small><strong>{{ $order->display_name }}</strong></div>
                <div><small>Teléfono</small><strong>{{ $order->customer_phone ?: 'Sin teléfono' }}</strong></div>
                <div><small>Forma de cobro</small><strong>{{ $order->delivery_method_label }}</strong></div>
                <div><small>Origen</small><strong>{{ $order->source === 'kiosk' ? 'Kiosco' : 'Punto de venta' }}</strong></div>
            </div>

            @if($assignment)
                <div class="delivery-detail__assignment">
                    <span><i class="bx bx-cycling"></i></span>
                    <div><small>Repartidor asignado</small><strong>{{ $assignment->driver?->name ?? 'Usuario eliminado' }}</strong><p>Tomó el pedido a las {{ $assignment->assigned_at?->format('H:i') }}.</p></div>
                </div>
            @endif

            <section class="delivery-detail__items" aria-labelledby="delivery-products-title">
                <div class="delivery-detail__section-title">
                    <div><span><i class="bx bx-shopping-bag"></i></span><h3 id="delivery-products-title">Productos</h3></div>
                    <b>{{ $order->items->where('is_cancelled', false)->sum('quantity') }} artículos</b>
                </div>

                @foreach($order->items->where('is_cancelled', false) as $item)
                    <article class="delivery-product">
                        <div class="delivery-product__heading">
                            <span>{{ $item->quantity }}</span>
                            <div><strong>{{ $item->product_name }}</strong><small>${{ number_format($item->product_price, 2) }} c/u</small></div>
                            <b>${{ number_format($item->subtotal, 2) }}</b>
                        </div>
                        @if($item->addons->isNotEmpty())
                            <div class="delivery-product__options"><small>Complementos</small><p>{{ $item->addons->map(fn($addon) => $addon->addon_name.' ×'.$addon->quantity)->implode(' · ') }}</p></div>
                        @endif
                        @if($item->ingredients->isNotEmpty())
                            <div class="delivery-product__options"><small>Ingredientes</small><p>{{ $item->ingredients->map(fn($ingredient) => $ingredient->ingredient_name.' ×'.$ingredient->quantity)->implode(' · ') }}</p></div>
                        @endif
                        @if($item->notes)<div class="delivery-product__note"><i class="bx bx-note"></i><span>{{ $item->notes }}</span></div>@endif
                    </article>
                @endforeach
            </section>

            @if($order->notes)
                <section class="delivery-detail__notes"><i class="bx bx-message-square-detail"></i><div><small>Nota general</small><p>{{ $order->notes }}</p></div></section>
            @endif

            <dl class="delivery-detail__totals">
                <div><dt>Subtotal</dt><dd>${{ number_format($order->subtotal, 2) }}</dd></div>
                @if($discount > 0)<div class="is-discount"><dt>Descuento</dt><dd>−${{ number_format($discount, 2) }}</dd></div>@endif
                <div class="is-total"><dt>Total</dt><dd>${{ number_format($order->total, 2) }}</dd></div>
            </dl>
        </div>

        <footer class="delivery-detail__footer">
            <button type="button" class="delivery-btn delivery-btn--secondary" wire:click="closeOrder">Cerrar</button>
            @if($canFinish)
                <button type="button" class="delivery-btn delivery-btn--success" wire:click="askToMarkDelivered({{ $order->id }})"><i class="bx bx-check-double"></i> Marcar entregado</button>
            @endif
        </footer>
    </section>
</div>
