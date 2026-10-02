<x-public-menu.site-layout :business="$business" :menu-settings="$menuSettings"
    title="{{ $onlineOrder->display_folio }} · {{ $business->business_name }}"
    description="Consulta el estado de tu pedido en línea." body-class="online-order-tracking-page"
    :styles="['assets/css/online-orders.css']">
    @php
        $order = $onlineOrder->order;
        $effectiveStatus = $order?->status;
        $headline = match (true) {
            $onlineOrder->status === 'rejected' => 'El pedido no fue concretado',
            $onlineOrder->status === 'awaiting_whatsapp' => 'Falta un paso para confirmar',
            $onlineOrder->status === 'pending_confirmation' => 'Estamos confirmando tu pedido',
            $effectiveStatus === 'en_preparacion' => 'Estamos preparando tu pedido',
            in_array($effectiveStatus, ['lista', 'pagada'], true) => 'Tu pedido está listo',
            $effectiveStatus === 'en_reparto' => 'Tu pedido va en camino',
            $effectiveStatus === 'entregada' => 'Pedido entregado',
            default => 'Pedido confirmado',
        };
    @endphp
    <main class="online-track">
        <a class="online-track__brand" href="{{ route('public.menu') }}"><x-business.brand-mark :settings="$business" /><span><strong>{{ $business->business_name }}</strong><small>Seguimiento en línea</small></span></a>
        <section class="online-track__card">
            <span class="online-track__origin"><i class="bx bx-store-alt" aria-hidden="true"></i> Pedido realizado desde el Menú Digital</span>
            <span class="online-track__folio">{{ $onlineOrder->display_folio }}</span>
            <div class="online-track__icon"><i class="bx {{ $onlineOrder->status === 'awaiting_whatsapp' ? 'bxl-whatsapp' : ($onlineOrder->status === 'rejected' ? 'bx-x' : 'bx-check') }}"></i></div>
            <h1>{{ $headline }}</h1>
            @if($onlineOrder->status === 'awaiting_whatsapp')
                <p>Tu solicitud ya tiene folio, pero el restaurante necesita que envíes el mensaje de WhatsApp para concretarla.</p>
                <a class="online-track__whatsapp" href="{{ route('online-orders.whatsapp', $onlineOrder->public_token) }}"><i class="bx bxl-whatsapp"></i>Enviar pedido por WhatsApp</a>
                <small>Al volver a esta página podrás consultar el seguimiento con este mismo enlace.</small>
            @elseif($onlineOrder->status === 'pending_confirmation')
                <p>El mensaje ya fue preparado. El restaurante verificará disponibilidad y confirmará el pedido antes de enviarlo a cocina.</p>
            @elseif($onlineOrder->status === 'rejected')
                <p>{{ $onlineOrder->rejection_reason ?: 'Comunícate con el restaurante si necesitas ayuda.' }}</p>
            @else
                <p>{{ $onlineOrder->fulfillment === 'delivery' ? 'Conserva este enlace para seguir la preparación y entrega.' : 'Conserva este enlace y presenta tu folio al recoger.' }}</p>
                <div class="online-track__steps" aria-label="Estado del pedido">
                    @foreach(['Recibido', 'Preparando', 'Listo'] as $index => $label)
                        @php($level = match($effectiveStatus) {'pendiente' => 1, 'en_preparacion' => 2, 'lista', 'pagada', 'en_reparto', 'entregada' => 3, default => 1})
                        <span @class(['is-active' => $level >= $index + 1])><i class="bx bx-check"></i>{{ $label }}</span>
                    @endforeach
                </div>
            @endif
        </section>
        <section class="online-track__customer" aria-labelledby="online-customer-title">
            <header><div><span>Datos del cliente</span><h2 id="online-customer-title">{{ $onlineOrder->customer_name }}</h2></div><i class="bx bx-user-circle" aria-hidden="true"></i></header>
            <dl>
                <div><dt>Teléfono</dt><dd>{{ $onlineOrder->customer_phone }}</dd></div>
                <div><dt>Servicio</dt><dd>{{ $onlineOrder->fulfillment_label }}</dd></div>
                @if($onlineOrder->customer_address)
                    <div class="is-wide"><dt>Dirección de entrega</dt><dd>{{ $onlineOrder->customer_address }}@if($onlineOrder->customer_neighborhood), {{ $onlineOrder->customer_neighborhood }}@endif</dd></div>
                @endif
                <div><dt>Pago seleccionado</dt><dd>{{ ucfirst($onlineOrder->payment_method) }}@if($onlineOrder->cash_tendered) · paga con ${{ number_format((float) $onlineOrder->cash_tendered, 2) }}@endif</dd></div>
            </dl>
        </section>
        <section class="online-track__summary">
            <header><div><span>{{ $onlineOrder->fulfillment_label }}</span><h2>Resumen de tu pedido</h2></div><strong>${{ number_format((float)$onlineOrder->total, 2) }}</strong></header>
            @foreach($onlineOrder->cart_snapshot as $line)
                <div class="online-track__line"><span><strong>{{ $line['quantity'] }}× {{ $line['product_name'] }}</strong>@if(!empty($line['addons']) || !empty($line['ingredients']))<small>{{ collect($line['addons'] ?? [])->pluck('name')->merge(collect($line['ingredients'] ?? [])->pluck('name'))->implode(' · ') }}</small>@endif</span><b>${{ number_format((float)$line['subtotal'], 2) }}</b></div>
            @endforeach
            <footer><span>Pago previsto: {{ ucfirst($onlineOrder->payment_method) }}</span><a href="{{ request()->url() }}"><i class="bx bx-refresh"></i>Actualizar estado</a></footer>
        </section>
    </main>
    <x-slot:scripts><script>window.addEventListener('load',()=>document.body.setAttribute('aria-busy','false'));</script></x-slot:scripts>
</x-public-menu.site-layout>
