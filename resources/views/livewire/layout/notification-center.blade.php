<div class="notification-center notification-center--{{ $placement }}" data-notification-center>
    <button type="button" class="notification-center__trigger" wire:click="togglePanel"
        aria-label="Notificaciones{{ $this->unreadCount ? ', ' . $this->unreadCount . ' sin leer' : '' }}"
        aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="notification-panel-{{ $this->getId() }}">
        <i class="bx bx-bell" aria-hidden="true"></i>
        @if ($this->unreadCount > 0)
            <span class="notification-center__badge"
                aria-hidden="true">{{ $this->unreadCount > 99 ? '99+' : $this->unreadCount }}</span>
        @endif
        @if ($placement === 'pos')
            <span class="notification-center__trigger-label"></span>
        @endif
    </button>

    @if ($open)
        @teleport('body')
        <div class="notification-center__portal" wire:keydown.escape.window="closePanel">
            <button type="button" class="notification-center__backdrop" wire:click="closePanel"
                aria-label="Cerrar notificaciones"></button>
            <section id="notification-panel-{{ $this->getId() }}" class="notification-center__panel" role="dialog"
                aria-modal="true" aria-labelledby="notification-title-{{ $this->getId() }}">
            <header class="notification-center__header">
                <div>
                    <span class="notification-center__eyebrow">Centro de actividad</span>
                    <h2 id="notification-title-{{ $this->getId() }}">Notificaciones</h2>
                </div>
                <button type="button" class="notification-center__icon-button" wire:click="closePanel"
                    aria-label="Cerrar">
                    <i class="bx bx-x" aria-hidden="true"></i>
                </button>
            </header>

            <nav class="notification-center__filters" aria-label="Filtrar notificaciones">
                @foreach (['all' => 'Todas', 'orders' => 'Pedidos', 'tables' => 'Mesas', 'delivery' => 'Delivery', 'system' => 'Sistema'] as $key => $label)
                    <button type="button" wire:click="setFilter('{{ $key }}')"
                        class="{{ $filter === $key ? 'is-active' : '' }}"
                        aria-pressed="{{ $filter === $key ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </nav>

            <div class="notification-center__list" aria-live="polite" wire:loading.class="is-loading">
                @forelse ($this->notifications as $notification)
                    @php
                        $icon = match ($notification->category) {
                            'tables' => 'bx-table',
                            'delivery' => 'bx-cycling',
                            'system' => 'bx-error-circle',
                            default => 'bx-receipt',
                        };
                    @endphp
                    <article
                        class="notification-item {{ $notification->read_at ? '' : 'is-unread' }} notification-item--{{ $notification->priority }}"
                        wire:key="notification-{{ $notification->id }}">
                        <button type="button" class="notification-item__main"
                            wire:click="openNotification('{{ $notification->id }}')">
                            <span class="notification-item__icon"><i class="bx {{ $icon }}"
                                    aria-hidden="true"></i></span>
                            <span class="notification-item__copy">
                                <strong>{{ $notification->data['title'] ?? 'Notificación' }}</strong>
                                <span>{{ $notification->data['message'] ?? '' }}</span>
                                <small>{{ $notification->created_at->diffForHumans() }}</small>
                            </span>
                            @if (!$notification->read_at)
                                <span class="notification-item__dot"><span class="visually-hidden">Sin
                                        leer</span></span>
                            @endif
                        </button>
                        @if (!$notification->read_at)
                            <button type="button" class="notification-item__read"
                                wire:click="markRead('{{ $notification->id }}')" aria-label="Marcar como leída"
                                title="Marcar como leída"><i class="bx bx-check" aria-hidden="true"></i></button>
                        @endif
                    </article>
                @empty
                    <div class="notification-center__empty">
                        <span><i class="bx bx-bell-off" aria-hidden="true"></i></span>
                        <h3>Todo al día</h3>
                        <p>No hay notificaciones en este apartado.</p>
                    </div>
                @endforelse
            </div>

            @if ($this->unreadCount > 0)
                <footer class="notification-center__footer">
                    <button type="button" wire:click="markAllRead"><i class="bx bx-check-double"
                            aria-hidden="true"></i> Marcar todas como leídas</button>
                </footer>
            @endif
            </section>
        </div>
        @endteleport
    @endif
</div>
