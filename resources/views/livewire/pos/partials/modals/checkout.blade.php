@if ($showCheckoutModal)
    <div class="pos-modal-wrap show" wire:click.self="$set('showCheckoutModal',false)" role="dialog" aria-modal="true" aria-labelledby="checkout-modal-title">
        <div class="pos-modal" @click.stop>
            <div class="modal-header-pos">
                <i class="bx bx-receipt" data-ui="xui-szn55b"></i>
                <h4 id="checkout-modal-title">Confirmar pedido</h4>
                <button type="button" wire:click="$set('showCheckoutModal',false)" class="pos-btn pos-btn-ghost pos-btn-sm" aria-label="Cerrar"><i
                        class="bx bx-x" data-ui="xui-miwya2"></i></button>
            </div>
            <div class="modal-body-pos">

                {{-- Resumen --}}
                <div class="co-section-title"><i class="bx bx-list-ul me-1"></i>Resumen</div>
                <div class="co-summary">
                    @foreach ($cart as $item)
                        <div class="co-sum-item">
                            @if(!empty($item['product_image']))
                                <img class="co-sum-image" src="{{ Storage::url($item['product_image']) }}"
                                     alt="" width="48" height="48" loading="lazy" decoding="async">
                            @else
                                <span class="co-sum-image is-empty" aria-hidden="true"><i class="bx bx-food-menu"></i></span>
                            @endif
                            <div>
                                <div class="co-sum-name">{{ $item['quantity'] }}× {{ $item['product_name'] }}</div>
                                @foreach ($item['addons'] as $a)
                                    <div class="co-sum-addon">+ {{ $a['addon_name'] }}@if ($a['extra_price'] > 0)
                                            (+${{ number_format($a['extra_price'], 2) }})
                                        @endif
                                    </div>
                                @endforeach
                                @if ($item['notes'])
                                    <div data-ui="xui-1u6iimp">{{ $item['notes'] }}</div>
                                @endif
                            </div>
                            <div class="co-sum-price">${{ number_format($item['subtotal'], 2) }}</div>
                        </div>
                    @endforeach
                    <div class="co-sum-total">
                        <span>Total</span>
                        <span>${{ number_format($this->cartTotal, 2) }}</span>
                    </div>
                </div>

                {{-- Tipo de venta --}}
                <div class="co-section-title"><i class="bx bx-category me-1"></i>Tipo de venta</div>
                <div class="type-pills">
                    <div wire:click="$set('orderType','ventanilla')"
                        class="type-pill takeout {{ $orderType === 'ventanilla' ? 'active' : '' }}">
                        <i class="bx bx-store"></i>
                        <div class="pill-label">Ventanilla</div>
                    </div>
                    <div wire:click="$set('orderType','pick_up')"
                        class="type-pill {{ $orderType === 'pick_up' ? 'active' : '' }}">
                        <i class="bx bx-store-alt"></i>
                        <div class="pill-label">Para recoger</div>
                    </div>
                    <div wire:click="$set('orderType','delivery')"
                        class="type-pill delivery {{ $orderType === 'delivery' ? 'active' : '' }}">
                        <i class="bx bx-cycling"></i>
                        <div class="pill-label">Delivery</div>
                    </div>
                </div>

                {{-- Cliente --}}
                <div class="co-section-title"><i class="bx bx-user me-1"></i>Cliente</div>
                @if ($customerId)
                    <div data-ui="xui-232dwh">
                        <div data-ui="xui-1l6bmjz">
                            <span data-ui="xui-1vhwqlq">{{ strtoupper(substr($customerName, 0, 1)) }}</span>
                        </div>
                        <div data-ui="xui-1vcuwtj">
                            <div data-ui="xui-d4ici8">{{ $customerName }}</div>
                            <div data-ui="xui-17weutf">{{ $customerPhone }}</div>
                        </div>
                        <button wire:click="clearCustomer" data-ui="xui-65xspq">
                            <i class="bx bx-x"></i> Cambiar
                        </button>
                    </div>
                @else
                    <div class="co-search-group">
                        <div class="co-search-wrap">
                            <i class="bx bx-search co-search-icon"></i>
                            <input type="search" wire:model.live.debounce.450ms="customerSearch"
                                class="co-input co-search-input" placeholder="Buscar cliente en CRM…"
                                autocomplete="off">
                        </div>
                        <button wire:click="openAddCustomerModal" class="pos-btn pos-btn-primary co-search-btn">
                            <i class="bx bx-user-plus"></i>
                            <span>Nuevo</span>
                        </button>
                    </div>

                    @if (strlen(trim($customerSearch)) >= 2 && $this->customerSearchResults->count() > 0)
                        <div class="co-search-results">
                            @foreach ($this->customerSearchResults as $cs)
                                <button wire:click="selectCustomer({{ $cs->id }})" class="co-search-result-row">
                                    <div class="co-avatar">{{ strtoupper(substr($cs->name, 0, 1)) }}</div>
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
                            <input type="text" wire:model="customerName" class="co-input"
                                placeholder="Nombre del cliente">
                        </div>
                        <div>
                            <label class="co-label">Teléfono</label>
                            <input type="tel" wire:model="customerPhone" class="co-input" placeholder="+52 999…">
                        </div>
                    </div>
                @endif

                @if ($orderType === 'delivery')
                    <div class="co-grid full">
                        <div>
                            <label class="co-label"><i class="bx bx-map me-1"></i>Dirección <span
                                    class="req">*</span></label>
                            <input type="text" wire:model="customerAddress" class="co-input"
                                placeholder="Calle, número, colonia">
                            @error('customerAddress')
                                <div data-ui="xui-1bwpvep">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="co-grid full" data-ui="xui-pnujsk">
                        <div>
                            <label class="co-label">Referencia</label>
                            <input type="text" wire:model="customerReferences" class="co-input"
                                placeholder="Casa azul, frente al parque…">
                        </div>
                    </div>
                    <div class="co-section-title"><i class="bx bx-cycling me-1"></i>Método de entrega</div>
                    <div data-ui="xui-1g1om3w">
                        <div wire:click="$set('deliveryMethod','contra_entrega')"
                            class="type-pill {{ $deliveryMethod === 'contra_entrega' ? 'active' : '' }}">
                            <i class="bx bx-money"></i>
                            <div class="pill-label">Contraentrega</div>
                        </div>
                        <div wire:click="$set('deliveryMethod','cash')"
                            class="type-pill {{ $deliveryMethod === 'cash' ? 'active' : '' }}">
                            <i class="bx bx-wallet"></i>
                            <div class="pill-label">Efectivo en local</div>
                        </div>
                        <div wire:click="$set('deliveryMethod','card')"
                            class="type-pill {{ $deliveryMethod === 'card' ? 'active' : '' }}">
                            <i class="bx bx-credit-card"></i>
                            <div class="pill-label">Tarjeta en local</div>
                        </div>
                        <div wire:click="$set('deliveryMethod','transfer')"
                            class="type-pill {{ $deliveryMethod === 'transfer' ? 'active' : '' }}">
                            <i class="bx bx-transfer"></i>
                            <div class="pill-label">Transferencia</div>
                        </div>
                    </div>
                @endif

                <div data-ui="xui-jm24uy">
                    <label class="co-label"><i class="bx bx-note me-1"></i>Nota general</label>
                    <input type="text" wire:model="orderNotes" class="co-input"
                        placeholder="Instrucción general para el pedido…">
                </div>

                {{-- ── PAGOS según tipo de pedido ── --}}
                @if ($orderType === 'pick_up')
                    {{-- Para recoger: sin cobro ahora --}}
                    <div data-ui="xui-hbznie">
                        <div>
                            <div data-ui="xui-h18r1e">Sin cobro por ahora</div>
                            <div data-ui="xui-1pv66bf">El pago se registra al momento de la entrega desde el panel
                                <strong>Para recoger</strong>.</div>
                        </div>
                    </div>
                @elseif($orderType === 'delivery' && $deliveryMethod === 'contra_entrega')
                    {{-- Delivery contra entrega: el repartidor cobra, no se registra en caja --}}
                    <div data-ui="xui-1t8h76s">
                        <div>
                            <div data-ui="xui-1k2x21j">Cobro contra entrega</div>
                            <div data-ui="xui-1pv66bf">El repartidor cobra
                                <strong>${{ number_format($this->cartTotal, 2) }}</strong> al cliente. No se registra
                                ingreso en caja.</div>
                        </div>
                    </div>
                @elseif($orderType === 'delivery' && in_array($deliveryMethod, ['cash', 'card', 'transfer']))
                    {{-- Delivery pagado en línea (tarjeta/transferencia): un solo pago, sin efectivo --}}
                    <div class="co-section-title" data-ui="xui-12bzlse">Pago anticipado</div>
                    <div data-ui="xui-1s5jbyx">
                        <div class="pay-methods" data-ui="xui-w2df8o">
                            <button wire:click="$set('payMethod','cash')"
                                class="pay-btn {{ $payMethod === 'cash' ? 'active' : '' }}">
                                <div class="pay-label">Efectivo</div>
                            </button>
                            <button wire:click="$set('payMethod','card')"
                                class="pay-btn {{ $payMethod === 'card' ? 'active' : '' }}">
                                <div class="pay-label">Tarjeta</div>
                            </button>
                            <button wire:click="$set('payMethod','transfer')"
                                class="pay-btn {{ $payMethod === 'transfer' ? 'active' : '' }}">
                                <div class="pay-label">Transferencia</div>
                            </button>
                        </div>
                        <div class="co-grid" data-ui="xui-3ocpgz">
                            <div>
                                <label class="co-label">Monto</label>
                                <input type="number" wire:model="payAmount" class="co-input"
                                    placeholder="{{ number_format($this->cartTotal, 2) }}" step="0.01"
                                    min="0">
                            </div>
                            @if ($payMethod === 'card')
                                <div>
                                    <label class="co-label">Últimos 4 dígitos</label>
                                    <input type="text" wire:model="payCardLast4" class="co-input"
                                        placeholder="0000" maxlength="4">
                                </div>
                            @elseif($payMethod === 'transfer')
                                <div>
                                    <label class="co-label">Referencia</label>
                                    <input type="text" wire:model="payTransferRef" class="co-input"
                                        placeholder="Núm. referencia">
                                </div>
                            @endif
                        </div>
                        @if (!empty($payments))
                            <div data-ui="xui-itl3ia">
                                <span data-ui="xui-168ppgz">Pago registrado</span>
                                <strong>${{ number_format($this->paidTotal, 2) }}</strong>
                            </div>
                        @endif
                        <button wire:click="addPayment" class="pos-btn pos-btn-secondary" data-ui="xui-9wgym">
                            Registrar pago
                        </button>
                    </div>
                @else
                    {{-- Ventanilla: flujo normal con efectivo, tarjeta y transferencia --}}
                    <div class="co-section-title" data-ui="xui-12bzlse">
                        Pagos
                        <span data-ui="xui-svg6s4">
                            Pagado: <strong>${{ number_format($this->paidTotal, 2) }}</strong>
                            &nbsp;·&nbsp;
                            Pendiente: <strong
                                class="{{ $this->paymentRemaining > 0 ? 'pos-text-danger' : 'pos-text-success' }}">${{ number_format($this->paymentRemaining, 2) }}</strong>
                        </span>
                    </div>

                    @if (!empty($payments))
                        <div data-ui="xui-1x3vyu3">
                            @foreach ($payments as $pi => $p)
                                <div data-ui="xui-cft161">
                                    <span>
                                        @if ($p['method'] === 'cash')
                                            Efectivo
                                        @elseif($p['method'] === 'card')
                                            Tarjeta @if (!empty($p['card_last4']))
                                                *{{ $p['card_last4'] }}
                                            @endif
                                        @elseif($p['method'] === 'transfer')
                                            Transferencia @if (!empty($p['transfer_ref']))
                                                ({{ $p['transfer_ref'] }})
                                            @endif
                                        @endif
                                        @if (isset($p['cash_change']) && $p['cash_change'] > 0)
                                            <span data-ui="xui-nolzwl"> · cambio
                                                ${{ number_format($p['cash_change'], 2) }}</span>
                                        @endif
                                    </span>
                                    <span data-ui="xui-1a303bl">
                                        <strong>${{ number_format($p['amount'], 2) }}</strong>
                                        <button wire:click="removePayment({{ $pi }})"
                                            data-ui="xui-u6hbsq"><i class="bx bx-x"></i></button>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div data-ui="xui-1shtu8b">
                        <div class="pay-methods">
                            <button wire:click="$set('payMethod','cash')"
                                class="pay-btn {{ $payMethod === 'cash' ? 'active' : '' }}">
                                <div class="pay-label">Efectivo</div>
                            </button>
                            <button wire:click="$set('payMethod','card')"
                                class="pay-btn {{ $payMethod === 'card' ? 'active' : '' }}">
                                <div class="pay-label">Tarjeta</div>
                            </button>
                            <button wire:click="$set('payMethod','transfer')"
                                class="pay-btn {{ $payMethod === 'transfer' ? 'active' : '' }}">
                                <div class="pay-label">Transferencia</div>
                            </button>
                        </div>
                        <div class="co-grid" data-ui="xui-bhmr11">
                            <div>
                                <label class="co-label">Monto</label>
                                <input type="number" wire:model="payAmount" class="co-input" placeholder="0.00"
                                    step="0.50" min="0">
                            </div>
                            @if ($payMethod === 'cash')
                                <div>
                                    <label class="co-label">Con cuánto paga</label>
                                    <input type="number" wire:model.live.debounce.350ms="payCashReceived" class="co-input"
                                        placeholder="0.00" step="0.50" min="0">
                                </div>
                            @elseif($payMethod === 'card')
                                <div>
                                    <label class="co-label">Últimos 4 dígitos</label>
                                    <input type="text" wire:model="payCardLast4" class="co-input"
                                        placeholder="0000" maxlength="4">
                                </div>
                            @elseif($payMethod === 'transfer')
                                <div>
                                    <label class="co-label">Referencia</label>
                                    <input type="text" wire:model="payTransferRef" class="co-input"
                                        placeholder="Núm. referencia">
                                </div>
                            @endif
                        </div>
                        @if ($payMethod === 'cash' && (float) $payCashReceived > 0 && (float) $payAmount > 0)
                            <div data-ui="xui-ylbqh5">
                                Cambio: ${{ number_format(max(0, (float) $payCashReceived - (float) $payAmount), 2) }}
                            </div>
                        @endif
                        <button wire:click="addPayment" class="pos-btn pos-btn-secondary" data-ui="xui-n8egc2">
                            Agregar pago
                        </button>
                    </div>
                @endif

            </div>
            <div class="modal-footer-pos">
                <button wire:click="$set('showCheckoutModal',false)" class="pos-btn pos-btn-ghost">Cancelar</button>
                <span data-ui="xui-ckcaff"></span>
                @if ($orderType === 'ventanilla')
                    <button wire:click="submitOrder" wire:loading.attr="disabled" wire:target="submitOrder"
                        class="pos-btn pos-btn-primary pos-btn-lg">
                        <span wire:loading wire:target="submitOrder" class="pos-btn-spinner"></span>
                        <i wire:loading.remove wire:target="submitOrder" class="bx bx-send"></i>
                        Cobrar y enviar
                    </button>
                @elseif($orderType === 'pick_up')
                    <button wire:click="submitPickupLater" wire:loading.attr="disabled"
                        wire:target="submitPickupLater" class="pos-btn pos-btn-pickup pos-btn-lg">
                        <span wire:loading wire:target="submitPickupLater" class="pos-btn-spinner"></span>
                        <i wire:loading.remove wire:target="submitPickupLater" class="bx bx-store-alt"></i>
                        Enviar a cocina
                    </button>
                @elseif($orderType === 'delivery' && $deliveryMethod === 'contra_entrega')
                    <button wire:click="submitOrder" wire:loading.attr="disabled" wire:target="submitOrder"
                        class="pos-btn pos-btn-primary pos-btn-lg">
                        <span wire:loading wire:target="submitOrder" class="pos-btn-spinner"></span>
                        <i wire:loading.remove wire:target="submitOrder" class="bx bx-cycling"></i>
                        Enviar a delivery
                    </button>
                @elseif($orderType === 'delivery')
                    <button wire:click="submitOrder" wire:loading.attr="disabled" wire:target="submitOrder"
                        class="pos-btn pos-btn-primary pos-btn-lg">
                        <span wire:loading wire:target="submitOrder" class="pos-btn-spinner"></span>
                        <i wire:loading.remove wire:target="submitOrder" class="bx bx-send"></i>
                        Confirmar pedido · ${{ number_format($this->cartTotal, 2) }}
                    </button>
                @else
                    <button wire:click="submitOrder" wire:loading.attr="disabled" wire:target="submitOrder"
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
