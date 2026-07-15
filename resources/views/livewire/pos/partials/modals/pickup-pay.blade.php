@if($showPickupPayModal)
@php
    $ppo = \App\Models\Order::with(['items.addons','items.product','customer','payments'])->find($pickupPayOrderId);
    $ppoItems = $ppo ? $ppo->items : collect();
    $ppoPaidAmt = collect($pickupPayments)->sum('amount');
    $ppoRem = $ppo ? max(0, $ppo->total - $ppoPaidAmt) : 0;
    $ppoCanConfirm = !empty($pickupPayments) && $ppoPaidAmt >= ($ppo->total ?? 0) - 0.01;
@endphp
<div class="pos-modal-wrap show" style="z-index:1450" wire:click.self="closePickupPayModal">
    <div class="pos-modal" style="max-width:480px">
        <div class="modal-header-pos">
            <i class="bx bx-dollar-circle" style="font-size:1.1rem;color:#16a34a"></i>
            <h5 style="margin:0 0 0 8px;font-size:1rem;font-weight:700">Cobrar y entregar</h5>
            <div style="margin-left:auto;display:flex;align-items:center;gap:6px">
                @if($ppo)
                    <span style="font-size:.75rem;color:var(--pos-muted)">Pedido #{{ $ppo->id }}</span>
                @endif
                <button class="pos-btn pos-btn-ghost" style="padding:4px 8px" wire:click="closePickupPayModal">
                    <i class="bx bx-x" style="font-size:1.1rem"></i>
                </button>
            </div>
        </div>

        <div class="modal-body-pos" style="padding:16px;max-height:65vh;overflow-y:auto">

            @if($ppo)
            <div style="background:var(--pos-bg);border-radius:10px;padding:12px;margin-bottom:16px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                    <span style="font-size:.78rem;font-weight:700;color:var(--pos-muted);text-transform:uppercase;letter-spacing:.04em">Resumen</span>
                    @if($ppo->customer_name)
                        <span style="font-size:.8rem;color:var(--pos-text)"><i class="bx bx-user" style="font-size:.8rem"></i> {{ $ppo->customer_name }}</span>
                    @endif
                </div>
                @foreach($ppoItems as $item)
                    <div style="display:flex;justify-content:space-between;align-items:baseline;padding:4px 0;border-bottom:1px solid var(--pos-border);font-size:.85rem">
                        <span>{{ $item->quantity }}x {{ $item->product->name ?? $item->product_name }}</span>
                        <span style="font-weight:600">${{ number_format($item->subtotal, 2) }}</span>
                    </div>
                @endforeach
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;font-size:1rem;font-weight:700;color:var(--pos-text)">
                    <span>Total</span>
                    <span>${{ number_format($ppo->total, 2) }}</span>
                </div>
            </div>
            @endif

            @if(!empty($pickupPayments))
                <div style="margin-bottom:12px">
                    @foreach($pickupPayments as $pi => $pp)
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:6px 10px;background:rgba(34,197,94,.08);border-radius:8px;margin-bottom:4px;font-size:.83rem">
                            <span><i class="bx bx-check-circle" style="color:#22c55e;margin-right:4px"></i>{{ ['cash'=>'Efectivo','card'=>'Tarjeta','transfer'=>'Transferencia'][$pp['method']] ?? ucfirst($pp['method']) }}</span>
                            <div style="display:flex;align-items:center;gap:8px">
                                <strong>${{ number_format($pp['amount'], 2) }}</strong>
                                <button wire:click="removePickupPayment({{ $pi }})" style="background:none;border:none;color:var(--pos-danger);cursor:pointer;padding:0"><i class="bx bx-x"></i></button>
                            </div>
                        </div>
                    @endforeach
                    <div style="font-size:.8rem;text-align:right;{{ $ppoRem > 0 ? 'color:var(--pos-danger)' : 'color:#22c55e;font-weight:700' }}">
                        @if($ppoRem > 0) Restante: ${{ number_format($ppoRem, 2) }}
                        @else <i class="bx bx-check-circle"></i> Pagado completo @endif
                    </div>
                </div>
            @endif

            @if($ppoRem > 0 || empty($pickupPayments))
                <div style="display:flex;gap:6px;margin-bottom:12px">
                    @foreach(['cash'=>'Efectivo','card'=>'Tarjeta','transfer'=>'Transferencia'] as $m => $label)
                        <button wire:click="$set('pickupPayMethod','{{ $m }}')"
                                class="pos-btn {{ $pickupPayMethod===$m ? 'pos-btn-primary' : 'pos-btn-secondary' }}"
                                style="flex:1;justify-content:center;font-size:.78rem;padding:7px 4px">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                @if($pickupPayMethod === 'cash')
                    <div style="display:flex;gap:8px;margin-bottom:8px">
                        <div style="position:relative;flex:1">
                            <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--pos-muted);font-size:.82rem">$</span>
                            <input type="number" wire:model.live="pickupPayAmount" class="pos-input" style="padding-left:22px"
                                   placeholder="{{ number_format($ppoRem, 2) }}"
                                   value="{{ $pickupPayAmount ?: number_format($ppoRem, 2) }}"
                                   step="0.01" min="0">
                            <span style="position:absolute;right:8px;top:50%;transform:translateY(-50%);font-size:.68rem;color:var(--pos-muted);pointer-events:none">Monto</span>
                        </div>
                        <div style="position:relative;flex:1">
                            <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--pos-muted);font-size:.82rem">$</span>
                            <input type="number" wire:model.live="pickupPayReceived" class="pos-input" style="padding-left:22px"
                                   placeholder="Recibido" step="0.01" min="0">
                            <span style="position:absolute;right:8px;top:50%;transform:translateY(-50%);font-size:.68rem;color:var(--pos-muted);pointer-events:none">Recibido</span>
                        </div>
                    </div>
                    @php
                        $ppoMonto    = (float)($pickupPayAmount ?: $ppoRem);
                        $ppoRecibido = (float)$pickupPayReceived;
                    @endphp
                    @if($ppoRecibido > 0)
                        <div style="font-size:.78rem;{{ $ppoRecibido >= $ppoMonto ? 'color:#16a34a;font-weight:700' : 'color:var(--pos-danger)' }};margin-bottom:8px">
                            @if($ppoRecibido >= $ppoMonto)
                                <i class="bx bx-check-circle"></i> Cambio: <strong>${{ number_format($ppoRecibido - $ppoMonto, 2) }}</strong>
                            @else
                                <i class="bx bx-error-circle"></i> Faltan: <strong>${{ number_format($ppoMonto - $ppoRecibido, 2) }}</strong>
                            @endif
                        </div>
                    @endif
                @elseif($pickupPayMethod === 'card')
                    <div style="display:flex;gap:8px;margin-bottom:8px">
                        <div style="position:relative;flex:1">
                            <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--pos-muted);font-size:.82rem">$</span>
                            <input type="number" wire:model.live="pickupPayAmount" class="pos-input" style="padding-left:22px"
                                   placeholder="{{ number_format($ppoRem, 2) }}" step="0.01" min="0">
                            <span style="position:absolute;right:8px;top:50%;transform:translateY(-50%);font-size:.68rem;color:var(--pos-muted);pointer-events:none">Monto</span>
                        </div>
                        <div style="position:relative;flex:1">
                            <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--pos-muted);font-size:.82rem">#</span>
                            <input type="text" wire:model="pickupPayCard" class="pos-input" style="padding-left:22px" placeholder="Últimos 4" maxlength="4">
                            <span style="position:absolute;right:8px;top:50%;transform:translateY(-50%);font-size:.68rem;color:var(--pos-muted);pointer-events:none">Tarjeta</span>
                        </div>
                    </div>
                @elseif($pickupPayMethod === 'transfer')
                    <div style="display:flex;gap:8px;margin-bottom:8px">
                        <div style="position:relative;flex:1">
                            <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--pos-muted);font-size:.82rem">$</span>
                            <input type="number" wire:model.live="pickupPayAmount" class="pos-input" style="padding-left:22px"
                                   placeholder="{{ number_format($ppoRem, 2) }}" step="0.01" min="0">
                            <span style="position:absolute;right:8px;top:50%;transform:translateY(-50%);font-size:.68rem;color:var(--pos-muted);pointer-events:none">Monto</span>
                        </div>
                        <div style="position:relative;flex:1">
                            <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--pos-muted);font-size:.75rem">#</span>
                            <input type="text" wire:model="pickupPayRef" class="pos-input" style="padding-left:22px" placeholder="Referencia">
                            <span style="position:absolute;right:8px;top:50%;transform:translateY(-50%);font-size:.68rem;color:var(--pos-muted);pointer-events:none">Ref.</span>
                        </div>
                    </div>
                @endif

                <button wire:click="addPickupPayment" class="pos-btn pos-btn-secondary" style="width:100%;justify-content:center;margin-bottom:4px">
                    <i class="bx bx-plus"></i> Agregar pago
                </button>
            @endif
        </div>

        <div class="modal-footer-pos" style="justify-content:flex-end;gap:8px">
            <button class="pos-btn pos-btn-ghost pos-btn-lg" wire:click="closePickupPayModal">Cancelar</button>
            <button wire:click="confirmPickupPayment"
                    wire:loading.attr="disabled" wire:target="confirmPickupPayment"
                    class="pos-btn pos-btn-primary pos-btn-lg"
                    {{ $ppoCanConfirm ? '' : 'disabled' }}>
                <span wire:loading wire:target="confirmPickupPayment" class="pos-btn-spinner"></span>
                <i wire:loading.remove wire:target="confirmPickupPayment" class="bx bx-check-circle"></i>
                Cobrar y entregar
            </button>
        </div>
    </div>
</div>
@endif
