@php
    $statusClass = match($order->status) {
        'pagada', 'lista', 'entregada' => 'success',
        'cancelada' => 'danger',
        'en_preparacion', 'en_reparto' => 'info',
        default => 'warning',
    };
    $isKioskOrder = $order->source === 'kiosk';
    $channelLabel = $isKioskOrder ? 'Kiosco' : $order->type_label;
    $channelIcon = $isKioskOrder ? 'bx-desktop' : $order->type_icon;
    $fulfillmentLabel = match($order->fulfillment) {
        'dine_in' => 'Comer aquí',
        'delivery' => 'Para domicilio',
        default => 'Para llevar',
    };
@endphp
<tr wire:key="order-row-{{ $order->id }}">
    <td class="orders-col--primary">
        <a href="{{ route('app.ordenes.show', $order) }}" class="orders-order-link">
            <span>{{ $order->display_folio }}</span>
            <i class="bx bx-chevron-right" aria-hidden="true"></i>
        </a>
        <strong class="orders-customer-name">{{ $order->display_name }}</strong>
        @if($order->customer_phone)<small class="orders-muted">{{ $order->customer_phone }}</small>@endif
    </td>
    <td class="orders-col--secondary">
        <span class="orders-channel-badge {{ $isKioskOrder ? 'is-kiosk' : '' }}">
            <i class="bx {{ $channelIcon }}" aria-hidden="true"></i>{{ $channelLabel }}
        </span>
        @if($isKioskOrder)<small class="orders-muted">{{ $fulfillmentLabel }}</small>@endif
        @if($order->table_identifier)<small class="orders-muted">{{ $order->table_identifier }}</small>@endif
    </td>
    <td><span class="orders-status orders-status--{{ $statusClass }}"><i aria-hidden="true"></i>{{ $order->status_label }}</span></td>
    <td><strong class="orders-total">${{ number_format($order->total, 2) }}</strong></td>
    <td class="orders-col--secondary"><strong class="orders-cell-title">{{ $order->seller?->name ?? 'Sin asignar' }}</strong><small class="orders-muted">{{ $order->cashRegister?->name ?? 'Sin caja' }}</small></td>
    <td class="orders-col--secondary"><strong class="orders-cell-title">{{ $order->created_at->format('d M Y') }}</strong><small class="orders-muted">{{ $order->created_at->format('H:i') }} h</small></td>
    <td class="orders-actions-cell">@include('livewire.orders.partials.order-actions', ['order' => $order])</td>
</tr>
