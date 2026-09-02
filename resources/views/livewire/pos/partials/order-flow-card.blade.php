@php
    $flowStateOrder = ['pendiente' => 1, 'en_preparacion' => 2, 'lista' => 3];
    $flowSourceLabel = $flowOrder->source === 'kiosk' ? 'Kiosco' : $flowSourceLabel ?? 'Caja';
    $canConvertToDelivery =
        ($allowConvertToDelivery ?? false) && $flowOrder->type !== 'delivery' && $flowOrder->status !== 'pagada';
    $showOperationalStatus = $showOperationalStatus ?? true;
    $showKitchenActions = $showKitchenActions ?? true;
    $showFinancialTotal = $showFinancialTotal ?? true;
    $billingOnly = ! $showOperationalStatus && ! $showKitchenActions;
@endphp
<article
    class="pos-flow-card {{ $flowOrder->source === 'kiosk' ? 'is-kiosk' : '' }} {{ $showOperationalStatus && $flowOrder->status === 'lista' ? 'is-ready' : '' }} {{ $billingOnly ? 'is-billing-summary' : '' }}"
    wire:key="flow-order-{{ $flowOrder->id }}">
    <header class="pos-flow-card__header">
        <div class="pos-flow-card__identity">
            <span class="pos-flow-card__icon"><i
                    class="bx {{ $flowOrder->source === 'kiosk' ? 'bx-desktop' : $flowIcon ?? 'bx-receipt' }}"></i></span>
            <div>
                <div class="pos-flow-card__eyebrow">
                    <span>Orden {{ $flowOrder->display_folio }}</span>
                    <span
                        class="pos-source-chip {{ $flowOrder->source === 'kiosk' ? 'is-kiosk' : '' }}">{{ $flowSourceLabel }}</span>
                </div>
                <strong>{{ $flowOrder->customer_name ?: ($billingOnly ? 'Consumo de mesa' : 'Cliente sin nombre') }}</strong>
                <small>{{ $flowArea }} · {{ $flowOrder->items->count() }}
                    {{ $flowOrder->items->count() === 1 ? 'producto' : 'productos' }} ·
                    {{ $flowOrder->created_at->format('g:i A') }}</small>
            </div>
        </div>
        <div class="pos-flow-card__metrics">
            <span
                class="pos-flow-card__items-count"><strong>{{ $flowOrder->items->sum('quantity') }}</strong><small>items</small></span>
            @if ($showFinancialTotal)
                <strong class="pos-flow-card__total">${{ number_format($flowOrder->total, 2) }}</strong>
            @endif
        </div>
    </header>

    @if (($showDeliveryData ?? false) && ($flowOrder->customer_phone || $flowOrder->customer_address))
        <div class="pos-flow-card__delivery">
            @if ($flowOrder->customer_phone)
                <span><i class="bx bx-phone"></i>{{ $flowOrder->customer_phone }}</span>
            @endif
            @if ($flowOrder->customer_address)
                <span><i class="bx bx-map"></i>{{ $flowOrder->customer_address }}</span>
            @endif
        </div>
    @endif

    @if ($showOperationalStatus)
        <div class="pos-order-progress pos-order-progress--wide" aria-label="Estado de la orden">
            @foreach ([['pendiente', 'Recibido'], ['en_preparacion', 'Preparando'], ['lista', 'Listo']] as [$state, $label])
                <span
                    class="{{ ($flowStateOrder[$flowOrder->status] ?? 1) >= $flowStateOrder[$state] ? 'is-complete' : '' }} {{ $flowOrder->status === $state ? 'is-current' : '' }}">
                    <i class="bx bx-check"></i>{{ $label }}
                </span>
            @endforeach
        </div>
    @endif

    @if ($showKitchenActions)
    <div class="pos-flow-card__actions">
        @if ($flowOrder->status === 'pendiente')
            @can('iniciar preparacion en punto de venta')
            <button type="button" wire:click="markKitchenReady({{ $flowOrder->id }})" wire:loading.attr="disabled"
                wire:target="markKitchenReady({{ $flowOrder->id }})" class="pos-btn pos-btn-primary">
                <i class="bx bx-printer"></i> Imprimir cocina
            </button>
            @endcan
            @can('convertir pedidos a delivery en punto de venta')
            @if ($canConvertToDelivery)
                <button type="button" wire:click="openConvertDeliveryModal({{ $flowOrder->id }})"
                    class="pos-btn pos-btn-secondary pos-btn-convert-delivery"><i class="bx bx-cycling"></i> Enviar a
                    delivery</button>
            @endif
            @endcan
        @elseif ($flowOrder->status === 'en_preparacion')
            @can('marcar pedidos listos en punto de venta')
            <button type="button" wire:click="markKitchenReady({{ $flowOrder->id }})" wire:loading.attr="disabled"
                wire:target="markKitchenReady({{ $flowOrder->id }})" class="pos-btn pos-btn-primary">
                <i class="bx bx-check-circle"></i> Marcar listo
            </button>
            @endcan
            @can('reimprimir tickets')
            <button type="button" wire:click="reprintKitchenOrder({{ $flowOrder->id }})"
                class="pos-btn pos-btn-secondary" aria-label="Reimprimir orden {{ $flowOrder->id }} para cocina">
                <i class="bx bx-printer"></i> Cocina
            </button>
            @endcan
            @can('convertir pedidos a delivery en punto de venta')
            @if ($canConvertToDelivery)
                <button type="button" wire:click="openConvertDeliveryModal({{ $flowOrder->id }})"
                    class="pos-btn pos-btn-secondary pos-btn-convert-delivery"><i class="bx bx-cycling"></i> Enviar a
                    delivery</button>
            @endif
            @endcan
        @else
            @if (($allowOrderPayment ?? true) === false)
                @can('reimprimir tickets')
                <button type="button" wire:click="reprintKitchenOrder({{ $flowOrder->id }})"
                    wire:loading.attr="disabled" wire:target="reprintKitchenOrder({{ $flowOrder->id }})"
                    class="pos-btn pos-btn-secondary"
                    aria-label="Reimprimir orden {{ $flowOrder->display_folio }} para cocina">
                    <i class="bx bx-printer"></i> Reimprimir cocina
                </button>
                @endcan
            @else
                @can('cobrar pedidos en punto de venta')
                <button type="button" wire:click="openPickupPayModal({{ $flowOrder->id }})"
                    class="pos-btn pos-btn-primary">
                    <i class="bx bx-dollar-circle"></i> Cobrar ahora
                </button>
                @endcan
                @can('reimprimir tickets')
                <button type="button" wire:click="reprintKitchenOrder({{ $flowOrder->id }})"
                    class="pos-btn pos-btn-secondary" aria-label="Reimprimir orden {{ $flowOrder->id }} para cocina">
                    <i class="bx bx-printer"></i> Cocina
                </button>
                @endcan
                @can('convertir pedidos a delivery en punto de venta')
                @if ($canConvertToDelivery)
                    <button type="button" wire:click="openConvertDeliveryModal({{ $flowOrder->id }})"
                        class="pos-btn pos-btn-secondary pos-btn-convert-delivery"><i class="bx bx-cycling"></i> Enviar
                        a delivery</button>
                @endif
                @endcan
            @endif
        @endif
    </div>
    @endif
</article>
