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
    $mpCanPay    = !empty($mesaPayments) && $mpPaid >= $mpTotal - 0.01;
    $assignment  = $mpMesa?->currentAssignment;
@endphp
<div class="pos-modal-wrap show" style="z-index:1450" wire:click.self="closeMesaPayModal">
    <div class="pos-modal" style="max-width:500px">
        <div class="modal-header-pos">
            <i class="bx bx-table" style="font-size:1.1rem;color:#ef4444"></i>
            <div style="margin-left:8px">
                <h5 style="margin:0;font-size:1rem;font-weight:700">
                    @if($mpIsSplit) {{ $mpAccountLabel }} @else Cobrar {{ $mpMesa?->display_name ?? '' }} @endif
                </h5>
                @if($mpMesa)
                    <div style="font-size:.72rem;color:var(--pos-muted);margin-top:1px">
                        {{ $mpMesa->display_name }} · {{ $mpMesa->area?->name ?? '' }}
                        @if($assignment)
                            &nbsp;·&nbsp;<i class="bx bx-user" style="font-size:.7rem"></i> {{ $assignment->waiter?->name }}
                            &nbsp;·&nbsp;Apertura: {{ $assignment->assigned_at->format('H:i') }}
                        @endif
                    </div>
                @endif
            </div>
            <button class="pos-btn pos-btn-ghost" style="padding:4px 8px;margin-left:auto" wire:click="closeMesaPayModal">
                <i class="bx bx-x" style="font-size:1.1rem"></i>
            </button>
        </div>

        <div class="modal-body-pos" style="padding:16px;max-height:65vh;overflow-y:auto">

            {{-- Order / account summary --}}
            <div style="background:var(--pos-bg);border-radius:10px;padding:12px;margin-bottom:16px">
                @if($mpIsSplit)
                    {{-- Split account items snapshot --}}
                    <div style="font-size:.72rem;font-weight:700;color:var(--pos-muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px">
                        {{ $mpAccountLabel }}
                    </div>
                    @foreach($mpItems as $item)
                        <div style="display:flex;justify-content:space-between;align-items:baseline;padding:3px 0;border-bottom:1px solid var(--pos-border);font-size:.82rem">
                            <span>{{ $item['qty'] }}x {{ $item['name'] }}</span>
                            <span style="font-weight:600">${{ number_format((float)$item['subtotal'], 2) }}</span>
                        </div>
                    @endforeach
                    @if($mpItems->isEmpty())
                        <div style="font-size:.78rem;color:var(--pos-muted);font-style:italic">
                            División en partes iguales
                        </div>
                    @endif
                @else
                    {{-- All orders --}}
                    @foreach($mpOrders as $order)
                        <div style="margin-bottom:6px">
                            <div style="font-size:.72rem;font-weight:700;color:var(--pos-muted);margin-bottom:3px">
                                Orden #{{ $order->id }}
                            </div>
                            @foreach($order->items as $item)
                                <div style="display:flex;justify-content:space-between;align-items:baseline;padding:2px 0 2px 8px;font-size:.82rem;border-bottom:1px solid var(--pos-border)">
                                    <span>{{ $item->quantity }}x {{ $item->product_name }}</span>
                                    <span style="font-weight:600">${{ number_format($item->subtotal, 2) }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                @endif
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;font-size:1rem;font-weight:700;color:var(--pos-text)">
                    <span>Total</span>
                    <span>${{ number_format($mpTotal, 2) }}</span>
                </div>
            </div>

            {{-- Payments already added --}}
            @if(!empty($mesaPayments))
                <div style="margin-bottom:12px">
                    @foreach($mesaPayments as $pi => $pp)
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:6px 10px;background:rgba(34,197,94,.08);border-radius:8px;margin-bottom:4px;font-size:.83rem">
                            <span><i class="bx bx-check-circle" style="color:#22c55e;margin-right:4px"></i>{{ ['cash'=>'Efectivo','card'=>'Tarjeta','transfer'=>'Transferencia'][$pp['method']] ?? ucfirst($pp['method']) }}</span>
                            <div style="display:flex;align-items:center;gap:8px">
                                <strong>${{ number_format($pp['amount'], 2) }}</strong>
                                <button wire:click="removeMesaPayment({{ $pi }})" style="background:none;border:none;color:var(--pos-danger);cursor:pointer;padding:0"><i class="bx bx-x"></i></button>
                            </div>
                        </div>
                    @endforeach
                    <div style="font-size:.8rem;text-align:right;{{ $mpRem > 0 ? 'color:var(--pos-danger)' : 'color:#22c55e;font-weight:700' }}">
                        @if($mpRem > 0) Restante: ${{ number_format($mpRem, 2) }}
                        @else <i class="bx bx-check-circle"></i> Pagado completo @endif
                    </div>
                </div>
            @endif

            {{-- Payment input --}}
            @if($mpRem > 0 || empty($mesaPayments))
                <div style="display:flex;gap:6px;margin-bottom:12px">
                    @foreach(['cash'=>'Efectivo','card'=>'Tarjeta','transfer'=>'Transferencia'] as $m => $label)
                        <button wire:click="$set('mesaPayMethod','{{ $m }}')"
                                class="pos-btn {{ $mesaPayMethod===$m ? 'pos-btn-primary' : 'pos-btn-secondary' }}"
                                style="flex:1;justify-content:center;font-size:.78rem;padding:7px 4px">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                @if($mesaPayMethod === 'cash')
                    <div style="display:flex;gap:8px;margin-bottom:8px">
                        <div style="position:relative;flex:1">
                            <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--pos-muted);font-size:.82rem">$</span>
                            <input type="number" wire:model.live="mesaPayAmount" class="pos-input" style="padding-left:22px"
                                   placeholder="{{ number_format($mpRem, 2) }}" step="0.01" min="0">
                            <span style="position:absolute;right:8px;top:50%;transform:translateY(-50%);font-size:.68rem;color:var(--pos-muted);pointer-events:none">Monto</span>
                        </div>
                        <div style="position:relative;flex:1">
                            <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--pos-muted);font-size:.82rem">$</span>
                            <input type="number" wire:model.live="mesaPayReceived" class="pos-input" style="padding-left:22px"
                                   placeholder="Recibido" step="0.01" min="0">
                            <span style="position:absolute;right:8px;top:50%;transform:translateY(-50%);font-size:.68rem;color:var(--pos-muted);pointer-events:none">Recibido</span>
                        </div>
                    </div>
                    @php
                        $mpMonto    = (float)($mesaPayAmount ?: $mpRem);
                        $mpRecibido = (float)$mesaPayReceived;
                    @endphp
                    @if($mpRecibido > 0)
                        <div style="font-size:.78rem;{{ $mpRecibido >= $mpMonto ? 'color:#16a34a;font-weight:700' : 'color:var(--pos-danger)' }};margin-bottom:8px">
                            @if($mpRecibido >= $mpMonto)
                                <i class="bx bx-check-circle"></i> Cambio: <strong>${{ number_format($mpRecibido - $mpMonto, 2) }}</strong>
                            @else
                                <i class="bx bx-error-circle"></i> Faltan: <strong>${{ number_format($mpMonto - $mpRecibido, 2) }}</strong>
                            @endif
                        </div>
                    @endif
                @elseif($mesaPayMethod === 'card')
                    <div style="display:flex;gap:8px;margin-bottom:8px">
                        <div style="position:relative;flex:1">
                            <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--pos-muted);font-size:.82rem">$</span>
                            <input type="number" wire:model.live="mesaPayAmount" class="pos-input" style="padding-left:22px"
                                   placeholder="{{ number_format($mpRem, 2) }}" step="0.01" min="0">
                            <span style="position:absolute;right:8px;top:50%;transform:translateY(-50%);font-size:.68rem;color:var(--pos-muted);pointer-events:none">Monto</span>
                        </div>
                        <div style="position:relative;flex:1">
                            <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--pos-muted);font-size:.82rem">#</span>
                            <input type="text" wire:model="mesaPayCard" class="pos-input" style="padding-left:22px" placeholder="Últimos 4" maxlength="4">
                            <span style="position:absolute;right:8px;top:50%;transform:translateY(-50%);font-size:.68rem;color:var(--pos-muted);pointer-events:none">Tarjeta</span>
                        </div>
                    </div>
                @elseif($mesaPayMethod === 'transfer')
                    <div style="display:flex;gap:8px;margin-bottom:8px">
                        <div style="position:relative;flex:1">
                            <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--pos-muted);font-size:.82rem">$</span>
                            <input type="number" wire:model.live="mesaPayAmount" class="pos-input" style="padding-left:22px"
                                   placeholder="{{ number_format($mpRem, 2) }}" step="0.01" min="0">
                            <span style="position:absolute;right:8px;top:50%;transform:translateY(-50%);font-size:.68rem;color:var(--pos-muted);pointer-events:none">Monto</span>
                        </div>
                        <div style="position:relative;flex:1">
                            <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--pos-muted);font-size:.75rem">#</span>
                            <input type="text" wire:model="mesaPayRef" class="pos-input" style="padding-left:22px" placeholder="Referencia">
                            <span style="position:absolute;right:8px;top:50%;transform:translateY(-50%);font-size:.68rem;color:var(--pos-muted);pointer-events:none">Ref.</span>
                        </div>
                    </div>
                @endif

                <button wire:click="addMesaPayment" class="pos-btn pos-btn-secondary" style="width:100%;justify-content:center;margin-bottom:4px">
                    <i class="bx bx-plus"></i> Agregar pago
                </button>
            @endif
        </div>

        <div class="modal-footer-pos" style="justify-content:flex-end;gap:8px">
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
