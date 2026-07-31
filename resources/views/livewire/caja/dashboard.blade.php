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
            $ordersCount = $reg->orders()->finalizedForAccounting()->count();
            $cashIn = $reg
                ->orders()
                ->finalizedForAccounting()
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

        <section class="app-card delivery-reconciliation" aria-labelledby="delivery-reconciliation-title">
            <header class="delivery-reconciliation__header">
                <div>
                    <span><i class="bx bx-cycling" aria-hidden="true"></i></span>
                    <div>
                        <div class="app-eyebrow">Arqueo por área · Delivery</div>
                        <h2 id="delivery-reconciliation-title">Mini cortes de repartidores</h2>
                        <p>Notas entregadas, efectivo bajo resguardo y pagos digitales del turno.</p>
                    </div>
                </div>
                @if($this->unassignedDeliveryCount > 0)
                    <span class="delivery-reconciliation__warning"><i class="bx bx-error-circle"></i>{{ $this->unassignedDeliveryCount }} sin asignar</span>
                @else
                    <span class="app-status app-status--success"><i class="bx bx-check-shield"></i>Sin pedidos huérfanos</span>
                @endif
            </header>

            @if($this->unassignedDeliveryCount > 0)
                <div class="delivery-reconciliation__notice" role="alert">
                    <i class="bx bx-lock-alt" aria-hidden="true"></i>
                    <div><strong>El corte general está bloqueado.</strong><p>Todo pedido a domicilio debe tener un repartidor asignado, incluso si ya fue pagado en sucursal.</p></div>
                    @can('ver delivery')<a href="{{ route('app.delivery') }}">Asignar pedidos <i class="bx bx-right-arrow-alt"></i></a>@endcan
                </div>
            @endif

            <div class="table-responsive">
                <table class="delivery-reconciliation__table">
                    <thead>
                        <tr><th>Repartidor</th><th class="text-end">En ruta</th><th class="text-end">Notas por arquear</th><th class="text-end">Efectivo esperado</th><th class="text-end">Transferencias</th><th class="text-end">Venta</th><th>Estado / acción</th></tr>
                    </thead>
                    <tbody>
                        @forelse($this->deliveryReconciliations as $driver)
                            <tr>
                                <td><span class="delivery-reconciliation__driver"><i class="bx bx-user"></i><strong>{{ $driver['name'] }}</strong></span></td>
                                <td class="text-end">{{ $driver['in_route'] }}</td>
                                <td class="text-end">{{ $driver['pending_notes'] }}</td>
                                <td class="text-end is-cash">${{ number_format($driver['cash_expected'], 2) }}</td>
                                <td class="text-end">${{ number_format($driver['transfer_total'], 2) }}</td>
                                <td class="text-end"><strong>${{ number_format($driver['sales_total'], 2) }}</strong></td>
                                <td>
                                    @if($driver['can_settle'])
                                        @can('cerrar caja')
                                            <button type="button" class="btn btn-sm btn-primary" wire:click="openDeliverySettlement({{ $driver['driver_id'] }})">
                                                <i class="bx bx-calculator"></i> Realizar arqueo
                                            </button>
                                        @else
                                            <span class="app-status app-status--warning"><i class="bx bx-time-five"></i>Pendiente de caja</span>
                                        @endcan
                                    @elseif($driver['in_route'] > 0)
                                        <span class="app-status app-status--warning"><i class="bx bx-cycling"></i>Entrega en curso</span>
                                    @elseif($driver['settlements']->isNotEmpty())
                                        <span class="app-status app-status--success"><i class="bx bx-check-double"></i>Arqueo completado</span>
                                    @else
                                        <span class="app-status app-status--neutral"><i class="bx bx-minus"></i>Sin notas</span>
                                    @endif
                                </td>
                            </tr>
                            @foreach($driver['settlements'] as $settlement)
                                <tr class="delivery-reconciliation__history">
                                    <td colspan="7">
                                        <span><i class="bx bx-check-circle"></i><strong>{{ $driver['name'] }} · arqueo completado</strong></span>
                                        <span>{{ $settlement->orders_count }} notas</span>
                                        <span>Efectivo ${{ number_format($settlement->declared_cash, 2) }}</span>
                                        <span class="{{ (float)$settlement->difference === 0.0 ? 'is-exact' : 'is-difference' }}">
                                            {{ (float)$settlement->difference === 0.0 ? 'Cuadra exacto' : 'Diferencia $'.number_format($settlement->difference, 2) }}
                                        </span>
                                        <time datetime="{{ $settlement->completed_at->toIso8601String() }}">{{ $settlement->completed_at->format('g:i A') }}</time>
                                    </td>
                                </tr>
                            @endforeach
                        @empty
                            <tr><td colspan="7"><div class="cash-cut-empty"><span><i class="bx bx-cycling"></i></span><div><strong>Sin actividad de delivery</strong><p>Cuando un repartidor tome un pedido, su resumen aparecerá aquí.</p></div></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="d-flex flex-wrap gap-3 mt-4">
            <a href="{{ route('app.pos') }}" class="btn btn-primary">
                <i class="bx bx-store me-1"></i> Ir al POS
            </a>
            <a href="{{ route('app.caja.corte') }}" class="btn btn-danger">
                <i class="bx bx-calculator me-1"></i> Realizar corte de caja
            </a>
        </div>

        @if($settlementDriverId)
            @php $settlementRow = $this->deliveryReconciliations->firstWhere('driver_id', $settlementDriverId); @endphp
            <div class="cash-cut-modal-backdrop" role="presentation" x-data x-on:keydown.escape.window="$wire.closeDeliverySettlement()">
                <section class="cash-cut-modal delivery-settlement-modal" role="dialog" aria-modal="true" aria-labelledby="delivery-settlement-title">
                    <header><span><i class="bx bx-cycling"></i></span><div><small>Mini corte · Delivery</small><h2 id="delivery-settlement-title">{{ $settlementRow['name'] ?? 'Repartidor' }}</h2></div></header>
                    <div class="cash-cut-modal__body">
                        <p>Confirma las notas y cuenta el efectivo que entrega el repartidor. Este arqueo quedará ligado al turno y a sus pedidos.</p>
                        <dl>
                            <div><dt>Notas entregadas</dt><dd>{{ $settlementRow['pending_notes'] ?? 0 }}</dd></div>
                            <div><dt>Venta entregada</dt><dd>${{ number_format($settlementRow['sales_total'] ?? 0, 2) }}</dd></div>
                            <div><dt>Transferencias / tarjeta</dt><dd>${{ number_format(($settlementRow['transfer_total'] ?? 0) + ($settlementRow['card_total'] ?? 0), 2) }}</dd></div>
                            <div><dt>Efectivo esperado</dt><dd>${{ number_format($settlementRow['cash_expected'] ?? 0, 2) }}</dd></div>
                        </dl>
                        <label class="cash-cut-field">
                            <span>Efectivo entregado <small>Obligatorio</small></span>
                            <div class="cash-cut-money-input"><b>$</b><input type="number" wire:model="settlementDeclaredCash" step="0.01" min="0" inputmode="decimal" aria-describedby="delivery-settlement-help"></div>
                        </label>
                        @error('settlementDeclaredCash')<p class="cash-cut-error" role="alert"><i class="bx bx-error-circle"></i>{{ $message }}</p>@enderror
                        @error('deliverySettlement')<p class="cash-cut-error" role="alert"><i class="bx bx-error-circle"></i>{{ $message }}</p>@enderror
                        <p id="delivery-settlement-help" class="delivery-settlement-help">Si hay diferencia, se registrará para auditoría; no se perderá el detalle de las notas.</p>
                        <label class="cash-cut-field"><span>Observaciones <small>Opcional</small></span><textarea wire:model="settlementNotes" rows="3" maxlength="500" placeholder="Incidencias, faltantes o aclaraciones…"></textarea></label>
                    </div>
                    <footer>
                        <button type="button" class="btn btn-outline-secondary" wire:click="closeDeliverySettlement">Cancelar</button>
                        <button type="button" class="btn btn-primary" wire:click="completeDeliverySettlement" wire:loading.attr="disabled" wire:target="completeDeliverySettlement">
                            <span wire:loading.remove wire:target="completeDeliverySettlement"><i class="bx bx-check-double"></i> Completar arqueo</span>
                            <span wire:loading wire:target="completeDeliverySettlement">Registrando…</span>
                        </button>
                    </footer>
                </section>
            </div>
        @endif
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
