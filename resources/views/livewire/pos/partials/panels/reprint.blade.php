<div class="pos-overlay-panel" :class="panels.reprint ? 'show' : ''">
    <div class="pos-overlay-backdrop" @click="panels.reprint = false"></div>
    <section class="pos-panel pos-area-panel pos-reprint-panel" role="dialog" aria-modal="true" aria-labelledby="pos-reprint-title">
        <header class="panel-header pos-area-panel__header">
            <span class="pos-area-panel__mark is-reprint"><i class="bx bx-printer"></i></span>
            <div>
                <span class="pos-area-panel__eyebrow">Documentos</span>
                <h2 id="pos-reprint-title">Reimprimir tickets</h2>
                <p>Cocina imprime productos por área; Cliente muestra productos, precios y total.</p>
            </div>
            <button type="button" class="btn-panel-close" @click="panels.reprint = false" aria-label="Cerrar Reimpresión"><i class="bx bx-x"></i></button>
        </header>

        <div class="pos-reprint-tabs" role="tablist" aria-label="Filtrar pedidos por área">
            @foreach (['ventanilla' => ['Ventanilla', 'bx-store-alt'], 'delivery' => ['Delivery', 'bx-cycling'], 'mesas' => ['Mesas', 'bx-table']] as $type => [$label, $icon])
                <button type="button" wire:click="$set('reprintType', '{{ $type }}')"
                    class="pos-reprint-tab {{ $reprintType === $type ? 'is-active' : '' }}" role="tab" aria-selected="{{ $reprintType === $type ? 'true' : 'false' }}">
                    <i class="bx {{ $icon }}"></i>{{ $label }}
                </button>
            @endforeach
        </div>

        <div class="pos-area-panel__tools">
            <label class="pos-area-search">
                <i class="bx bx-search"></i>
                <span class="visually-hidden">Buscar orden para reimprimir</span>
                <input type="search" class="pos-input" wire:model.live.debounce.300ms="reprintSearch" placeholder="Número de pedido o cliente">
            </label>
        </div>

        <div class="panel-body pos-area-panel__body">
            @if ($reprintType === 'mesas')
                @forelse ($this->reprintMesaGroups as $group)
                    <article class="pos-reprint-table-group">
                        <header>
                            <div><span><i class="bx bx-table"></i></span><div><strong>{{ $group->mesa?->display_name ?: 'Sin mesa asignada' }}</strong><small>{{ $group->orders->count() }} {{ $group->orders->count() === 1 ? 'orden' : 'órdenes' }}</small></div></div>
                            <strong>${{ number_format($group->total, 2) }}</strong>
                        </header>
                        <div class="pos-reprint-order-list">
                            @foreach ($group->orders as $ro)
                                <div class="pos-reprint-order-row" wire:key="reprint-table-order-{{ $ro->id }}">
                                    <div><strong>#{{ $ro->id }}</strong><span>{{ $ro->customer_name ?: 'Cliente de mesa' }} · {{ $ro->created_at->format('H:i') }}</span></div>
                                    <div class="pos-reprint-actions">
                                        <button type="button" wire:click="openReprintModal({{ $ro->id }})" @click="panels.reprint = false" class="pos-btn pos-btn-secondary"><i class="bx bx-receipt"></i>Cliente</button>
                                        <button type="button" wire:click="reprintKitchenOrder({{ $ro->id }})" @click="panels.reprint = false" class="pos-btn pos-btn-primary"><i class="bx bx-dish"></i>Cocina</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </article>
                @empty
                    <div class="pos-area-empty"><span><i class="bx bx-printer"></i></span><h3>Sin órdenes de mesa</h3><p>No encontramos tickets de hoy con ese criterio.</p></div>
                @endforelse
            @else
                @forelse ($this->recentOrders as $ro)
                    <article class="pos-reprint-order-card" wire:key="reprint-order-{{ $ro->id }}">
                        <div class="pos-reprint-order-card__identity">
                            <span><i class="bx {{ $reprintType === 'delivery' ? 'bx-cycling' : 'bx-store-alt' }}"></i></span>
                            <div><strong>Orden #{{ $ro->display_folio }}</strong><small>{{ $ro->customer_name ?: 'Cliente sin nombre' }} · {{ $ro->created_at->format('H:i') }}</small><b>${{ number_format($ro->total, 2) }}</b></div>
                        </div>
                        <div class="pos-reprint-actions">
                            <button type="button" wire:click="openReprintModal({{ $ro->id }})" @click="panels.reprint = false" class="pos-btn pos-btn-secondary"><i class="bx bx-receipt"></i>Cliente</button>
                            <button type="button" wire:click="reprintKitchenOrder({{ $ro->id }})" @click="panels.reprint = false" class="pos-btn pos-btn-primary"><i class="bx bx-dish"></i>Cocina</button>
                        </div>
                    </article>
                @empty
                    <div class="pos-area-empty"><span><i class="bx bx-printer"></i></span><h3>Sin pedidos para reimprimir</h3><p>No encontramos tickets de hoy con ese criterio.</p></div>
                @endforelse
            @endif
        </div>
    </section>
</div>
