@props([
    'order',
    'takeable' => false,
    'showDriver' => false,
    'canTake' => false,
    'canComplete' => false,
    'canManageAll' => false,
])

@php
    $assignment = $order->deliveryAssignment;
    $isOwnAssignment = $assignment?->driver_id === auth()->id();
    $canFinishThis = $canComplete && ($isOwnAssignment || $canManageAll) && $assignment?->status === 'asignado';
    $discount = max(0, (float) $order->subtotal - (float) $order->total);
    $searchKey = str(implode(' ', [
        $order->display_folio,
        $order->display_name,
        $order->customer_phone,
        $order->customer_address,
        $order->customer_references,
        $order->delivery_method_label,
    ]))->ascii()->lower()->squish();
    $openButtonId = 'delivery-open-'.$order->id;
@endphp

<article class="delivery-order-card" wire:key="delivery-order-{{ $order->id }}" data-delivery-search="{{ $searchKey }}" x-show="matches($el.dataset.deliverySearch)" x-transition.opacity aria-labelledby="delivery-order-title-{{ $order->id }}">
    <header class="delivery-order-card__header">
        <div>
            <span id="delivery-order-title-{{ $order->id }}" class="delivery-order-card__folio">Pedido #{{ $order->display_folio }}</span>
            <time class="delivery-order-card__time" datetime="{{ $order->created_at->toIso8601String() }}">{{ $order->created_at->format('H:i') }}</time>
        </div>
        <x-delivery.status-pill :status="$order->status" />
    </header>

    <div class="delivery-order-card__address">
        <span><i class="bx bx-map" aria-hidden="true"></i></span>
        <div>
            <small>Dirección de entrega</small>
            <strong>{{ $order->customer_address ?: 'Dirección no capturada' }}</strong>
            @if($order->customer_references)
                <p>{{ $order->customer_references }}</p>
            @endif
        </div>
    </div>

    <dl class="delivery-order-card__meta">
        <div><dt>Cliente</dt><dd>{{ $order->display_name }}</dd></div>
        <div><dt>Teléfono</dt><dd>@if($order->customer_phone)<a href="tel:{{ preg_replace('/\D+/', '', $order->customer_phone) }}">{{ $order->customer_phone }}</a>@else Sin teléfono @endif</dd></div>
        <div><dt>Cobro</dt><dd>{{ $order->delivery_method_label }}</dd></div>
        <div><dt>Total</dt><dd>${{ number_format($order->total, 2) }}</dd></div>
    </dl>

    @if($discount > 0)
        <p class="delivery-order-card__discount"><i class="bx bx-purchase-tag" aria-hidden="true"></i> Descuento aplicado: ${{ number_format($discount, 2) }}</p>
    @endif

    @if($showDriver && $assignment)
        <div class="delivery-order-card__driver">
            <span><i class="bx bx-user-check" aria-hidden="true"></i></span>
            <div><small>Repartidor</small><strong>{{ $assignment->driver?->name ?? 'Usuario eliminado' }}</strong></div>
        </div>
    @endif

    <footer class="delivery-order-card__actions">
        <button id="{{ $openButtonId }}" type="button" class="delivery-btn delivery-btn--secondary" x-on:click="lastTriggerId = '{{ $openButtonId }}'" wire:click="openOrder({{ $order->id }})" wire:loading.attr="disabled" wire:target="openOrder({{ $order->id }})">
            <i class="bx bx-detail" aria-hidden="true"></i> Ver pedido
        </button>

        @if($takeable && $canTake)
            <button type="button" class="delivery-btn delivery-btn--primary" wire:click="takeOrder({{ $order->id }})" wire:loading.attr="disabled" wire:target="takeOrder({{ $order->id }})">
                <span wire:loading.remove wire:target="takeOrder({{ $order->id }})"><i class="bx bx-hand" aria-hidden="true"></i> Tomar pedido</span>
                <span wire:loading wire:target="takeOrder({{ $order->id }})">Asignando…</span>
            </button>
        @elseif($canFinishThis)
            <button type="button" class="delivery-btn delivery-btn--success" wire:click="askToMarkDelivered({{ $order->id }})">
                <i class="bx bx-check-double" aria-hidden="true"></i> Marcar entregado
            </button>
        @endif
    </footer>

    <div class="delivery-card-busy" wire:loading.flex wire:target="takeOrder({{ $order->id }})" role="status">
        <i class="bx bx-loader-alt bx-spin" aria-hidden="true"></i><span>Asignando pedido…</span>
    </div>
</article>
