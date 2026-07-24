<header class="dashboard-welcome">
    <div class="dashboard-identity">
        <a href="{{ route('profile') }}" class="dashboard-avatar" aria-label="Ver mi perfil" wire:navigate>
            @if($avatar)
                <img src="{{ $avatar }}" alt="Foto de perfil de {{ $user->name }}">
            @else
                <span aria-hidden="true">{{ $initials }}</span>
            @endif
            <i class="bx bx-check" aria-hidden="true"></i>
        </a>
        <div>
            <div class="dashboard-context">
                <span><i class="bx {{ $dashboard['profile']['icon'] }}" aria-hidden="true"></i>{{ $dashboard['profile']['label'] }}</span>
                <span>{{ now()->translatedFormat('l d \d\e F') }}</span>
            </div>
            <h1>Hola, {{ str($user->name)->before(' ') }}</h1>
            <p><strong>{{ $dashboard['profile']['title'] }}.</strong> {{ $dashboard['profile']['subtitle'] }}</p>
        </div>
    </div>

    <div class="dashboard-welcome__aside">
        @if($dashboard['can_manage_kiosks'])
            <div
                class="dashboard-kiosk-switcher"
                x-data="{ open: false }"
                @click.outside="open = false"
                @keydown.escape.window="open = false"
            >
                <button
                    type="button"
                    class="dashboard-kiosk-trigger"
                    @click="open = ! open"
                    :aria-expanded="open.toString()"
                    aria-controls="dashboard-active-kiosks"
                >
                    <i class="bx bx-desktop" aria-hidden="true"></i>
                    <span>Kioscos activos</span>
                    <strong>{{ $dashboard['active_kiosks']->count() }}</strong>
                    <i class="bx bx-chevron-down" aria-hidden="true"></i>
                </button>

                <div
                    id="dashboard-active-kiosks"
                    class="dashboard-kiosk-menu"
                    x-cloak
                    x-show="open"
                    x-transition.opacity.duration.150ms
                    role="menu"
                    aria-label="Kioscos activos disponibles"
                >
                    <div class="dashboard-kiosk-menu__header">
                        <span>Terminales disponibles</span>
                        <small>{{ $dashboard['active_kiosks']->count() }} {{ $dashboard['active_kiosks']->count() === 1 ? 'activo' : 'activos' }}</small>
                    </div>

                    @forelse($dashboard['active_kiosks'] as $kiosk)
                        <a
                            href="{{ route('app.kioscos.open', $kiosk) }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            role="menuitem"
                            @click="open = false"
                            aria-label="Abrir {{ $kiosk->name }} en una pestaña nueva"
                        >
                            <i class="bx bx-desktop" aria-hidden="true"></i>
                            <span>
                                <strong>{{ $kiosk->name }}</strong>
                                <small>{{ $kiosk->last_used_at ? 'Usado '.$kiosk->last_used_at->diffForHumans() : 'Listo para abrir' }}</small>
                            </span>
                            <i class="bx bx-link-external" aria-hidden="true"></i>
                        </a>
                    @empty
                        <div class="dashboard-kiosk-menu__empty">
                            <i class="bx bx-power-off" aria-hidden="true"></i>
                            <span><strong>No hay kioscos activos</strong><small>Activa uno desde la configuración.</small></span>
                        </div>
                    @endforelse

                    <a href="{{ route('app.kioscos') }}" class="dashboard-kiosk-manage" wire:navigate>
                        <i class="bx bx-cog" aria-hidden="true"></i>
                        <span><strong>Administrar kioscos</strong><small>Configuración y accesos</small></span>
                        <i class="bx bx-chevron-right" aria-hidden="true"></i>
                    </a>
                </div>
            </div>
        @endif
        <span class="dashboard-role"><i class="bx bx-id-card" aria-hidden="true"></i>{{ $dashboard['role_label'] }}</span>
        <button type="button" class="dashboard-refresh" wire:click="refreshDashboard" wire:loading.attr="disabled" wire:target="refreshDashboard" aria-label="Actualizar dashboard">
            <i class="bx bx-refresh" aria-hidden="true"></i><span>Actualizar</span>
        </button>
    </div>
</header>
