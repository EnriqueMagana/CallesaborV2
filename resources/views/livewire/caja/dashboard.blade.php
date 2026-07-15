<div class="container-xxl flex-grow-1 container-p-y">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold mb-0">Caja</h4>
            <small class="text-muted">Gestión de turno y apertura de caja</small>
        </div>
    </div>

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
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="badge bg-label-success p-2"><i class="bx bx-dollar-circle fs-5"></i></span>
                            <div>
                                <p class="mb-0 text-muted" style="font-size:.75rem">CAJA ACTIVA</p>
                                <h6 class="mb-0 fw-bold">{{ $reg->name }}</h6>
                            </div>
                            <span class="badge bg-success ms-auto">Abierta</span>
                        </div>
                        <div class="d-flex justify-content-between text-muted" style="font-size:.8rem">
                            <span>Apertura</span>
                            <span class="fw-semibold text-dark">{{ $reg->opened_at->format('d/m/Y g:i A') }}</span>
                        </div>
                        <div class="d-flex justify-content-between text-muted mt-1" style="font-size:.8rem">
                            <span>Fondo inicial</span>
                            <span class="fw-semibold text-dark">${{ number_format($reg->initial_amount, 2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between text-muted mt-1" style="font-size:.8rem">
                            <span>Abierta por</span>
                            <span class="fw-semibold text-dark">{{ $reg->opener->name }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <p class="text-muted mb-1" style="font-size:.75rem">PEDIDOS COBRADOS</p>
                        <h2 class="fw-bold mb-0" style="color:var(--bs-primary)">{{ $ordersCount }}</h2>
                        <p class="text-muted mt-1" style="font-size:.78rem">en este turno</p>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <p class="text-muted mb-1" style="font-size:.75rem">EFECTIVO ESTIMADO EN CAJA</p>
                        <h2 class="fw-bold mb-0 text-success">${{ number_format($reg->initial_amount + $cashIn, 2) }}
                        </h2>
                        <p class="text-muted mt-1" style="font-size:.78rem">fondo + ventas en efectivo</p>
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
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <span class="badge bg-label-warning p-3 mb-2" style="border-radius:50%">
                                <i class="bx bx-lock-open-alt" style="font-size:1.6rem"></i>
                            </span>
                            <h5 class="fw-bold mb-1">Apertura de caja</h5>
                            <p class="text-muted" style="font-size:.82rem">No hay ninguna caja activa. Registra el turno
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
