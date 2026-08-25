<nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)" aria-label="Abrir menú principal">
            <i class="bx bx-menu bx-sm" aria-hidden="true"></i>
        </a>
    </div>

    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
        <div class="navbar-nav align-items-center flex-grow-1 me-3">
            <livewire:layout.global-search />
        </div>

        <ul class="navbar-nav flex-row align-items-center ms-auto">
            <li class="nav-item me-1">
                <livewire:layout.notification-center placement="navbar" />
            </li>
            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <button type="button" class="nav-link dropdown-toggle hide-arrow app-navbar-profile-trigger" data-bs-toggle="dropdown" aria-label="Abrir menú de usuario" aria-expanded="false">
                    <span class="avatar avatar-online app-navbar-avatar">
                        @if(auth()->user()?->avatar)
                            <img src="{{ Storage::url(auth()->user()->avatar) }}" alt="Foto de {{ auth()->user()->name }}" class="rounded-circle app-navbar-avatar-image">
                        @else
                            <span class="avatar-initial rounded-circle bg-label-primary">{{ auth()->user() ? mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) : 'U' }}</span>
                        @endif
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end app-navbar-user-menu">
                    <li>
                        <a class="dropdown-item app-navbar-user-summary" href="{{ route('profile') }}" wire:navigate>
                            <span class="d-flex align-items-center">
                                <span class="avatar avatar-online app-navbar-avatar app-navbar-avatar--menu flex-shrink-0 me-3">
                                    @if(auth()->user()?->avatar)
                                        <img src="{{ Storage::url(auth()->user()->avatar) }}" alt="" class="rounded-circle app-navbar-avatar-image">
                                    @else
                                        <span class="avatar-initial rounded-circle bg-label-primary">{{ auth()->user() ? mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) : 'U' }}</span>
                                    @endif
                                </span>
                                <span class="flex-grow-1 app-navbar-user-copy">
                                    <strong>{{ auth()->user()?->name }}</strong>
                                    <small>{{ auth()->user()?->email }}</small>
                                </span>
                            </span>
                        </a>
                    </li>
                    <li><div class="dropdown-divider"></div></li>
                    <li>
                        <a class="dropdown-item" href="{{ route('profile') }}" wire:navigate>
                            <i class="bx bx-user me-2" aria-hidden="true"></i><span>Mi perfil</span>
                        </a>
                    </li>
                    {{-- Modo oscuro pausado temporalmente; la interfaz opera solo en modo claro.
                    <li>
                        <button type="button" class="dropdown-item app-theme-toggle" data-theme-toggle aria-label="Cambiar a modo oscuro" aria-pressed="false">
                            <i class="bx bx-moon me-2" data-theme-toggle-icon aria-hidden="true"></i>
                            <span data-theme-toggle-label>Modo oscuro</span>
                        </button>
                    </li>
                    --}}
                    <li><div class="dropdown-divider"></div></li>
                    <li>
                        <button type="button" class="dropdown-item" wire:click="logout" wire:loading.attr="disabled" wire:target="logout">
                            <i class="bx bx-power-off me-2" aria-hidden="true"></i>
                            <span wire:loading.remove wire:target="logout">Cerrar sesión</span>
                            <span wire:loading wire:target="logout">Cerrando…</span>
                        </button>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</nav>
