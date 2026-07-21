<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
    <div class="app-brand demo">
        <a href="{{ route('app.dashboard') }}" class="app-brand-link">
            <span class="app-brand-logo demo">
                @if($businessSettings?->logo_path)
                    <img src="{{ Storage::url($businessSettings->logo_path) }}" alt="Logo de {{ $businessSettings->business_name }}" class="biz-sidebar-logo">
                @else
                    <span class="biz-sidebar-logo-fallback"><i class="bx bx-bowl-hot"></i></span>
                @endif
            </span>
            <span class="app-brand-text demo menu-text fw-bolder ms-2">{{ $businessSettings?->platform_name ?? config('app.name') }}</span>
        </a>
        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none" aria-label="Cerrar menú lateral"><i class="bx bx-chevron-left bx-sm align-middle"></i></a>
    </div>

    <div class="menu-inner-shadow"></div>
    <ul class="menu-inner py-1">
        @foreach($sidebarItems as $item)
            <x-sidebar.menu-node :item="$item" />
        @endforeach

        <li class="menu-header small text-uppercase"><span class="menu-header-text">Sesión</span></li>
        <li class="menu-item">
            <button type="button" class="menu-link sidebar-logout-button" wire:click="logout" wire:loading.attr="disabled" wire:target="logout">
                <i class="menu-icon tf-icons bx bx-power-off"></i><span>Cerrar sesión</span>
            </button>
        </li>
    </ul>
</aside>
