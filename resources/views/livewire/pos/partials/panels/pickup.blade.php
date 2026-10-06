<x-pos.area-panel panel="pickup" title="Pedidos no pagados" title-id="pos-window-title"
    eyebrow="Área operativa" description="Pedidos en sucursal, teléfono, WhatsApp y kiosco para recoger."
    icon="bx-receipt" tone="window" close-label="Cerrar pedidos no pagados"
    close-action="panels.pickup = false; $wire.closeOperationalPanels()">
        <x-slot:tools>
            <label class="pos-area-search">
                <i class="bx bx-search"></i>
                <span class="visually-hidden">Buscar pedido de ventanilla</span>
                <input type="search" class="pos-input" wire:model.live.debounce.600ms="pickupSearch" placeholder="Pedido, nombre o teléfono">
            </label>
            <div class="pos-area-summary" aria-live="polite">
                <strong wire:loading.remove wire:target="openPickupPanel">{{ $this->pickupOrders->count() }}</strong>
                <strong wire:loading wire:target="openPickupPanel" aria-hidden="true">—</strong>
                <span wire:loading.remove wire:target="openPickupPanel">órdenes activas</span>
                <span wire:loading wire:target="openPickupPanel">consultando</span>
            </div>
        </x-slot:tools>

        <div wire:loading.flex wire:target="openPickupPanel"
            class="pos-skeleton-list" aria-label="Consultando pedidos por cobrar">
            @for ($s = 0; $s < 2; $s++)
                <div class="pos-table-skeleton"><span></span><div><i></i><i></i><i></i></div></div>
            @endfor
        </div>

        <div wire:loading.remove wire:target="openPickupPanel">
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
</x-pos.area-panel>
