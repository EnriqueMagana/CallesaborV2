<div class="pos-overlay-panel" :class="panels.mesas ? 'show' : ''">
    <div class="pos-overlay-backdrop" @click="panels.mesas = false"></div>
    <div class="pos-panel">
        <div class="panel-header">
            <i class="bx bx-table" style="font-size:1.1rem;color:var(--pos-accent)"></i>
            <h5>Cobrar mesa</h5>
            <button class="btn-panel-close" @click="panels.mesas = false"><i class="bx bx-x"></i></button>
        </div>
        <div class="panel-body" style="padding:16px">
            @if($this->mesasPendientes->isEmpty())
                <div style="text-align:center;color:var(--pos-muted);padding:48px 20px">
                    <i class="bx bx-table" style="font-size:3rem;display:block;margin-bottom:12px;opacity:.3"></i>
                    <div style="font-size:.9rem;font-weight:700;margin-bottom:6px;color:var(--pos-text)">Sin mesas pendientes</div>
                    <div style="font-size:.78rem;line-height:1.5">No hay mesas en espera de cobro.</div>
                </div>
            @else
                <div style="font-size:.72rem;font-weight:700;color:var(--pos-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">
                    {{ $this->mesasPendientes->count() }} mesa(s) pendiente(s) de cobro
                </div>

                @foreach($this->mesasPendientes as $mesa)
                    <div style="background:var(--pos-bg);border-radius:12px;padding:14px;margin-bottom:12px;border:1px solid var(--pos-border)">
                        {{-- Mesa header --}}
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                            <div style="display:flex;align-items:center;gap:8px">
                                <div style="width:36px;height:36px;border-radius:8px;background:rgba(239,68,68,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                    <i class="bx bx-table" style="color:#ef4444;font-size:1.1rem"></i>
                                </div>
                                <div>
                                    <div style="font-weight:700;font-size:.9rem;color:var(--pos-text)">{{ $mesa->display_name }}</div>
                                    <div style="font-size:.72rem;color:var(--pos-muted)">
                                        {{ $mesa->area->name ?? '–' }}
                                        @if($mesa->currentAssignment)
                                            · <i class="bx bx-user" style="font-size:.7rem"></i> {{ $mesa->currentAssignment->waiter?->name }}
                                            · <i class="bx bx-time" style="font-size:.7rem"></i> {{ $mesa->currentAssignment->assigned_at->format('H:i') }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div style="text-align:right">
                                <div style="font-size:1rem;font-weight:700;color:var(--pos-text)">${{ number_format($mesa->mesa_total, 2) }}</div>
                                @if($mesa->active_split)
                                    <div style="font-size:.68rem;color:#f59e0b;font-weight:600">
                                        <i class="bx bx-split"></i> Cuenta dividida
                                    </div>
                                @else
                                    <div style="font-size:.7rem;color:var(--pos-muted)">{{ $mesa->orders->count() }} orden(es)</div>
                                @endif
                            </div>
                        </div>

                        @if($mesa->active_split)
                            {{-- Split accounts --}}
                            @foreach($mesa->active_split->split_data as $idx => $account)
                                <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 10px;border-radius:8px;margin-bottom:6px;border:1px solid var(--pos-border);background:{{ $account['paid'] ? 'rgba(34,197,94,.06)' : 'var(--pos-card)' }}">
                                    <div>
                                        <div style="font-size:.82rem;font-weight:600;color:{{ $account['paid'] ? '#22c55e' : 'var(--pos-text)' }}">
                                            @if($account['paid'])
                                                <i class="bx bx-check-circle" style="color:#22c55e"></i>
                                            @else
                                                <i class="bx bx-receipt" style="color:var(--pos-accent)"></i>
                                            @endif
                                            {{ $account['label'] }}
                                        </div>
                                        <div style="font-size:.7rem;color:var(--pos-muted)">
                                            ${{ number_format($account['total'], 2) }}
                                            @if(!empty($account['items']))
                                                · {{ count($account['items']) }} producto(s)
                                            @endif
                                        </div>
                                    </div>
                                    @if(!$account['paid'])
                                        <button wire:click="openMesaSplitPayModal({{ $mesa->active_split->id }}, {{ $idx }})"
                                                class="pos-btn pos-btn-primary"
                                                style="font-size:.75rem;padding:5px 10px;flex-shrink:0">
                                            <i class="bx bx-dollar-circle me-1"></i> Cobrar
                                        </button>
                                    @else
                                        <span style="font-size:.72rem;color:#22c55e;font-weight:700">Pagada</span>
                                    @endif
                                </div>
                            @endforeach
                        @else
                            {{-- Full mesa --}}
                            <button wire:click="openMesaPayModal({{ $mesa->id }})"
                                    class="pos-btn pos-btn-primary"
                                    style="width:100%;justify-content:center;font-size:.82rem;padding:8px">
                                <i class="bx bx-dollar-circle me-1"></i> Cobrar mesa completa
                            </button>
                        @endif
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</div>
