<div class="app-page orders-page"
    x-data="{ toasts: [] }"
    x-on:notify.window="
        toasts.push({ id: Date.now(), type: $event.detail.type, message: $event.detail.message });
        setTimeout(() => toasts.shift(), 3500);
    ">

    <header class="app-page-header">
        <div class="app-page-heading">
            <span class="app-page-icon" aria-hidden="true"><i class="bx bx-receipt"></i></span>
            <div>
                <div class="app-eyebrow">Operación · Ventas</div>
                <h1 class="app-page-title">Órdenes</h1>
                <p class="app-page-subtitle">Órdenes de la caja abierta. Las ventas cerradas se conservan en el historial de caja.</p>
            </div>
        </div>
        <div class="app-page-actions">
            <span class="app-count-pill">
                <i class="bx bx-list-ul" aria-hidden="true"></i>
                {{ $this->orders->total() }} {{ $this->orders->total() === 1 ? 'resultado' : 'resultados' }}
            </span>
        </div>
    </header>

    <nav class="orders-channel-tabs" aria-label="Canal de las órdenes">
        @foreach([
            '' => ['bx-grid-alt', 'Todas', $this->channelCounts['all']],
            'ventanilla' => ['bx-store', 'Ventanilla', $this->channelCounts['ventanilla']],
            'mesa' => ['bx-table', 'Mesas', $this->channelCounts['mesa']],
            'delivery' => ['bx-cycling', 'Domicilio', $this->channelCounts['delivery']],
            'kiosk' => ['bx-desktop', 'Kiosco', $this->channelCounts['kiosk']],
        ] as $channel => $tab)
            <button type="button" class="orders-channel-tab {{ $typeFilter === $channel ? 'is-active' : '' }} {{ $channel === 'kiosk' ? 'is-kiosk' : '' }}" wire:click="filterByChannel('{{ $channel }}')" aria-pressed="{{ $typeFilter === $channel ? 'true' : 'false' }}">
                <i class="bx {{ $tab[0] }}" aria-hidden="true"></i><span>{{ $tab[1] }}</span><b>{{ $tab[2] }}</b>
            </button>
        @endforeach
    </nav>

    <section class="app-card app-filter-card" aria-labelledby="orders-filter-title">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
            <div>
                <h2 id="orders-filter-title" class="app-card-title">Filtros</h2>
                <p class="app-card-description">Acota los resultados por cliente, estado, tipo o fecha.</p>
            </div>
            <i class="bx bx-slider-alt fs-4 text-primary" aria-hidden="true"></i>
        </div>

        <div class="row g-3 align-items-end">
            <div class="col-sm-6 col-xl-3">
                <label class="form-label" for="orders-search">Orden o cliente</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bx bx-search" aria-hidden="true"></i></span>
                    <input id="orders-search" wire:model.live.debounce.400ms="search" type="search" class="form-control"
                        placeholder="# orden, nombre o teléfono">
                </div>
            </div>
            <div class="col-sm-6 col-xl-2">
                <label class="form-label" for="orders-status">Estado</label>
                <select id="orders-status" wire:model.live="statusFilter" class="form-select">
                    <option value="">Todos los estados</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="en_preparacion">En preparación</option>
                    <option value="lista">Listo</option>
                    <option value="en_reparto">Delivery asignado</option>
                    <option value="entregada">Entregado</option>
                    <option value="pagada">Pagada</option>
                    <option value="cancelada">Cancelada</option>
                </select>
            </div>
            <div class="col-sm-6 col-xl-2">
                <label class="form-label" for="orders-type">Canal</label>
                <select id="orders-type" wire:model.live="typeFilter" class="form-select">
                    <option value="">Todos los canales</option>
                    <option value="mesa">Mesa</option>
                    <option value="ventanilla">Ventanilla</option>
                    <option value="delivery">Delivery</option>
                    <option value="kiosk">Kiosco</option>
                </select>
            </div>
            <div class="col-sm-6 col-xl-2">
                <label class="form-label" for="orders-date-from">Desde</label>
                <input id="orders-date-from" wire:model.live="dateFrom" type="date" class="form-control">
            </div>
            <div class="col-sm-6 col-xl-2">
                <label class="form-label" for="orders-date-to">Hasta</label>
                <input id="orders-date-to" wire:model.live="dateTo" type="date" class="form-control">
            </div>
            <div class="col-sm-6 col-xl-1">
                <button type="button"
                    wire:click="clearFilters"
                    class="btn btn-outline-secondary w-100 px-xl-2" aria-label="Limpiar todos los filtros">
                    <i class="bx bx-reset fs-5" aria-hidden="true"></i>
                    <span class="d-xl-none ms-1">Limpiar</span>
                </button>
            </div>
        </div>
    </section>

    <section class="app-card app-table-card" aria-labelledby="orders-table-title">
        <div class="app-card-header">
            <div>
                <h2 id="orders-table-title" class="app-card-title">Órdenes de caja abierta</h2>
                <p class="app-card-description">
                    @if($this->activeCashRegister)
                        {{ $this->activeCashRegister->name }} · Solo se muestran pedidos vinculados a este turno.
                    @else
                        No hay una caja abierta; abre una caja para comenzar a recibir órdenes.
                    @endif
                </p>
            </div>
            <span class="app-count-pill">Página {{ $this->orders->currentPage() }} de {{ $this->orders->lastPage() }}</span>
        </div>

        @if($this->orders->isEmpty())
            <div class="app-empty-state">
                <span class="app-empty-icon" aria-hidden="true"><i class="bx bx-search-alt"></i></span>
                <h3>No encontramos órdenes</h3>
                <p>Prueba con otros filtros o limpia la búsqueda para volver a ver todo el historial.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table app-table align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Orden</th>
                            <th scope="col">Cliente</th>
                            <th scope="col">Tipo</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Total</th>
                            <th scope="col">Vendedor</th>
                            <th scope="col">Caja</th>
                            <th scope="col">Fecha</th>
                            <th scope="col" class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->orders as $order)
                            @php
                                $statusClass = match($order->status) {
                                    'pagada' => 'success',
                                    'lista' => 'success',
                                    'cancelada' => 'danger',
                                    'en_preparacion' => 'info',
                                    default => 'warning',
                                };
                                $isKioskOrder = $order->source === 'kiosk';
                            @endphp
                                    <tr wire:key="order-row-{{ $order->id }}">
                                <td><span class="fw-bold text-primary">#{{ $order->display_folio }}</span><div class="small app-muted">ID {{ $order->id }}</div></td>
                                <td>
                                    <div class="fw-semibold">{{ $order->display_name }}</div>
                                    @if($order->customer_phone)
                                        <div class="small app-muted">{{ $order->customer_phone }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="app-status {{ $isKioskOrder ? 'app-status--primary orders-kiosk-badge' : 'app-status--neutral' }}">
                                        <i class="bx {{ $isKioskOrder ? 'bx-desktop' : $order->type_icon }}" aria-hidden="true"></i>{{ $isKioskOrder ? 'Kiosco' : $order->type_label }}
                                    </span>
                                    @if($isKioskOrder)
                                        <div class="small app-muted mt-1">{{ match($order->fulfillment) { 'dine_in' => 'Comer aquí', 'delivery' => 'Para domicilio', default => 'Para llevar' } }}</div>
                                    @endif
                                    @if($order->table_identifier)
                                        <div class="small app-muted mt-1">{{ $order->table_identifier }}</div>
                                    @endif
                                </td>
                                <td><span class="app-status app-status--{{ $statusClass }}">{{ $order->status_label }}</span></td>
                                <td><span class="app-money">${{ number_format($order->total, 2) }}</span></td>
                                <td><span class="small">{{ $order->seller?->name ?? '—' }}</span></td>
                                <td><span class="small app-muted">{{ $order->cashRegister?->name ?? '—' }}</span></td>
                                <td>
                                    <div class="small fw-semibold">{{ $order->created_at->format('d/m/Y') }}</div>
                                    <div class="small app-muted">{{ $order->created_at->format('H:i') }}</div>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('app.ordenes.show', $order) }}" class="btn btn-icon btn-outline-primary"
                                        title="Ver detalle" aria-label="Ver detalle de la orden {{ $order->id }}">
                                        <i class="bx bx-show" aria-hidden="true"></i>
                                    </a>
                                    @if($order->status !== 'cancelada' && $order->status !== 'pagada')
                                        @can('editar ordenes')
                                            <button type="button" class="btn btn-icon btn-outline-secondary" title="Cambiar estado"
                                                aria-label="Cambiar estado de la orden {{ $order->id }}"
                                                wire:click="openStatusModal({{ $order->id }})">
                                                <i class="bx bx-transfer" aria-hidden="true"></i>
                                            </button>
                                        @endcan
                                        @can('cancelar ordenes')
                                            <button type="button" class="btn btn-icon btn-outline-warning" title="Cancelar orden"
                                                aria-label="Cancelar la orden {{ $order->id }}"
                                                wire:click="openCancelModal({{ $order->id }})">
                                                <i class="bx bx-x-circle" aria-hidden="true"></i>
                                            </button>
                                        @endcan
                                    @endif
                                    @can('eliminar ordenes')
                                        <button type="button" class="btn btn-icon btn-outline-danger" title="Eliminar orden"
                                            aria-label="Eliminar la orden {{ $order->id }}"
                                            wire:click="confirmDeleteOrder({{ $order->id }})">
                                            <i class="bx bx-trash" aria-hidden="true"></i>
                                        </button>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-top">{{ $this->orders->links() }}</div>
        @endif
    </section>

    @if($showStatusModal)
        <div class="modal-backdrop app-modal-backdrop fade show" wire:click="$set('showStatusModal',false)"></div>
        <div class="modal app-modal app-modal-layer fade show d-block" tabindex="-1" role="dialog"
            aria-modal="true" aria-labelledby="status-modal-title">
            <div class="modal-dialog modal-dialog-centered app-modal-sm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 id="status-modal-title" class="modal-title fs-5"><i class="bx bx-transfer me-2 text-primary" aria-hidden="true"></i>Cambiar estado</h2>
                        <button type="button" class="btn-close" wire:click="$set('showStatusModal',false)" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="edit-order-status">Nuevo estado</label>
                        <select id="edit-order-status" wire:model="editStatus" class="form-select">
                            <option value="pendiente">Pendiente</option>
                            <option value="en_preparacion">En preparación</option>
                            <option value="lista">Listo</option>
                            <option value="pagada">Pagada</option>
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="$set('showStatusModal',false)">Cancelar</button>
                        <button type="button" class="btn btn-primary" wire:click="saveStatus" wire:loading.attr="disabled" wire:target="saveStatus">
                            <span wire:loading.remove wire:target="saveStatus"><i class="bx bx-check me-1" aria-hidden="true"></i>Guardar</span>
                            <span wire:loading wire:target="saveStatus"><span class="spinner-border spinner-border-sm me-1"></span>Guardando…</span>
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
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 id="cancel-modal-title" class="modal-title fs-5"><i class="bx bx-x-circle me-2 text-danger" aria-hidden="true"></i>Cancelar orden</h2>
                        <button type="button" class="btn-close" wire:click="$set('showCancelModal',false)" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="cancel-reason">Motivo de cancelación <span class="text-danger">*</span></label>
                        <textarea id="cancel-reason" wire:model="cancelReason" class="form-control @error('cancelReason') is-invalid @enderror"
                            rows="3" placeholder="Describe el motivo…"></textarea>
                        @error('cancelReason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="$set('showCancelModal',false)">Cerrar</button>
                        <button type="button" class="btn btn-danger" wire:click="confirmCancel" wire:loading.attr="disabled" wire:target="confirmCancel">
                            <span wire:loading.remove wire:target="confirmCancel"><i class="bx bx-x me-1" aria-hidden="true"></i>Cancelar orden</span>
                            <span wire:loading wire:target="confirmCancel"><span class="spinner-border spinner-border-sm me-1"></span>Procesando…</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="app-toast-stack" aria-live="polite" aria-atomic="true">
        <template x-for="toast in toasts" :key="toast.id">
            <div x-show="true" x-transition.opacity.duration.200ms class="toast show align-items-center border-0 text-white"
                :class="{
                    'bg-success': toast.type === 'success',
                    'bg-danger': toast.type === 'danger',
                    'bg-warning': toast.type === 'warning',
                    'bg-info': toast.type === 'info'
                }" role="status">
                <div class="d-flex">
                    <div class="toast-body" x-text="toast.message"></div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto"
                        @click="toasts = toasts.filter(t => t.id !== toast.id)" aria-label="Cerrar notificación"></button>
                </div>
            </div>
        </template>
    </div>
</div>
