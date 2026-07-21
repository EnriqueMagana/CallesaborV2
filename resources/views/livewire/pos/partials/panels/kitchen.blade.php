<div class="pos-overlay-panel" :class="panels.kitchen ? 'show' : ''">
    <div class="pos-overlay-backdrop" @click="panels.kitchen = false"></div>
    <div class="pos-panel">
        <div class="panel-header">
            <i class="bx bx-restaurant" data-ui="xui-17t7e5w"></i>
            <h5>Cocina — Flujo de preparación</h5>
            <button class="btn-panel-close" @click="panels.kitchen = false"><i class="bx bx-x"></i></button>
        </div>

        <div data-ui="xui-13pr088">
            <div data-ui="xui-1eviv88">
                <i class="bx bx-search" data-ui="xui-r3yeoq"></i>
                <input type="text" wire:model.live.debounce.300ms="kitchenSearch"
                       class="pos-input" data-ui="xui-1le4og8"
                       placeholder="Buscar por mesa, cliente o # orden…">
            </div>
        </div>

        <div class="panel-body" data-ui="xui-1y41yc7">
            @if($this->kitchenOrders->isEmpty())
                <div data-ui="xui-tiicut">
                    <i class="bx bx-check-circle" data-ui="xui-159i0fq"></i>
                    <div data-ui="xui-nw60f0">Todo en orden</div>
                    <div data-ui="xui-op3n57">No hay órdenes pendientes de cocina.</div>
                </div>
            @else
                <div data-ui="xui-z8pdt">
                    <span data-ui="xui-3x1lfu">
                        {{ $this->kitchenOrders->count() }}
                    </span>
                    orden(es) en preparación
                </div>

                @foreach($this->kitchenOrders as $order)
                <div data-ui="xui-18bj846"
                     wire:key="kitchen-{{ $order->id }}">

                    {{-- Order header --}}
                    <div data-ui="xui-kwk9cj">
                        <div data-ui="xui-1a303bl">
                            <div data-ui="xui-1amh5z8">
                                <i class="bx {{ $order->source === 'kiosk' ? 'bx-desktop' : 'bx-table' }}" data-ui="xui-1boam43"></i>
                            </div>
                            <div>
                                <div data-ui="xui-g0zfpy">
                                    @if($order->source === 'kiosk')
                                        Kiosco · {{ match($order->fulfillment) { 'dine_in' => 'Comer aquí', 'delivery' => 'Domicilio', default => 'Para recoger' } }}
                                    @else
                                        Mesa {{ $order->mesa?->number ?? '—' }}
                                    @endif
                                    @if($order->source !== 'kiosk' && $order->mesa?->name)
                                        <span data-ui="xui-rs67wr">· {{ $order->mesa->name }}</span>
                                    @endif
                                </div>
                                <div data-ui="xui-1j28yn7">
                                    Orden #{{ $order->display_folio }}
                                    @if($order->source === 'kiosk' && $order->customer_name)
                                        · {{ $order->customer_name }}
                                    @elseif($order->mesa?->area)
                                        · {{ $order->mesa->area->name }}
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div data-ui="xui-106slq2">
                            <div data-ui="xui-mpu5pv">${{ number_format($order->total, 2) }}</div>
                            <div data-ui="xui-1h23qsc">{{ $order->created_at->diffForHumans() }}</div>
                        </div>
                    </div>

                    <div class="pos-order-progress pos-order-progress--wide" aria-label="Estado del pedido">
                        <span class="is-complete {{ $order->status === 'pendiente' ? 'is-current' : '' }}"><i class="bx bx-check"></i>Recibido</span>
                        <span class="{{ $order->status === 'en_preparacion' ? 'is-complete is-current' : '' }}"><i class="bx bx-check"></i>Preparando</span>
                        <span><i class="bx bx-check"></i>Listo</span>
                    </div>

                    {{-- Items --}}
                    <div data-ui="xui-1y3nryy">
                        @foreach($order->items as $item)
                        <div data-ui="xui-1m4dd4e" wire:key="kitem-{{ $item->id }}">
                            <span data-ui="xui-pgiy6m">{{ $item->quantity }}×</span>
                            <div data-ui="xui-ckcaff">
                                <span data-ui="xui-3t2ogn">{{ $item->product_name }}</span>
                                @foreach($item->addons as $a)
                                    <span data-ui="xui-qnvq7p">+ {{ $a->addon_name }}</span>
                                @endforeach
                                @foreach($item->ingredients as $ing)
                                    <span data-ui="xui-2b35uk">{{ $ing->ingredient_name }}@if($ing->quantity>1)×{{$ing->quantity}}@endif</span>
                                @endforeach
                                @if($item->notes)
                                    <div data-ui="xui-1vht1fx">"{{ $item->notes }}"</div>
                                @endif
                            </div>
                        </div>
                        @endforeach
                        @if($order->notes)
                            <div data-ui="xui-171urtn">
                                <i class="bx bx-note me-1"></i>{{ $order->notes }}
                            </div>
                        @endif
                    </div>

                    {{-- Actions --}}
                    <div data-ui="xui-6bm9x1">
                        <button wire:click="markKitchenReady({{ $order->id }})"
                                wire:loading.attr="disabled" wire:target="markKitchenReady({{ $order->id }})"
                                class="pos-btn pos-btn-primary"
                                data-ui="xui-1g8aqme">
                            <span wire:loading wire:target="markKitchenReady({{ $order->id }})" class="pos-btn-spinner"></span>
                            <i wire:loading.remove wire:target="markKitchenReady({{ $order->id }})" class="bx {{ $order->status === 'pendiente' ? 'bx-bowl-hot' : 'bx-check-circle' }} me-1"></i>
                            <span wire:loading.remove wire:target="markKitchenReady({{ $order->id }})">{{ $order->status === 'pendiente' ? 'Iniciar preparación' : 'Marcar como listo' }}</span>
                            <span wire:loading wire:target="markKitchenReady({{ $order->id }})">Actualizando…</span>
                        </button>
                        <button wire:click="reprintKitchenOrder({{ $order->id }})"
                                class="pos-btn pos-btn-ghost"
                                data-ui="xui-1jziqnb"
                                title="Solo reimprimir">
                            <i class="bx bx-refresh"></i>
                        </button>
                    </div>
                </div>
                @endforeach
            @endif
        </div>
    </div>
</div>
