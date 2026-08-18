<main class="kiosk-shell kiosk-tracking-shell">
    @php
        $order = $this->order;
        $isDelivery = $order->fulfillment === 'delivery' || $order->type === 'delivery';
        $effectiveStatus =
            $isDelivery && $order->deliveryAssignment?->status === 'entregado' ? 'entregada' : $order->status;
        $states = $isDelivery
            ? [
                'pendiente' => 1,
                'en_preparacion' => 2,
                'lista' => 3,
                'pagada' => 3,
                'en_reparto' => 4,
                'entregada' => 5,
                'cancelada' => 0,
            ]
            : ['pendiente' => 1, 'en_preparacion' => 2, 'lista' => 3, 'pagada' => 3, 'cancelada' => 0];
        $current = $states[$effectiveStatus] ?? 1;
        $driverFirstName = str($order->deliveryAssignment?->driver?->name)
            ->before(' ')
            ->toString();
    @endphp

    <header class="kiosk-header">
        <div class="kiosk-brand"><x-business.brand-mark :settings="$businessSettings"
                class="kiosk-brand-mark" /><span><strong>{{ $businessSettings?->business_name ?? config('app.name') }}</strong><small>Seguimiento
                    de pedido</small></span></div>
    </header>

    <section class="kiosk-tracking-card">
        <div
            class="kiosk-tracking-hero {{ $effectiveStatus === 'cancelada' ? 'is-cancelled' : '' }} {{ $isDelivery ? 'is-delivery' : '' }}">
            <span class="kiosk-eyebrow">Pedido #{{ $order->display_folio }}</span>
            @if ($effectiveStatus === 'cancelada')
                <div class="kiosk-tracking-icon"><i class="bx bx-x"></i></div>
                <h1>Pedido cancelado</h1>
                <p>Comunícate con el restaurante si necesitas ayuda.</p>
            @elseif($effectiveStatus === 'entregada')
                <div class="kiosk-tracking-icon"><i class="bx bx-home-heart"></i></div>
                <h1>Pedido entregado</h1>
                <p>Gracias por elegirnos. Esperamos que disfrutes tu pedido.</p>
            @elseif($effectiveStatus === 'en_reparto')
                <div class="kiosk-tracking-icon"><i class="bx bx-cycling"></i></div>
                <h1>Tu pedido va en camino</h1>
                <p>{{ $driverFirstName ? $driverFirstName . ' lleva tu pedido al domicilio indicado.' : 'El repartidor lleva tu pedido al domicilio indicado.' }}
                </p>
            @elseif(in_array($effectiveStatus, ['lista', 'pagada'], true))
                <div class="kiosk-tracking-icon"><i class="bx bx-check"></i></div>
                <h1>{{ $isDelivery ? '¡Tu pedido está listo para salir!' : '¡Tu pedido está listo!' }}</h1>
                <p>{{ $isDelivery ? 'En breve un repartidor tomará tu entrega.' : $order->customer_name . ', acércate al mostrador con tu número.' }}
                </p>
            @elseif($effectiveStatus === 'en_preparacion')
                <div class="kiosk-tracking-icon"><i class="bx bx-bowl-hot"></i></div>
                <h1>Estamos preparando tu pedido</h1>
                <p>En cocina están trabajando para tenerlo listo muy pronto.</p>
            @else
                <div class="kiosk-tracking-icon"><i class="bx bx-time-five"></i></div>
                <h1>Pedido recibido</h1>
                <p>En breve comenzaremos a prepararlo.</p>
            @endif
        </div>

        @if ($effectiveStatus !== 'cancelada')
            <div class="kiosk-status-timeline {{ $isDelivery ? 'is-delivery' : '' }}" aria-label="Estado del pedido">
                @php
                    $timeline = $isDelivery
                        ? [
                            1 => ['bx-receipt', 'Recibido'],
                            2 => ['bx bx-dish', 'Preparando'],
                            3 => ['bx-check', 'Listo'],
                            4 => ['bx-cycling', 'En camino'],
                            5 => ['bx-home-heart', 'Entregado'],
                        ]
                        : [
                            1 => ['bx-receipt', 'Recibido'],
                            2 => ['bx bx-dish', 'Preparando'],
                            3 => ['bx-check', 'Listo'],
                        ];
                @endphp
                @foreach ($timeline as $index => $state)
                    <div
                        class="kiosk-status-step {{ $current >= $index ? 'is-complete' : '' }} {{ $current === $index ? 'is-current' : '' }}">
                        <span><i class="bx {{ $state[0] }}"></i></span><strong>{{ $state[1] }}</strong>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($isDelivery)
            <section class="kiosk-tracking-delivery-card">
                <span><i class="bx bx-map"></i></span>
                <div><small>Dirección de entrega</small><strong>{{ $order->customer_address }}</strong>
                    @if ($order->customer_references)
                        <p>{{ $order->customer_references }}</p>
                    @endif
                </div>
            </section>
        @endif

        <div class="kiosk-tracking-card">
            <div class="kiosk-section-title"><span><i class="bx bx-shopping-bag"></i></span>
                <div>
                    <h2>Tu pedido</h2>
                    <p>{{ match ($order->fulfillment) {'dine_in' => 'Comer aquí','delivery' => 'Para domicilio',default => 'Para llevar'} }}
                        · {{ $order->created_at->format('H:i') }}</p>
                </div>
            </div>
            <div class="kiosk-summary-lines">
                @foreach ($order->items->where('is_cancelled', false) as $item)
                    <div class="kiosk-tracking-line">
                        <div><strong>{{ $item->quantity }}× {{ $item->product_name }}</strong>
                            @if ($item->addons->isNotEmpty() || $item->ingredients->isNotEmpty())
                                <small>{{ $item->addons->pluck('addon_name')->merge($item->ingredients->map(fn($ingredient) => $ingredient->ingredient_name . ' ×' . $ingredient->quantity))->implode(' · ') }}</small>
                            @endif
                        </div><b>${{ number_format($item->subtotal, 2) }}</b>
                    </div>
                @endforeach
            </div>
            <div class="kiosk-cart-total"><span>Total</span><strong>${{ number_format($order->total, 2) }}</strong>
            </div>
        </div>

        <div class="kiosk-manual-refresh">
            <button type="button" class="kiosk-primary-button" wire:click="refreshStatus" wire:loading.attr="disabled"
                wire:target="refreshStatus">
                <span wire:loading.remove wire:target="refreshStatus"><i class="bx bx-refresh"></i> Actualizar
                    estado</span>
                <span wire:loading wire:target="refreshStatus">Consultando estado…</span>
            </button>
        </div>
    </section>
</main>
