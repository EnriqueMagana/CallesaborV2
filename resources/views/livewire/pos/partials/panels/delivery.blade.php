<x-pos.area-panel panel="delivery" title="Delivery" title-id="pos-delivery-title"
    eyebrow="Área operativa" description="Pedidos para entregar a domicilio, incluidos los realizados en kiosco."
    icon="bx-cycling" tone="delivery" close-label="Cerrar Delivery">
        <x-slot:tools>
            <label class="pos-area-search">
                <i class="bx bx-search"></i>
                <span class="visually-hidden">Buscar pedido de delivery</span>
                <input type="search" class="pos-input" wire:model.live.debounce.300ms="deliverySearch" placeholder="Pedido, cliente, teléfono o dirección">
            </label>
            <div class="pos-area-summary"><strong>{{ $this->deliveryOrders->count() }}</strong><span>entregas activas</span></div>
        </x-slot:tools>

            @forelse ($this->deliveryOrders as $deliveryOrder)
                @include('livewire.pos.partials.order-flow-card', [
                    'flowOrder' => $deliveryOrder,
                    'flowArea' => 'Delivery',
                    'flowIcon' => 'bx-cycling',
                    'flowSourceLabel' => $deliveryOrder->source === 'kiosk' ? 'Kiosco' : 'Caja',
                    'showDeliveryData' => true,
                ])
            @empty
                <div class="pos-area-empty">
                    <span><i class="bx bx-check-circle"></i></span>
                    <h3>Sin entregas pendientes</h3>
                    <p>Los nuevos pedidos a domicilio aparecerán en esta área.</p>
                </div>
            @endforelse
</x-pos.area-panel>
