@if($showMesaPayModal)
@php
    $mpContext = $this->mesaPaymentContext;
    $mpMesa = $mpContext['mesa'];
    $mpAccount = $mpContext['account'];
    $mpAccountLabel = $mpContext['accountLabel'];
    $mpOrders = $mpContext['orders'];
    $mpTotal = $mpContext['total'];
    $mpItems = $mpContext['items'];
    $mpIsSplit = $mpContext['isSplit'];

    $mpPaid      = collect($mesaPayments)->sum('amount');
    $mpRem       = max(0, $mpTotal - $mpPaid);
    $mpCanPay    = !empty($mesaPayments) && $mpPaid >= $mpTotal - 0.01;
    $assignment  = $mpMesa?->currentAssignment;
@endphp
<div class="pos-modal-wrap show pos-modal-shell" data-ui="xui-6jaq3m" wire:click.self="closeMesaPayModal" role="dialog" aria-modal="true" aria-labelledby="mesa-pay-title">
    <div class="pos-modal pos-modal-modern pos-mesa-pay-modal" data-ui="xui-1iwymxt">
        <div class="modal-header-pos pos-modal-modern__header">
            <i class="bx bx-table" data-ui="xui-bdms3y"></i>
            <div data-ui="xui-o4rqv9">
                <h2 id="mesa-pay-title" data-ui="xui-84kbzi">
                    @if($mpIsSplit) {{ $mpAccountLabel }} @else Cobrar {{ $mpMesa?->display_name ?? '' }} @endif
                </h2>
                @if($mpMesa)
                    <div data-ui="xui-1qoh619">
                        {{ $mpMesa->display_name }} · {{ $mpMesa->area?->name ?? '' }}
                        @if($assignment)
                            &nbsp;·&nbsp;<i class="bx bx-user" data-ui="xui-1dnxcst"></i> {{ $assignment->waiter?->name }}
                            &nbsp;·&nbsp;Apertura: {{ $assignment->assigned_at->format('H:i') }}
                        @endif
                    </div>
                @endif
            </div>
            <button type="button" class="pos-modal-close" wire:click="closeMesaPayModal" aria-label="Cerrar cobro de mesa">
                <i class="bx bx-x" data-ui="xui-miwya2"></i>
            </button>
        </div>

        <div class="modal-body-pos pos-modal-modern__body pos-mesa-pay-body" data-ui="xui-1d9bqu5">

            {{-- Order / account summary --}}
            <section class="pos-payment-summary" data-ui="xui-1rz9ctb" aria-label="Resumen de la cuenta">
                @if($mpIsSplit)
                    {{-- Split account items snapshot --}}
                    <div data-ui="xui-ezsahi">
                        {{ $mpAccountLabel }}
                    </div>
                    @foreach($mpItems as $item)
                        <div data-ui="xui-16zjevx">
                            <span>{{ $item['qty'] }}x {{ $item['name'] }}</span>
                            <span data-ui="xui-y9mhin">${{ number_format((float)$item['subtotal'], 2) }}</span>
                        </div>
                    @endforeach
                    @if($mpItems->isEmpty())
                        <div data-ui="xui-1lk1hjf">
                            División en partes iguales
                        </div>
                    @endif
                @else
                    {{-- All orders --}}
                    @foreach($mpOrders as $order)
                        <div data-ui="xui-bhmr11">
                            <div data-ui="xui-yfsx9h">
                                Orden #{{ $order->display_folio }}
                            </div>
                            @foreach($order->items as $item)
                                <div data-ui="xui-1wennv6">
                                    <span>{{ $item->quantity }}x {{ $item->product_name }}</span>
                                    <span data-ui="xui-y9mhin">${{ number_format($item->subtotal, 2) }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                @endif
                <div data-ui="xui-1s0et43">
                    <span>Total</span>
                    <span>${{ number_format($mpTotal, 2) }}</span>
                </div>
            </section>

            {{-- Payments already added --}}
            @if(!empty($mesaPayments))
                <div data-ui="xui-n3c866">
                    @foreach($mesaPayments as $pi => $pp)
                        <div data-ui="xui-qr2m4u">
                            <span><i class="bx bx-check-circle" data-ui="xui-1dncjhl"></i>{{ ['cash'=>'Efectivo','card'=>'Tarjeta','transfer'=>'Transferencia'][$pp['method']] ?? ucfirst($pp['method']) }}</span>
                            <div data-ui="xui-1a303bl">
                                <strong>${{ number_format($pp['amount'], 2) }}</strong>
                                <button wire:click="removeMesaPayment({{ $pi }})" data-ui="xui-u6hbsq"><i class="bx bx-x"></i></button>
                            </div>
                        </div>
                    @endforeach
                    <div class="pos-payment-balance {{ $mpRem > 0 ? 'is-pending' : 'is-complete' }}">
                        @if($mpRem > 0) Restante: ${{ number_format($mpRem, 2) }}
                        @else <i class="bx bx-check-circle"></i> Pagado completo @endif
                    </div>
                </div>
            @endif

            {{-- Payment input --}}
            @if($mpRem > 0 || empty($mesaPayments))
                <div class="pos-payment-methods" data-ui="xui-1qqbkl9" role="group" aria-label="Forma de pago">
                    @foreach(['cash'=>'Efectivo','card'=>'Tarjeta','transfer'=>'Transferencia'] as $m => $label)
                        <button type="button" wire:click="$set('mesaPayMethod','{{ $m }}')"
                                class="pos-btn {{ $mesaPayMethod===$m ? 'pos-btn-primary' : 'pos-btn-secondary' }}"
                                aria-pressed="{{ $mesaPayMethod === $m ? 'true' : 'false' }}"
                                data-ui="xui-1hwm503">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                @if($mesaPayMethod === 'cash')
                    <div class="pos-payment-entry-grid">
                        <div class="pos-payment-field">
                            <label for="mesa-pay-amount">Monto a aplicar</label>
                            <div class="pos-payment-input">
                                <span aria-hidden="true">$</span>
                                <input id="mesa-pay-amount" type="text" wire:model.blur="mesaPayAmount" class="pos-input"
                                       placeholder="0.00" inputmode="decimal" autocomplete="off"
                                       pattern="[0-9]+([.][0-9]{1,2})?" maxlength="12">
                            </div>
                            <small>Saldo pendiente: ${{ number_format($mpRem, 2) }}</small>
                            @error('mesaPayAmount')<span class="pos-payment-field-error"><i class="bx bx-error-circle"></i>{{ $message }}</span>@enderror
                        </div>
                        <div class="pos-payment-field">
                            <label for="mesa-pay-received">Efectivo recibido</label>
                            <div class="pos-payment-input">
                                <span aria-hidden="true">$</span>
                                <input id="mesa-pay-received" type="text" wire:model.blur="mesaPayReceived" class="pos-input"
                                       placeholder="0.00" inputmode="decimal" autocomplete="off"
                                       pattern="[0-9]+([.][0-9]{1,2})?" maxlength="12">
                            </div>
                            <small>Captura lo entregado por el cliente.</small>
                            @error('mesaPayReceived')<span class="pos-payment-field-error"><i class="bx bx-error-circle"></i>{{ $message }}</span>@enderror
                        </div>
                    </div>
                    @php
                        $mpMonto    = (float)$mesaPayAmount;
                        $mpRecibido = (float)$mesaPayReceived;
                    @endphp
                    @if($mpMonto > 0 && $mpRecibido > 0)
                        <div class="pos-payment-received {{ $mpRecibido >= $mpMonto ? 'is-complete' : 'is-pending' }}">
                            @if($mpRecibido >= $mpMonto)
                                <i class="bx bx-check-circle"></i> Cambio: <strong>${{ number_format($mpRecibido - $mpMonto, 2) }}</strong>
                            @else
                                <i class="bx bx-error-circle"></i> Faltan: <strong>${{ number_format($mpMonto - $mpRecibido, 2) }}</strong>
                            @endif
                        </div>
                    @endif
                @elseif($mesaPayMethod === 'card')
                    <div class="pos-payment-entry-grid">
                        <div class="pos-payment-field">
                            <label for="mesa-card-amount">Monto a aplicar</label>
                            <div class="pos-payment-input">
                                <span aria-hidden="true">$</span>
                                <input id="mesa-card-amount" type="text" wire:model.blur="mesaPayAmount" class="pos-input"
                                       placeholder="0.00" inputmode="decimal" autocomplete="off"
                                       pattern="[0-9]+([.][0-9]{1,2})?" maxlength="12">
                            </div>
                            <small>Saldo pendiente: ${{ number_format($mpRem, 2) }}</small>
                            @error('mesaPayAmount')<span class="pos-payment-field-error"><i class="bx bx-error-circle"></i>{{ $message }}</span>@enderror
                        </div>
                        <div class="pos-payment-field">
                            <label for="mesa-card-last4">Últimos 4 dígitos</label>
                            <div class="pos-payment-input pos-payment-input--reference">
                                <span aria-hidden="true">#</span>
                                <input id="mesa-card-last4" type="text" wire:model="mesaPayCard" class="pos-input"
                                       placeholder="0000" inputmode="numeric" autocomplete="off"
                                       pattern="[0-9]{4}" maxlength="4">
                            </div>
                            <small>Dato opcional para localizar el pago.</small>
                        </div>
                    </div>
                @elseif($mesaPayMethod === 'transfer')
                    <div class="pos-payment-entry-grid">
                        <div class="pos-payment-field">
                            <label for="mesa-transfer-amount">Monto a aplicar</label>
                            <div class="pos-payment-input">
                                <span aria-hidden="true">$</span>
                                <input id="mesa-transfer-amount" type="text" wire:model.blur="mesaPayAmount" class="pos-input"
                                       placeholder="0.00" inputmode="decimal" autocomplete="off"
                                       pattern="[0-9]+([.][0-9]{1,2})?" maxlength="12">
                            </div>
                            <small>Saldo pendiente: ${{ number_format($mpRem, 2) }}</small>
                            @error('mesaPayAmount')<span class="pos-payment-field-error"><i class="bx bx-error-circle"></i>{{ $message }}</span>@enderror
                        </div>
                        <div class="pos-payment-field">
                            <label for="mesa-transfer-reference">Referencia</label>
                            <div class="pos-payment-input pos-payment-input--reference">
                                <span aria-hidden="true">#</span>
                                <input id="mesa-transfer-reference" type="text" wire:model="mesaPayRef" class="pos-input"
                                       placeholder="Folio o referencia" autocomplete="off" maxlength="80">
                            </div>
                            <small>Dato opcional para conciliación.</small>
                        </div>
                    </div>
                @endif

                <button type="button" wire:click="addMesaPayment"
                        wire:loading.attr="disabled" wire:target="addMesaPayment"
                        class="pos-btn pos-btn-secondary pos-add-payment" data-ui="xui-5q5jzi">
                    <span wire:loading wire:target="addMesaPayment" class="pos-btn-spinner"></span>
                    <i wire:loading.remove wire:target="addMesaPayment" class="bx bx-plus"></i>
                    Agregar pago
                </button>
            @endif

            @if(empty($mesaPayments))
                <div class="pos-payment-required" role="status">
                    <i class="bx bx-info-circle" aria-hidden="true"></i>
                    <span>Agrega al menos un pago para habilitar el cobro.</span>
                </div>
            @endif
            @error('mesaPayments')
                <div class="pos-inline-alert pos-inline-alert--danger" role="alert">
                    <i class="bx bx-error-circle" aria-hidden="true"></i>{{ $message }}
                </div>
            @enderror
        </div>

        <div class="modal-footer-pos pos-modal-modern__footer" data-ui="xui-c1sc8d">
            <button class="pos-btn pos-btn-ghost pos-btn-lg" wire:click="closeMesaPayModal">Cancelar</button>
            <button wire:click="confirmMesaPayment"
                    wire:loading.attr="disabled" wire:target="confirmMesaPayment"
                    class="pos-btn pos-btn-primary pos-btn-lg"
                    {{ $mpCanPay ? '' : 'disabled' }}>
                <span wire:loading wire:target="confirmMesaPayment" class="pos-btn-spinner"></span>
                <i wire:loading.remove wire:target="confirmMesaPayment" class="bx bx-check-circle"></i>
                @if($mpIsSplit) Cobrar cuenta @else Cobrar y liberar mesa @endif
            </button>
        </div>
    </div>
</div>
@endif
