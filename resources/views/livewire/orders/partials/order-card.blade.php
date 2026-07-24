@php
    $statusClass = match($order->status) {
        'pagada', 'lista', 'entregada' => 'success',
        'cancelada' => 'danger',
        'en_preparacion', 'en_reparto' => 'info',
        default => 'warning',
    };
    $isKioskOrder = $order->source === 'kiosk';
    $fulfillmentLabel = match($order->fulfillment) {
        'dine_in' => 'Comer aquí',
        'delivery' => 'Para domicilio',
        default => 'Para llevar',
    };
@endphp
<article class="orders-mobile-card" wire:key="order-card-{{ $order->id }}">
    <header>
        <span><small>Orden</small><strong>#{{ $order->display_folio }}</strong></span>
        <span class="orders-status orders-status--{{ $statusClass }}"><i aria-hidden="true"></i>{{ $order->status_label }}</span>
    </header>
    <div class="orders-mobile-card__customer">
        <span aria-hidden="true"><i class="bx bx-user"></i></span>
        <div><strong>{{ $order->display_name }}</strong><small>{{ $order->customer_phone ?: 'Sin teléfono registrado' }}</small></div>
        <b>${{ number_format($order->total, 2) }}</b>
    </div>
    <dl>
        <div><dt>Canal</dt><dd><i class="bx {{ $isKioskOrder ? 'bx-desktop' : $order->type_icon }}" aria-hidden="true"></i>{{ $isKioskOrder ? "Kiosco · {$fulfillmentLabel}" : $order->type_label }}</dd></div>
        <div><dt>Responsable</dt><dd>{{ $order->seller?->name ?? 'Sin asignar' }}</dd></div>
        <div><dt>Registro</dt><dd>{{ $order->created_at->format('d/m/Y · H:i') }}</dd></div>
    </dl>
    <footer>@include('livewire.orders.partials.order-actions', ['order' => $order, 'mobile' => true])</footer>
</article>
