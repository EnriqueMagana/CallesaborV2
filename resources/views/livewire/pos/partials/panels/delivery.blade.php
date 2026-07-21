<div class="pos-overlay-panel" :class="panels.delivery ? 'show' : ''">
    <div class="pos-overlay-backdrop" @click="panels.delivery = false"></div>
    <section class="pos-panel pos-area-panel" role="dialog" aria-modal="true" aria-labelledby="pos-delivery-title">
        <header class="panel-header pos-area-panel__header">
            <span class="pos-area-panel__mark is-delivery"><i class="bx bx-cycling"></i></span>
            <div>
                <span class="pos-area-panel__eyebrow">Área operativa</span>
                <h2 id="pos-delivery-title">Delivery</h2>
                <p>Pedidos para entregar a domicilio, incluidos los realizados en kiosco.</p>
            </div>
            <button type="button" class="btn-panel-close" @click="panels.delivery = false" aria-label="Cerrar Delivery"><i class="bx bx-x"></i></button>
        </header>

        <div class="pos-area-panel__tools">
            <label class="pos-area-search">
                <i class="bx bx-search"></i>
                <span class="visually-hidden">Buscar pedido de delivery</span>
                <input type="search" class="pos-input" wire:model.live.debounce.300ms="deliverySearch" placeholder="Pedido, cliente, teléfono o dirección">
            </label>
            <div class="pos-area-summary"><strong>{{ $this->deliveryOrders->count() }}</strong><span>entregas activas</span></div>
        </div>

        <div class="panel-body pos-area-panel__body">
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
        </div>
    </section>
</div>
