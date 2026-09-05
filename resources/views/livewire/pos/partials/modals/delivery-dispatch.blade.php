@if ($showDeliveryDispatchModal)
    @php
        $dispatchDrivers = $this->deliveryDispatchDrivers;
        $dispatchOrders = $this->deliveryDispatchOrders;
        $selectedDispatchOrder = $this->selectedDeliveryDispatchOrder;
        $ordersByDriver = $dispatchOrders->groupBy('deliveryAssignment.driver_id');
        $currentDispatchDriver = $selectedDispatchOrder?->deliveryAssignment?->driver;
        $targetDispatchDrivers = $selectedDispatchOrder
            ? $dispatchDrivers->where('id', '!=', $currentDispatchDriver?->id)
            : collect();
    @endphp

    <div class="pos-modal-wrap show pos-delivery-dispatch-wrap" wire:click.self="closeDeliveryDispatchModal"
        role="dialog" aria-modal="true" aria-labelledby="pos-delivery-dispatch-title"
        x-data="{
            query: '',
            normalize(value) { return (value || '').toString().normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim(); },
            matches(value) { return this.normalize(value).includes(this.normalize(this.query)); }
        }"
        x-on:keydown.escape.window="$wire.closeDeliveryDispatchModal()">
        <div class="pos-modal wide pos-delivery-dispatch-modal" wire:click.stop>
            <header class="modal-header-pos pos-delivery-dispatch-header">
                <i class="bx bx-group" aria-hidden="true"></i>
                <div>
                    <span class="pos-modal-eyebrow">Delivery activo &middot; caja abierta</span>
                    <h4 id="pos-delivery-dispatch-title">Repartidores y pedidos</h4>
                    <p>Consulta qui&eacute;n lleva cada entrega y corrige una asignaci&oacute;n sin salir del POS.</p>
                </div>
                <button type="button" wire:click="closeDeliveryDispatchModal" aria-label="Cerrar panel de repartidores">
                    <i class="bx bx-x" aria-hidden="true"></i>
                </button>
            </header>

            <div class="modal-body-pos pos-delivery-dispatch-body">
                <aside class="pos-delivery-dispatch-directory" aria-labelledby="pos-dispatch-directory-title">
                    <div class="pos-delivery-dispatch-guide">
                        <span><i class="bx bx-cycling" aria-hidden="true"></i></span>
                        <div>
                            <strong id="pos-dispatch-directory-title">Repartidores del turno</strong>
                            <small>Selecciona un pedido para revisar sus datos y reasignarlo.</small>
                        </div>
                    </div>

                    <label class="pos-delivery-dispatch-search">
                        <span>Buscar repartidor o pedido</span>
                        <div>
                            <i class="bx bx-search" aria-hidden="true"></i>
                            <input type="search" x-model.debounce.120ms="query"
                                placeholder="Nombre, folio o cliente" autocomplete="off">
                            <button type="button" x-show="query" x-cloak x-on:click="query = ''"
                                aria-label="Limpiar b&uacute;squeda"><i class="bx bx-x" aria-hidden="true"></i></button>
                        </div>
                    </label>

                    <div class="pos-delivery-dispatch-driver-list">
                        @forelse ($dispatchDrivers as $driver)
                            @php
                                $driverOrders = $ordersByDriver->get($driver->id, collect());
                                $driverSearch = str($driver->name.' '.$driverOrders->map(fn ($order) => $order->display_folio.' '.$order->display_name)->implode(' '))
                                    ->ascii()->lower()->squish();
                            @endphp
                            <article class="pos-delivery-driver" wire:key="pos-delivery-driver-{{ $driver->id }}"
                                data-dispatch-search="{{ $driverSearch }}" x-show="matches($el.dataset.dispatchSearch)" x-cloak>
                                <header>
                                    <span class="pos-delivery-driver__avatar">
                                        @if ($driver->avatar)
                                            <img src="{{ Storage::url($driver->avatar) }}" alt="Foto de {{ $driver->name }}"
                                                width="44" height="44" loading="lazy">
                                        @else
                                            {{ mb_strtoupper(mb_substr($driver->name, 0, 1)) }}
                                        @endif
                                    </span>
                                    <div>
                                        <strong>{{ $driver->name }}</strong>
                                        <small>{{ $driverOrders->count() }} {{ $driverOrders->count() === 1 ? 'pedido asignado' : 'pedidos asignados' }}</small>
                                    </div>
                                    <span class="pos-delivery-driver__online"><i class="bx bx-check" aria-hidden="true"></i><span class="visually-hidden">Cuenta activa</span></span>
                                </header>

                                <div class="pos-delivery-driver__orders">
                                    @forelse ($driverOrders as $order)
                                        <button type="button" wire:click="selectDeliveryDispatchOrder({{ $order->id }})"
                                            wire:loading.attr="disabled" wire:target="selectDeliveryDispatchOrder({{ $order->id }})"
                                            class="pos-delivery-driver-order {{ $deliveryDispatchOrderId === $order->id ? 'is-selected' : '' }}"
                                            aria-pressed="{{ $deliveryDispatchOrderId === $order->id ? 'true' : 'false' }}">
                                            <span>
                                                <strong>{{ $order->display_folio }}</strong>
                                                <small>{{ $order->display_name }}</small>
                                            </span>
                                            <span>
                                                <strong>${{ number_format((float) $order->total, 2) }}</strong>
                                                <small>{{ $order->status_label }}</small>
                                            </span>
                                            <i class="bx bx-chevron-right" aria-hidden="true"></i>
                                        </button>
                                    @empty
                                        <div class="pos-delivery-driver__empty">
                                            <i class="bx bx-check-circle" aria-hidden="true"></i> Sin pedidos activos
                                        </div>
                                    @endforelse
                                </div>
                            </article>
                        @empty
                            <div class="pos-delivery-dispatch-empty is-directory">
                                <i class="bx bx-user-x" aria-hidden="true"></i>
                                <strong>No hay repartidores configurados</strong>
                                <p>Asigna el permiso &ldquo;Entregar delivery&rdquo; a los usuarios que realizan entregas.</p>
                            </div>
                        @endforelse
                    </div>
                </aside>

                <section class="pos-delivery-dispatch-detail" aria-live="polite">
                    @if ($selectedDispatchOrder)
                        <header class="pos-delivery-dispatch-order-head">
                            <div>
                                <span>Pedido seleccionado</span>
                                <h5>{{ $selectedDispatchOrder->display_folio }}</h5>
                                <p>{{ $selectedDispatchOrder->display_name }}</p>
                            </div>
                            <span class="pos-delivery-status is-{{ $selectedDispatchOrder->status }}">
                                <i class="bx bx-radio-circle-marked" aria-hidden="true"></i>
                                {{ $selectedDispatchOrder->status_label }}
                            </span>
                        </header>

                        <div class="pos-delivery-dispatch-facts">
                            <div>
                                <small>Repartidor actual</small>
                                <strong>{{ $currentDispatchDriver?->name ?? 'Usuario eliminado' }}</strong>
                                <span>Asignado {{ optional($selectedDispatchOrder->deliveryAssignment?->assigned_at)->format('g:i A') ?? 'sin hora' }}</span>
                            </div>
                            <div>
                                <small>Total de la orden</small>
                                <strong>${{ number_format((float) $selectedDispatchOrder->total, 2) }}</strong>
                                <span>{{ $selectedDispatchOrder->amount_to_collect > 0 ? 'Por cobrar $'.number_format($selectedDispatchOrder->amount_to_collect, 2) : 'Pago cubierto' }}</span>
                            </div>
                        </div>

                        <div class="pos-delivery-destination">
                            <i class="bx bx-map" aria-hidden="true"></i>
                            <div>
                                <small>Destino</small>
                                <strong>{{ $selectedDispatchOrder->customer_address ?: 'Direcci&oacute;n no capturada' }}</strong>
                                <span>{{ $selectedDispatchOrder->customer_phone ?: 'Sin tel&eacute;fono' }} &middot; {{ $selectedDispatchOrder->delivery_method_label }}</span>
                                @if ($selectedDispatchOrder->customer_references)
                                    <p>{{ $selectedDispatchOrder->customer_references }}</p>
                                @endif
                            </div>
                        </div>

                        <section class="pos-delivery-dispatch-items" aria-labelledby="pos-dispatch-items-title">
                            <header>
                                <strong id="pos-dispatch-items-title">Contenido del pedido</strong>
                                <span>{{ $selectedDispatchOrder->items->where('is_cancelled', false)->sum('quantity') }} art&iacute;culos</span>
                            </header>
                            <ul>
                                @foreach ($selectedDispatchOrder->items->where('is_cancelled', false) as $item)
                                    <li>
                                        <b>{{ $item->quantity }}&times;</b>
                                        <span>{{ $item->product_name }}</span>
                                        <strong>${{ number_format((float) $item->subtotal, 2) }}</strong>
                                    </li>
                                @endforeach
                            </ul>
                        </section>

                        <form class="pos-delivery-reassign-form" wire:submit="reassignDeliveryFromPos">
                            <div class="pos-delivery-reassign-title">
                                <i class="bx bx-transfer-alt" aria-hidden="true"></i>
                                <div><strong>Reasignar pedido</strong><small>El estado, los productos, pagos y total permanecer&aacute;n intactos.</small></div>
                            </div>

                            <label>
                                <span>Nuevo repartidor <b>*</b></span>
                                <select wire:model="deliveryDispatchDriverId" @disabled($targetDispatchDrivers->isEmpty())>
                                    <option value="">Selecciona una persona</option>
                                    @foreach ($targetDispatchDrivers as $driver)
                                        <option value="{{ $driver->id }}">{{ $driver->name }} &middot; {{ $ordersByDriver->get($driver->id, collect())->count() }} activos</option>
                                    @endforeach
                                </select>
                                @error('deliveryDispatchDriverId')<small class="pos-delivery-field-error">{{ $message }}</small>@enderror
                            </label>

                            <label>
                                <span>Motivo <b>*</b></span>
                                <textarea wire:model="deliveryDispatchReason" rows="2" maxlength="500"
                                    placeholder="Ej. El pedido fue asignado al repartidor equivocado."></textarea>
                                @error('deliveryDispatchReason')<small class="pos-delivery-field-error">{{ $message }}</small>@enderror
                            </label>

                            @error('delivery')
                                <div class="pos-delivery-field-error" role="alert">{{ $message }}</div>
                            @enderror

                            <button type="submit" class="pos-btn pos-btn-primary"
                                wire:loading.attr="disabled" wire:target="reassignDeliveryFromPos"
                                @disabled($targetDispatchDrivers->isEmpty())>
                                <span wire:loading.remove wire:target="reassignDeliveryFromPos"><i class="bx bx-transfer-alt"></i> Confirmar reasignaci&oacute;n</span>
                                <span wire:loading wire:target="reassignDeliveryFromPos"><i class="bx bx-loader-alt bx-spin"></i> Reasignando</span>
                            </button>
                        </form>
                    @else
                        <div class="pos-delivery-dispatch-empty">
                            <span><i class="bx bx-pointer" aria-hidden="true"></i></span>
                            <small>PASO 2</small>
                            <h5>Selecciona un pedido</h5>
                            <p>Ver&aacute;s su estado, destino, contenido, importe y repartidor actual antes de realizar cualquier cambio.</p>
                            <ul>
                                <li><i class="bx bx-show" aria-hidden="true"></i> Revisi&oacute;n completa antes de reasignar</li>
                                <li><i class="bx bx-shield-quarter" aria-hidden="true"></i> Cambio protegido por permiso</li>
                                <li><i class="bx bx-history" aria-hidden="true"></i> Motivo guardado en el historial</li>
                            </ul>
                        </div>
                    @endif
                </section>
            </div>

            <footer class="modal-footer-pos pos-delivery-dispatch-footer">
                <span><i class="bx bx-info-circle" aria-hidden="true"></i> Solo aparecen entregas asignadas de la caja abierta.</span>
                <button type="button" class="pos-btn pos-btn-secondary" wire:click="closeDeliveryDispatchModal">Cerrar</button>
            </footer>
        </div>
    </div>
@endif
