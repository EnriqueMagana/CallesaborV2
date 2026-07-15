<div class="pos-overlay-panel" :class="panels.reprint ? 'show' : ''">
    <div class="pos-overlay-backdrop" @click="panels.reprint = false"></div>
    <div class="pos-panel">
        <div class="panel-header">
            <i class="bx bx-printer" style="font-size:1.1rem;color:var(--pos-accent)"></i>
            <h5>Reimprimir ticket</h5>
            <button class="btn-panel-close" @click="panels.reprint = false"><i class="bx bx-x"></i></button>
        </div>

        {{-- Tabs --}}
        <div style="display:flex;border-bottom:1px solid var(--pos-border)">
            @foreach(['ventanilla'=>'Ventanilla','mesas'=>'Mesas','delivery'=>'Delivery'] as $type => $label)
                <button wire:click="$set('reprintType','{{ $type }}')"
                        style="flex:1;padding:11px 6px;font-size:.74rem;font-weight:600;border:none;background:none;cursor:pointer;
                               border-bottom:2px solid {{ $reprintType===$type ? 'var(--pos-accent)' : 'transparent' }};
                               color:{{ $reprintType===$type ? 'var(--pos-accent)' : 'var(--pos-muted)' }};
                               transition:color .15s,border-color .15s">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="panel-body">
            {{-- Búsqueda --}}
            <div style="margin-bottom:12px">
                <div class="search-wrap">
                    <i class="bx bx-search si-icon"></i>
                    <input type="text" class="pos-input" style="width:100%;padding-left:32px"
                           wire:model.live.debounce.300ms="reprintSearch"
                           placeholder="# pedido o nombre…">
                </div>
            </div>

            {{-- Lista --}}
            @forelse($this->recentOrders as $ro)
                @php
                    // Pill de tipo
                    if ($ro->type === 'pick_up') {
                        $pillLabel = 'Para recoger';
                        $pillColor = 'rgba(245,158,11,.15)';
                        $pillText  = '#b45309';
                    } elseif ($ro->type === 'delivery') {
                        $pillMap   = ['contra_entrega'=>'Contra entrega','card'=>'Tarjeta online','transfer'=>'Transferencia'];
                        $pillLabel = $pillMap[$ro->delivery_method] ?? 'Delivery';
                        $pillColor = 'rgba(105,108,255,.1)';
                        $pillText  = 'var(--pos-accent)';
                    } else {
                        $pillLabel = 'Ventanilla';
                        $pillColor = 'rgba(34,197,94,.1)';
                        $pillText  = '#16a34a';
                    }
                @endphp
                <div class="recent-order-row">
                    <div style="width:34px;height:34px;border-radius:9px;background:rgba(105,108,255,.1);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <i class="bx bx-receipt" style="color:var(--pos-accent)"></i>
                    </div>
                    <div class="recent-order-info" style="flex:1;min-width:0">
                        <div style="display:flex;align-items:center;gap:5px;flex-wrap:wrap">
                            <span class="recent-order-num">#{{ $ro->id }}</span>
                            <span style="background:{{ $pillColor }};color:{{ $pillText }};font-size:.6rem;border-radius:20px;padding:1px 7px;font-weight:700;white-space:nowrap">
                                {{ $pillLabel }}
                            </span>
                        </div>
                        <div style="font-size:.8rem;color:var(--pos-text);margin-top:1px">{{ $ro->customer_name ?: 'Anónimo' }}</div>
                        <div class="recent-order-meta">
                            ${{ number_format($ro->total,2) }}
                            · {{ $ro->created_at->format('g:i A') }}
                        </div>
                    </div>
                    <div style="display:flex;gap:5px;flex-shrink:0">
                        <button wire:click="openReprintModal({{ $ro->id }})" @click="panels.reprint = false"
                                class="pos-btn pos-btn-secondary pos-btn-sm" title="Ticket cliente">
                            <i class="bx bx-user"></i>
                        </button>
                        <button wire:click="openReprintModal({{ $ro->id }})" @click="panels.reprint = false; $nextTick(() => posTicketTab('cocina'))"
                                class="pos-btn pos-btn-secondary pos-btn-sm" title="Ticket cocina">
                            <i class="bx bx-dish"></i>
                        </button>
                    </div>
                </div>
            @empty
                <div style="text-align:center;padding:40px 20px;color:var(--pos-muted)">
                    <i class="bx bx-printer" style="font-size:2.5rem;display:block;margin-bottom:8px;opacity:.3"></i>
                    <span style="font-size:.82rem">Sin pedidos hoy</span>
                </div>
            @endforelse
        </div>
    </div>
</div>
