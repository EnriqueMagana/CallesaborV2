<x-pos.area-panel panel="kitchen" title="Flujo de preparación" title-id="pos-kitchen-title"
    eyebrow="Cocina" description="Consulta las órdenes recibidas y avanza su preparación sin perder el contexto."
    icon="bx-restaurant" tone="kitchen" panel-class="pos-kitchen-panel" close-label="Cerrar Cocina">
        <x-slot:tools>
            <label class="pos-area-search">
                <i class="bx bx-search" data-ui="xui-r3yeoq"></i>
                <span class="visually-hidden">Buscar órdenes de cocina</span>
                <input type="text" wire:model.live.debounce.600ms="kitchenSearch"
                       class="pos-input" data-ui="xui-1le4og8"
                       placeholder="Buscar por mesa, cliente o # orden…">
            </label>
            <div class="pos-area-summary">
                <strong>{{ $this->kitchenOrders->count() }}</strong><span>órdenes activas</span>
            </div>
        </x-slot:tools>

            @if($this->kitchenOrders->isEmpty())
                <div class="pos-area-empty">
                    <span><i class="bx bx-check-circle"></i></span>
                    <h3>Todo en orden</h3>
                    <p>No hay órdenes pendientes de cocina.</p>
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
                                        @if($order->fulfillment === 'dine_in' && $order->mesa)
                                            · Mesa {{ $order->mesa->number }}
                                        @endif
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
                        @can($order->status === 'pendiente' ? 'iniciar preparacion en punto de venta' : 'marcar pedidos listos en punto de venta')
                        <button wire:click="markKitchenReady({{ $order->id }})"
                                wire:loading.attr="disabled" wire:target="markKitchenReady({{ $order->id }})"
                                class="pos-btn pos-btn-primary"
                                data-ui="xui-1g8aqme">
                            <span wire:loading wire:target="markKitchenReady({{ $order->id }})" class="pos-btn-spinner"></span>
                            <i wire:loading.remove wire:target="markKitchenReady({{ $order->id }})" class="bx {{ $order->status === 'pendiente' ? 'bx-restaurant' : 'bx-check-circle' }} me-1"></i>
                            <span wire:loading.remove wire:target="markKitchenReady({{ $order->id }})">{{ $order->status === 'pendiente' ? 'Iniciar preparación' : 'Marcar como listo' }}</span>
                            <span wire:loading wire:target="markKitchenReady({{ $order->id }})">Actualizando…</span>
                        </button>
                        @endcan
                        @can('reimprimir tickets')
                        <button wire:click="reprintKitchenOrder({{ $order->id }})"
                                class="pos-btn pos-btn-ghost"
                                data-ui="xui-1jziqnb"
                                title="Solo reimprimir">
                            <i class="bx bx-refresh"></i>
                        </button>
                        @endcan
                    </div>
                </div>
                @endforeach
            @endif
</x-pos.area-panel>
