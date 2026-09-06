@if ($showOrderDataModal)
    @php($selectedOrder = $this->editableOrderDataOrders->firstWhere('id', $orderDataOrderId))
    <div class="pos-modal-wrap show pos-order-data-wrap" wire:click.self="closeOrderDataModal"
        role="dialog" aria-modal="true" aria-labelledby="order-data-modal-title">
        <div class="pos-modal wide pos-order-data-modal" x-on:click.stop="void 0">
            <header class="modal-header-pos">
                <i class="bx bx-edit-alt" aria-hidden="true"></i>
                <div class="pos-order-data-heading">
                    <span class="pos-modal-eyebrow">Caja abierta &middot; correcci&oacute;n directa</span>
                    <h4 id="order-data-modal-title">Cambiar datos de la orden</h4>
                    <p>Contacto, entrega y forma de pago. Productos e importes permanecen bloqueados.</p>
                </div>
                <button type="button" wire:click="closeOrderDataModal" aria-label="Cerrar">
                    <i class="bx bx-x" aria-hidden="true"></i>
                </button>
            </header>

            <div class="modal-body-pos pos-order-data-body">
                <aside class="pos-order-data-orders" aria-label="Seleccionar orden">
                    <div class="pos-order-data-column-guide">
                        <span>1</span>
                        <div>
                            <strong>Selecciona una orden</strong>
                            <small>Mostramos únicamente las órdenes de la caja abierta.</small>
                        </div>
                    </div>
                    <label class="pos-order-data-search">
                        <span>Buscar orden</span>
                        <div>
                            <i class="bx bx-search" aria-hidden="true"></i>
                            <input type="search" wire:model.live.debounce.350ms="orderDataSearch"
                                placeholder="Folio, cliente o tel&eacute;fono" autocomplete="off">
                        </div>
                    </label>

                    <div class="pos-order-data-list">
                        @forelse ($this->editableOrderDataOrders as $candidate)
                            <button type="button" wire:key="order-data-option-{{ $candidate->id }}"
                                wire:click="selectOrderForDataEdit({{ $candidate->id }})"
                                class="pos-order-data-option {{ $orderDataOrderId === $candidate->id ? 'is-selected' : '' }}">
                                <span>
                                    <strong>{{ $candidate->display_folio }}</strong>
                                    <small>{{ $candidate->display_name }}</small>
                                </span>
                                <span>
                                    <strong>${{ number_format((float) $candidate->total, 2) }}</strong>
                                    <small>{{ $candidate->status_label }}</small>
                                </span>
                            </button>
                        @empty
                            <div class="pos-order-data-empty-list">
                                <i class="bx bx-receipt" aria-hidden="true"></i>
                                <p>No hay &oacute;rdenes que coincidan en la caja abierta.</p>
                            </div>
                        @endforelse
                    </div>
                </aside>

                <section class="pos-order-data-editor">
                    @if ($selectedOrder)
                        <div class="pos-order-data-summary">
                            <div>
                                <span>{{ $selectedOrder->display_folio }} &middot; {{ ucfirst(str_replace('_', ' ', $selectedOrder->type)) }}</span>
                                <strong>{{ $selectedOrder->display_name }}</strong>
                            </div>
                            <div>
                                <span>Total protegido</span>
                                <strong>${{ number_format((float) $selectedOrder->total, 2) }}</strong>
                            </div>
                        </div>

                        <div class="co-section-title"><i class="bx bx-user"></i> Datos de contacto</div>
                        <div class="pos-order-data-fields">
                            <label class="co-field">
                                <span class="co-label">Nombre</span>
                                <input class="co-input" type="text" wire:model.defer="orderDataCustomerName" maxlength="150">
                                @error('orderDataCustomerName')<small class="co-error">{{ $message }}</small>@enderror
                            </label>
                            <label class="co-field">
                                <span class="co-label">Tel&eacute;fono</span>
                                <input class="co-input" type="tel" wire:model.defer="orderDataCustomerPhone" maxlength="30">
                                @error('orderDataCustomerPhone')<small class="co-error">{{ $message }}</small>@enderror
                            </label>
                        </div>

                        @if ($selectedOrder->type === 'delivery')
                            <div class="co-section-title"><i class="bx bx-map"></i> Entrega</div>
                            <div class="pos-order-data-fields">
                                <label class="co-field co-field--full">
                                    <span class="co-label">Direcci&oacute;n</span>
                                    <input class="co-input" type="text" wire:model.defer="orderDataCustomerAddress" maxlength="255">
                                    @error('orderDataCustomerAddress')<small class="co-error">{{ $message }}</small>@enderror
                                </label>
                                <label class="co-field">
                                    <span class="co-label">Colonia o zona</span>
                                    <input class="co-input" type="text" wire:model.defer="orderDataCustomerNeighborhood" maxlength="120">
                                    @error('orderDataCustomerNeighborhood')<small class="co-error">{{ $message }}</small>@enderror
                                </label>
                                <label class="co-field">
                                    <span class="co-label">Referencias</span>
                                    <input class="co-input" type="text" wire:model.defer="orderDataCustomerReferences" maxlength="255">
                                    @error('orderDataCustomerReferences')<small class="co-error">{{ $message }}</small>@enderror
                                </label>
                                @if ($selectedOrder->payments->isEmpty())
                                    <label class="co-field co-field--full">
                                        <span class="co-label">Forma de pago acordada</span>
                                        <select class="co-input" wire:model.defer="orderDataDeliveryMethod">
                                            <option value="contra_entrega">Efectivo contra entrega</option>
                                            <option value="tarjeta">Tarjeta</option>
                                            <option value="transferencia">Transferencia</option>
                                        </select>
                                    </label>
                                @endif
                            </div>
                        @endif

                        @if ($orderDataPayments !== [])
                            <div class="co-section-title"><i class="bx bx-credit-card"></i> Pagos registrados</div>
                            <p class="pos-order-data-help">En pagos parciales corrige cada m&eacute;todo por separado. El importe no se puede editar.</p>
                            <div class="pos-order-data-payments">
                                @foreach ($orderDataPayments as $index => $payment)
                                    <article class="pos-order-data-payment" wire:key="order-data-payment-{{ $payment['id'] }}">
                                        <div class="pos-order-data-payment__top">
                                            <span>Pago {{ $index + 1 }}</span>
                                            <strong>${{ number_format((float) $payment['amount'], 2) }}</strong>
                                        </div>
                                        <label class="co-field">
                                            <span class="co-label">M&eacute;todo correcto</span>
                                            <select class="co-input" wire:model.live="orderDataPayments.{{ $index }}.method">
                                                <option value="efectivo">Efectivo</option>
                                                <option value="tarjeta">Tarjeta</option>
                                                <option value="transferencia">Transferencia</option>
                                                @if ($selectedOrder->type === 'delivery')
                                                    <option value="contra_entrega">Pendiente contra entrega</option>
                                                @endif
                                            </select>
                                        </label>
                                        @if (($payment['method'] ?? null) === 'efectivo')
                                            <label class="co-field">
                                                <span class="co-label">Efectivo recibido</span>
                                                <input class="co-input" type="number" min="{{ $payment['amount'] }}" step="0.01"
                                                    wire:model.defer="orderDataPayments.{{ $index }}.received_amount">
                                                @error("orderDataPayments.{$index}.received_amount")<small class="co-error">{{ $message }}</small>@enderror
                                            </label>
                                        @elseif (($payment['method'] ?? null) === 'tarjeta')
                                            <label class="co-field">
                                                <span class="co-label">&Uacute;ltimos 4 d&iacute;gitos</span>
                                                <input class="co-input" type="text" inputmode="numeric" maxlength="4"
                                                    wire:model.defer="orderDataPayments.{{ $index }}.card_last4">
                                                @error("orderDataPayments.{$index}.card_last4")<small class="co-error">{{ $message }}</small>@enderror
                                            </label>
                                        @elseif (($payment['method'] ?? null) === 'transferencia')
                                            <label class="co-field">
                                                <span class="co-label">Referencia</span>
                                                <input class="co-input" type="text" maxlength="120"
                                                    wire:model.defer="orderDataPayments.{{ $index }}.transfer_reference">
                                                @error("orderDataPayments.{$index}.transfer_reference")<small class="co-error">{{ $message }}</small>@enderror
                                            </label>
                                        @else
                                            <p class="pos-order-data-help">El pago se registrar&aacute; cuando se liquide la entrega.</p>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                            @error('orderDataPayments')<small class="co-error">{{ $message }}</small>@enderror
                        @endif

                        <div class="pos-order-data-guardrail">
                            <i class="bx bx-shield-quarter" aria-hidden="true"></i>
                            <span>Esta acci&oacute;n queda registrada. Para cambiar productos, descuentos o total utiliza una solicitud.</span>
                        </div>
                    @else
                        <div class="pos-order-data-empty" aria-live="polite">
                            <span><i class="bx bx-pointer" aria-hidden="true"></i></span>
                            <small>PASO 2</small>
                            <h5>Revisa y corrige sus datos</h5>
                            <p>Selecciona una orden en la columna izquierda. Aquí aparecerán sus datos actuales.</p>
                            <ul>
                                <li><i class="bx bx-check" aria-hidden="true"></i> Contacto y dirección de entrega</li>
                                <li><i class="bx bx-check" aria-hidden="true"></i> Método de cada pago parcial</li>
                                <li><i class="bx bx-lock-alt" aria-hidden="true"></i> Productos e importes protegidos</li>
                            </ul>
                        </div>
                    @endif
                </section>
            </div>

            <footer class="modal-footer-pos">
                <button type="button" class="pos-btn pos-btn-secondary" wire:click="closeOrderDataModal">Cerrar</button>
                @if ($selectedOrder)
                    <button type="button" class="pos-btn pos-btn-primary" wire:click="saveOrderData"
                        wire:loading.attr="disabled" wire:target="saveOrderData">
                        <span wire:loading.remove wire:target="saveOrderData"><i class="bx bx-save"></i> Guardar correcci&oacute;n</span>
                        <span wire:loading wire:target="saveOrderData"><i class="bx bx-loader-alt bx-spin"></i> Guardando</span>
                    </button>
                @endif
            </footer>
        </div>
    </div>
@endif
