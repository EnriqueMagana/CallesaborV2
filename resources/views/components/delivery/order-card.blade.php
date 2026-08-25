@props([
    'order',
    'takeable' => false,
    'showDriver' => false,
    'canTake' => false,
    'canComplete' => false,
    'canManageAll' => false,
    'highlighted' => false,
])

@php
    $assignment = $order->deliveryAssignment;
    $displayStatus = $assignment?->status === 'entregado' ? 'entregada' : $order->status;
    $isOwnAssignment = $assignment?->driver_id === auth()->id();
    $controlsAssignment = $isOwnAssignment || $canManageAll;
    $canPickUpThis = $canComplete
        && $controlsAssignment
        && $assignment?->status === 'asignado'
        && in_array($order->status, ['pendiente', 'en_preparacion', 'lista', 'pagada'], true);
    $canFinishThis = $canComplete
        && $controlsAssignment
        && $assignment?->status === 'asignado'
        && $order->status === 'en_reparto';
    $searchKey = str(implode(' ', [
        $order->display_folio,
        $order->display_name,
        $order->customer_phone,
        $order->customer_address,
        $order->customer_references,
        $order->delivery_method_label,
        $order->origin_label,
    ]))->ascii()->lower()->squish();
    $originIcon = $order->source === 'kiosk' ? 'bx-desktop' : 'bx-store';
    $expandId = 'delivery-card-details-'.$order->id;
@endphp

<article id="delivery-order-{{ $order->id }}" tabindex="-1"
    class="delivery-bank-card {{ $order->status === 'en_reparto' ? 'is-picked-up' : '' }} {{ $highlighted ? 'is-highlighted' : '' }}"
    wire:key="delivery-order-{{ $order->id }}" data-delivery-search="{{ $searchKey }}"
    x-data="{ expanded: false }" x-show="matches($el.dataset.deliverySearch)" x-transition.opacity
    aria-labelledby="delivery-order-title-{{ $order->id }}">
    <header class="delivery-bank-card__header">
        <div class="delivery-bank-card__identity">
            <span class="delivery-bank-card__icon"><i class="bx bx-package" aria-hidden="true"></i></span>
            <div>
                <span class="delivery-bank-card__eyebrow">Pedido</span>
                <strong id="delivery-order-title-{{ $order->id }}">#{{ $order->display_folio }}</strong>
                <time datetime="{{ $order->created_at->toIso8601String() }}">{{ $order->created_at->format('H:i') }}</time>
            </div>
        </div>
        <x-delivery.status-pill :status="$displayStatus" />
    </header>

    <div class="delivery-bank-card__chips">
        <span><i class="bx {{ $originIcon }}" aria-hidden="true"></i>{{ $order->origin_label }}</span>
        <span class="{{ $order->amount_to_collect > 0 ? 'is-collect' : 'is-paid' }}">
            <i class="bx {{ $order->amount_to_collect > 0 ? 'bx-money' : 'bx-check-shield' }}"
                aria-hidden="true"></i>
            {{ $order->amount_to_collect > 0
                ? 'Cobrar $'.number_format($order->amount_to_collect, 2)
                : 'Pagado' }}
        </span>
    </div>

    <section class="delivery-bank-card__destination" aria-label="Destino de entrega">
        <span><i class="bx bx-map" aria-hidden="true"></i></span>
        <div>
            <small>Entregar en</small>
            <strong>{{ $order->customer_address ?: 'Dirección no capturada' }}</strong>
            <p>{{ $order->display_name }}</p>
        </div>
    </section>

    @if ($showDriver && $assignment)
        <div class="delivery-bank-card__driver">
            <i class="bx bx-user-check" aria-hidden="true"></i>
            <span><small>Asignado a</small><strong>{{ $assignment->driver?->name ?? 'Usuario eliminado' }}</strong></span>
        </div>
    @endif

    <div id="{{ $expandId }}" class="delivery-bank-card__details" x-show="expanded" x-cloak
        x-transition:enter="delivery-expand-enter" x-transition:enter-start="delivery-expand-enter-start"
        x-transition:enter-end="delivery-expand-enter-end">
        <dl>
            <div>
                <dt>Teléfono</dt>
                <dd>
                    @if ($order->customer_phone)
                        <a href="tel:{{ preg_replace('/\D+/', '', $order->customer_phone) }}">{{ $order->customer_phone }}</a>
                    @else
                        Sin teléfono
                    @endif
                </dd>
            </div>
            <div><dt>Forma de entrega</dt><dd>{{ $order->delivery_method_label }}</dd></div>
            @if ($order->customer_references)
                <div class="is-wide"><dt>Referencias</dt><dd>{{ $order->customer_references }}</dd></div>
            @endif
        </dl>

        @if ($order->items->isNotEmpty())
            <section class="delivery-bank-card__items" aria-label="Contenido del pedido">
                <strong>Contenido</strong>
                <ul>
                    @foreach ($order->items->where('is_cancelled', false) as $item)
                        <li><b>{{ $item->quantity }}×</b><span>{{ $item->product_name }}</span></li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>

    <footer class="delivery-bank-card__actions">
        <button type="button" class="delivery-btn delivery-btn--secondary"
            x-on:click="expanded = !expanded" x-bind:aria-expanded="expanded"
            aria-controls="{{ $expandId }}">
            <i class="bx bx-chevron-down" aria-hidden="true" x-bind:class="expanded && 'is-rotated'"></i>
            <span x-text="expanded ? 'Ocultar' : 'Ver detalles'"></span>
        </button>

        @if ($takeable && $canTake)
            <button type="button" class="delivery-btn delivery-btn--primary"
                wire:click="takeOrder({{ $order->id }})" wire:loading.attr="disabled"
                wire:target="takeOrder({{ $order->id }})">
                <span wire:loading.remove wire:target="takeOrder({{ $order->id }})"><i
                        class="bx bx-user-check" aria-hidden="true"></i> Asignarme</span>
                <span wire:loading wire:target="takeOrder({{ $order->id }})"><i
                        class="bx bx-loader-alt bx-spin" aria-hidden="true"></i> Asignando…</span>
            </button>
        @elseif ($canPickUpThis)
            <button type="button" class="delivery-btn delivery-btn--pickup"
                wire:click="markPickedUp({{ $order->id }})" wire:loading.attr="disabled"
                wire:target="markPickedUp({{ $order->id }})">
                <span wire:loading.remove wire:target="markPickedUp({{ $order->id }})"><i
                        class="bx bx-shopping-bag" aria-hidden="true"></i> Recogí el pedido</span>
                <span wire:loading wire:target="markPickedUp({{ $order->id }})"><i
                        class="bx bx-loader-alt bx-spin" aria-hidden="true"></i> Actualizando…</span>
            </button>
        @elseif ($canFinishThis)
            <button type="button" class="delivery-btn delivery-btn--success"
                wire:click="askToMarkDelivered({{ $order->id }})">
                <i class="bx bx-check-double" aria-hidden="true"></i> Marcar entregado
            </button>
        @endif
    </footer>
</article>
