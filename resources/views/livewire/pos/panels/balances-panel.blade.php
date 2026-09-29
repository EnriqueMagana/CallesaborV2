<x-pos.area-panel panel="balances" title="Saldos pendientes" title-id="pos-balances-title"
    eyebrow="Área operativa" description="Diferencias por cobrar de pedidos de ventanilla o para recoger."
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

    @unless ($loaded)
        <x-pos.balances.skeleton />
    @else
        <div class="pos-balances-body">
            @if ($this->summary['count'] > 0)
                <x-pos.balances.summary :summary="$this->summary" />
            @endif

            <div class="pos-balance-list" aria-live="polite" aria-busy="false"
                wire:loading.class="is-refreshing" wire:target="search">
                @forelse ($this->orders as $pending)
                    <x-pos.balances.card :order="$pending" />
                @empty
                    <div class="pos-area-empty">
                        <span><i class="bx bx-check-circle" aria-hidden="true"></i></span>
                        <h3>{{ $search !== '' ? 'Sin coincidencias' : 'Sin saldos pendientes' }}</h3>
                        <p>{{ $search !== '' ? 'Ninguna orden de ventanilla con saldo coincide con la búsqueda.' : 'No hay diferencias pendientes por cobrar en ventanilla.' }}</p>
                    </div>
                @endforelse
            </div>
        </div>
    @endunless
</x-pos.area-panel>
