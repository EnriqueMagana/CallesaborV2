@props(['order'])

@php
    $total = (float) $order->total;
    $paid = $order->net_paid_amount;
    $due = $order->uncovered_amount;
    $progress = $total > 0 ? (int) round(min(100, max(0, $paid / $total * 100))) : 0;
    $itemCount = (int) $order->items->sum('quantity');
    $titleId = 'pos-balance-'.$order->id;
@endphp

<article class="pos-balance-card is-register" aria-labelledby="{{ $titleId }}"
    wire:key="pos-balance-{{ $order->id }}">
    <header class="pos-balance-card__head">
        <span class="pos-balance-card__mark" aria-hidden="true"><i class="bx bx-store-alt"></i></span>
        <div class="pos-balance-card__title">
            <h3 id="{{ $titleId }}">{{ $order->display_folio }} <span>· {{ $order->display_name }}</span></h3>
            <p>{{ $order->type_label }} · {{ $order->status_label }} · {{ \App\Support\BusinessTime::format($order->created_at, 'g:i A') }}</p>
        </div>
        <span class="pos-balance-card__who"><i class="bx bx-store-alt" aria-hidden="true"></i>Cobrar en caja</span>
    </header>

    <div class="pos-balance-card__amounts">
        <div class="pos-balance-card__due">
            <span>Falta por cobrar</span>
            <strong>${{ number_format($due, 2) }}</strong>
        </div>
        <div class="pos-balance-card__progress">
            <div class="pos-balance-card__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100"
                aria-valuenow="{{ $progress }}" aria-label="Pagado {{ $progress }}% del total">
                <span style="--bal-progress: {{ $progress / 100 }}"></span>
            </div>
            <p>Pagado <b>${{ number_format($paid, 2) }}</b> de <b>${{ number_format($total, 2) }}</b></p>
        </div>
    </div>

    <details class="pos-balance-card__items">
        <summary>
            <span>{{ $itemCount }} {{ $itemCount === 1 ? 'producto' : 'productos' }}</span>
            <i class="bx bx-chevron-down" aria-hidden="true"></i>
        </summary>
        <ul>
            @foreach ($order->items as $item)
                <li><span>{{ $item->quantity }}× {{ $item->product_name }}</span><span>${{ number_format($item->subtotal, 2) }}</span></li>
            @endforeach
        </ul>
    </details>

    <footer class="pos-balance-card__actions">
        @can('reimprimir tickets')
            <button type="button" class="pos-balance-btn is-secondary"
                wire:click="$parent.openReprintModal({{ $order->id }})"
                aria-label="Reimprimir ticket de {{ $order->display_folio }}">
                <i class="bx bx-printer" aria-hidden="true"></i><span>Ticket</span>
            </button>
        @endcan

        @can('cobrar pedidos en punto de venta')
            @if ($order->status === 'lista')
                <button type="button" class="pos-balance-btn is-primary"
                    wire:click="$parent.openPickupPayModal({{ $order->id }})">
                    <i class="bx bx-dollar-circle" aria-hidden="true"></i><span>Cobrar ${{ number_format($due, 2) }}</span>
                </button>
            @else
                <p class="pos-balance-card__hint"><i class="bx bx-time-five" aria-hidden="true"></i> Se cobra cuando el pedido esté listo</p>
            @endif
        @endcan
    </footer>
</article>
