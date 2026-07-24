@if($showPickupPayModal)
@php
    $ppo = \App\Models\Order::with(['items.addons','items.product','customer','payments'])->find($pickupPayOrderId);
    $ppoItems = $ppo ? $ppo->items : collect();
    $ppoPaidAmt = collect($pickupPayments)->sum('amount');
    $ppoRem = $ppo ? max(0, $ppo->total - $ppoPaidAmt) : 0;
    $ppoCanConfirm = !empty($pickupPayments) && $ppoPaidAmt >= ($ppo->total ?? 0) - 0.01;
    $ppoIsContraDelivery = $ppo && $ppo->type === 'delivery' && $ppo->delivery_method === 'contra_entrega';
@endphp
<div class="pos-modal-wrap show" data-ui="xui-6jaq3m" wire:click.self="closePickupPayModal"
     role="dialog" aria-modal="true" aria-labelledby="pickup-pay-modal-title">
    <div class="pos-modal pos-pickup-pay-modal" data-ui="xui-6rsuae">
        <div class="modal-header-pos">
            <i class="bx bx-dollar-circle" data-ui="xui-13aobil"></i>
            <h4 id="pickup-pay-modal-title">Cobrar pedido listo</h4>
            <div data-ui="xui-17xfp9b">
                @if($ppo)
                    <span data-ui="xui-y4gxmi">Pedido #{{ $ppo->display_folio }}</span>
                @endif
                <button type="button" class="pos-btn pos-btn-ghost" data-ui="xui-1a0g5qw" wire:click="closePickupPayModal" aria-label="Cerrar">
                    <i class="bx bx-x" data-ui="xui-miwya2"></i>
                </button>
            </div>
        </div>

        <div class="modal-body-pos" data-ui="xui-1d9bqu5">

            @if($ppo)
            <div data-ui="xui-1rz9ctb">
                <div data-ui="xui-1beedj5">
                    <span data-ui="xui-6mock9">Resumen</span>
                    @if($ppo->customer_name)
                        <span data-ui="xui-abc7jm"><i class="bx bx-user" data-ui="xui-19cyg9q"></i> {{ $ppo->customer_name }}</span>
                    @endif
                </div>
                @foreach($ppoItems as $item)
                    <div data-ui="xui-1iolvfd">
                        <span>{{ $item->quantity }}x {{ $item->product->name ?? $item->product_name }}</span>
                        <span data-ui="xui-y9mhin">${{ number_format($item->subtotal, 2) }}</span>
                    </div>
                @endforeach
                <div data-ui="xui-1s0et43">
                    <span>Total</span>
                    <span>${{ number_format($ppo->total, 2) }}</span>
                </div>
            </div>
            @endif

            @if(!empty($pickupPayments))
                <div data-ui="xui-n3c866">
                    @foreach($pickupPayments as $pi => $pp)
                        <div data-ui="xui-qr2m4u">
                            <span><i class="bx bx-check-circle" data-ui="xui-1dncjhl"></i>{{ ['cash'=>'Efectivo','card'=>'Tarjeta','transfer'=>'Transferencia'][$pp['method']] ?? ucfirst($pp['method']) }}</span>
                            <div data-ui="xui-1a303bl">
                                <strong>${{ number_format($pp['amount'], 2) }}</strong>
                                <button wire:click="removePickupPayment({{ $pi }})" data-ui="xui-u6hbsq"><i class="bx bx-x"></i></button>
                            </div>
                        </div>
                    @endforeach
                    <div class="pos-payment-balance {{ $ppoRem > 0 ? 'is-pending' : 'is-complete' }}">
                        @if($ppoRem > 0) Restante: ${{ number_format($ppoRem, 2) }}
                        @else <i class="bx bx-check-circle"></i> Pagado completo @endif
                    </div>
                </div>
            @endif

            @if($ppoRem > 0 || empty($pickupPayments))
                <div class="pos-payment-method-tabs" data-ui="xui-1qqbkl9" role="group" aria-label="Método de pago">
                    @foreach(($ppoIsContraDelivery ? ['contra_entrega'=>'Contra entrega'] : ['cash'=>'Efectivo','contra_entrega'=>'Contra entrega','card'=>'Tarjeta','transfer'=>'Transferencia']) as $m => $label)
                        <button type="button" wire:click="$set('pickupPayMethod','{{ $m }}')"
                                class="pos-btn {{ $pickupPayMethod===$m ? 'pos-btn-primary' : 'pos-btn-secondary' }}"
                                aria-pressed="{{ $pickupPayMethod === $m ? 'true' : 'false' }}"
                                data-ui="xui-1hwm503">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                @if($pickupPayMethod === 'contra_entrega')
                    <div class="pos-payment-deferred">
                        <i class="bx bx-cycling"></i>
                        <div><strong>Cobro contra entrega</strong><small>El repartidor cobra este importe. No se registra en la caja del local.</small></div>
                    </div>
                    <input type="hidden" wire:model="pickupPayAmount" value="{{ number_format($ppoRem, 2, '.', '') }}">
                @elseif($pickupPayMethod === 'cash')
                    <div class="pos-payment-entry-grid">
                        <div class="pos-payment-field">
                            <label for="pickup-pay-amount">Monto a cobrar</label>
                            <div class="pos-payment-input">
                                <span aria-hidden="true">$</span>
                                <input id="pickup-pay-amount" type="number" wire:model.live="pickupPayAmount" class="pos-input"
                                   placeholder="{{ number_format($ppoRem, 2) }}"
                                   value="{{ $pickupPayAmount ?: number_format($ppoRem, 2) }}"
                                   step="0.01" min="0" inputmode="decimal">
                            </div>
                            <small>Saldo pendiente: ${{ number_format($ppoRem, 2) }}</small>
                        </div>
                        <div class="pos-payment-field">
                            <label for="pickup-pay-received">Efectivo recibido</label>
                            <div class="pos-payment-input">
                                <span aria-hidden="true">$</span>
                                <input id="pickup-pay-received" type="number" wire:model.live="pickupPayReceived" class="pos-input"
                                   placeholder="0.00" step="0.01" min="0" inputmode="decimal">
                            </div>
                            <small>Captura lo entregado por el cliente.</small>
                        </div>
                    </div>
                    @php
                        $ppoMonto    = (float)($pickupPayAmount ?: $ppoRem);
                        $ppoRecibido = (float)$pickupPayReceived;
                    @endphp
                    @if($ppoRecibido > 0)
                        <div class="pos-payment-received {{ $ppoRecibido >= $ppoMonto ? 'is-complete' : 'is-pending' }}">
                            @if($ppoRecibido >= $ppoMonto)
                                <i class="bx bx-check-circle"></i> Cambio: <strong>${{ number_format($ppoRecibido - $ppoMonto, 2) }}</strong>
                            @else
                                <i class="bx bx-error-circle"></i> Faltan: <strong>${{ number_format($ppoMonto - $ppoRecibido, 2) }}</strong>
                            @endif
                        </div>
                    @endif
                @elseif($pickupPayMethod === 'card')
                    <div data-ui="xui-167pdlk">
                        <div data-ui="xui-1x8awyf">
                            <span data-ui="xui-1n7u11m">$</span>
                            <input type="number" wire:model.live="pickupPayAmount" class="pos-input" data-ui="xui-78yyj2"
                                   placeholder="{{ number_format($ppoRem, 2) }}" step="0.01" min="0">
                            <span data-ui="xui-1p3z5bq">Monto</span>
                        </div>
                        <div data-ui="xui-1x8awyf">
                            <span data-ui="xui-1n7u11m">#</span>
                            <input type="text" wire:model="pickupPayCard" class="pos-input" data-ui="xui-78yyj2" placeholder="Últimos 4" maxlength="4">
                            <span data-ui="xui-1p3z5bq">Tarjeta</span>
                        </div>
                    </div>
                @elseif($pickupPayMethod === 'transfer')
                    <div data-ui="xui-167pdlk">
                        <div data-ui="xui-1x8awyf">
                            <span data-ui="xui-1n7u11m">$</span>
                            <input type="number" wire:model.live="pickupPayAmount" class="pos-input" data-ui="xui-78yyj2"
                                   placeholder="{{ number_format($ppoRem, 2) }}" step="0.01" min="0">
                            <span data-ui="xui-1p3z5bq">Monto</span>
                        </div>
                        <div data-ui="xui-1x8awyf">
                            <span data-ui="xui-13xqprg">#</span>
                            <input type="text" wire:model="pickupPayRef" class="pos-input" data-ui="xui-78yyj2" placeholder="Referencia">
                            <span data-ui="xui-1p3z5bq">Ref.</span>
                        </div>
                    </div>
                @endif

                <button type="button" wire:click="addPickupPayment" class="pos-btn pos-btn-secondary pos-add-payment" data-ui="xui-5q5jzi">
                    <i class="bx bx-plus"></i> Agregar pago
                </button>
            @endif
        </div>

        <div class="modal-footer-pos" data-ui="xui-c1sc8d">
            <button type="button" class="pos-btn pos-btn-ghost pos-btn-lg" wire:click="closePickupPayModal">Cancelar</button>
            <button type="button" wire:click="confirmPickupPayment"
                    wire:loading.attr="disabled" wire:target="confirmPickupPayment"
                    class="pos-btn pos-btn-primary pos-btn-lg"
                    {{ $ppoCanConfirm ? '' : 'disabled' }}>
                <span wire:loading wire:target="confirmPickupPayment" class="pos-btn-spinner"></span>
                <i wire:loading.remove wire:target="confirmPickupPayment" class="bx bx-check-circle"></i>
                Confirmar pago
            </button>
        </div>
    </div>
</div>
@endif
