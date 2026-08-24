<x-pos.area-panel panel="delivery" title="Delivery" title-id="pos-delivery-title"
    eyebrow="Área operativa" description="Pedidos para entregar a domicilio, incluidos los realizados en kiosco."
    icon="bx-cycling" tone="delivery" close-label="Cerrar Delivery"
    close-action="panels.delivery = false; $wire.closeDeliveryPanel()">
        <x-slot:tools>
            <label class="pos-area-search">
                <i class="bx bx-search"></i>
                <span class="visually-hidden">Buscar pedido de delivery</span>
                <input type="search" class="pos-input" wire:model.live.debounce.300ms="deliverySearch" placeholder="Pedido, cliente, teléfono o dirección">
            </label>
            <div class="pos-area-summary"><strong>{{ $this->deliveryOrders->count() }}</strong><span>entregas activas</span></div>
            @can('ver delivery')
                <a href="{{ route('app.delivery') }}" class="pos-btn pos-btn-secondary">
                    <i class="bx bx-map-alt"></i> Gestionar reparto
                </a>
            @endcan
        </x-slot:tools>

        <x-slot:beforeBody>
        <div wire:loading.flex wire:target="openDeliveryPanel"
            class="pos-skeleton-list" aria-label="Consultando pedidos a domicilio">
            @for ($s = 0; $s < 2; $s++)
                <div class="pos-table-skeleton"><span></span><div><i></i><i></i><i></i></div></div>
            @endfor
        </div>
        </x-slot:beforeBody>

        <x-slot:body>
        <div class="panel-body pos-area-panel__body" wire:loading.remove wire:target="openDeliveryPanel">
            @forelse ($this->deliveryOrders as $deliveryOrder)
                @include('livewire.pos.partials.order-flow-card', [
                    'flowOrder' => $deliveryOrder,
                    'flowArea' => 'Delivery',
                    'flowIcon' => 'bx-cycling',
                    'flowSourceLabel' => $deliveryOrder->source === 'kiosk' ? 'Kiosco' : 'Caja',
                    'showDeliveryData' => true,
                    'allowOrderPayment' => false,
                ])
            @empty
                <div class="pos-area-empty">
                    <span><i class="bx bx-check-circle"></i></span>
                    <h3>Sin entregas pendientes</h3>
                    <p>Los nuevos pedidos a domicilio aparecerán en esta área.</p>
                </div>
            @endforelse
        </div>
        </x-slot:body>
</x-pos.area-panel>
