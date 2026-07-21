<div class="app-page cash-page">

    @if(session('cash_register_required'))
        <div class="cash-access-notice" role="alert">
            <span><i class="bx bx-lock-alt" aria-hidden="true"></i></span>
            <div><strong>Este acceso requiere una caja abierta</strong><p>{{ session('cash_register_required') }} Abre el turno para continuar.</p></div>
        </div>
    @endif

    <header class="app-page-header">
        <div class="app-page-heading">
            <span class="app-page-icon" aria-hidden="true"><i class="bx bx-wallet"></i></span>
            <div>
                <div class="app-eyebrow">Operación · Efectivo</div>
                <h1 class="app-page-title">Caja</h1>
                <p class="app-page-subtitle">Controla la apertura del turno, el efectivo disponible y el cierre de caja.</p>
            </div>
        </div>
        @if($this->activeRegister)
            <span class="app-status app-status--success"><i class="bx bx-check-circle" aria-hidden="true"></i>Turno activo</span>
        @else
            <span class="app-status app-status--warning"><i class="bx bx-lock" aria-hidden="true"></i>Caja cerrada</span>
        @endif
    </header>

    @if ($this->activeRegister)
        {{-- Caja activa --}}
        @php
            $reg = $this->activeRegister;
            $ordersCount = $reg->orders()->where('status', 'pagada')->count();
            $cashIn = $reg
                ->orders()
                ->where('status', 'pagada')
                ->with('payments')
                ->get()
                ->flatMap(fn($o) => $o->payments->where('method', 'efectivo'))
                ->sum('amount');
        @endphp

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card app-card cash-summary-card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="badge bg-label-success p-2"><i class="bx bx-dollar-circle fs-5"></i></span>
                            <div>
                                <p class="mb-0 text-muted" data-ui="xui-1l0qd0m">CAJA ACTIVA</p>
                                <h6 class="mb-0 fw-bold">{{ $reg->name }}</h6>
                            </div>
                            <span class="badge bg-success ms-auto">Abierta</span>
                        </div>
                        <div class="d-flex justify-content-between text-muted" data-ui="xui-19cyg9q">
                            <span>Apertura</span>
                            <span class="fw-semibold text-dark">{{ $reg->opened_at->format('d/m/Y g:i A') }}</span>
                        </div>
                        <div class="d-flex justify-content-between text-muted mt-1" data-ui="xui-19cyg9q">
                            <span>Fondo inicial</span>
                            <span class="fw-semibold text-dark">${{ number_format($reg->initial_amount, 2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between text-muted mt-1" data-ui="xui-19cyg9q">
                            <span>Abierta por</span>
                            <span class="fw-semibold text-dark">{{ $reg->opener->name }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card app-card cash-summary-card h-100">
                    <div class="card-body text-center">
                        <p class="text-muted mb-1" data-ui="xui-1l0qd0m">PEDIDOS COBRADOS</p>
                        <h2 class="fw-bold mb-0" data-ui="xui-1x4f9ki">{{ $ordersCount }}</h2>
                        <p class="text-muted mt-1" data-ui="xui-op3n57">en este turno</p>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card app-card cash-summary-card h-100">
                    <div class="card-body text-center">
                        <p class="text-muted mb-1" data-ui="xui-1l0qd0m">EFECTIVO ESTIMADO EN CAJA</p>
                        <h2 class="fw-bold mb-0 text-success">${{ number_format($reg->initial_amount + $cashIn, 2) }}
                        </h2>
                        <p class="text-muted mt-1" data-ui="xui-op3n57">fondo + ventas en efectivo</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-3">
            <a href="{{ route('app.pos') }}" class="btn btn-primary">
                <i class="bx bx-store me-1"></i> Ir al POS
            </a>
            <a href="{{ route('app.caja.corte') }}" class="btn btn-danger">
                <i class="bx bx-calculator me-1"></i> Realizar corte de caja
            </a>
        </div>
    @else
        {{-- Sin caja activa — formulario de apertura --}}
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card app-card cash-open-card">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <span class="badge bg-label-warning p-3 mb-2" data-ui="xui-18kfxku">
                                <i class="bx bx-lock-open-alt" data-ui="xui-4xrukp"></i>
                            </span>
                            <h5 class="fw-bold mb-1">Apertura de caja</h5>
                            <p class="text-muted" data-ui="xui-1fzausk">No hay ninguna caja activa. Registra el turno
                                para comenzar.</p>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nombre / Turno</label>
                            <input type="text" wire:model="registerName"
                                class="form-control @error('registerName') is-invalid @enderror"
                                placeholder="Ej: Turno Mañana, Caja 1…">
                            @error('registerName')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Fondo inicial</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" wire:model="initialAmount" step="0.01" min="0"
                                    class="form-control @error('initialAmount') is-invalid @enderror"
                                    placeholder="0.00">
                                @error('initialAmount')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <button wire:click="openRegister" wire:loading.attr="disabled" class="btn btn-primary w-100">
                            <span wire:loading wire:target="openRegister"
                                class="spinner-border spinner-border-sm me-1"></span>
                            <i wire:loading.remove wire:target="openRegister" class="bx bx-door-open me-1"></i>
                            Abrir caja
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>
