<div class="app-page order-requests-page"
    x-data="{ toasts: [] }"
    x-on:notify.window="
        toasts.push({ id: Date.now(), type: $event.detail.type, message: $event.detail.message });
        setTimeout(() => toasts.shift(), 3500);
    ">
    <header class="order-requests-hero">
        <div class="order-requests-hero__content">
            <span class="order-requests-hero__icon" aria-hidden="true"><i class="bx bx-shield-quarter"></i></span>
            <div>
                <p class="orders-eyebrow">Autorizaciones · Órdenes</p>
                <h1>Solicitudes de órdenes</h1>
                <p>Revisa cancelaciones, ajustes, pagos y direcciones antes de que cambien una orden.</p>
            </div>
        </div>
        <a class="orders-button orders-button--ghost" href="{{ route('app.ordenes') }}" wire:navigate>
            <i class="bx bx-left-arrow-alt" aria-hidden="true"></i><span>Volver a órdenes</span>
        </a>
    </header>

    <section class="orders-stats" aria-label="Resumen de solicitudes">
        @foreach([
            ['bx-time-five', 'Por revisar', $this->summary['pending'], 'warning'],
            ['bx-x-circle', 'Cancelaciones', $this->summary['cancellations'], 'danger'],
            ['bx-edit-alt', 'Modificaciones', $this->summary['modifications'], 'info'],
            ['bx-credit-card', 'Cambios de pago', $this->summary['payment_changes'], 'info'],
            ['bx-map', 'Cambios de dirección', $this->summary['address_changes'], 'warning'],
            ['bx-check-shield', 'Resueltas', $this->summary['resolved'], 'success'],
        ] as $stat)
            <article class="orders-stat orders-stat--{{ $stat[3] }}">
                <span class="orders-stat__icon" aria-hidden="true"><i class="bx {{ $stat[0] }}"></i></span>
                <span><small>{{ $stat[1] }}</small><strong>{{ $stat[2] }}</strong></span>
            </article>
        @endforeach
    </section>

    <section class="orders-filter-card" aria-labelledby="request-filters-title">
        <div class="orders-section-heading">
            <span class="orders-section-heading__icon" aria-hidden="true"><i class="bx bx-filter-alt"></i></span>
            <div>
                <h2 id="request-filters-title">Bandeja de revisión</h2>
                <p>Filtra por estado, tipo, orden, cliente, solicitante o motivo.</p>
            </div>
            @if($search || $typeFilter || $statusFilter !== 'pending')
                <button type="button" class="orders-clear-button" wire:click="clearFilters">
                    <i class="bx bx-reset" aria-hidden="true"></i><span>Limpiar filtros</span>
                </button>
            @endif
        </div>
        <div class="order-requests-filter-grid">
            <div class="orders-field orders-field--search">
                <label for="request-search">Buscar solicitud</label>
                <div class="orders-control">
                    <i class="bx bx-search" aria-hidden="true"></i>
                    <input id="request-search" type="search" wire:model.live.debounce.400ms="search"
                        placeholder="Orden, cliente, solicitante o motivo" autocomplete="off">
                </div>
            </div>
            <div class="orders-field">
                <label for="request-status">Estado</label>
                <select id="request-status" wire:model.live="statusFilter">
                    <option value="">Todos</option>
                    <option value="pending">Pendientes</option>
                    <option value="approved">Aprobadas</option>
                    <option value="rejected">Rechazadas</option>
                </select>
            </div>
            <div class="orders-field">
                <label for="request-type">Tipo</label>
                <select id="request-type" wire:model.live="typeFilter">
                    <option value="">Todos</option>
                    <option value="cancellation">Cancelación</option>
                    <option value="modification">Modificación</option>
                    <option value="payment_change">Cambio de método de pago</option>
                    <option value="address_change">Cambio de dirección</option>
                </select>
            </div>
        </div>
    </section>

    <div class="order-requests-workspace">
        <section class="order-requests-list" aria-labelledby="requests-list-title">
            <header>
                <div>
                    <h2 id="requests-list-title">Solicitudes recibidas</h2>
                    <p>{{ $this->requests->total() }} {{ $this->requests->total() === 1 ? 'resultado' : 'resultados' }}</p>
                </div>
            </header>

            <div class="order-requests-table-wrap">
                <table class="order-requests-table">
                    <thead><tr><th>Orden</th><th>Solicitud</th><th>Solicitó</th><th>Estado</th><th><span class="visually-hidden">Acción</span></th></tr></thead>
                    <tbody>
                        @forelse($this->requests as $request)
                            <tr wire:key="order-request-{{ $request->id }}" class="{{ $selectedRequestId === $request->id ? 'is-selected' : '' }}">
                                <td data-label="Orden"><strong>{{ $request->order?->display_folio }}</strong><small>{{ $request->order?->customer?->name ?? $request->order?->customer_name ?? 'Sin cliente' }}</small></td>
                                <td data-label="Solicitud"><span class="order-request-type is-{{ $request->type }}"><i class="bx {{ match($request->scope) {'full' => 'bx-x-circle', 'partial' => 'bx-minus-circle', 'payment' => 'bx-credit-card', 'address' => 'bx-map', default => 'bx-edit-alt'} }}"></i>{{ $request->type_label }}</span><small>{{ $request->created_at->format('d/m/Y H:i') }}</small></td>
                                <td data-label="Solicitó">{{ $request->requester?->name }}</td>
                                <td data-label="Estado"><span class="order-request-status is-{{ $request->status }}"><i class="bx {{ match($request->status) {'approved' => 'bx-check-circle', 'rejected' => 'bx-x-circle', default => 'bx-time-five'} }}"></i>{{ $request->status_label }}</span></td>
                                <td><button type="button" class="order-request-open" wire:click="selectRequest({{ $request->id }})" aria-label="Revisar solicitud de la orden {{ $request->order?->display_folio }}"><span>Revisar</span><i class="bx bx-chevron-right"></i></button></td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="orders-review-empty"><i class="bx bx-check-shield"></i><strong>Sin solicitudes para mostrar</strong><p>Ajusta los filtros o espera una nueva solicitud.</p></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($this->requests->hasPages())
                <footer class="order-requests-pagination">{{ $this->requests->links() }}</footer>
            @endif
        </section>

        <aside class="order-request-detail" aria-live="polite" aria-labelledby="request-detail-title">
            @if($this->selectedRequest)
                @php($review = $this->selectedRequest)
                <header class="orders-review-heading">
                    <span><small>{{ $review->type_label }}</small><h2 id="request-detail-title">Orden {{ $review->order?->display_folio }}</h2></span>
                    <b class="is-{{ $review->status }}">{{ $review->status_label }}</b>
                </header>
                <dl class="orders-review-meta">
                    <div><dt>Solicita</dt><dd>{{ $review->requester?->name }}</dd></div>
                    <div><dt>Fecha</dt><dd>{{ $review->created_at->format('d/m/Y H:i') }}</dd></div>
                    <div><dt>Cliente</dt><dd>{{ $review->order?->customer?->name ?? $review->order?->customer_name ?? 'Sin cliente' }}</dd></div>
                    <div><dt>Total actual</dt><dd>${{ number_format($review->original_total, 2) }}</dd></div>
                </dl>
                @php($context = data_get($review->proposed_changes, 'request_context', []))
                @if($context)
                    <dl class="order-request-context">
                        <div><dt>Cliente</dt><dd>{{ match(data_get($context, 'customer_confirmed')) {'yes' => 'Cambio confirmado', 'no' => 'Sin confirmar', default => 'No aplica'} }}</dd></div>
                        <div><dt>Preparación</dt><dd>{{ match(data_get($context, 'preparation_stage')) {'not_started' => 'No iniciada', 'in_progress' => 'En proceso', 'ready' => 'Lista', default => 'Sin confirmar'} }}</dd></div>
                    </dl>
                @endif
                @if((float) data_get($context, 'refund_amount', 0) > 0)
                    @php($requiresExternalReference = collect(data_get($context, 'refund_allocations', []))->except(['efectivo', 'contra_entrega'])->sum() > 0)
                    <section class="order-request-refund" aria-label="Devolución requerida">
                        <header><i class="bx bx-receipt"></i><span><small>Devolución requerida</small><strong>${{ number_format(data_get($context, 'refund_amount'), 2) }}</strong></span></header>
                        <dl>
                            @foreach(data_get($context, 'refund_allocations', []) as $method => $amount)
                                <div><dt>{{ match($method) {'efectivo' => 'Efectivo', 'tarjeta' => 'Tarjeta', 'transferencia' => 'Transferencia', 'contra_entrega' => 'Contra entrega', default => ucfirst(str_replace('_', ' ', $method))} }}</dt><dd>${{ number_format($amount, 2) }}</dd></div>
                            @endforeach
                            <div><dt>Destino de productos</dt><dd>{{ match(data_get($context, 'inventory_disposition')) {'restock' => 'Reintegrar', 'waste' => 'Merma', default => 'No aplica'} }}</dd></div>
                        </dl>
                    </section>
                @endif
                <div class="orders-review-reason"><small>Motivo indicado</small><p>{{ $review->reason }}</p></div>

                @if($review->type === \App\Models\OrderChangeRequest::TYPE_MODIFICATION)
                    <h3>Cambios exactos</h3>
                    <div class="orders-review-changes">
                        @foreach(data_get($review->proposed_changes, 'items', []) as $change)
                            <div>
                                <span class="is-{{ $change['action'] }}">{{ match($change['action']) {'add' => 'Agregar', 'remove' => 'Retirar', default => 'Cambiar'} }}</span>
                                <strong>{{ $change['product_name'] }}</strong>
                                <small>{{ $change['from_quantity'] }} → {{ $change['to_quantity'] }} unidades</small>
                                <b>${{ number_format($change['after_subtotal'], 2) }}</b>
                            </div>
                        @endforeach
                    </div>
                    <div class="orders-total-comparison"><span>Actual <b>${{ number_format($review->original_total, 2) }}</b></span><i class="bx bx-right-arrow-alt"></i><span>Propuesto <strong>${{ number_format($review->proposed_total, 2) }}</strong></span></div>
                @elseif($review->type === \App\Models\OrderChangeRequest::TYPE_CANCELLATION)
                    <div class="order-request-warning"><i class="bx bx-info-circle" aria-hidden="true"></i><p>Al aprobar, la orden se marcará como cancelada. El registro y sus productos se conservarán para auditoría.</p></div>
                @elseif($review->type === \App\Models\OrderChangeRequest::TYPE_PAYMENT_CHANGE)
                    @php($paymentChange = data_get($review->proposed_changes, 'payment_change', []))
                    <h3>Reclasificación del cobro</h3>
                    <div class="order-wizard-review-change"><span><small>Método registrado</small><strong>{{ match(data_get($paymentChange, 'before.method')) {'efectivo' => 'Efectivo', 'tarjeta' => 'Tarjeta', 'transferencia' => 'Transferencia', default => 'Otro'} }}</strong></span><i class="bx bx-right-arrow-alt"></i><span><small>Método solicitado</small><strong>{{ match(data_get($paymentChange, 'after.method')) {'efectivo' => 'Efectivo', 'tarjeta' => 'Tarjeta', 'transferencia' => 'Transferencia', default => 'Otro'} }}</strong></span></div>
                    <div class="order-request-warning is-neutral"><i class="bx bx-receipt"></i><p>El importe permanece en <strong>${{ number_format(data_get($paymentChange, 'amount', $review->original_total), 2) }}</strong>. Se actualizará el pago existente; no se creará un segundo cobro.</p></div>
                    @if(data_get($paymentChange, 'after.transfer_reference'))<div class="orders-review-reason"><small>Referencia nueva</small><p>{{ data_get($paymentChange, 'after.transfer_reference') }}</p></div>@endif
                @elseif($review->type === \App\Models\OrderChangeRequest::TYPE_ADDRESS_CHANGE)
                    @php($addressChange = data_get($review->proposed_changes, 'address_change', []))
                    <h3>Cambio de destino</h3>
                    <div class="order-request-address-comparison"><article><small>Dirección actual</small><strong>{{ data_get($addressChange, 'before.address') ?: 'Sin dirección' }}</strong><p>{{ data_get($addressChange, 'before.neighborhood') }}@if(data_get($addressChange, 'before.references')) · {{ data_get($addressChange, 'before.references') }}@endif</p></article><i class="bx bx-right-arrow-alt"></i><article><small>Nueva dirección</small><strong>{{ data_get($addressChange, 'after.address') }}</strong><p>{{ data_get($addressChange, 'after.neighborhood') }}@if(data_get($addressChange, 'after.references')) · {{ data_get($addressChange, 'after.references') }}@endif</p></article></div>
                    @if($review->order?->deliveryAssignment?->driver)<div class="order-request-warning is-neutral"><i class="bx bx-cycling"></i><p>Pedido asignado a <strong>{{ $review->order->deliveryAssignment->driver->name }}</strong>. Recibirá una notificación al aprobar el cambio.</p></div>@endif
                @endif

                @if($review->status === 'pending')
                    @if((float) data_get($context, 'refund_amount', 0) > 0)
                        <div class="order-request-refund-confirmation">
                            @if($requiresExternalReference)
                                <div class="orders-field">
                                    <label for="refund-reference">Referencia de devolución</label>
                                    <input id="refund-reference" type="text" wire:model="refundReference" maxlength="120" placeholder="Folio emitido por terminal o banco">
                                    @error('refundReference') <div class="orders-field-error">{{ $message }}</div> @enderror
                                </div>
                            @endif
                            <label class="order-request-confirm-check"><input type="checkbox" wire:model="refundConfirmed"><span>Confirmo que la devolución de ${{ number_format(data_get($context, 'refund_amount'), 2) }} fue realizada.</span></label>
                            @error('refundConfirmed') <div class="orders-field-error">{{ $message }}</div> @enderror
                        </div>
                    @endif
                    <div class="orders-field order-request-notes">
                        <label for="review-notes">Notas de revisión <small>Obligatorias al rechazar</small></label>
                        <textarea id="review-notes" wire:model="reviewNotes" rows="4" placeholder="Documenta la decisión tomada"></textarea>
                        @error('reviewNotes') <div class="orders-field-error">{{ $message }}</div> @enderror
                        @error('review') <div class="orders-field-error">{{ $message }}</div> @enderror
                    </div>
                    <footer class="order-request-actions">
                        <button type="button" class="orders-button orders-button--ghost" wire:click="rejectRequest" wire:loading.attr="disabled" wire:target="approveRequest,rejectRequest"><i class="bx bx-x-circle"></i><span>Rechazar</span></button>
                        <button type="button" class="orders-button orders-button--primary" wire:click="approveRequest" wire:loading.attr="disabled" wire:target="approveRequest,rejectRequest"><i class="bx bx-check-shield"></i><span>{{ (float) data_get($context, 'refund_amount', 0) > 0 ? 'Aprobar y registrar devolución' : 'Aprobar y aplicar' }}</span></button>
                    </footer>
                @else
                    <div class="orders-review-resolution"><strong>{{ $review->status_label }} por {{ $review->reviewer?->name }}</strong><small>{{ $review->reviewed_at?->format('d/m/Y H:i') }}</small><p>{{ $review->reviewer_notes ?: 'Sin notas adicionales.' }}</p></div>
                    @if($review->refund)
                        <div class="orders-review-resolution"><strong>Reembolso #{{ $review->refund->id }} registrado</strong><small>{{ $review->refund->processed_at?->format('d/m/Y H:i') }}</small><p>${{ number_format($review->refund->amount, 2) }}{{ $review->refund->external_reference ? ' · Ref. '.$review->refund->external_reference : ' · Efectivo' }}</p></div>
                    @endif
                @endif
            @else
                <div class="orders-review-empty"><i class="bx bx-pointer"></i><strong>Selecciona una solicitud</strong><p>Aquí verás el motivo, los cambios exactos y las acciones disponibles.</p></div>
            @endif
        </aside>
    </div>

    <div class="app-toast-stack" aria-live="polite" aria-atomic="true">
        <template x-for="toast in toasts" :key="toast.id">
            <div x-show="true" x-transition.opacity.duration.200ms class="toast show align-items-center border-0 text-white"
                :class="{ 'bg-success': toast.type === 'success', 'bg-danger': toast.type === 'danger', 'bg-warning': toast.type === 'warning', 'bg-info': toast.type === 'info' }" role="status">
                <div class="d-flex"><div class="toast-body" x-text="toast.message"></div><button type="button" class="btn-close btn-close-white me-2 m-auto" @click="toasts = toasts.filter(t => t.id !== toast.id)" aria-label="Cerrar notificación"></button></div>
            </div>
        </template>
    </div>
</div>
