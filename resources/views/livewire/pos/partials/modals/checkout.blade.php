@if($showCheckoutModal)
<div class="pos-modal-wrap show" wire:click.self="$set('showCheckoutModal',false)">
    <div class="pos-modal" @click.stop>
        <div class="modal-header-pos">
            <i class="bx bx-receipt" style="font-size:1.3rem;color:var(--pos-accent)"></i>
            <h4>Confirmar pedido</h4>
            <button wire:click="$set('showCheckoutModal',false)" class="pos-btn pos-btn-ghost pos-btn-sm"><i class="bx bx-x" style="font-size:1.1rem"></i></button>
        </div>
        <div class="modal-body-pos">

            {{-- Resumen --}}
            <div class="co-section-title"><i class="bx bx-list-ul me-1"></i>Resumen</div>
            <div class="co-summary">
                @foreach($cart as $item)
                    <div class="co-sum-item">
                        <div>
                            <div class="co-sum-name">{{ $item['quantity'] }}× {{ $item['product_name'] }}</div>
                            @foreach($item['addons'] as $a)
                                <div class="co-sum-addon">+ {{ $a['addon_name'] }}@if($a['extra_price']>0) (+${{ number_format($a['extra_price'],2) }})@endif</div>
                            @endforeach
                            @if($item['notes'])<div style="font-size:.65rem;color:var(--pos-muted);font-style:italic">{{ $item['notes'] }}</div>@endif
                        </div>
                        <div class="co-sum-price">${{ number_format($item['subtotal'],2) }}</div>
                    </div>
                @endforeach
                <div class="co-sum-total">
                    <span>Total</span>
                    <span>${{ number_format($this->cartTotal,2) }}</span>
                </div>
            </div>

            {{-- Tipo de venta --}}
            <div class="co-section-title"><i class="bx bx-category me-1"></i>Tipo de venta</div>
            <div class="type-pills">
                <div wire:click="$set('orderType','ventanilla')" class="type-pill takeout {{ $orderType==='ventanilla' ? 'active' : '' }}">
                    <i class="bx bx-store"></i>
                    <div class="pill-label">Ventanilla</div>
                </div>
                <div wire:click="$set('orderType','pick_up')" class="type-pill {{ $orderType==='pick_up' ? 'active' : '' }}">
                    <i class="bx bx-store-alt"></i>
                    <div class="pill-label">Para recoger</div>
                </div>
                <div wire:click="$set('orderType','delivery')" class="type-pill delivery {{ $orderType==='delivery' ? 'active' : '' }}">
                    <i class="bx bx-cycling"></i>
                    <div class="pill-label">Delivery</div>
                </div>
            </div>

            {{-- Cliente --}}
            <div class="co-section-title"><i class="bx bx-user me-1"></i>Cliente</div>
            @if($customerId)
                <div style="background:rgba(34,197,94,.07);border:1.5px solid rgba(34,197,94,.3);border-radius:8px;padding:10px 12px;display:flex;align-items:center;gap:10px;margin-bottom:10px">
                    <div style="width:34px;height:34px;border-radius:50%;background:var(--pos-accent);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <span style="font-size:.9rem;font-weight:700;color:#fff">{{ strtoupper(substr($customerName,0,1)) }}</span>
                    </div>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:.85rem;font-weight:600;color:var(--pos-text)">{{ $customerName }}</div>
                        <div style="font-size:.72rem;color:var(--pos-muted)">{{ $customerPhone }}</div>
                    </div>
                    <button wire:click="clearCustomer" style="background:none;border:1px solid var(--pos-border);border-radius:6px;color:var(--pos-muted);font-size:.7rem;padding:4px 8px;cursor:pointer">
                        <i class="bx bx-x"></i> Cambiar
                    </button>
                </div>
            @else
                <div class="co-search-group">
                    <div class="co-search-wrap">
                        <i class="bx bx-search co-search-icon"></i>
                        <input type="text" wire:model.live.debounce.300ms="customerSearch"
                               class="co-input co-search-input"
                               placeholder="Buscar cliente en CRM…" autocomplete="off">
                    </div>
                    <button wire:click="openAddCustomerModal" class="pos-btn pos-btn-primary co-search-btn">
                        <i class="bx bx-user-plus"></i>
                        <span>Nuevo</span>
                    </button>
                </div>

                @if(strlen(trim($customerSearch)) >= 2 && $this->customerSearchResults->count() > 0)
                    <div class="co-search-results">
                        @foreach($this->customerSearchResults as $cs)
                            <button wire:click="selectCustomer({{ $cs->id }})" class="co-search-result-row">
                                <div class="co-avatar">{{ strtoupper(substr($cs->name,0,1)) }}</div>
                                <div>
                                    <div class="co-result-name">{{ $cs->name }}</div>
                                    <div class="co-result-meta">{{ $cs->phone }}</div>
                                </div>
                            </button>
                        @endforeach
                    </div>
                @endif

                <div class="co-grid">
                    <div>
                        <label class="co-label">Nombre</label>
                        <input type="text" wire:model="customerName" class="co-input" placeholder="Nombre del cliente">
                    </div>
                    <div>
                        <label class="co-label">Teléfono</label>
                        <input type="tel" wire:model="customerPhone" class="co-input" placeholder="+52 999…">
                    </div>
                </div>
            @endif

            @if($orderType === 'delivery')
                <div class="co-grid full">
                    <div>
                        <label class="co-label"><i class="bx bx-map me-1"></i>Dirección <span class="req">*</span></label>
                        <input type="text" wire:model="customerAddress" class="co-input" placeholder="Calle, número, colonia">
                        @error('customerAddress')<div style="font-size:.72rem;color:var(--pos-danger);margin-top:3px">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="co-grid full" style="margin-top:-4px">
                    <div>
                        <label class="co-label">Referencia</label>
                        <input type="text" wire:model="customerReferences" class="co-input" placeholder="Casa azul, frente al parque…">
                    </div>
                </div>
                <div class="co-section-title"><i class="bx bx-cycling me-1"></i>Método de entrega</div>
                <div style="display:flex;gap:8px;margin-bottom:4px">
                    <div wire:click="$set('deliveryMethod','contra_entrega')" class="type-pill {{ $deliveryMethod==='contra_entrega' ? 'active' : '' }}">
                        <i class="bx bx-money"></i>
                        <div class="pill-label">Contraentrega</div>
                    </div>
                    <div wire:click="$set('deliveryMethod','card')" class="type-pill {{ $deliveryMethod==='card' ? 'active' : '' }}">
                        <i class="bx bx-credit-card"></i>
                        <div class="pill-label">Tarjeta online</div>
                    </div>
                    <div wire:click="$set('deliveryMethod','transfer')" class="type-pill {{ $deliveryMethod==='transfer' ? 'active' : '' }}">
                        <i class="bx bx-transfer"></i>
                        <div class="pill-label">Transferencia</div>
                    </div>
                </div>
            @endif

            <div style="margin-top:12px">
                <label class="co-label"><i class="bx bx-note me-1"></i>Nota general</label>
                <input type="text" wire:model="orderNotes" class="co-input" placeholder="Instrucción general para el pedido…">
            </div>

            {{-- ── PAGOS según tipo de pedido ── --}}
            @if($orderType === 'pick_up')
                {{-- Para recoger: sin cobro ahora --}}
                <div style="margin-top:16px;padding:14px 16px;background:rgba(245,158,11,.08);border:1.5px solid rgba(245,158,11,.3);border-radius:var(--pos-radius-sm);display:flex;align-items:center;gap:12px">
                    <div>
                        <div style="font-size:.82rem;font-weight:700;color:#b45309">Sin cobro por ahora</div>
                        <div style="font-size:.75rem;color:var(--pos-muted);margin-top:2px">El pago se registra al momento de la entrega desde el panel <strong>Para recoger</strong>.</div>
                    </div>
                </div>

            @elseif($orderType === 'delivery' && $deliveryMethod === 'contra_entrega')
                {{-- Delivery contra entrega: el repartidor cobra, no se registra en caja --}}
                <div style="margin-top:16px;padding:14px 16px;background:rgba(105,108,255,.06);border:1.5px solid rgba(105,108,255,.25);border-radius:var(--pos-radius-sm);display:flex;align-items:center;gap:12px">
                    <div>
                        <div style="font-size:.82rem;font-weight:700;color:var(--pos-accent)">Cobro contra entrega</div>
                        <div style="font-size:.75rem;color:var(--pos-muted);margin-top:2px">El repartidor cobra <strong>${{ number_format($this->cartTotal,2) }}</strong> al cliente. No se registra ingreso en caja.</div>
                    </div>
                </div>

            @elseif($orderType === 'delivery' && in_array($deliveryMethod, ['card','transfer']))
                {{-- Delivery pagado en línea (tarjeta/transferencia): un solo pago, sin efectivo --}}
                <div class="co-section-title" style="margin-top:16px">Pago anticipado</div>
                <div style="background:var(--pos-bg);border:1px solid var(--pos-border);border-radius:var(--pos-radius-sm);padding:12px;margin-bottom:4px">
                    <div class="pay-methods" style="margin-bottom:10px">
                        <button wire:click="$set('payMethod','card')" class="pay-btn {{ $payMethod==='card' ? 'active' : '' }}">
                            <div class="pay-label">Tarjeta online</div>
                        </button>
                        <button wire:click="$set('payMethod','transfer')" class="pay-btn {{ $payMethod==='transfer' ? 'active' : '' }}">
                            <div class="pay-label">Transferencia</div>
                        </button>
                    </div>
                    <div class="co-grid" style="margin-bottom:0">
                        <div>
                            <label class="co-label">Monto</label>
                            <input type="number" wire:model="payAmount" class="co-input"
                                   placeholder="{{ number_format($this->cartTotal, 2) }}" step="0.01" min="0">
                        </div>
                        @if($payMethod === 'card')
                        <div>
                            <label class="co-label">Últimos 4 dígitos</label>
                            <input type="text" wire:model="payCardLast4" class="co-input" placeholder="0000" maxlength="4">
                        </div>
                        @elseif($payMethod === 'transfer')
                        <div>
                            <label class="co-label">Referencia</label>
                            <input type="text" wire:model="payTransferRef" class="co-input" placeholder="Núm. referencia">
                        </div>
                        @endif
                    </div>
                    @if(!empty($payments))
                        <div style="margin-top:8px;padding:6px 8px;background:rgba(34,197,94,.08);border-radius:6px;font-size:.8rem;display:flex;justify-content:space-between">
                            <span style="color:#16a34a">Pago registrado</span>
                            <strong>${{ number_format($this->paidTotal, 2) }}</strong>
                        </div>
                    @endif
                    <button wire:click="addPayment" class="pos-btn pos-btn-secondary" style="width:100%;justify-content:center;margin-top:8px;font-size:.8rem">
                        Registrar pago
                    </button>
                </div>

            @else
                {{-- Ventanilla: flujo normal con efectivo, tarjeta y transferencia --}}
                <div class="co-section-title" style="margin-top:16px">
                    Pagos
                    <span style="margin-left:auto;font-size:.7rem;font-weight:400">
                        Pagado: <strong>${{ number_format($this->paidTotal,2) }}</strong>
                        &nbsp;·&nbsp;
                        Pendiente: <strong style="color:{{ $this->paymentRemaining > 0 ? 'var(--pos-danger)' : '#22c55e' }}">${{ number_format($this->paymentRemaining,2) }}</strong>
                    </span>
                </div>

                @if(!empty($payments))
                    <div style="margin-bottom:8px">
                        @foreach($payments as $pi => $p)
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:6px 10px;background:var(--pos-bg);border-radius:var(--pos-radius-sm);margin-bottom:4px;font-size:.8rem">
                                <span>
                                    @if($p['method']==='cash') Efectivo
                                    @elseif($p['method']==='card') Tarjeta @if(!empty($p['card_last4'])) *{{ $p['card_last4'] }} @endif
                                    @elseif($p['method']==='transfer') Transferencia @if(!empty($p['transfer_ref'])) ({{ $p['transfer_ref'] }}) @endif
                                    @endif
                                    @if(isset($p['cash_change']) && $p['cash_change']>0)
                                        <span style="color:var(--pos-muted);font-size:.7rem"> · cambio ${{ number_format($p['cash_change'],2) }}</span>
                                    @endif
                                </span>
                                <span style="display:flex;align-items:center;gap:8px">
                                    <strong>${{ number_format($p['amount'],2) }}</strong>
                                    <button wire:click="removePayment({{ $pi }})" style="background:none;border:none;color:var(--pos-danger);cursor:pointer;padding:0"><i class="bx bx-x"></i></button>
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div style="background:var(--pos-bg);border:1px solid var(--pos-border);border-radius:var(--pos-radius-sm);padding:10px;margin-bottom:4px">
                    <div class="pay-methods">
                        <button wire:click="$set('payMethod','cash')" class="pay-btn {{ $payMethod==='cash' ? 'active' : '' }}"><div class="pay-label">Efectivo</div></button>
                        <button wire:click="$set('payMethod','card')" class="pay-btn {{ $payMethod==='card' ? 'active' : '' }}"><div class="pay-label">Tarjeta</div></button>
                        <button wire:click="$set('payMethod','transfer')" class="pay-btn {{ $payMethod==='transfer' ? 'active' : '' }}"><div class="pay-label">Transferencia</div></button>
                    </div>
                    <div class="co-grid" style="margin-bottom:6px">
                        <div>
                            <label class="co-label">Monto</label>
                            <input type="number" wire:model="payAmount" class="co-input" placeholder="0.00" step="0.50" min="0">
                        </div>
                        @if($payMethod==='cash')
                        <div>
                            <label class="co-label">Con cuánto paga</label>
                            <input type="number" wire:model.live="payCashReceived" class="co-input" placeholder="0.00" step="0.50" min="0">
                        </div>
                        @elseif($payMethod==='card')
                        <div>
                            <label class="co-label">Últimos 4 dígitos</label>
                            <input type="text" wire:model="payCardLast4" class="co-input" placeholder="0000" maxlength="4">
                        </div>
                        @elseif($payMethod==='transfer')
                        <div>
                            <label class="co-label">Referencia</label>
                            <input type="text" wire:model="payTransferRef" class="co-input" placeholder="Núm. referencia">
                        </div>
                        @endif
                    </div>
                    @if($payMethod==='cash' && (float)$payCashReceived > 0 && (float)$payAmount > 0)
                        <div style="font-size:.75rem;color:#22c55e;padding:2px 0">
                            Cambio: ${{ number_format(max(0,(float)$payCashReceived-(float)$payAmount),2) }}
                        </div>
                    @endif
                    <button wire:click="addPayment" class="pos-btn pos-btn-secondary" style="width:100%;justify-content:center;margin-top:4px;font-size:.8rem">
                        Agregar pago
                    </button>
                </div>
            @endif

        </div>
        <div class="modal-footer-pos">
            <button wire:click="$set('showCheckoutModal',false)" class="pos-btn pos-btn-ghost">Cancelar</button>
            <span style="flex:1"></span>
            @if($orderType === 'pick_up')
                <button wire:click="submitPickupLater"
                        wire:loading.attr="disabled" wire:target="submitPickupLater"
                        class="pos-btn pos-btn-pickup pos-btn-lg">
                    <span wire:loading wire:target="submitPickupLater" class="pos-btn-spinner"></span>
                    <i wire:loading.remove wire:target="submitPickupLater" class="bx bx-store-alt"></i>
                    Enviar a cocina
                </button>
            @elseif($orderType === 'delivery' && $deliveryMethod === 'contra_entrega')
                <button wire:click="submitOrder"
                        wire:loading.attr="disabled" wire:target="submitOrder"
                        class="pos-btn pos-btn-primary pos-btn-lg">
                    <span wire:loading wire:target="submitOrder" class="pos-btn-spinner"></span>
                    <i wire:loading.remove wire:target="submitOrder" class="bx bx-cycling"></i>
                    Enviar a delivery
                </button>
            @elseif($orderType === 'delivery')
                <button wire:click="submitOrder"
                        wire:loading.attr="disabled" wire:target="submitOrder"
                        class="pos-btn pos-btn-primary pos-btn-lg">
                    <span wire:loading wire:target="submitOrder" class="pos-btn-spinner"></span>
                    <i wire:loading.remove wire:target="submitOrder" class="bx bx-send"></i>
                    Confirmar pedido · ${{ number_format($this->cartTotal,2) }}
                </button>
            @else
                <button wire:click="submitOrder"
                        wire:loading.attr="disabled" wire:target="submitOrder"
                        class="pos-btn pos-btn-primary pos-btn-lg">
                    <span wire:loading wire:target="submitOrder" class="pos-btn-spinner"></span>
                    <i wire:loading.remove wire:target="submitOrder" class="bx bx-send"></i>
                    Cobrar y enviar
                </button>
            @endif
        </div>
    </div>
</div>
@endif
