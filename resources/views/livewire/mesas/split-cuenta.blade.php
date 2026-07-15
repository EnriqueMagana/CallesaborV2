<div>
    @once
        <link rel="stylesheet" href="{{ asset('assets/css/mesas.css') }}">
    @endonce

    {{-- Flash --}}
    @if(session('success'))
        <div class="mesas-toast" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)">
            <i class="bx bx-check-circle"></i> {{ session('success') }}
        </div>
    @endif

    {{-- ══════════════════ HEADER ══════════════════ --}}
    <div class="mesas-page-header">
        <div class="mesas-page-title">
            <a href="{{ route('app.mesas') }}" class="btn btn-icon btn-outline-secondary me-2" wire:navigate>
                <i class="bx bx-arrow-back"></i>
            </a>
            <i class="bx bx-split mesas-page-icon" style="color:#696cff"></i>
            <div>
                <h4>Dividir Cuenta · {{ $this->mesa->display_name }}</h4>
                <p>
                    <span class="mh-stat"><i class="bx bx-map-pin"></i> {{ $this->mesa->area->name ?? '–' }}</span>
                    <span class="mh-stat"><i class="bx bx-money"></i> Total: ${{ number_format($this->grandTotal, 2) }}</span>
                    @if($this->mesa->currentAssignment)
                        <span class="mh-stat"><i class="bx bx-user"></i> {{ $this->mesa->currentAssignment->waiter?->name }}</span>
                    @endif
                </p>
            </div>
        </div>
    </div>

    @if($confirmed)
        {{-- ════════════════ CONFIRMED: COBRAR MODE ════════════════ --}}
        <div class="split-confirmed-wrap">
            <div class="split-confirmed-header">
                <i class="bx bx-check-circle text-success" style="font-size:2rem"></i>
                <div>
                    <h5 class="mb-0">Split confirmado · Mesa {{ $this->mesa->number }}</h5>
                    <small class="text-muted">Marca cada cuenta como cobrada al recibir el pago.</small>
                </div>
                @php $anyPaid = collect($accounts)->contains(fn($a) => $a['paid']); @endphp
                @if(!$anyPaid)
                <button class="btn btn-outline-secondary btn-sm ms-auto" wire:click="cancelConfirm"
                        wire:confirm="¿Cancelar el split y volver a editar?">
                    <i class="bx bx-edit me-1"></i> Editar split
                </button>
                @endif
            </div>

            @foreach($accounts as $idx => $account)
            <div class="split-pay-card {{ $account['paid'] ? 'is-paid' : '' }}">
                <div class="split-pay-header">
                    <div class="split-pay-label">
                        <i class="bx {{ $account['paid'] ? 'bx-check-circle text-success' : 'bx-receipt text-primary' }}"></i>
                        {{ $account['label'] }}
                    </div>
                    <div class="split-pay-amount">
                        ${{ number_format($this->accountTotals[$idx] ?? 0, 2) }}
                    </div>
                </div>

                @if($mode === 'manual')
                <div class="split-pay-items">
                    @php $assignedItems = collect($account['item_ids'])->map(fn($id) => $this->allItems->firstWhere('id', $id))->filter() @endphp
                    @foreach($assignedItems as $item)
                        <div class="detail-item">
                            <span class="detail-item-qty">{{ $item->quantity }}×</span>
                            <span class="detail-item-name">{{ $item->name }}</span>
                            <span class="detail-item-price">${{ number_format($item->subtotal, 2) }}</span>
                        </div>
                    @endforeach
                </div>
                @else
                <div class="split-pay-items">
                    <small class="text-muted">
                        <i class="bx bx-info-circle me-1"></i>
                        Partes iguales: ${{ number_format($this->grandTotal / max(1, $equalParts), 2) }} c/u
                    </small>
                </div>
                @endif

                @if(!$account['paid'])
                    @can('cobrar mesas')
                    <button class="btn btn-success btn-sm mt-2 w-100" wire:click="markPaid({{ $idx }})">
                        <i class="bx bx-check me-1"></i> Marcar como cobrada
                    </button>
                    @endcan
                @else
                    <div class="text-center text-success small mt-2 fw-semibold">
                        <i class="bx bx-check-circle me-1"></i> Cobrada
                    </div>
                @endif
            </div>
            @endforeach
        </div>

    @else
        {{-- ════════════════ SPLIT EDITOR ════════════════ --}}
        <div class="split-layout">

            {{-- ── LEFT: Items ── --}}
            <div class="split-items-panel">
                <div class="split-panel-header">
                    <h6><i class="bx bx-list-ul me-2"></i>Productos de la mesa</h6>
                    <span class="badge bg-label-secondary">{{ $this->allItems->count() }} items</span>
                </div>

                @if($this->allItems->isEmpty())
                    <div class="mesas-empty mesas-empty--sm">
                        <i class="bx bx-receipt text-muted"></i>
                        <p>No hay productos activos en esta mesa.</p>
                    </div>
                @else
                    {{-- Mode selector --}}
                    <div class="split-mode-bar">
                        <button class="split-mode-btn {{ $mode === 'manual' ? 'active' : '' }}"
                                wire:click="setMode('manual')">
                            <i class="bx bx-list-check"></i> Manual
                        </button>
                        <button class="split-mode-btn {{ $mode === 'igual' ? 'active' : '' }}"
                                wire:click="setMode('igual')">
                            <i class="bx bx-equalizer"></i> Partes iguales
                        </button>
                    </div>

                    @if($mode === 'igual')
                        <div class="split-equal-controls">
                            <label class="form-label fw-semibold">Dividir entre:</label>
                            <div class="split-equal-stepper">
                                <button wire:click="setEqualParts({{ $equalParts - 1 }})" @if($equalParts <= 2) disabled @endif>
                                    <i class="bx bx-minus"></i>
                                </button>
                                <span class="split-equal-num">{{ $equalParts }}</span>
                                <button wire:click="setEqualParts({{ $equalParts + 1 }})">
                                    <i class="bx bx-plus"></i>
                                </button>
                            </div>
                            <small class="text-muted">${{ number_format($this->grandTotal / $equalParts, 2) }} por persona</small>
                        </div>
                    @endif

                    @if($mode === 'manual')
                        {{-- Unassigned items --}}
                        <div class="split-items-list">
                            @if($this->unassignedItems->isEmpty())
                                <div class="split-all-assigned">
                                    <i class="bx bx-check-circle text-success"></i>
                                    Todos los productos asignados
                                </div>
                            @else
                                <div class="split-unassigned-header">
                                    <span class="text-warning fw-semibold small">
                                        <i class="bx bx-error me-1"></i>
                                        {{ $this->unassignedItems->count() }} sin asignar
                                    </span>
                                </div>
                                @foreach($this->unassignedItems as $item)
                                <div class="split-item-row is-unassigned">
                                    <div class="split-item-info">
                                        <span class="split-item-qty">{{ $item->quantity }}×</span>
                                        <span class="split-item-name">{{ $item->name }}</span>
                                        <span class="split-item-price">${{ number_format($item->subtotal, 2) }}</span>
                                    </div>
                                    <div class="split-item-assign-btns">
                                        @foreach($accounts as $idx => $acc)
                                            <button class="split-assign-btn" wire:click="assignItem({{ $item->id }}, {{ $idx }})"
                                                    title="Asignar a {{ $acc['label'] }}">
                                                {{ $idx + 1 }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                                @endforeach
                            @endif

                            {{-- All items with assignment --}}
                            @foreach($accounts as $idx => $account)
                                @php $accItems = collect($account['item_ids'])->map(fn($id) => $this->allItems->firstWhere('id', $id))->filter() @endphp
                                @foreach($accItems as $item)
                                <div class="split-item-row is-assigned" style="--acc-idx: {{ $idx }}">
                                    <div class="split-item-badge">{{ $idx + 1 }}</div>
                                    <div class="split-item-info">
                                        <span class="split-item-qty">{{ $item->quantity }}×</span>
                                        <span class="split-item-name">{{ $item->name }}</span>
                                        <span class="split-item-price">${{ number_format($item->subtotal, 2) }}</span>
                                    </div>
                                    <button class="split-unassign-btn" wire:click="unassignItem({{ $item->id }})"
                                            title="Quitar asignación">
                                        <i class="bx bx-x"></i>
                                    </button>
                                </div>
                                @endforeach
                            @endforeach
                        </div>
                    @else
                        {{-- Equal mode: show all items read-only --}}
                        <div class="split-items-list">
                            @foreach($this->allItems as $item)
                            <div class="split-item-row">
                                <div class="split-item-info">
                                    <span class="split-item-qty">{{ $item->quantity }}×</span>
                                    <span class="split-item-name">{{ $item->name }}</span>
                                    <span class="split-item-price">${{ number_format($item->subtotal, 2) }}</span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>

            {{-- ── RIGHT: Accounts ── --}}
            <div class="split-accounts-panel">
                <div class="split-panel-header">
                    <h6><i class="bx bx-wallet me-2"></i>Cuentas</h6>
                    <button class="btn btn-outline-primary btn-sm" wire:click="addAccount">
                        <i class="bx bx-plus"></i> Agregar
                    </button>
                </div>

                @error('split') <div class="alert alert-danger small p-2 mb-3">{{ $message }}</div> @enderror

                <div class="split-accounts-list">
                    @foreach($accounts as $idx => $account)
                    <div class="split-account-card" style="--acc-color: {{ ['#696cff','#22c55e','#f59e0b','#ef4444','#8b5cf6'][$idx % 5] }}">
                        <div class="split-acc-header">
                            <div class="split-acc-badge">{{ $idx + 1 }}</div>
                            <input type="text" class="split-acc-label-input"
                                   value="{{ $account['label'] }}"
                                   wire:change="updateAccountLabel({{ $idx }}, $event.target.value)">
                            @if(count($accounts) > 2)
                                <button class="split-acc-remove" wire:click="removeAccount({{ $idx }})" title="Eliminar cuenta">
                                    <i class="bx bx-x"></i>
                                </button>
                            @endif
                        </div>

                        @if($mode === 'manual')
                            <div class="split-acc-items">
                                @php $accItems = collect($account['item_ids'])->map(fn($id) => $this->allItems->firstWhere('id', $id))->filter() @endphp
                                @if($accItems->isEmpty())
                                    <div class="split-acc-empty">Sin productos</div>
                                @else
                                    @foreach($accItems as $item)
                                        <div class="split-acc-item">
                                            <span>{{ $item->quantity }}× {{ $item->name }}</span>
                                            <span>${{ number_format($item->subtotal, 2) }}</span>
                                        </div>
                                    @endforeach
                                @endif
                                @if($this->unassignedItems->count())
                                    <button class="split-acc-assign-all" wire:click="assignAll({{ $idx }})">
                                        <i class="bx bx-down-arrow-circle me-1"></i> Asignar todos aquí
                                    </button>
                                @endif
                            </div>
                        @endif

                        <div class="split-acc-total">
                            <span>Subtotal:</span>
                            <strong>${{ number_format($this->accountTotals[$idx] ?? 0, 2) }}</strong>
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- Grand total recap --}}
                <div class="split-grand-recap">
                    <div class="split-grand-row">
                        <span>Total mesa:</span>
                        <strong>${{ number_format($this->grandTotal, 2) }}</strong>
                    </div>
                    <div class="split-grand-row">
                        <span>Total cuentas:</span>
                        <strong>${{ number_format(collect($this->accountTotals)->sum(), 2) }}</strong>
                    </div>
                    @php $diff = $this->grandTotal - collect($this->accountTotals)->sum(); @endphp
                    @if(abs($diff) > 0.01)
                        <div class="split-grand-row text-warning">
                            <span>Por asignar:</span>
                            <strong>${{ number_format(abs($diff), 2) }}</strong>
                        </div>
                    @else
                        <div class="split-grand-row text-success">
                            <i class="bx bx-check me-1"></i> Cuadrado
                        </div>
                    @endif
                </div>

                <button class="btn btn-primary w-100 mt-3" wire:click="confirm" wire:loading.attr="disabled">
                    <span wire:loading wire:target="confirm" class="spinner-border spinner-border-sm me-1"></span>
                    <i class="bx bx-check-circle me-1"></i> Confirmar y cobrar
                </button>
                <a href="{{ route('app.mesas') }}" class="btn btn-outline-secondary w-100 mt-2" wire:navigate>
                    Cancelar
                </a>
            </div>

        </div>
    @endif

</div>
