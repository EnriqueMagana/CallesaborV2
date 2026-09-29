@if($showPickupPayModal)
@php
    $ppo = $this->pickupPayOrder;
    $ppoItems = $ppo?->items ?? collect();
    $ppoPaidAmt = (float) collect($pickupPayments)->sum('amount');
    $ppoPrevPaid = $ppo?->net_paid_amount ?? 0;
    $ppoDue = $ppo?->uncovered_amount ?? 0;
    $ppoRem = max(0, $ppoDue - $ppoPaidAmt);
    $ppoCanConfirm = $ppo && !empty($pickupPayments)
        && round($ppoPaidAmt * 100) === round($ppoDue * 100);
@endphp
<div class="pos-modal-wrap show pos-charge-dialog" wire:click.self="closePickupPayModal"
     role="dialog" aria-modal="true" aria-labelledby="pickup-pay-modal-title"
     aria-describedby="pickup-pay-modal-description">
    <div class="pos-modal pos-pickup-pay-modal">
        <header class="modal-header-pos pos-charge-dialog__header">
            <span class="pos-charge-dialog__mark" aria-hidden="true"><i class="bx bx-dollar-circle"></i></span>
            <div class="pos-charge-dialog__heading">
                <span>COBRO EN VENTANILLA</span>
                <h4 id="pickup-pay-modal-title">Cobrar saldo restante</h4>
                <p id="pickup-pay-modal-description">Registra únicamente la diferencia pendiente de esta orden.</p>
            </div>
            <div class="pos-charge-dialog__header-actions">
                @if($ppo)<span class="pos-charge-dialog__folio">{{ $ppo->display_folio }}</span>@endif
                <button type="button" class="pos-charge-dialog__close" wire:click="closePickupPayModal" aria-label="Cerrar cobro">
                    <i class="bx bx-x" aria-hidden="true"></i>
                </button>
            </div>
        </header>

        <div class="modal-body-pos pos-charge-dialog__body">
            @if($ppo)
                <section class="pos-charge-summary" aria-label="Resumen del cobro">
                    <div class="pos-charge-summary__identity">
                        <div>
                            <span>Orden</span>
                            <strong>{{ $ppo->display_name }}</strong>
                        </div>
                        <span>{{ $ppoItems->sum('quantity') }} productos</span>
                    </div>
                    <dl class="pos-charge-summary__totals">
                        <div><dt>Total actualizado</dt><dd>${{ number_format($ppo->total, 2) }}</dd></div>
                        <div><dt>Ya cobrado</dt><dd>−${{ number_format($ppoPrevPaid, 2) }}</dd></div>
                        <div class="is-due"><dt>Saldo por cobrar</dt><dd>${{ number_format($ppoDue, 2) }}</dd></div>
                    </dl>
                    <details class="pos-charge-summary__items">
                        <summary>Ver productos <i class="bx bx-chevron-down" aria-hidden="true"></i></summary>
                        <ul>
                            @foreach($ppoItems as $item)
                                <li><span>{{ $item->quantity }}× {{ $item->product->name ?? $item->product_name }}</span><strong>${{ number_format($item->subtotal, 2) }}</strong></li>
                            @endforeach
                        </ul>
                    </details>
                </section>
            @else
                <div class="pos-inline-alert pos-inline-alert--danger" role="alert">
                    <i class="bx bx-error-circle" aria-hidden="true"></i>
                    <span>La orden ya no está disponible para cobro.</span>
                </div>
            @endif

            @if(!empty($pickupPayments))
                <section class="pos-charge-payments" aria-label="Pagos agregados">
                    <header><span>Pagos agregados</span><strong>${{ number_format($ppoPaidAmt, 2) }}</strong></header>
                    @foreach($pickupPayments as $pi => $pp)
                        <div class="pos-charge-payment" wire:key="pickup-payment-{{ $pi }}">
                            <span><i class="bx bx-check-circle" aria-hidden="true"></i>{{ ['cash'=>'Efectivo','card'=>'Tarjeta','transfer'=>'Transferencia'][$pp['method']] ?? ucfirst($pp['method']) }}</span>
                            <strong>${{ number_format($pp['amount'], 2) }}</strong>
                            <button type="button" wire:click="removePickupPayment({{ $pi }})" aria-label="Quitar pago de ${{ number_format($pp['amount'], 2) }}">
                                <i class="bx bx-trash" aria-hidden="true"></i>
                            </button>
                        </div>
                    @endforeach
                    <div class="pos-payment-balance {{ $ppoRem > 0 ? 'is-pending' : 'is-complete' }}" aria-live="polite">
                        @if($ppoRem > 0)
                            Restante: <strong>${{ number_format($ppoRem, 2) }}</strong>
                        @else
                            <i class="bx bx-check-circle" aria-hidden="true"></i> Saldo cubierto
                        @endif
                    </div>
                </section>
            @endif

            @if($ppo && ($ppoRem > 0 || empty($pickupPayments)))
                <section class="pos-charge-entry" aria-labelledby="pickup-payment-method-title">
                    <div class="pos-charge-entry__title">
                        <div><span>Paso 1</span><h5 id="pickup-payment-method-title">Método de pago</h5></div>
                        <strong>Restan ${{ number_format($ppoRem, 2) }}</strong>
                    </div>
                    <div class="pos-payment-method-tabs" role="group" aria-label="Método de pago">
                        @foreach(['cash'=>'Efectivo','card'=>'Tarjeta','transfer'=>'Transferencia'] as $m => $label)
                            <button type="button" wire:click="$set('pickupPayMethod','{{ $m }}')"
                                class="pos-charge-method {{ $pickupPayMethod === $m ? 'is-active' : '' }}"
                                aria-pressed="{{ $pickupPayMethod === $m ? 'true' : 'false' }}">
                                <i class="bx {{ ['cash'=>'bx-money','card'=>'bx-credit-card','transfer'=>'bx-transfer'][$m] }}" aria-hidden="true"></i>
                                <span>{{ $label }}</span>
                            </button>
                        @endforeach
                    </div>

                    <div class="pos-charge-entry__title is-fields">
                        <div><span>Paso 2</span><h5>Datos del cobro</h5></div>
                    </div>

                    @if($pickupPayMethod === 'cash')
                        <div class="pos-payment-entry-grid">
                            <div class="pos-payment-field">
                                <label for="pickup-pay-amount">Monto a cobrar</label>
                                <div class="pos-payment-input"><span aria-hidden="true">$</span>
                                    <input id="pickup-pay-amount" type="number" wire:model.blur="pickupPayAmount" class="pos-input"
                                        placeholder="{{ number_format($ppoRem, 2, '.', '') }}" step="0.01" min="0" max="{{ number_format($ppoRem, 2, '.', '') }}" inputmode="decimal">
                                </div>
                                <small>Máximo disponible: ${{ number_format($ppoRem, 2) }}</small>
                            </div>
                            <div class="pos-payment-field">
                                <label for="pickup-pay-received">Efectivo recibido</label>
                                <div class="pos-payment-input"><span aria-hidden="true">$</span>
                                    <input id="pickup-pay-received" type="number" wire:model.blur="pickupPayReceived" class="pos-input"
                                        placeholder="{{ number_format($ppoRem, 2, '.', '') }}" step="0.01" min="0" inputmode="decimal">
                                </div>
                                <small>Déjalo vacío si el cliente entrega el monto exacto.</small>
                                @error('pickupPayReceived')<p class="pos-payment-field-error" role="alert"><i class="bx bx-error-circle"></i>{{ $message }}</p>@enderror
                            </div>
                        </div>
                        @php
                            $ppoMonto = (float)($pickupPayAmount ?: $ppoRem);
                            $ppoRecibido = (float)$pickupPayReceived;
                        @endphp
                        @if($ppoRecibido > 0)
                            <div class="pos-payment-received {{ $ppoRecibido >= $ppoMonto ? 'is-complete' : 'is-pending' }}" aria-live="polite">
                                @if($ppoRecibido >= $ppoMonto)
                                    <i class="bx bx-check-circle" aria-hidden="true"></i> Cambio: <strong>${{ number_format($ppoRecibido - $ppoMonto, 2) }}</strong>
                                @else
                                    <i class="bx bx-error-circle" aria-hidden="true"></i> Faltan: <strong>${{ number_format($ppoMonto - $ppoRecibido, 2) }}</strong>
                                @endif
                            </div>
                        @endif
                    @elseif($pickupPayMethod === 'card')
                        <div class="pos-payment-entry-grid">
                            <div class="pos-payment-field">
                                <label for="pickup-card-amount">Monto con tarjeta</label>
                                <div class="pos-payment-input"><span aria-hidden="true">$</span>
                                    <input id="pickup-card-amount" type="number" wire:model.blur="pickupPayAmount" class="pos-input"
                                        placeholder="{{ number_format($ppoRem, 2, '.', '') }}" step="0.01" min="0" max="{{ number_format($ppoRem, 2, '.', '') }}" inputmode="decimal">
                                </div>
                            </div>
                            <div class="pos-payment-field">
                                <label for="pickup-card-last4">Últimos 4 dígitos</label>
                                <div class="pos-payment-input pos-payment-input--reference"><span aria-hidden="true">#</span>
                                    <input id="pickup-card-last4" type="text" wire:model="pickupPayCard" class="pos-input" placeholder="1234" maxlength="4" inputmode="numeric">
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="pos-payment-entry-grid">
                            <div class="pos-payment-field">
                                <label for="pickup-transfer-amount">Monto transferido</label>
                                <div class="pos-payment-input"><span aria-hidden="true">$</span>
                                    <input id="pickup-transfer-amount" type="number" wire:model.blur="pickupPayAmount" class="pos-input"
                                        placeholder="{{ number_format($ppoRem, 2, '.', '') }}" step="0.01" min="0" max="{{ number_format($ppoRem, 2, '.', '') }}" inputmode="decimal">
                                </div>
                            </div>
                            <div class="pos-payment-field">
                                <label for="pickup-transfer-reference">Referencia</label>
                                <div class="pos-payment-input pos-payment-input--reference"><span aria-hidden="true">#</span>
                                    <input id="pickup-transfer-reference" type="text" wire:model="pickupPayRef" class="pos-input" placeholder="Referencia de operación">
                                </div>
                            </div>
                        </div>
                    @endif

                    <button type="button" wire:click="addPickupPayment" wire:loading.attr="disabled"
                        wire:target="addPickupPayment" class="pos-btn pos-btn-secondary pos-add-payment" @disabled($ppoRem <= 0)>
                        <span wire:loading wire:target="addPickupPayment" class="pos-btn-spinner"></span>
                        <i wire:loading.remove wire:target="addPickupPayment" class="bx bx-plus" aria-hidden="true"></i>
                        Agregar pago
                    </button>
                </section>
            @endif
        </div>

        <footer class="modal-footer-pos pos-charge-dialog__footer">
            <button type="button" class="pos-btn pos-btn-ghost pos-btn-lg" wire:click="closePickupPayModal">Cancelar</button>
            <button type="button" wire:click="confirmPickupPayment" wire:loading.attr="disabled"
                wire:target="confirmPickupPayment" class="pos-btn pos-btn-primary pos-btn-lg"
                {{ $ppoCanConfirm ? '' : 'disabled' }}>
                <span wire:loading wire:target="confirmPickupPayment" class="pos-btn-spinner"></span>
                <i wire:loading.remove wire:target="confirmPickupPayment" class="bx bx-check-circle" aria-hidden="true"></i>
                Cobrar ${{ number_format($ppoDue, 2) }}
            </button>
        </footer>
    </div>
</div>
@endif
