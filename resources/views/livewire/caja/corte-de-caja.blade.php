<div class="container-xxl flex-grow-1 container-p-y">

    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold mb-0">Corte de caja</h4>
            @if(!$cutDone)
                <small class="text-muted">{{ $this->register->name }} · Abierta {{ $this->register->opened_at->format('d/m/Y g:i A') }}</small>
            @endif
        </div>
        <a href="{{ route('app.caja') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bx bx-arrow-back me-1"></i> Caja
        </a>
    </div>

    @if($cutDone)
        {{-- ✅ Corte generado --}}
        <div class="row justify-content-center">
            <div class="col-md-5 text-center">
                <div class="card shadow-sm border-0 p-5">
                    <span class="badge bg-label-success mx-auto mb-3 p-3" style="border-radius:50%;width:fit-content">
                        <i class="bx bx-check-circle" style="font-size:2rem"></i>
                    </span>
                    <h5 class="fw-bold">Caja cerrada correctamente</h5>
                    <p class="text-muted mb-4">El corte ha sido registrado.</p>
                    <div class="d-flex gap-3 justify-content-center">
                        <button onclick="window.open('{{ route('app.caja.corte.print', $cutId) }}','_blank')"
                                class="btn btn-outline-primary">
                            <i class="bx bx-printer me-1"></i> Imprimir corte
                        </button>
                        <a href="{{ route('app.caja') }}" class="btn btn-primary">
                            <i class="bx bx-door-open me-1"></i> Nueva apertura
                        </a>
                    </div>
                </div>
            </div>
        </div>

    @else
        @php
            $t    = $this->totals;
            $reg  = $this->register;
            $diff = $this->difference;
        @endphp

        <div class="row g-4">

            {{-- ── Columna izquierda: resumen de ventas ── --}}
            <div class="col-lg-7">

                {{-- Tabla de ventas --}}
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-transparent border-0 pb-0">
                        <h6 class="fw-bold mb-0">Ventas del turno</h6>
                        <small class="text-muted" style="font-size:.75rem">
                            Tarjeta y transferencia son informativos — no entran a la caja física
                        </small>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0" style="font-size:.83rem">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Área</th>
                                        <th class="text-end text-success">Efectivo ✓</th>
                                        <th class="text-end text-muted">Tarjeta</th>
                                        <th class="text-end text-muted">Transfer.</th>
                                        <th class="text-end pe-3">Total área</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="ps-3 fw-semibold">Ventanilla</td>
                                        <td class="text-end text-success fw-semibold">${{ number_format($t['v']['efectivo'],2) }}</td>
                                        <td class="text-end text-muted">${{ number_format($t['v']['tarjeta'],2) }}</td>
                                        <td class="text-end text-muted">${{ number_format($t['v']['transfer'],2) }}</td>
                                        <td class="text-end pe-3 fw-semibold">${{ number_format($t['v']['total'],2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="ps-3 fw-semibold">Mesas</td>
                                        <td class="text-end text-success fw-semibold">${{ number_format($t['m']['efectivo'],2) }}</td>
                                        <td class="text-end text-muted">${{ number_format($t['m']['tarjeta'],2) }}</td>
                                        <td class="text-end text-muted">${{ number_format($t['m']['transfer'],2) }}</td>
                                        <td class="text-end pe-3 fw-semibold">${{ number_format($t['m']['total'],2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="ps-3 fw-semibold">Delivery</td>
                                        <td class="text-end text-success fw-semibold">${{ number_format($t['d']['efectivo'],2) }}</td>
                                        <td class="text-end text-muted">${{ number_format($t['d']['tarjeta'],2) }}</td>
                                        <td class="text-end text-muted">${{ number_format($t['d']['transfer'],2) }}</td>
                                        <td class="text-end pe-3 fw-semibold">${{ number_format($t['d']['total'],2) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr class="fw-bold">
                                        <td class="ps-3">Total</td>
                                        <td class="text-end text-success">
                                            ${{ number_format($t['v']['efectivo']+$t['m']['efectivo']+$t['d']['efectivo'],2) }}
                                        </td>
                                        <td class="text-end text-muted" style="font-size:.78rem">
                                            ${{ number_format($t['v']['tarjeta']+$t['m']['tarjeta']+$t['d']['tarjeta'],2) }}
                                        </td>
                                        <td class="text-end text-muted" style="font-size:.78rem">
                                            ${{ number_format($t['v']['transfer']+$t['m']['transfer']+$t['d']['transfer'],2) }}
                                        </td>
                                        <td class="text-end pe-3">
                                            ${{ number_format($t['v']['total']+$t['m']['total']+$t['d']['total'],2) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Gastos --}}
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-transparent border-0 pb-0 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0">Gastos del turno</h6>
                        <small class="text-muted" style="font-size:.75rem">
                            Solo gastos en efectivo afectan el corte
                        </small>
                    </div>
                    <div class="card-body p-0">
                        @if($this->expenses->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-sm mb-0" style="font-size:.83rem">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3">Concepto</th>
                                            <th>Método</th>
                                            <th class="text-end pe-3">Monto</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($this->expenses as $exp)
                                            <tr>
                                                <td class="ps-3">{{ $exp->description ?: $exp->category_label }}</td>
                                                <td>
                                                    @if($exp->payment_method === 'cash')
                                                        <span class="badge bg-label-danger" style="font-size:.68rem">Efectivo</span>
                                                    @else
                                                        <span class="badge bg-label-secondary" style="font-size:.68rem">Digital</span>
                                                    @endif
                                                </td>
                                                <td class="text-end pe-3
                                                    {{ $exp->payment_method === 'cash' ? 'text-danger fw-semibold' : 'text-muted' }}">
                                                    -${{ number_format($exp->amount,2) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr class="fw-bold">
                                            <td class="ps-3" colspan="2">Gastos en efectivo</td>
                                            <td class="text-end pe-3 text-danger">-${{ number_format($this->totalExpensesCash,2) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-4 text-muted" style="font-size:.82rem">Sin gastos registrados en este turno</div>
                        @endif
                    </div>
                </div>

                {{-- Historial pedidos --}}
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-transparent border-0 pb-0">
                        <h6 class="fw-bold mb-0">Historial de pedidos ({{ $this->orders->count() }})</h6>
                    </div>
                    <div class="card-body p-0" style="max-height:300px;overflow-y:auto">
                        @forelse($this->orders as $order)
                            @php
                                $cashAmt = $order->payments->where('method','efectivo')->sum('amount');
                            @endphp
                            <div class="d-flex align-items-center gap-3 px-3 py-2 border-bottom" style="font-size:.82rem">
                                <span class="text-muted" style="min-width:36px">#{{ $order->id }}</span>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold">{{ $order->customer_name ?: 'Anónimo' }}</div>
                                    <div class="text-muted" style="font-size:.72rem">
                                        {{ $order->type_label }} · {{ $order->created_at->format('g:i A') }}
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold">${{ number_format($order->total,2) }}</div>
                                    @if($cashAmt > 0)
                                        <div class="text-success" style="font-size:.7rem">Efectivo ${{ number_format($cashAmt,2) }}</div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-4 text-muted" style="font-size:.82rem">Sin pedidos en este turno</div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- ── Columna derecha: declaración y cierre ── --}}
            <div class="col-lg-5">
                <div class="card shadow-sm border-0 sticky-top" style="top:80px">
                    <div class="card-header bg-transparent border-0">
                        <h6 class="fw-bold mb-0">Declaración de efectivo</h6>
                    </div>
                    <div class="card-body">

                        {{-- Resumen movimientos --}}
                        <div class="bg-light rounded p-3 mb-4" style="font-size:.84rem">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Fondo inicial</span>
                                <span class="fw-semibold">${{ number_format($reg->initial_amount,2) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">+ Ventas en efectivo</span>
                                <span class="fw-semibold text-success">+${{ number_format($this->totalCashIn,2) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">- Gastos en efectivo</span>
                                <span class="fw-semibold text-danger">-${{ number_format($this->totalExpensesCash,2) }}</span>
                            </div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between">
                                <span class="fw-bold">Efectivo esperado</span>
                                <span class="fw-bold fs-6">${{ number_format($this->expectedCash,2) }}</span>
                            </div>
                        </div>

                        {{-- Input declarado --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Efectivo contado en caja</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text">$</span>
                                <input type="number" wire:model.live="declaredCash" step="0.01" min="0"
                                       class="form-control @error('declaredCash') is-invalid @enderror"
                                       placeholder="0.00">
                                @error('declaredCash')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        {{-- Diferencia en vivo --}}
                        @if($declaredCash !== '')
                            @php $diffVal = $this->difference; @endphp
                            <div class="alert {{ $diffVal == 0 ? 'alert-success' : ($diffVal > 0 ? 'alert-info' : 'alert-danger') }} py-2 mb-3" style="font-size:.84rem">
                                @if($diffVal == 0)
                                    <i class="bx bx-check-circle me-1"></i> Cuadra exacto
                                @elseif($diffVal > 0)
                                    <i class="bx bx-trending-up me-1"></i> Sobrante: ${{ number_format(abs($diffVal),2) }}
                                @else
                                    <i class="bx bx-trending-down me-1"></i> Faltante: ${{ number_format(abs($diffVal),2) }}
                                @endif
                            </div>
                        @endif

                        {{-- Nota opcional --}}
                        <div class="mb-4">
                            <label class="form-label">Notas de cierre <span class="text-muted">(opcional)</span></label>
                            <textarea wire:model="closingNotes" class="form-control" rows="2"
                                      placeholder="Observaciones del turno…"></textarea>
                        </div>

                        <button wire:click="confirmCut"
                                wire:loading.attr="disabled"
                                class="btn btn-danger w-100">
                            <i class="bx bx-calculator me-1"></i>
                            Generar corte y cerrar caja
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal de confirmación --}}
        @if($showConfirm)
            <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,.5)">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header border-0">
                            <h5 class="modal-title fw-bold">Confirmar cierre de caja</h5>
                        </div>
                        <div class="modal-body" style="font-size:.88rem">
                            <p class="mb-3">Estás a punto de cerrar la caja <strong>{{ $this->register->name }}</strong>. Esta acción no se puede deshacer.</p>
                            <div class="bg-light rounded p-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Efectivo esperado</span>
                                    <span class="fw-semibold">${{ number_format($this->expectedCash,2) }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Efectivo declarado</span>
                                    <span class="fw-semibold">${{ number_format((float)$declaredCash,2) }}</span>
                                </div>
                                @php $diffConfirm = $this->difference; @endphp
                                <div class="d-flex justify-content-between fw-bold {{ $diffConfirm >= 0 ? 'text-success' : 'text-danger' }}">
                                    <span>Diferencia</span>
                                    <span>{{ $diffConfirm >= 0 ? '+' : '' }}${{ number_format($diffConfirm,2) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-0">
                            <button wire:click="$set('showConfirm',false)" class="btn btn-outline-secondary">
                                Cancelar
                            </button>
                            <button wire:click="generateCut"
                                    wire:loading.attr="disabled"
                                    class="btn btn-danger">
                                <span wire:loading wire:target="generateCut" class="spinner-border spinner-border-sm me-1"></span>
                                Confirmar y cerrar caja
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

    @endif
</div>
