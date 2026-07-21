@props(['item'])
@if($item->type === 'section')
    <li class="menu-header small text-uppercase"><span class="menu-header-text">{{ $item->label }}</span></li>
    @foreach($item->children as $child)<x-sidebar.menu-node :item="$child" />@endforeach
@elseif($item->type === 'group')
    @php
        $groupActive = $item->children->contains(fn ($child) => $child->is_current || $child->children->contains(fn ($nested) => $nested->is_current));
    @endphp
    <li class="menu-item {{ $groupActive ? 'active open' : '' }}">
        <button type="button" class="menu-link menu-toggle sidebar-parent-toggle" aria-expanded="{{ $groupActive ? 'true' : 'false' }}" aria-controls="sidebar-menu-group-{{ $item->id }}">
            @if($item->icon)<i class="menu-icon tf-icons bx {{ $item->icon }}"></i>@endif
            <div>{{ $item->label }}</div>
            <span class="sidebar-parent-chevron" aria-hidden="true"><i class="bx bx-chevron-right"></i></span>
        </button>
        <ul id="sidebar-menu-group-{{ $item->id }}" class="menu-sub">
            @foreach($item->children as $child)<x-sidebar.menu-node :item="$child" />@endforeach
        </ul>
    </li>
@elseif($item->type === 'link')
    <li class="menu-item {{ $item->is_current ? 'active' : '' }} {{ $item->register_locked ? 'is-register-locked' : '' }}">
        @if($item->register_locked)
            <button type="button" class="menu-link sidebar-register-locked" disabled aria-label="{{ $item->label }}: requiere una caja abierta" title="Requiere una caja abierta">
                @if($item->icon)<i class="menu-icon tf-icons bx {{ $item->icon }}"></i>@endif
                <div>{{ $item->label }}</div><i class="bx bx-lock-alt sidebar-register-lock-icon" aria-hidden="true"></i>
            </button>
        @else
            <a href="{{ $item->resolved_url }}" class="menu-link" @if($item->route_name) wire:navigate @endif>
                @if($item->icon)<i class="menu-icon tf-icons bx {{ $item->icon }}"></i>@endif
                <div>{{ $item->label }}</div>
            </a>
        @endif
    </li>
@endif
