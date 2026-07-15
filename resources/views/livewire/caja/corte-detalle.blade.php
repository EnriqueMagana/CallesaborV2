<div class="container-xxl flex-grow-1 container-p-y">

    @php
        $cut  = $this->cut;
        $reg  = $cut->cashRegister;
        $diff = (float) $cut->difference;

        $areaLabels = ['ventanilla' => 'Ventanilla', 'mesas' => 'Mesas', 'delivery' => 'Delivery'];
        $areas      = $this->ordersByArea;
    @endphp

    {{-- Header --}}
    <div class="d-flex align-items-start justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h4 class="fw-bold mb-0">{{ $cut->folio }}</h4>
                @if($diff > 0)
                    <span class="badge bg-label-info">Sobrante ${{ number_format(abs($diff),2) }}</span>
                @elseif($diff < 0)
                    <span class="badge bg-label-danger">Faltante ${{ number_format(abs($diff),2) }}</span>
                @else
                    <span class="badge bg-label-success">Cuadrado</span>
                @endif
            </div>
            <small class="text-muted">
                {{ $reg->name }} · Apertura {{ $reg->opened_at->format('d/m/Y g:i A') }}
                · Cierre {{ $cut->generated_at->format('d/m/Y g:i A') }}
                · Cerrado por <strong>{{ $cut->generator->name }}</strong>
            </small>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('app.caja.cortes') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bx bx-arrow-back me-1"></i> Historial
            </a>
            <a href="{{ route('app.caja.corte.print', $cut) }}" target="_blank" class="btn btn-sm btn-primary">
                <i class="bx bx-printer me-1"></i> Reimprimir ticket
            </a>
        </div>
    </div>

    <div class="row g-4">

        {{-- ── Columna izquierda ── --}}
        <div class="col-lg-8">

            {{-- Tabla resumen de ventas --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-0 pb-0">
                    <h6 class="fw-bold mb-0">Resumen de ventas</h6>
                    <small class="text-muted" style="font-size:.74rem">
                        Solo la columna Efectivo ingresó a la caja física
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
                                @foreach([
                                    ['label'=>'Ventanilla','ef'=>$cut->v_efectivo,'tar'=>$cut->v_tarjeta,'tr'=>$cut->v_transfer],
                                    ['label'=>'Mesas',     'ef'=>$cut->m_efectivo,'tar'=>$cut->m_tarjeta,'tr'=>$cut->m_transfer],
                                    ['label'=>'Delivery',  'ef'=>$cut->d_efectivo,'tar'=>$cut->d_tarjeta,'tr'=>$cut->d_transfer],
                                ] as $row)
                                    <tr>
                                        <td class="ps-3 fw-semibold">{{ $row['label'] }}</td>
                                        <td class="text-end text-success fw-semibold">${{ number_format($row['ef'],2) }}</td>
                                        <td class="text-end text-muted">${{ number_format($row['tar'],2) }}</td>
                                        <td class="text-end text-muted">${{ number_format($row['tr'],2) }}</td>
                                        <td class="text-end pe-3 fw-semibold">${{ number_format($row['ef']+$row['tar']+$row['tr'],2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td class="ps-3">Total</td>
                                    <td class="text-end text-success">${{ number_format($cut->total_cash_in,2) }}</td>
                                    <td class="text-end text-muted" style="font-size:.78rem">
                                        ${{ number_format($cut->v_tarjeta+$cut->m_tarjeta+$cut->d_tarjeta,2) }}
                                    </td>
                                    <td class="text-end text-muted" style="font-size:.78rem">
                                        ${{ number_format($cut->v_transfer+$cut->m_transfer+$cut->d_transfer,2) }}
                                    </td>
                                    <td class="text-end pe-3">
                                        ${{ number_format(
                                            $cut->v_efectivo+$cut->v_tarjeta+$cut->v_transfer+
                                            $cut->m_efectivo+$cut->m_tarjeta+$cut->m_transfer+
                                            $cut->d_efectivo+$cut->d_tarjeta+$cut->d_transfer,2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Pedidos por área --}}
            @foreach($areas as $areaKey => $areaOrders)
                @if($areaOrders->count() > 0)
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-transparent border-0 pb-0 d-flex align-items-center justify-content-between">
                        <h6 class="fw-bold mb-0">{{ $areaLabels[$areaKey] }}</h6>
                        <span class="badge bg-label-primary" style="font-size:.72rem">
                            {{ $areaOrders->count() }} pedido{{ $areaOrders->count() !== 1 ? 's' : '' }}
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0" style="font-size:.82rem">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">#</th>
                                        <th>Cliente</th>
                                        <th>Hora</th>
                                        <th>Método(s)</th>
                                        <th class="text-end pe-3">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($areaOrders as $order)
                                        @php
                                            $paysSummary = $order->payments->map(fn($p) =>
                                                match($p->method){
                                                    'efectivo'      => '<span class="badge bg-label-success" style="font-size:.65rem">Efec $'.number_format($p->amount,2).'</span>',
                                                    'tarjeta'       => '<span class="badge bg-label-info" style="font-size:.65rem">Tar $'.number_format($p->amount,2).'</span>',
                                                    'transferencia' => '<span class="badge bg-label-warning" style="font-size:.65rem">Trans $'.number_format($p->amount,2).'</span>',
                                                    'contra_entrega'=> '<span class="badge bg-label-secondary" style="font-size:.65rem">C.E. $'.number_format($p->amount,2).'</span>',
                                                    default         => '<span class="badge bg-label-secondary" style="font-size:.65rem">'.e($p->method).'</span>',
                                                }
                                            )->implode(' ');
                                        @endphp
                                        <tr>
                                            <td class="ps-3 text-muted">#{{ $order->id }}</td>
                                            <td>{{ $order->customer_name ?: 'Anónimo' }}</td>
                                            <td class="text-muted">{{ $order->created_at->format('g:i A') }}</td>
                                            <td>{!! $paysSummary !!}</td>
                                            <td class="text-end pe-3 fw-semibold">${{ number_format($order->total,2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="table-light fw-bold">
                                    <tr>
                                        <td class="ps-3" colspan="4">Subtotal {{ $areaLabels[$areaKey] }}</td>
                                        <td class="text-end pe-3">${{ number_format($areaOrders->sum('total'),2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                @endif
            @endforeach

            {{-- Gastos --}}
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent border-0 pb-0 d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold mb-0">Gastos del turno</h6>
                    <span class="fw-bold text-danger" style="font-size:.82rem">
                        -${{ number_format($cut->total_expenses_cash,2) }} en efectivo
                    </span>
                </div>
                <div class="card-body p-0">
                    @if($this->expenses->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm mb-0" style="font-size:.82rem">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Concepto</th>
                                        <th>Categoría</th>
                                        <th>Método</th>
                                        <th class="text-end pe-3">Monto</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($this->expenses as $exp)
                                        <tr>
                                            <td class="ps-3">{{ $exp->description ?: '—' }}</td>
                                            <td class="text-muted">{{ $exp->category_label }}</td>
                                            <td>
                                                @if($exp->payment_method === 'cash')
                                                    <span class="badge bg-label-danger" style="font-size:.65rem">Efectivo</span>
                                                @else
                                                    <span class="badge bg-label-secondary" style="font-size:.65rem">Digital</span>
                                                @endif
                                            </td>
                                            <td class="text-end pe-3 {{ $exp->payment_method === 'cash' ? 'text-danger fw-semibold' : 'text-muted' }}">
                                                -${{ number_format($exp->amount,2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4 text-muted" style="font-size:.82rem">Sin gastos en este turno</div>
                    @endif
                </div>
            </div>

        </div>

        {{-- ── Columna derecha: resumen del corte ── --}}
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 sticky-top" style="top:80px">
                <div class="card-header bg-transparent border-0">
                    <h6 class="fw-bold mb-0">Resumen del corte</h6>
                </div>
                <div class="card-body">

                    <div class="bg-light rounded p-3 mb-3" style="font-size:.84rem">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Fondo inicial</span>
                            <span class="fw-semibold">${{ number_format($cut->initial_amount,2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">+ Ventas en efectivo</span>
                            <span class="fw-semibold text-success">+${{ number_format($cut->total_cash_in,2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">- Gastos en efectivo</span>
                            <span class="fw-semibold text-danger">-${{ number_format($cut->total_expenses_cash,2) }}</span>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="fw-bold">Efectivo esperado</span>
                            <span class="fw-bold">${{ number_format($cut->expected_cash,2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Efectivo declarado</span>
                            <span class="fw-semibold">${{ number_format($cut->declared_cash,2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between fw-bold
                            {{ $diff == 0 ? 'text-success' : ($diff > 0 ? 'text-info' : 'text-danger') }}">
                            <span>Diferencia</span>
                            <span>{{ $diff >= 0 ? '+' : '' }}${{ number_format($diff,2) }}</span>
                        </div>
                    </div>

                    {{-- Totales digitales (informativo) --}}
                    <div class="border rounded p-3" style="font-size:.82rem">
                        <p class="text-muted mb-2" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.4px">Ventas digitales (informativo)</p>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Tarjeta</span>
                            <span class="fw-semibold">
                                ${{ number_format($cut->v_tarjeta+$cut->m_tarjeta+$cut->d_tarjeta,2) }}
                            </span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Transferencia</span>
                            <span class="fw-semibold">
                                ${{ number_format($cut->v_transfer+$cut->m_transfer+$cut->d_transfer,2) }}
                            </span>
                        </div>
                    </div>

                    @if($reg->closing_notes)
                        <div class="mt-3 p-3 bg-light rounded" style="font-size:.82rem">
                            <p class="text-muted mb-1" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.4px">Notas de cierre</p>
                            <p class="mb-0">{{ $reg->closing_notes }}</p>
                        </div>
                    @endif

                    <a href="{{ route('app.caja.corte.print', $cut) }}" target="_blank"
                       class="btn btn-primary w-100 mt-3">
                        <i class="bx bx-printer me-1"></i> Reimprimir ticket
                    </a>

                </div>
            </div>
        </div>

    </div>
</div>
