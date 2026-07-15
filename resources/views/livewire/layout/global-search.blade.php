<div class="gs-wrap" x-data="{ open: false }" @click.outside="open = false; $wire.clear()">

    {{-- Trigger visible en desktop, ícono en móvil --}}
    <div class="gs-input-wrap" @click="open = true">
        <i class="bx bx-search gs-icon"></i>
        <input
            type="text"
            class="gs-input"
            placeholder="Buscar módulos, pedidos, clientes…"
            wire:model.live.debounce.300ms="query"
            @focus="open = true"
            @keydown.escape="open = false; $wire.clear()"
            autocomplete="off"
            spellcheck="false"
        >
        @if($query)
            <button class="gs-clear" wire:click="clear" @click.stop type="button">
                <i class="bx bx-x"></i>
            </button>
        @endif
    </div>

    {{-- Dropdown de resultados --}}
    @if($query && strlen($query) >= 2)
    <div class="gs-dropdown" x-show="open" x-transition:enter="gs-enter" x-transition:enter-start="gs-enter-start" x-transition:enter-end="gs-enter-end">

        @php
            $res   = $this->results;
            $empty = collect($res)->flatten(1)->isEmpty();

            $groups = [
                'nav'          => ['label' => 'Módulos',       'color' => 'var(--bs-primary)'],
                'orders'       => ['label' => 'Pedidos',       'color' => '#10b981'],
                'customers'    => ['label' => 'Clientes',      'color' => '#f59e0b'],
                'reservations' => ['label' => 'Reservaciones', 'color' => '#8b5cf6'],
                'products'     => ['label' => 'Productos',     'color' => '#ef4444'],
            ];
        @endphp

        <div wire:loading wire:target="query" class="gs-loading">
            <span class="spinner-border spinner-border-sm text-primary me-2"></span>
            Buscando…
        </div>

        <div wire:loading.remove wire:target="query">
            @if($empty)
                <div class="gs-empty">
                    <i class="bx bx-search-alt"></i>
                    <span>Sin resultados para <strong>"{{ $query }}"</strong></span>
                </div>
            @else
                @foreach($groups as $key => $group)
                    @if(!empty($res[$key]))
                        <div class="gs-group-label">{{ $group['label'] }}</div>
                        @foreach($res[$key] as $item)
                            <a href="{{ $item['url'] }}"
                               class="gs-item"
                               wire:navigate
                               @click="open = false; $wire.clear()">
                                <span class="gs-item-icon" style="background:{{ $group['color'] }}1a;color:{{ $group['color'] }}">
                                    <i class="bx {{ $item['icon'] }}"></i>
                                </span>
                                <span class="gs-item-body">
                                    <span class="gs-item-label">{{ $item['label'] }}</span>
                                    <span class="gs-item-desc">{{ $item['description'] }}</span>
                                </span>
                                <i class="bx bx-chevron-right gs-item-arrow"></i>
                            </a>
                        @endforeach
                    @endif
                @endforeach
            @endif
        </div>

    </div>
    @endif

</div>
