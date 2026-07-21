@php
    $flowStateOrder = ['pendiente' => 1, 'en_preparacion' => 2, 'lista' => 3];
    $flowSourceLabel = $flowOrder->source === 'kiosk' ? 'Kiosco' : ($flowSourceLabel ?? 'Caja');
    $canConvertToDelivery = ($allowConvertToDelivery ?? false) && $flowOrder->type !== 'delivery' && $flowOrder->status !== 'pagada';
@endphp
<article class="pos-flow-card {{ $flowOrder->source === 'kiosk' ? 'is-kiosk' : '' }} {{ $flowOrder->status === 'lista' ? 'is-ready' : '' }}" wire:key="flow-order-{{ $flowOrder->id }}">
    <header class="pos-flow-card__header">
        <div class="pos-flow-card__identity">
            <span class="pos-flow-card__icon"><i class="bx {{ $flowOrder->source === 'kiosk' ? 'bx-desktop' : ($flowIcon ?? 'bx-receipt') }}"></i></span>
            <div>
                <div class="pos-flow-card__eyebrow">
                    <span>Orden #{{ $flowOrder->display_folio }}</span>
                    <span class="pos-source-chip {{ $flowOrder->source === 'kiosk' ? 'is-kiosk' : '' }}">{{ $flowSourceLabel }}</span>
                </div>
                <strong>{{ $flowOrder->customer_name ?: 'Cliente sin nombre' }}</strong>
                <small>{{ $flowArea }} · {{ $flowOrder->items->count() }} {{ $flowOrder->items->count() === 1 ? 'producto' : 'productos' }} · {{ $flowOrder->created_at->format('H:i') }}</small>
            </div>
        </div>
        <div class="pos-flow-card__metrics">
            <span class="pos-flow-card__items-count"><strong>{{ $flowOrder->items->sum('quantity') }}</strong><small>items</small></span>
            <strong class="pos-flow-card__total">${{ number_format($flowOrder->total, 2) }}</strong>
        </div>
    </header>

    @if (($showDeliveryData ?? false) && ($flowOrder->customer_phone || $flowOrder->customer_address))
        <div class="pos-flow-card__delivery">
            @if ($flowOrder->customer_phone)<span><i class="bx bx-phone"></i>{{ $flowOrder->customer_phone }}</span>@endif
            @if ($flowOrder->customer_address)<span><i class="bx bx-map"></i>{{ $flowOrder->customer_address }}</span>@endif
        </div>
    @endif

    <div class="pos-order-progress pos-order-progress--wide" aria-label="Estado de la orden">
        @foreach ([['pendiente', 'Recibido'], ['en_preparacion', 'Preparando'], ['lista', 'Listo']] as [$state, $label])
            <span class="{{ ($flowStateOrder[$flowOrder->status] ?? 1) >= $flowStateOrder[$state] ? 'is-complete' : '' }} {{ $flowOrder->status === $state ? 'is-current' : '' }}">
                <i class="bx bx-check"></i>{{ $label }}
            </span>
        @endforeach
    </div>

    <div class="pos-flow-card__actions">
        @if ($flowOrder->status === 'pendiente')
            <button type="button" wire:click="markKitchenReady({{ $flowOrder->id }})"
                wire:loading.attr="disabled" wire:target="markKitchenReady({{ $flowOrder->id }})"
                class="pos-btn pos-btn-primary">
                <i class="bx bx-printer"></i> Imprimir cocina
            </button>
            @if ($canConvertToDelivery)
                <button type="button" wire:click="openConvertDeliveryModal({{ $flowOrder->id }})" class="pos-btn pos-btn-secondary pos-btn-convert-delivery"><i class="bx bx-cycling"></i> Enviar a delivery</button>
            @endif
        @elseif ($flowOrder->status === 'en_preparacion')
            <button type="button" wire:click="markKitchenReady({{ $flowOrder->id }})"
                wire:loading.attr="disabled" wire:target="markKitchenReady({{ $flowOrder->id }})"
                class="pos-btn pos-btn-primary">
                <i class="bx bx-check-circle"></i> Marcar listo
            </button>
            <button type="button" wire:click="reprintKitchenOrder({{ $flowOrder->id }})"
                class="pos-btn pos-btn-secondary" aria-label="Reimprimir orden {{ $flowOrder->id }} para cocina">
                <i class="bx bx-printer"></i> Cocina
            </button>
            @if ($canConvertToDelivery)
                <button type="button" wire:click="openConvertDeliveryModal({{ $flowOrder->id }})" class="pos-btn pos-btn-secondary pos-btn-convert-delivery"><i class="bx bx-cycling"></i> Enviar a delivery</button>
            @endif
        @else
            @if (($allowOrderPayment ?? true) === false)
                <span class="pos-order-flow-hint"><i class="bx bx-table"></i> Lista para cobrar en Cobrar mesas</span>
            @else
            <button type="button" wire:click="openPickupPayModal({{ $flowOrder->id }})" class="pos-btn pos-btn-primary">
                <i class="bx bx-dollar-circle"></i> Cobrar ahora
            </button>
            <button type="button" wire:click="reprintKitchenOrder({{ $flowOrder->id }})"
                class="pos-btn pos-btn-secondary" aria-label="Reimprimir orden {{ $flowOrder->id }} para cocina">
                <i class="bx bx-printer"></i> Cocina
            </button>
            @if ($canConvertToDelivery)
                <button type="button" wire:click="openConvertDeliveryModal({{ $flowOrder->id }})" class="pos-btn pos-btn-secondary pos-btn-convert-delivery"><i class="bx bx-cycling"></i> Enviar a delivery</button>
            @endif
            @endif
        @endif
    </div>
</article>
