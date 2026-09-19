<x-pos.area-panel panel="balances" title="Saldos pendientes" title-id="pos-balances-title"
    eyebrow="Área operativa" description="Órdenes que ya recibieron dinero pero todavía no cubren su total."
    icon="bx-time-five" tone="balances" panel-class="pos-balances" close-label="Cerrar saldos pendientes"
    close-action="panels.balances = false; $wire.close()">
    <x-slot:tools>
        <label class="pos-area-search">
            <i class="bx bx-search" aria-hidden="true"></i>
            <span class="visually-hidden">Buscar orden con saldo</span>
            <input type="search" class="pos-input" wire:model.live.debounce.400ms="search"
                placeholder="Folio, cliente o teléfono" autocomplete="off">
        </label>
    </x-slot:tools>

    {{-- Mientras el panel no ha cargado se muestra el esqueleto, nunca el estado
         vacío: abrirlo toma dos viajes (el POS cierra los demás paneles y luego
         avisa a este) y un "Sin saldos" momentáneo sería falso. Cerrar el panel
         lo regresa a no cargado, así que cada apertura empieza aquí. --}}
    @unless ($loaded)
        <x-pos.balances.skeleton />
    @else
        <div class="pos-balances-body">
            @if ($this->summary['count'] > 0)
                <x-pos.balances.summary :summary="$this->summary" :filter="$filter" />
            @endif

            <div class="pos-balance-list" aria-live="polite" aria-busy="false"
                wire:loading.class="is-refreshing" wire:target="setFilter,search,settle">
                @forelse ($this->visibleOrders as $pending)
                    <x-pos.balances.card :order="$pending" :by-driver="$this->collectedByDriver($pending)" />
                @empty
                    <div class="pos-area-empty">
                        <span><i class="bx bx-check-circle" aria-hidden="true"></i></span>
                        @if ($this->summary['count'] === 0)
                            <h3>{{ $search !== '' ? 'Sin coincidencias' : 'Sin saldos pendientes' }}</h3>
                            <p>{{ $search !== '' ? 'Ninguna orden con saldo coincide con la búsqueda.' : 'Todas las órdenes con pago cubren su total.' }}</p>
                        @else
                            <h3>Nada en este filtro</h3>
                            <p>{{ $filter === 'driver' ? 'Ningún repartidor trae efectivo pendiente.' : 'No hay saldos por cobrar en caja.' }}</p>
                            <button type="button" class="pos-balance-btn is-secondary" wire:click="setFilter('all')">Ver todas</button>
                        @endif
                    </div>
                @endforelse
            </div>
        </div>
    @endunless
</x-pos.area-panel>
