<main class="app-page order-wizard-page" aria-labelledby="wizard-title">
    <header class="order-wizard-header">
        <a class="order-wizard-back" href="{{ $source === 'list' ? route('app.ordenes') : route('app.ordenes.show', $order) }}" aria-label="Salir del asistente">
            <i class="bx bx-left-arrow-alt" aria-hidden="true"></i><span>Salir</span>
        </a>
        <div>
            <p class="orders-eyebrow">Orden #{{ $order->display_folio }}</p>
            <h1 id="wizard-title">Solicitar un cambio</h1>
            <p>La orden permanecerá sin cambios hasta recibir autorización.</p>
        </div>
        <span class="order-wizard-secure"><i class="bx bx-shield-quarter" aria-hidden="true"></i>Solicitud auditada</span>
    </header>

    <nav class="order-wizard-progress" aria-label="Progreso de la solicitud">
        @foreach([1 => 'Tipo de cambio', 2 => 'Configurar', 3 => 'Confirmar'] as $number => $label)
            <div class="{{ $step === $number ? 'is-current' : '' }} {{ $step > $number ? 'is-complete' : '' }}" @if($step === $number) aria-current="step" @endif>
                <span>@if($step > $number)<i class="bx bx-check"></i>@else{{ $number }}@endif</span><strong>{{ $label }}</strong>
            </div>
        @endforeach
    </nav>

    @if($this->isPaidOrder)
        <div class="order-wizard-paid-notice" role="status">
            <i class="bx bx-credit-card" aria-hidden="true"></i>
            <div><strong>Esta orden ya fue pagada</strong><p>El pago original se conservará. Si se autoriza un importe menor, se registrará una devolución auditada por ${{ number_format($this->refundAmount, 2) }}.</p></div>
        </div>
    @endif

    <div class="order-wizard-layout">
        <section class="order-wizard-card" wire:loading.class="is-loading" wire:target="chooseScope,nextStep,previousStep,submit">
            @if($step === 1)
                <div class="order-wizard-step" wire:key="wizard-step-1">
                    <div class="order-wizard-step__heading"><span>1</span><div><h2>¿Qué necesita cambiar?</h2><p>Elige la opción que describa mejor lo solicitado.</p></div></div>
                    <div class="order-wizard-scope-grid" role="radiogroup" aria-label="Tipo de cambio">
                        @if($this->canRequestCancellation)
                            <button type="button" wire:click="chooseScope('full')" role="radio" aria-checked="{{ $scope === 'full' ? 'true' : 'false' }}" class="is-danger {{ $scope === 'full' ? 'is-selected' : '' }}">
                                <i class="bx bx-x-circle" aria-hidden="true"></i><span><strong>Cancelar toda la orden</strong><small>El pedido completo dejará de continuar.</small></span><b>Total</b>
                            </button>
                        @endif
                        @if($this->canRequestModification)
                            <button type="button" wire:click="chooseScope('partial')" role="radio" aria-checked="{{ $scope === 'partial' ? 'true' : 'false' }}" class="is-warning {{ $scope === 'partial' ? 'is-selected' : '' }}">
                                <i class="bx bx-minus-circle" aria-hidden="true"></i><span><strong>Cancelación parcial</strong><small>Retira artículos o reduce cantidades.</small></span><b>Parcial</b>
                            </button>
                            <button type="button" wire:click="chooseScope('adjustment')" role="radio" aria-checked="{{ $scope === 'adjustment' ? 'true' : 'false' }}" class="is-primary {{ $scope === 'adjustment' ? 'is-selected' : '' }}">
                                <i class="bx bx-edit-alt" aria-hidden="true"></i><span><strong>Ajustar el pedido</strong><small>Agrega, retira o cambia cantidades.</small></span><b>Modificar</b>
                            </button>
                        @endif
                    </div>
                    @error('scope') <p class="orders-field-error" role="alert">{{ $message }}</p> @enderror
                </div>
            @elseif($step === 2)
                <div class="order-wizard-step" wire:key="wizard-step-2">
                    <div class="order-wizard-step__heading"><span>2</span><div><h2>{{ $scope === 'full' ? 'Documenta la cancelación' : 'Configura el nuevo pedido' }}</h2><p>{{ $scope === 'full' ? 'Confirma el contexto para que la autorización sea rápida.' : 'Marca exactamente lo que se agrega, retira o cambia.' }}</p></div></div>

                    @if($scope !== 'full')
                        <fieldset class="order-wizard-fieldset">
                            <legend>Artículos de la orden</legend>
                            <div class="orders-change-lines">
                                @foreach($requestItems as $index => $line)
                                    <div class="orders-change-line {{ $line['quantity'] === 0 ? 'is-removed' : '' }}" wire:key="wizard-line-{{ $line['key'] }}">
                                        <span><strong>{{ $line['name'] }}</strong><small>{{ $line['kind'] === 'new' ? 'Artículo nuevo' : 'Antes: '.$line['original_quantity'] }} · ${{ number_format($line['unit_subtotal'], 2) }} c/u</small></span>
                                        <div class="orders-quantity-control" aria-label="Cantidad de {{ $line['name'] }}">
                                            <button type="button" wire:click="adjustRequestItem({{ $index }}, -1)" aria-label="Quitar una unidad de {{ $line['name'] }}"><i class="bx bx-minus"></i></button>
                                            <b aria-live="polite">{{ $line['quantity'] }}</b>
                                            <button type="button" wire:click="adjustRequestItem({{ $index }}, 1)" aria-label="Agregar una unidad de {{ $line['name'] }}"><i class="bx bx-plus"></i></button>
                                        </div>
                                        <strong>${{ number_format($line['unit_subtotal'] * $line['quantity'], 2) }}</strong>
                                    </div>
                                @endforeach
                            </div>
                            @error('requestItems') <p class="orders-field-error" role="alert">{{ $message }}</p> @enderror
                        </fieldset>

                        <fieldset class="order-wizard-fieldset">
                            <legend>Agregar otro producto</legend>
                            <label class="visually-hidden" for="wizard-product-search">Buscar producto</label>
                            <div class="orders-control"><i class="bx bx-search" aria-hidden="true"></i><input id="wizard-product-search" type="search" wire:model.live.debounce.350ms="productSearch" placeholder="Buscar en el menú" autocomplete="off"></div>
                            <div class="orders-product-results" aria-label="Productos disponibles">
                                @foreach($this->productResults as $product)
                                    <button type="button" wire:click="addProductToRequest({{ $product->id }})"><span>{{ $product->name }}</span><b>${{ number_format($product->price, 2) }}</b><i class="bx bx-plus-circle" aria-hidden="true"></i></button>
                                @endforeach
                            </div>
                        </fieldset>
                    @else
                        <div class="order-wizard-danger-summary"><i class="bx bx-error-circle" aria-hidden="true"></i><div><strong>Se solicitará cancelar toda la orden</strong><p>Los {{ $order->items->sum('quantity') }} artículos permanecerán en el historial; no se eliminará ningún registro.</p></div></div>
                    @endif

                    <fieldset class="order-wizard-fieldset">
                        <legend>Motivo principal</legend>
                        <div class="order-wizard-reasons" role="radiogroup" aria-label="Motivo de la solicitud">
                            @foreach($this->reasonOptions as $code => $option)
                                <button type="button" role="radio" aria-checked="{{ $reasonCode === $code ? 'true' : 'false' }}" wire:click="selectReason('{{ $code }}')" class="{{ $reasonCode === $code ? 'is-selected' : '' }}"><i class="bx {{ $option[1] }}" aria-hidden="true"></i><span>{{ $option[0] }}</span></button>
                            @endforeach
                        </div>
                        @error('reasonCode') <p class="orders-field-error" role="alert">Selecciona el motivo principal.</p> @enderror
                        <label for="wizard-reason-detail">Detalle {{ $reasonCode === 'other' ? '(obligatorio)' : '(opcional)' }}</label>
                        <textarea id="wizard-reason-detail" wire:model.blur="reasonDetail" rows="3" placeholder="Información adicional que ayudará a tomar la decisión"></textarea>
                        @error('reasonDetail') <p class="orders-field-error" role="alert">{{ $message }}</p> @enderror
                    </fieldset>

                    <div class="order-wizard-context-grid">
                        <div class="orders-field"><label for="wizard-customer-confirmed">¿El cliente confirmó el cambio?</label><select id="wizard-customer-confirmed" wire:model="customerConfirmed"><option value="">Selecciona</option><option value="yes">Sí, está confirmado</option><option value="no">No se logró confirmar</option><option value="not_applicable">No aplica</option></select>@error('customerConfirmed')<p class="orders-field-error" role="alert">Indica si el cliente lo confirmó.</p>@enderror</div>
                        <div class="orders-field"><label for="wizard-preparation-stage">Etapa real de preparación</label><select id="wizard-preparation-stage" wire:model="preparationStage"><option value="not_started">Aún no inicia</option><option value="in_progress">En preparación</option><option value="ready">Ya está listo</option><option value="unknown">No se pudo confirmar</option></select>@error('preparationStage')<p class="orders-field-error" role="alert">{{ $message }}</p>@enderror</div>
                    </div>

                    @if($this->isPaidOrder)
                        <fieldset class="order-wizard-fieldset">
                            <legend>Destino de los productos retirados</legend>
                            <p class="orders-field-help">Esto deja evidencia operativa; no modifica existencias automáticamente.</p>
                            <div class="order-wizard-disposition" role="radiogroup" aria-label="Destino de inventario">
                                @foreach([
                                    'restock' => ['bx-undo', 'Reintegrar', 'Puede volver a utilizarse'],
                                    'waste' => ['bx-trash', 'Merma', 'Se desecha o ya fue preparado'],
                                    'not_applicable' => ['bx-minus-circle', 'No aplica', 'No hay producto físico que mover'],
                                ] as $value => $option)
                                    <button type="button" wire:click="$set('inventoryDisposition', '{{ $value }}')" class="{{ $inventoryDisposition === $value ? 'is-selected' : '' }}" aria-pressed="{{ $inventoryDisposition === $value ? 'true' : 'false' }}">
                                        <i class="bx {{ $option[0] }}"></i><span><strong>{{ $option[1] }}</strong><small>{{ $option[2] }}</small></span>
                                    </button>
                                @endforeach
                            </div>
                            @error('inventoryDisposition') <p class="orders-field-error" role="alert">{{ $message }}</p> @enderror
                        </fieldset>

                        <div class="order-wizard-refund-preview">
                            <span><small>Devolución estimada</small><strong>${{ number_format($this->refundAmount, 2) }}</strong></span>
                            <div>
                                @forelse($this->refundAllocations as $method => $amount)
                                    <small>{{ match($method) {'efectivo' => 'Efectivo', 'tarjeta' => 'Tarjeta', 'transferencia' => 'Transferencia', 'contra_entrega' => 'Contra entrega', default => ucfirst(str_replace('_', ' ', $method))} }}: <b>${{ number_format($amount, 2) }}</b></small>
                                @empty
                                    <small>Sin devolución monetaria.</small>
                                @endforelse
                            </div>
                        </div>
                    @endif
                </div>
            @else
                <div class="order-wizard-step" wire:key="wizard-step-3">
                    <div class="order-wizard-step__heading"><span>3</span><div><h2>Revisa antes de enviar</h2><p>La persona autorizadora recibirá exactamente esta información.</p></div></div>
                    <div class="order-wizard-review">
                        <div><small>Solicitud</small><strong>{{ match($scope) {'full' => 'Cancelación total', 'partial' => 'Cancelación parcial', default => 'Ajuste de pedido'} }}</strong></div>
                        <div><small>Motivo</small><strong>{{ $this->reasonOptions[$reasonCode][0] ?? 'Sin seleccionar' }}</strong></div>
                        <div><small>Confirmación del cliente</small><strong>{{ match($customerConfirmed) {'yes' => 'Confirmado', 'no' => 'Sin confirmar', default => 'No aplica'} }}</strong></div>
                        <div><small>Preparación</small><strong>{{ match($preparationStage) {'not_started' => 'No iniciada', 'in_progress' => 'En proceso', 'ready' => 'Lista', default => 'Sin confirmar'} }}</strong></div>
                    </div>
                    @if($reasonDetail)<div class="orders-review-reason"><small>Detalle adicional</small><p>{{ $reasonDetail }}</p></div>@endif
                    @if($scope !== 'full')
                        <div class="order-wizard-impact"><span><small>Retiradas</small><b>{{ $this->changeSummary['removed'] }}</b></span><span><small>Agregadas</small><b>{{ $this->changeSummary['added'] }}</b></span><span><small>Cantidades modificadas</small><b>{{ $this->changeSummary['updated'] }}</b></span></div>
                        <div class="orders-total-comparison"><span>Actual <b>${{ number_format($order->total, 2) }}</b></span><i class="bx bx-right-arrow-alt"></i><span>Propuesto <strong>${{ number_format($this->proposedTotal, 2) }}</strong></span></div>
                    @else
                        <div class="order-wizard-final-warning"><i class="bx bx-info-circle" aria-hidden="true"></i><p>Al aprobarse, la orden cambiará a estado cancelada. No se borrarán la orden ni sus artículos.</p></div>
                    @endif
                    @if($this->isPaidOrder)
                        <div class="order-wizard-final-warning is-refund"><i class="bx bx-receipt" aria-hidden="true"></i><p>Al aprobar, se registrará una devolución de <strong>${{ number_format($this->refundAmount, 2) }}</strong>. El autorizador confirmará el movimiento y capturará la referencia cuando corresponda.</p></div>
                    @endif
                    @error('requestItems') <p class="orders-field-error" role="alert">{{ $message }}</p> @enderror
                </div>
            @endif

            <footer class="order-wizard-actions">
                @if($step > 1)<button type="button" class="orders-button orders-button--ghost" wire:click="previousStep" wire:loading.attr="disabled"><i class="bx bx-left-arrow-alt"></i><span>Anterior</span></button>@else<span></span>@endif
                @if($step < 3)
                    @if($step === 2)<button type="button" class="orders-button orders-button--primary" wire:click="nextStep" wire:loading.attr="disabled"><span>Revisar solicitud</span><i class="bx bx-right-arrow-alt"></i></button>@endif
                @else
                    <button type="button" class="orders-button {{ $scope === 'full' ? 'orders-button--danger' : 'orders-button--primary' }}" wire:click="submit" wire:loading.attr="disabled" wire:target="submit"><span wire:loading.remove wire:target="submit"><i class="bx bx-send"></i>Enviar para autorización</span><span wire:loading wire:target="submit"><span class="spinner-border spinner-border-sm"></span>Enviando…</span></button>
                @endif
            </footer>
        </section>

        <aside class="order-wizard-order" aria-label="Resumen de la orden">
            <header><span><small>Orden seleccionada</small><strong>#{{ $order->display_folio }}</strong></span><b class="orders-status orders-status--{{ $order->status_color }}"><i></i>{{ $order->status_label }}</b></header>
            <dl><div><dt>Cliente</dt><dd>{{ $order->customer?->name ?? $order->customer_name ?? 'Sin cliente' }}</dd></div><div><dt>Canal</dt><dd>{{ $order->type_label }}</dd></div><div><dt>Artículos</dt><dd>{{ $order->items->sum('quantity') }}</dd></div><div><dt>Total</dt><dd>${{ number_format($order->total, 2) }}</dd></div></dl>
            <div class="order-wizard-order__items">@foreach($order->items as $item)<p><span>{{ $item->quantity }} × {{ $item->product_name }}</span><b>${{ number_format($item->subtotal, 2) }}</b></p>@endforeach</div>
            @if($this->isPaidOrder)
                <div class="order-wizard-order__payments">
                    <small>Pagos originales</small>
                    @foreach($order->payments as $payment)<p><span>{{ $payment->method_label }}</span><b>${{ number_format($payment->amount, 2) }}</b></p>@endforeach
                </div>
            @endif
            <div class="orders-audit-notice"><i class="bx bx-lock-alt"></i><span>Solo owner o super-admin pueden aplicar el cambio.</span></div>
        </aside>
    </div>
</main>
