<div class="pos-overlay-panel" :class="panels.kitchen ? 'show' : ''">
    <div class="pos-overlay-backdrop" @click="panels.kitchen = false"></div>
    <div class="pos-panel">
        <div class="panel-header">
            <i class="bx bx-restaurant" style="font-size:1.1rem;color:#f59e0b"></i>
            <h5>Cocina — Órdenes pendientes</h5>
            <button class="btn-panel-close" @click="panels.kitchen = false"><i class="bx bx-x"></i></button>
        </div>

        <div style="padding:10px 14px 8px;border-bottom:1px solid var(--pos-border)">
            <div style="position:relative">
                <i class="bx bx-search" style="position:absolute;left:9px;top:50%;transform:translateY(-50%);color:var(--pos-muted);font-size:.95rem"></i>
                <input type="text" wire:model.live.debounce.300ms="kitchenSearch"
                       class="pos-input" style="padding-left:28px;font-size:.82rem"
                       placeholder="Buscar por mesa o # orden…">
            </div>
        </div>

        <div class="panel-body" style="padding:12px">
            @if($this->kitchenOrders->isEmpty())
                <div style="text-align:center;padding:48px 20px;color:var(--pos-muted)">
                    <i class="bx bx-check-circle" style="font-size:3rem;display:block;margin-bottom:10px;color:#22c55e;opacity:.6"></i>
                    <div style="font-weight:700;font-size:.9rem;color:var(--pos-text);margin-bottom:4px">Todo en orden</div>
                    <div style="font-size:.78rem">No hay órdenes pendientes de cocina.</div>
                </div>
            @else
                <div style="font-size:.7rem;font-weight:700;color:var(--pos-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;display:flex;align-items:center;gap:6px">
                    <span style="background:#f59e0b;color:#fff;border-radius:8px;padding:1px 7px;font-size:.68rem">
                        {{ $this->kitchenOrders->count() }}
                    </span>
                    orden(es) esperando cocina
                </div>

                @foreach($this->kitchenOrders as $order)
                <div style="background:var(--pos-card);border:1px solid var(--pos-border);border-radius:12px;margin-bottom:10px;overflow:hidden"
                     wire:key="kitchen-{{ $order->id }}">

                    {{-- Order header --}}
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:var(--pos-bg);border-bottom:1px solid var(--pos-border)">
                        <div style="display:flex;align-items:center;gap:8px">
                            <div style="width:32px;height:32px;background:rgba(245,158,11,.15);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                <i class="bx bx-table" style="color:#f59e0b;font-size:1rem"></i>
                            </div>
                            <div>
                                <div style="font-weight:700;font-size:.88rem;color:var(--pos-text)">
                                    Mesa {{ $order->mesa?->number ?? '—' }}
                                    @if($order->mesa?->name)
                                        <span style="font-weight:400;color:var(--pos-muted)">· {{ $order->mesa->name }}</span>
                                    @endif
                                </div>
                                <div style="font-size:.7rem;color:var(--pos-muted)">
                                    Orden #{{ $order->id }}
                                    @if($order->mesa?->area)
                                        · {{ $order->mesa->area->name }}
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div style="text-align:right">
                            <div style="font-size:.78rem;font-weight:700;color:var(--pos-text)">${{ number_format($order->total, 2) }}</div>
                            <div style="font-size:.68rem;color:var(--pos-muted)">{{ $order->created_at->diffForHumans() }}</div>
                        </div>
                    </div>

                    {{-- Items --}}
                    <div style="padding:10px 14px">
                        @foreach($order->items as $item)
                        <div style="display:flex;align-items:baseline;gap:6px;padding:3px 0;border-bottom:1px solid var(--pos-border);font-size:.82rem" wire:key="kitem-{{ $item->id }}">
                            <span style="font-weight:700;color:#f59e0b;min-width:22px">{{ $item->quantity }}×</span>
                            <div style="flex:1">
                                <span style="font-weight:600;color:var(--pos-text)">{{ $item->product_name }}</span>
                                @foreach($item->addons as $a)
                                    <span style="display:inline-block;font-size:.67rem;background:#eef2ff;color:#4f46e5;padding:1px 6px;border-radius:4px;margin:1px 2px">+ {{ $a->addon_name }}</span>
                                @endforeach
                                @foreach($item->ingredients as $ing)
                                    <span style="display:inline-block;font-size:.67rem;background:#f0fdf4;color:#166534;padding:1px 6px;border-radius:4px;margin:1px 2px">{{ $ing->ingredient_name }}@if($ing->quantity>1)×{{$ing->quantity}}@endif</span>
                                @endforeach
                                @if($item->notes)
                                    <div style="font-size:.7rem;color:var(--pos-muted);font-style:italic">"{{ $item->notes }}"</div>
                                @endif
                            </div>
                        </div>
                        @endforeach
                        @if($order->notes)
                            <div style="margin-top:6px;font-size:.75rem;color:#92400e;background:#fffbeb;border-radius:6px;padding:4px 8px">
                                <i class="bx bx-note me-1"></i>{{ $order->notes }}
                            </div>
                        @endif
                    </div>

                    {{-- Actions --}}
                    <div style="display:flex;gap:8px;padding:10px 14px;border-top:1px solid var(--pos-border)">
                        <button wire:click="markKitchenReady({{ $order->id }})"
                                wire:loading.attr="disabled" wire:target="markKitchenReady({{ $order->id }})"
                                class="pos-btn pos-btn-primary"
                                style="flex:1;justify-content:center;font-size:.8rem;padding:8px 10px">
                            <span wire:loading wire:target="markKitchenReady({{ $order->id }})" class="pos-btn-spinner"></span>
                            <i wire:loading.remove wire:target="markKitchenReady({{ $order->id }})" class="bx bx-printer me-1"></i>
                            <span wire:loading.remove wire:target="markKitchenReady({{ $order->id }})">Imprimir y enviar</span>
                            <span wire:loading wire:target="markKitchenReady({{ $order->id }})">Enviando…</span>
                        </button>
                        <button wire:click="reprintKitchenOrder({{ $order->id }})"
                                class="pos-btn pos-btn-ghost"
                                style="padding:8px 10px;font-size:.8rem"
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
