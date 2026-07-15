<div class="pos-overlay-panel" :class="panels.pickup ? 'show' : ''">
    <div class="pos-overlay-backdrop" @click="panels.pickup = false; $wire.clearPickupOrder()"></div>
    <div class="pos-panel" style="width:420px">
        <div class="panel-header">
            <i class="bx bx-store-alt" style="font-size:1.1rem;color:#f59e0b"></i>
            <h5>Para recoger</h5>
            <button class="btn-panel-close" @click="panels.pickup = false; $wire.clearPickupOrder()">
                <i class="bx bx-x"></i>
            </button>
        </div>

        @if(!$pickupOrderId)
            <div class="panel-body">
                <div style="margin-bottom:12px">
                    <div class="search-wrap">
                        <i class="bx bx-search si-icon"></i>
                        <input type="text" class="pos-input" style="width:100%;padding-left:32px"
                               wire:model.live.debounce.300ms="pickupSearch"
                               placeholder="# pedido, nombre o teléfono…">
                    </div>
                </div>
                <div style="font-size:.7rem;color:var(--pos-muted);margin-bottom:8px;text-transform:uppercase;letter-spacing:.05em">
                    Pedidos pendientes de cobro
                </div>
                @forelse($this->pickupOrders as $po)
                    <div class="recent-order-row">
                        <div style="width:36px;height:36px;border-radius:10px;background:rgba(245,158,11,.15);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="bx bx-store-alt" style="color:#f59e0b;font-size:1rem"></i>
                        </div>
                        <div class="recent-order-info">
                            <div style="display:flex;align-items:center;gap:6px">
                                <span class="recent-order-num">#{{ $po->id }}</span>
                                <span style="background:rgba(245,158,11,.15);color:#b45309;font-size:.62rem;border-radius:20px;padding:2px 7px;font-weight:700">Para recoger</span>
                            </div>
                            <div style="font-size:.8rem;color:var(--pos-text)">{{ $po->customer_name ?: 'Sin nombre' }}</div>
                            <div class="recent-order-meta">{{ $po->items->count() }} art. · {{ $po->created_at->format('H:i') }}</div>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px">
                            <div style="font-size:.9rem;font-weight:700;color:var(--pos-text)">${{ number_format($po->total, 2) }}</div>
                            <button wire:click.stop="openPickupPayModal({{ $po->id }})"
                                    title="Cobrar y entregar"
                                    style="background:rgba(34,197,94,.15);border:none;border-radius:8px;width:32px;height:32px;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#16a34a;flex-shrink:0">
                                <i class="bx bx-dollar-circle" style="font-size:1.1rem"></i>
                            </button>
                        </div>
                    </div>
                @empty
                    <div style="text-align:center;padding:40px 20px;color:var(--pos-muted)">
                        <i class="bx bx-check-circle" style="font-size:2.5rem;display:block;margin-bottom:8px;color:#22c55e"></i>
                        <span style="font-size:.82rem">Sin pedidos pendientes de cobro</span>
                    </div>
                @endforelse
            </div>

        @else
            @php
                $po = $this->pickupOrders->firstWhere('id', $pickupOrderId)
                    ?? \App\Models\Order::with(['items','payments'])->find($pickupOrderId);
                $pickupPaidAmt = collect($pickupPayments)->sum('amount');
                $pickupRem     = $po ? max(0, $po->total - $pickupPaidAmt) : 0;
                $canConfirm    = !empty($pickupPayments) && $pickupPaidAmt >= ($po->total ?? 0) - 0.01;
            @endphp
            @if($po)
            <div class="panel-body" style="display:flex;flex-direction:column;gap:14px">
                <div style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.25);border-radius:12px;padding:12px 14px">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                        <div style="display:flex;align-items:center;gap:8px">
                            <span style="font-weight:800;font-size:.9rem">#{{ $po->id }}</span>
                            <span style="background:rgba(245,158,11,.2);color:#b45309;font-size:.62rem;border-radius:20px;padding:2px 8px;font-weight:700">Para recoger</span>
                        </div>
                        <button wire:click="clearPickupOrder" style="background:none;border:none;color:var(--pos-muted);cursor:pointer;font-size:.78rem;display:flex;align-items:center;gap:4px">
                            <i class="bx bx-arrow-back" style="font-size:.85rem"></i> Volver
                        </button>
                    </div>
                    @if($po->customer_name)
                    <div style="font-size:.82rem;font-weight:600;color:var(--pos-text);margin-bottom:8px">
                        <i class="bx bx-user me-1" style="color:#f59e0b"></i>{{ $po->customer_name }}
                        @if($po->customer_phone) <span style="color:var(--pos-muted);font-weight:400"> · {{ $po->customer_phone }}</span> @endif
                    </div>
                    @endif
                    <div style="border-top:1px dashed rgba(245,158,11,.3);padding-top:8px">
                        @foreach($po->items as $item)
                            <div style="display:flex;justify-content:space-between;font-size:.78rem;color:var(--pos-muted);margin-bottom:2px">
                                <span>{{ $item->quantity }}× {{ $item->product_name }}</span>
                                <span>${{ number_format($item->subtotal, 2) }}</span>
                            </div>
                        @endforeach
                        <div style="display:flex;justify-content:space-between;font-weight:800;font-size:.95rem;margin-top:6px;border-top:1px solid rgba(245,158,11,.3);padding-top:6px">
                            <span>Total</span>
                            <span style="color:#f59e0b">${{ number_format($po->total, 2) }}</span>
                        </div>
                    </div>
                </div>

                @if(!empty($pickupPayments))
                <div>
                    <div style="font-size:.7rem;color:var(--pos-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Pagos</div>
                    @foreach($pickupPayments as $pi => $pp)
                        <div style="display:flex;align-items:center;justify-content:space-between;background:var(--pos-bg);border:1px solid var(--pos-border);border-radius:8px;padding:8px 12px;margin-bottom:6px;font-size:.82rem">
                            <div style="display:flex;align-items:center;gap:8px">
                                <i class="bx {{ $pp['method']==='cash'?'bx-money':($pp['method']==='card'?'bx-credit-card':'bx-transfer') }}" style="color:var(--pos-accent)"></i>
                                <span>{{ ['cash'=>'Efectivo','card'=>'Tarjeta','transfer'=>'Transferencia'][$pp['method']] ?? $pp['method'] }}</span>
                                @if(isset($pp['cash_change']) && $pp['cash_change'] > 0)
                                    <span style="color:var(--pos-muted);font-size:.72rem">cambio: ${{ number_format($pp['cash_change'],2) }}</span>
                                @endif
                            </div>
                            <div style="display:flex;align-items:center;gap:8px">
                                <span style="font-weight:700">${{ number_format($pp['amount'],2) }}</span>
                                <button wire:click="removePickupPayment({{ $pi }})" style="background:none;border:none;color:var(--pos-danger);cursor:pointer;padding:0"><i class="bx bx-x"></i></button>
                            </div>
                        </div>
                    @endforeach
                    <div style="font-size:.8rem;text-align:right;{{ $pickupRem > 0 ? 'color:var(--pos-danger)' : 'color:#22c55e;font-weight:700' }}">
                        @if($pickupRem > 0) Restante: ${{ number_format($pickupRem,2) }}
                        @else <i class="bx bx-check-circle"></i> Pago completo @endif
                    </div>
                </div>
                @endif

                @if($pickupRem > 0 || empty($pickupPayments))
                <div style="background:var(--pos-bg);border:1px solid var(--pos-border);border-radius:12px;padding:14px">
                    <div style="font-size:.7rem;color:var(--pos-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px">Forma de pago</div>
                    <div style="display:flex;gap:6px;margin-bottom:10px;flex-wrap:wrap">
                        @foreach(['cash'=>['Efectivo','bx-money'],'card'=>['Tarjeta','bx-credit-card'],'transfer'=>['Transferencia','bx-transfer']] as $m => [$ml,$mi])
                            <button wire:click="$set('pickupPayMethod','{{ $m }}')"
                                    class="pos-btn {{ $pickupPayMethod===$m ? 'pos-btn-primary' : 'pos-btn-secondary' }}"
                                    style="flex:1;min-width:80px;justify-content:center;font-size:.75rem;padding:7px 8px">
                                <i class="bx {{ $mi }}"></i> {{ $ml }}
                            </button>
                        @endforeach
                    </div>
                    <div style="margin-bottom:8px">
                        <div style="font-size:.72rem;color:var(--pos-muted);margin-bottom:4px">Monto</div>
                        <input type="number" wire:model.live="pickupPayAmount" class="pos-input" style="padding-left:10px" placeholder="{{ number_format($pickupRem,2) }}" step="0.01" min="0">
                    </div>
                    @if($pickupPayMethod === 'cash')
                    <div style="margin-bottom:8px">
                        <div style="font-size:.72rem;color:var(--pos-muted);margin-bottom:4px">Efectivo recibido</div>
                        <input type="number" wire:model.live="pickupPayReceived" class="pos-input" style="padding-left:10px" placeholder="0.00" step="0.01" min="0">
                        @if((float)$pickupPayReceived > 0 && (float)$pickupPayAmount > 0)
                            <div style="font-size:.78rem;color:var(--pos-muted);margin-top:4px">Cambio: <strong style="color:var(--pos-text)">${{ number_format(max(0,(float)$pickupPayReceived-(float)$pickupPayAmount),2) }}</strong></div>
                        @endif
                    </div>
                    @elseif($pickupPayMethod === 'card')
                    <div style="margin-bottom:8px">
                        <div style="font-size:.72rem;color:var(--pos-muted);margin-bottom:4px">Últimos 4 dígitos</div>
                        <input type="text" wire:model="pickupPayCard" class="pos-input" style="padding-left:10px" placeholder="0000" maxlength="4">
                    </div>
                    @elseif($pickupPayMethod === 'transfer')
                    <div style="margin-bottom:8px">
                        <div style="font-size:.72rem;color:var(--pos-muted);margin-bottom:4px">Referencia</div>
                        <input type="text" wire:model="pickupPayRef" class="pos-input" style="padding-left:10px" placeholder="Ref. transferencia">
                    </div>
                    @endif
                    <button wire:click="addPickupPayment" class="pos-btn pos-btn-secondary" style="width:100%;justify-content:center;margin-top:4px">
                        <i class="bx bx-plus"></i> Agregar pago
                    </button>
                </div>
                @endif

                <button wire:click="confirmPickupPayment" wire:loading.attr="disabled" wire:target="confirmPickupPayment"
                        @click="panels.pickup = false"
                        class="pos-btn pos-btn-primary"
                        style="width:100%;justify-content:center;padding:13px;font-size:.92rem;{{ $canConfirm ? '' : 'opacity:.5;cursor:not-allowed' }}"
                        {{ $canConfirm ? '' : 'disabled' }}>
                    <span wire:loading wire:target="confirmPickupPayment"><i class="bx bx-loader-alt bx-spin"></i></span>
                    <i wire:loading.remove wire:target="confirmPickupPayment" class="bx bx-check-circle"></i>
                    Confirmar cobro · ${{ number_format($po->total, 2) }}
                </button>
            </div>
            @endif
        @endif
    </div>
</div>
