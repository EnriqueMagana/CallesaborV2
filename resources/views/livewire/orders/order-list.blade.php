<div class="app-page orders-page"
    x-data="{ toasts: [] }"
    x-on:notify.window="
        toasts.push({ id: Date.now(), type: $event.detail.type, message: $event.detail.message });
        setTimeout(() => toasts.shift(), 3500);
    ">
    <header class="orders-hero">
        <div class="orders-hero__content">
            <span class="orders-hero__icon" aria-hidden="true"><i class="bx bx-receipt"></i></span>
            <div>
                <p class="orders-eyebrow">Operación · Ventas</p>
                <h1>Órdenes</h1>
                <p>Supervisa los pedidos del turno, su avance y el canal desde el que fueron creados.</p>
            </div>
        </div>

        <div class="orders-hero__register {{ $this->activeCashRegister ? 'is-open' : 'is-closed' }}">
            <span class="orders-register-dot" aria-hidden="true"></span>
            <span>
                <small>{{ $this->activeCashRegister ? 'Caja en operación' : 'Operación detenida' }}</small>
                <strong>{{ $this->activeCashRegister?->name ?? 'Sin caja abierta' }}</strong>
            </span>
        </div>
    </header>

    <section class="orders-stats" aria-label="Resumen del turno">
        @foreach([
            ['bx-time-five', 'Pendientes', $this->statusCounts['pending'], 'warning'],
            ['bx-bowl-hot', 'En preparación', $this->statusCounts['preparing'], 'info'],
            ['bx-check-circle', 'Listas', $this->statusCounts['ready'], 'success'],
            ['bx-check-double', 'Completadas', $this->statusCounts['completed'], 'primary'],
        ] as $stat)
            <article class="orders-stat orders-stat--{{ $stat[3] }}">
                <span class="orders-stat__icon" aria-hidden="true"><i class="bx {{ $stat[0] }}"></i></span>
                <span><small>{{ $stat[1] }}</small><strong>{{ $stat[2] }}</strong></span>
            </article>
        @endforeach
    </section>

    <nav class="orders-channel-tabs" aria-label="Canal de las órdenes">
        @foreach([
            '' => ['bx-grid-alt', 'Todas', $this->channelCounts['all']],
            'ventanilla' => ['bx-store', 'Ventanilla', $this->channelCounts['ventanilla']],
            'mesa' => ['bx-table', 'Mesas', $this->channelCounts['mesa']],
            'delivery' => ['bx-cycling', 'Domicilio', $this->channelCounts['delivery']],
            'kiosk' => ['bx-desktop', 'Kiosco', $this->channelCounts['kiosk']],
        ] as $channel => $tab)
            <button type="button"
                class="orders-channel-tab {{ $typeFilter === $channel ? 'is-active' : '' }}"
                wire:click="filterByChannel('{{ $channel }}')"
                wire:loading.attr="disabled"
                aria-pressed="{{ $typeFilter === $channel ? 'true' : 'false' }}">
                <i class="bx {{ $tab[0] }}" aria-hidden="true"></i>
                <span>{{ $tab[1] }}</span>
                <b>{{ $tab[2] }}</b>
            </button>
        @endforeach
    </nav>

    <section class="orders-filter-card" aria-labelledby="orders-filter-title">
        <div class="orders-section-heading">
            <span class="orders-section-heading__icon" aria-hidden="true"><i class="bx bx-slider-alt"></i></span>
            <div>
                <h2 id="orders-filter-title">Encuentra una orden</h2>
                <p>Busca por folio o cliente y combina los filtros que necesites.</p>
            </div>
            @if($search || $statusFilter || $typeFilter || $dateFrom || $dateTo)
                <button type="button" class="orders-clear-button" wire:click="clearFilters" wire:loading.attr="disabled">
                    <i class="bx bx-reset" aria-hidden="true"></i><span>Limpiar filtros</span>
                </button>
            @endif
        </div>

        <div class="orders-filter-grid">
            <div class="orders-field orders-field--search">
                <label for="orders-search">Orden o cliente</label>
                <div class="orders-control">
                    <i class="bx bx-search" aria-hidden="true"></i>
                    <input id="orders-search" wire:model.live.debounce.400ms="search" type="search"
                        placeholder="Folio, nombre o teléfono" autocomplete="off">
                    <span class="spinner-border spinner-border-sm" wire:loading wire:target="search" aria-hidden="true"></span>
                </div>
            </div>
            <div class="orders-field">
                <label for="orders-status">Estado</label>
                <select id="orders-status" wire:model.live="statusFilter">
                    <option value="">Todos</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="en_preparacion">En preparación</option>
                    <option value="lista">Listo</option>
                    <option value="en_reparto">Recogido para entrega</option>
                    <option value="entregada">Entregado</option>
                    <option value="pagada">Pagada</option>
                    <option value="cancelada">Cancelada</option>
                </select>
            </div>
            <div class="orders-field">
                <label for="orders-type">Canal</label>
                <select id="orders-type" wire:model.live="typeFilter">
                    <option value="">Todos</option>
                    <option value="mesa">Mesa</option>
                    <option value="ventanilla">Ventanilla</option>
                    <option value="delivery">Domicilio</option>
                    <option value="kiosk">Kiosco</option>
                </select>
            </div>
            <div class="orders-field">
                <label for="orders-date-from">Desde</label>
                <input id="orders-date-from" wire:model.live="dateFrom" type="date">
            </div>
            <div class="orders-field">
                <label for="orders-date-to">Hasta</label>
                <input id="orders-date-to" wire:model.live="dateTo" type="date">
            </div>
        </div>
    </section>

    <section class="orders-results" aria-labelledby="orders-table-title">
        <header class="orders-results__header">
            <div>
                <h2 id="orders-table-title">Pedidos del turno</h2>
                <p>
                    @if($this->activeCashRegister)
                        {{ $this->orders->total() }} {{ $this->orders->total() === 1 ? 'resultado' : 'resultados' }}
                        en {{ $this->activeCashRegister->name }}.
                    @else
                        Abre una caja para comenzar a recibir y consultar pedidos.
                    @endif
                </p>
            </div>
            @if($this->orders->hasPages())
                <span class="orders-page-indicator">Página {{ $this->orders->currentPage() }} de {{ $this->orders->lastPage() }}</span>
            @endif
        </header>

        <div class="orders-results__loader"
            wire:loading.flex
            wire:target="search,statusFilter,typeFilter,dateFrom,dateTo,filterByChannel,clearFilters,gotoPage,nextPage,previousPage"
            role="status" aria-live="polite">
            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
            <span>Actualizando órdenes…</span>
        </div>

        @if($this->orders->isEmpty())
            <div class="orders-empty">
                <span aria-hidden="true"><i class="bx {{ $this->activeCashRegister ? 'bx-search-alt' : 'bx-lock-alt' }}"></i></span>
                <h3>{{ $this->activeCashRegister ? 'No encontramos órdenes' : 'La caja está cerrada' }}</h3>
                <p>
                    {{ $this->activeCashRegister
                        ? 'Prueba con otros filtros o limpia la búsqueda para volver a ver todos los pedidos.'
                        : 'Cuando abras una caja, las órdenes activas de ese turno aparecerán aquí.' }}
                </p>
                @if($this->activeCashRegister && ($search || $statusFilter || $typeFilter || $dateFrom || $dateTo))
                    <button type="button" class="orders-empty__button" wire:click="clearFilters">
                        <i class="bx bx-reset" aria-hidden="true"></i>Limpiar filtros
                    </button>
                @endif
            </div>
        @else
            <div class="orders-table-wrap d-none d-lg-block">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th scope="col">Orden y cliente</th>
                            <th scope="col">Canal</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Total</th>
                            <th scope="col">Responsable</th>
                            <th scope="col">Registro</th>
                            <th scope="col"><span class="visually-hidden">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->orders as $order)
                            @include('livewire.orders.partials.order-row', ['order' => $order])
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="orders-mobile-list d-lg-none">
                @foreach($this->orders as $order)
                    @include('livewire.orders.partials.order-card', ['order' => $order])
                @endforeach
            </div>

            @if($this->orders->hasPages())
                <footer class="orders-pagination">{{ $this->orders->links() }}</footer>
            @endif
        @endif
    </section>

    @if($showStatusModal)
        <div class="modal-backdrop app-modal-backdrop fade show" wire:click="$set('showStatusModal',false)"></div>
        <div class="modal app-modal app-modal-layer fade show d-block" tabindex="-1" role="dialog"
            aria-modal="true" aria-labelledby="status-modal-title">
            <div class="modal-dialog modal-dialog-centered app-modal-sm">
                <div class="modal-content orders-modal">
                    <div class="orders-modal__header">
                        <span class="orders-modal__icon orders-modal__icon--primary" aria-hidden="true"><i class="bx bx-transfer"></i></span>
                        <div><small>Gestión del pedido</small><h2 id="status-modal-title">Cambiar estado</h2></div>
                        <button type="button" class="orders-modal__close" wire:click="$set('showStatusModal',false)" aria-label="Cerrar"><i class="bx bx-x"></i></button>
                    </div>
                    <div class="orders-modal__body">
                        <label for="edit-order-status">Nuevo estado</label>
                        <select id="edit-order-status" wire:model="editStatus">
                            <option value="pendiente">Pendiente</option>
                            <option value="en_preparacion">En preparación</option>
                            <option value="lista">Listo</option>
                            <option value="pagada">Pagada</option>
                        </select>
                        <p>Este cambio será visible inmediatamente en el flujo operativo.</p>
                    </div>
                    <div class="orders-modal__footer">
                        <button type="button" class="orders-button orders-button--ghost" wire:click="$set('showStatusModal',false)">Cancelar</button>
                        <button type="button" class="orders-button orders-button--primary" wire:click="saveStatus" wire:loading.attr="disabled" wire:target="saveStatus">
                            <span wire:loading.remove wire:target="saveStatus"><i class="bx bx-check" aria-hidden="true"></i>Guardar estado</span>
                            <span wire:loading wire:target="saveStatus"><span class="spinner-border spinner-border-sm"></span>Guardando…</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($showCancelModal)
        <div class="modal-backdrop app-modal-backdrop fade show" wire:click="$set('showCancelModal',false)"></div>
        <div class="modal app-modal app-modal-layer fade show d-block" tabindex="-1" role="dialog"
            aria-modal="true" aria-labelledby="cancel-modal-title">
            <div class="modal-dialog modal-dialog-centered app-modal-md">
                <div class="modal-content orders-modal">
                    <div class="orders-modal__header">
                        <span class="orders-modal__icon orders-modal__icon--danger" aria-hidden="true"><i class="bx bx-x-circle"></i></span>
                        <div><small>Acción sensible</small><h2 id="cancel-modal-title">Cancelar orden</h2></div>
                        <button type="button" class="orders-modal__close" wire:click="$set('showCancelModal',false)" aria-label="Cerrar"><i class="bx bx-x"></i></button>
                    </div>
                    <div class="orders-modal__body">
                        <label for="cancel-reason">Motivo de cancelación <span aria-hidden="true">*</span></label>
                        <textarea id="cancel-reason" wire:model="cancelReason" class="@error('cancelReason') is-invalid @enderror"
                            rows="4" placeholder="Describe brevemente por qué se cancela la orden"></textarea>
                        @error('cancelReason') <div class="orders-field-error">{{ $message }}</div> @enderror
                        <p>El motivo quedará registrado para fines de seguimiento.</p>
                    </div>
                    <div class="orders-modal__footer">
                        <button type="button" class="orders-button orders-button--ghost" wire:click="$set('showCancelModal',false)">Volver</button>
                        <button type="button" class="orders-button orders-button--danger" wire:click="confirmCancel" wire:loading.attr="disabled" wire:target="confirmCancel">
                            <span wire:loading.remove wire:target="confirmCancel"><i class="bx bx-x-circle" aria-hidden="true"></i>Confirmar cancelación</span>
                            <span wire:loading wire:target="confirmCancel"><span class="spinner-border spinner-border-sm"></span>Procesando…</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="app-toast-stack" aria-live="polite" aria-atomic="true">
        <template x-for="toast in toasts" :key="toast.id">
            <div x-show="true" x-transition.opacity.duration.200ms class="toast show align-items-center border-0 text-white"
                :class="{ 'bg-success': toast.type === 'success', 'bg-danger': toast.type === 'danger', 'bg-warning': toast.type === 'warning', 'bg-info': toast.type === 'info' }" role="status">
                <div class="d-flex">
                    <div class="toast-body" x-text="toast.message"></div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto"
                        @click="toasts = toasts.filter(t => t.id !== toast.id)" aria-label="Cerrar notificación"></button>
                </div>
            </div>
        </template>
    </div>
</div>
