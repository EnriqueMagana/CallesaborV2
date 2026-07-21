@if($showMesaPayModal)
@php
    $mpMesa = \App\Models\Mesa::with([
        'area',
        'currentAssignment.waiter',
        'orders' => fn($q) => $q->whereIn('status', ['pendiente','en_preparacion','lista','entregada'])->with('items'),
    ])->find($mesaPayId);

    // Split mode
    if ($mesaSplitId !== null) {
        $mpSplit       = \App\Models\MesaSplit::find($mesaSplitId);
        $mpAccount     = $mpSplit ? ($mpSplit->split_data[$mesaSplitAccountIdx] ?? null) : null;
        $mpAccountLabel = $mpAccount ? $mpAccount['label'] : '';
        $mpTotal       = $mpAccount ? (float)$mpAccount['total'] : 0;
        $mpItems       = collect($mpAccount['items'] ?? []);
        $mpIsSplit     = true;
    } else {
        $mpAccount     = null;
        $mpAccountLabel = $mpMesa?->display_name ?? '';
        $mpOrders      = $mpMesa ? $mpMesa->orders : collect();
        $mpTotal       = (float) $mpOrders->sum('total');
        $mpItems       = collect(); // items shown per order below
        $mpIsSplit     = false;
    }

    $mpPaid      = collect($mesaPayments)->sum('amount');
    $mpRem       = max(0, $mpTotal - $mpPaid);
    $mpDirectAmount = empty($mesaPayments) ? (float)($mesaPayAmount ?: $mpRem) : 0;
    $mpCanPay    = ($mpPaid + $mpDirectAmount) >= $mpTotal - 0.01;
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
                        <button wire:click="$set('mesaPayMethod','{{ $m }}')"
                                class="pos-btn {{ $mesaPayMethod===$m ? 'pos-btn-primary' : 'pos-btn-secondary' }}"
                                data-ui="xui-1hwm503">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                @if($mesaPayMethod === 'cash')
                    <div data-ui="xui-167pdlk">
                        <div data-ui="xui-1x8awyf">
                            <span data-ui="xui-1n7u11m">$</span>
                            <input type="number" wire:model.live="mesaPayAmount" class="pos-input" data-ui="xui-78yyj2"
                                   placeholder="{{ number_format($mpRem, 2) }}" step="0.01" min="0">
                            <span data-ui="xui-1p3z5bq">Monto</span>
                        </div>
                        <div data-ui="xui-1x8awyf">
                            <span data-ui="xui-1n7u11m">$</span>
                            <input type="number" wire:model.live="mesaPayReceived" class="pos-input" data-ui="xui-78yyj2"
                                   placeholder="Recibido" step="0.01" min="0">
                            <span data-ui="xui-1p3z5bq">Recibido</span>
                        </div>
                    </div>
                    @php
                        $mpMonto    = (float)($mesaPayAmount ?: $mpRem);
                        $mpRecibido = (float)$mesaPayReceived;
                    @endphp
                    @if($mpRecibido > 0)
                        <div class="pos-payment-received {{ $mpRecibido >= $mpMonto ? 'is-complete' : 'is-pending' }}">
                            @if($mpRecibido >= $mpMonto)
                                <i class="bx bx-check-circle"></i> Cambio: <strong>${{ number_format($mpRecibido - $mpMonto, 2) }}</strong>
                            @else
                                <i class="bx bx-error-circle"></i> Faltan: <strong>${{ number_format($mpMonto - $mpRecibido, 2) }}</strong>
                            @endif
                        </div>
                    @endif
                @elseif($mesaPayMethod === 'card')
                    <div data-ui="xui-167pdlk">
                        <div data-ui="xui-1x8awyf">
                            <span data-ui="xui-1n7u11m">$</span>
                            <input type="number" wire:model.live="mesaPayAmount" class="pos-input" data-ui="xui-78yyj2"
                                   placeholder="{{ number_format($mpRem, 2) }}" step="0.01" min="0">
                            <span data-ui="xui-1p3z5bq">Monto</span>
                        </div>
                        <div data-ui="xui-1x8awyf">
                            <span data-ui="xui-1n7u11m">#</span>
                            <input type="text" wire:model="mesaPayCard" class="pos-input" data-ui="xui-78yyj2" placeholder="Últimos 4" maxlength="4">
                            <span data-ui="xui-1p3z5bq">Tarjeta</span>
                        </div>
                    </div>
                @elseif($mesaPayMethod === 'transfer')
                    <div data-ui="xui-167pdlk">
                        <div data-ui="xui-1x8awyf">
                            <span data-ui="xui-1n7u11m">$</span>
                            <input type="number" wire:model.live="mesaPayAmount" class="pos-input" data-ui="xui-78yyj2"
                                   placeholder="{{ number_format($mpRem, 2) }}" step="0.01" min="0">
                            <span data-ui="xui-1p3z5bq">Monto</span>
                        </div>
                        <div data-ui="xui-1x8awyf">
                            <span data-ui="xui-13xqprg">#</span>
                            <input type="text" wire:model="mesaPayRef" class="pos-input" data-ui="xui-78yyj2" placeholder="Referencia">
                            <span data-ui="xui-1p3z5bq">Ref.</span>
                        </div>
                    </div>
                @endif

                <button wire:click="addMesaPayment" class="pos-btn pos-btn-secondary pos-add-payment" data-ui="xui-5q5jzi">
                    <i class="bx bx-plus"></i> Agregar pago
                </button>
            @endif
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
