<div class="pos-overlay-panel" :class="panels.pickup ? 'show' : ''">
    <div class="pos-overlay-backdrop" @click="panels.pickup = false"></div>
    <section class="pos-panel pos-area-panel" role="dialog" aria-modal="true" aria-labelledby="pos-window-title">
        <header class="panel-header pos-area-panel__header">
            <span class="pos-area-panel__mark is-window"><i class="bx bx-receipt"></i></span>
            <div>
                <span class="pos-area-panel__eyebrow">Área operativa</span>
                <h2 id="pos-window-title">Pedidos no pagados</h2>
                <p>Pedidos en sucursal, teléfono, WhatsApp y kiosco para recoger.</p>
            </div>
            <button type="button" class="btn-panel-close" @click="panels.pickup = false" aria-label="Cerrar pedidos no pagados"><i class="bx bx-x"></i></button>
        </header>

        <div class="pos-area-panel__tools">
            <label class="pos-area-search">
                <i class="bx bx-search"></i>
                <span class="visually-hidden">Buscar pedido de ventanilla</span>
                <input type="search" class="pos-input" wire:model.live.debounce.300ms="pickupSearch" placeholder="Pedido, nombre o teléfono">
            </label>
            <div class="pos-area-summary"><strong>{{ $this->pickupOrders->count() }}</strong><span>órdenes activas</span></div>
        </div>

        <div class="panel-body pos-area-panel__body">
            @forelse ($this->pickupOrders as $po)
                @include('livewire.pos.partials.order-flow-card', [
                    'flowOrder' => $po,
                    'flowArea' => $po->type === 'delivery' ? 'Delivery' : 'Ventanilla',
                    'flowIcon' => $po->type === 'delivery' ? 'bx-cycling' : 'bx-store-alt',
                    'showDeliveryData' => $po->type === 'delivery',
                    'allowConvertToDelivery' => true,
                    'flowSourceLabel' => $po->source === 'kiosk' ? 'Kiosco' : 'Atención',
                ])
            @empty
                <div class="pos-area-empty">
                    <span><i class="bx bx-check-circle"></i></span>
                    <h3>Ventanilla al día</h3>
                    <p>No hay pedidos pendientes para preparar o cobrar.</p>
                </div>
            @endforelse
        </div>
    </section>
</div>
