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
        <div class="notification-center__portal" wire:keydown.escape.window="handleEscape">
            <button type="button" class="notification-center__backdrop" wire:click="closePanel"
                aria-label="Cerrar notificaciones"></button>
            <section id="notification-panel-{{ $this->getId() }}" class="notification-center__panel" role="dialog"
                aria-modal="true" aria-labelledby="notification-title-{{ $this->getId() }}">
            <header class="notification-center__header">
                <div>
                    <span class="notification-center__eyebrow">Centro de actividad</span>
                    <h2 id="notification-title-{{ $this->getId() }}">Notificaciones</h2>
                </div>
                <div class="notification-center__header-actions">
                    @if ($this->notifications->isNotEmpty())
                        <button type="button" class="notification-center__icon-button notification-center__icon-button--danger"
                            wire:click="requestClearAll"
                            aria-label="Eliminar todas las notificaciones" title="Limpiar notificaciones">
                            <i class="bx bx-trash" aria-hidden="true"></i>
                        </button>
                    @endif
                    <button type="button" class="notification-center__icon-button" wire:click="closePanel"
                        aria-label="Cerrar">
                        <i class="bx bx-x" aria-hidden="true"></i>
                    </button>
                </div>
            </header>

            <div class="notification-center__list" aria-live="polite" wire:loading.class="is-loading">
                @forelse ($this->notifications as $notification)
                    <article
                        class="notification-item {{ $notification->read_at ? '' : 'is-unread' }} notification-item--{{ $notification->priority }}"
                        wire:key="notification-{{ $notification->id }}">
                        <button type="button" class="notification-item__main"
                            wire:click="openNotification('{{ $notification->id }}')">
                            <span class="notification-item__icon notification-item__icon--{{ $notification->tone }}"><i class="bx {{ $notification->icon }}"
                                    aria-hidden="true"></i></span>
                            <span class="notification-item__copy">
                                <strong>{{ $notification->data['title'] ?? 'Notificación' }}</strong>
                                <span>{{ $notification->data['message'] ?? '' }}</span>
                                <small>{{ $notification->created_at->diffForHumans() }}</small>
                            </span>
                            @if (!$notification->read_at)
                                <span class="notification-item__dot"><span class="visually-hidden">Sin
                                        leer</span></span>
                            @else
                                <i class="bx bx-chevron-right notification-item__chevron" aria-hidden="true"></i>
                            @endif
                        </button>
                        @if (!$notification->read_at)
                            <button type="button" class="notification-item__read"
                                wire:click="markRead('{{ $notification->id }}')" aria-label="Marcar como leída"
                                title="Marcar como leída"><i class="bx bx-check" aria-hidden="true"></i></button>
                        @endif
                        @if ($actionLabel = $this->actionLabel($notification))
                            <button type="button" class="notification-item__action"
                                wire:click="performAction('{{ $notification->id }}')"
                                wire:loading.attr="disabled" wire:target="performAction('{{ $notification->id }}')">
                                <span wire:loading.remove wire:target="performAction('{{ $notification->id }}')">
                                    <i class="bx {{ $notification->event_key === 'delivery.available' ? 'bx-user-check' : ($notification->subject?->type === 'mesa' ? 'bx-table' : 'bx-right-arrow-alt') }}" aria-hidden="true"></i>
                                    {{ $actionLabel }}
                                </span>
                                <span wire:loading wire:target="performAction('{{ $notification->id }}')">
                                    <i class="bx bx-loader-alt bx-spin" aria-hidden="true"></i> Procesandoâ€¦
                                </span>
                            </button>
                        @endif
                    </article>
                @empty
                    <div class="notification-center__empty">
                        <span><i class="bx bx-bell-off" aria-hidden="true"></i></span>
                        <h3>Todo al día</h3>
                        <p>No tienes notificaciones pendientes.</p>
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

            @if ($confirmingClearAll)
                <div class="notification-clear-dialog" wire:key="notification-clear-dialog"
                    x-data x-init="$nextTick(() => $refs.cancelClearNotifications.focus())">
                    <button type="button" class="notification-clear-dialog__backdrop"
                        wire:click="cancelClearAll" aria-label="Cancelar y volver a las notificaciones"></button>
                    <section class="notification-clear-dialog__card" role="alertdialog" aria-modal="true"
                        aria-labelledby="notification-clear-title-{{ $this->getId() }}"
                        aria-describedby="notification-clear-description-{{ $this->getId() }}">
                        <div class="notification-clear-dialog__visual" aria-hidden="true">
                            <i class="bx bx-trash"></i>
                        </div>
                        <div class="notification-clear-dialog__content">
                            <span class="notification-clear-dialog__eyebrow">Acción irreversible</span>
                            <h3 id="notification-clear-title-{{ $this->getId() }}">¿Eliminar todas las notificaciones?</h3>
                            <p id="notification-clear-description-{{ $this->getId() }}">
                                Se borrarán permanentemente todos tus mensajes, tanto leídos como pendientes.
                            </p>
                            <div class="notification-clear-dialog__warning">
                                <i class="bx bx-error-circle" aria-hidden="true"></i>
                                <span>Esta acción elimina los registros de la base de datos y no se puede deshacer.</span>
                            </div>
                        </div>
                        <div class="notification-clear-dialog__actions">
                            <button type="button" class="notification-clear-dialog__cancel"
                                x-ref="cancelClearNotifications" wire:click="cancelClearAll">
                                Conservar mensajes
                            </button>
                            <button type="button" class="notification-clear-dialog__confirm"
                                wire:click="clearAll" wire:loading.attr="disabled" wire:target="clearAll">
                                <span wire:loading.remove wire:target="clearAll"><i class="bx bx-trash" aria-hidden="true"></i>Eliminar todo</span>
                                <span wire:loading wire:target="clearAll"><i class="bx bx-loader-alt bx-spin" aria-hidden="true"></i>Eliminando…</span>
                            </button>
                        </div>
                    </section>
                </div>
            @endif
        </div>
        @endteleport
    @endif
</div>
