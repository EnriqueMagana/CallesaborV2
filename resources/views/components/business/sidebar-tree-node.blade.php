@props(['item', 'depth' => 0])
<article class="sidebar-tree-node sidebar-depth-{{ min($depth, 3) }} {{ $item->is_active ? '' : 'is-disabled' }}" wire:key="sidebar-item-{{ $item->id }}">
    <div class="sidebar-tree-node__row">
        <span class="sidebar-tree-node__move-handle" aria-hidden="true"><i class="bx bx-grid-vertical"></i></span>
        <span class="sidebar-tree-node__icon"><i class="bx {{ $item->icon ?: ($item->type === 'section' ? 'bx-heading' : 'bx-circle') }}"></i></span>
        <span class="sidebar-tree-node__copy">
            <strong>{{ $item->label }}</strong>
            <small><b>{{ match($item->type) { 'section' => 'Sección principal', 'group' => 'Grupo padre', default => 'Módulo' } }}</b>{{ $item->type === 'link' ? ' · '.($item->route_name ?: $item->url) : '' }}</small>
        </span>
        @if($item->permission)<span class="sidebar-permission-chip"><i class="bx bx-lock-alt"></i>{{ $item->permission }}</span>@endif
        @if($item->requires_open_register)<span class="sidebar-register-chip"><i class="bx bx-wallet"></i>Requiere caja</span>@endif
        <div class="sidebar-tree-node__actions">
            @can('editar menu sidebar')
                <button type="button" wire:click="moveItem({{ $item->id }}, -1)" aria-label="Subir {{ $item->label }}" @disabled($item->is_first_sibling)><i class="bx bx-up-arrow-alt"></i></button>
                <button type="button" wire:click="moveItem({{ $item->id }}, 1)" aria-label="Bajar {{ $item->label }}" @disabled($item->is_last_sibling)><i class="bx bx-down-arrow-alt"></i></button>
                <button type="button" wire:click="editItem({{ $item->id }})" aria-label="Editar o mover {{ $item->label }}" title="Editar o mover"><i class="bx bx-edit-alt"></i></button>
            @endcan
            @can('eliminar menu sidebar')
                <button type="button" class="is-danger" wire:click="confirmDelete({{ $item->id }})" aria-label="Eliminar {{ $item->label }}"><i class="bx bx-trash"></i></button>
            @endcan
        </div>
    </div>
    @if($item->children->isNotEmpty())
        <div class="sidebar-tree-node__children">
            @foreach($item->children as $child)
                <x-business.sidebar-tree-node :item="$child" :depth="$depth + 1" />
            @endforeach
        </div>
    @endif
</article>
