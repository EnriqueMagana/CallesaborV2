<div class="app-page cash-cut-page">
    <header class="app-page-header cash-cut-header">
        <div class="app-page-heading">
            <span class="app-page-icon" aria-hidden="true"><i class="bx bx-calculator"></i></span>
            <div>
                <div class="app-eyebrow">Caja · Cierre de turno</div>
                <h1 class="app-page-title">Corte de caja</h1>
                @if(!$cutDone)
                    <p class="app-page-subtitle">{{ $this->register->name }} · Abierta {{ $this->register->opened_at->format('d/m/Y g:i A') }}</p>
                @else
                    <p class="app-page-subtitle">El turno fue conciliado y cerrado correctamente.</p>
                @endif
            </div>
        </div>
        <div class="app-page-actions">
            @if(!$cutDone)
                <span class="app-status app-status--success"><i class="bx bx-radio-circle-marked"></i>Turno activo</span>
            @endif
            <a href="{{ route('app.caja') }}" class="btn btn-outline-secondary"><i class="bx bx-arrow-back"></i> Volver a caja</a>
        </div>
    </header>

    @if($cutDone)
        <section class="cash-cut-success" aria-live="polite">
            <span class="cash-cut-success__icon"><i class="bx bx-check"></i></span>
            <span class="app-status app-status--success">Cierre completado</span>
            <h2>Caja cerrada correctamente</h2>
            <p>El corte quedó registrado y está listo para consulta o impresión.</p>
            <div class="cash-cut-success__actions">
                <a href="{{ route('app.caja.corte.print', $cutId) }}" target="_blank" rel="noopener" class="btn btn-outline-primary"><i class="bx bx-printer"></i> Imprimir corte</a>
                <a href="{{ route('app.caja') }}" class="btn btn-primary"><i class="bx bx-door-open"></i> Nueva apertura</a>
            </div>
        </section>
    @else
        @php
            $t = $this->totals;
            $reg = $this->register;
            $diff = $this->difference;
            $salesTotal = $t['v']['total'] + $t['m']['total'] + $t['d']['total'];
            $cardTotal = $t['v']['tarjeta'] + $t['m']['tarjeta'] + $t['d']['tarjeta'];
            $transferTotal = $t['v']['transfer'] + $t['m']['transfer'] + $t['d']['transfer'];
            $blockers = $this->closingBlockers;
        @endphp

        @if($blockers['has_blockers'])
            <section class="cash-cut-blockers" aria-labelledby="cash-cut-blockers-title">
                <header class="cash-cut-blockers__header">
                    <span class="cash-cut-blockers__icon" aria-hidden="true"><i class="bx bx-error-circle"></i></span>
                    <div>
                        <span class="cash-cut-blockers__eyebrow">Cierre bloqueado</span>
                        <h2 id="cash-cut-blockers-title">Hay operaciones que todavía deben resolverse</h2>
                        <p>Finaliza pedidos, asigna todos los delivery y libera las mesas.</p>
                    </div>
                    <button type="button" class="cash-cut-refresh" wire:click="$refresh" wire:loading.attr="disabled">
                        <span wire:loading.remove><i class="bx bx-refresh" aria-hidden="true"></i>Actualizar revisión</span>
                        <span wire:loading><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Revisando…</span>
                    </button>
                </header>

                <div class="cash-cut-blocker-summary" aria-label="Resumen de pendientes">
                    <article>
                        <span class="is-table" aria-hidden="true"><i class="bx bx-chair"></i></span>
                        <div><small>Mesas activas</small><strong>{{ $blockers['summary']['tables'] }}</strong></div>
                    </article>
                    <article>
                        <span class="is-order" aria-hidden="true"><i class="bx bx-food-menu"></i></span>
                        <div><small>Pedidos de mesa</small><strong>{{ $blockers['summary']['table_orders'] }}</strong></div>
                    </article>
                    <article>
                        <span class="is-kiosk" aria-hidden="true"><i class="bx bx-desktop"></i></span>
                        <div><small>Pedidos de kiosco</small><strong>{{ $blockers['summary']['kiosk'] }}</strong></div>
                    </article>
                    <article>
                        <span class="is-other" aria-hidden="true"><i class="bx bx-receipt"></i></span>
                        <div><small>Delivery sin asignar</small><strong>{{ $blockers['summary']['unassigned_delivery'] }}</strong></div>
                    </article>
                </div>

                @error('cutBlockers')
                    <div class="cash-cut-blockers__error" role="alert"><i class="bx bx-shield-x" aria-hidden="true"></i>{{ $message }}</div>
                @enderror

                <div class="cash-cut-blocker-columns">
                    <section aria-labelledby="cash-cut-pending-orders-title">
                        <div class="cash-cut-blocker-section-title">
                            <div>
                                <h3 id="cash-cut-pending-orders-title">Pedidos por resolver</h3>
                                <p>{{ $blockers['orders']->count() }} pedidos · ${{ number_format($blockers['unpaid_total'], 2) }} pendientes de conciliación</p>
                            </div>
                            <span>{{ $blockers['orders']->count() }}</span>
                        </div>

                        <div class="cash-cut-pending-list">
                            @forelse($blockers['orders'] as $pendingOrder)
                                @php
                                    $pendingChannel = $pendingOrder->source === 'kiosk'
                                        ? ['Kiosco', 'bx-desktop', 'kiosk']
                                        : match($pendingOrder->type) {
                                            'mesa' => ['Mesa', 'bx-chair', 'table'],
                                            'delivery' => ['Domicilio', 'bx-cycling', 'delivery'],
                                            default => ['Ventanilla', 'bx-store', 'counter'],
                                        };
                                @endphp
                                <article class="cash-cut-pending-item">
                                    <span class="cash-cut-pending-item__channel is-{{ $pendingChannel[2] }}" aria-hidden="true"><i class="bx {{ $pendingChannel[1] }}"></i></span>
                                    <div class="cash-cut-pending-item__copy">
                                        <div>
                                            <strong>Orden {{ $pendingOrder->display_folio }}</strong>
                                            <span class="cash-cut-pending-status">{{ $pendingOrder->status_label }}</span>
                                        </div>
                                        <p>
                                            {{ $pendingChannel[0] }}
                                            @if($pendingOrder->mesa) · {{ $pendingOrder->mesa->display_name }}@endif
                                            @if($pendingOrder->kioskTerminal) · {{ $pendingOrder->kioskTerminal->name }}@endif
                                        </p>
                                        <small>{{ $pendingOrder->display_name }} · {{ $pendingOrder->created_at->format('g:i A') }}</small>
                                    </div>
                                    <div class="cash-cut-pending-item__amount">
                                        <strong>${{ number_format($pendingOrder->total, 2) }}</strong>
                                        @if($pendingOrder->type === 'mesa' && $pendingOrder->mesa_id)
                                            @can('ver mesas')
                                                <a href="{{ route('app.mesas.ordenes', $pendingOrder->mesa_id) }}">Revisar mesa <i class="bx bx-chevron-right" aria-hidden="true"></i></a>
                                            @endcan
                                        @elseif(auth()->user()?->can('cerrar ordenes'))
                                            <a href="{{ route('app.pos') }}">Resolver en POS <i class="bx bx-chevron-right" aria-hidden="true"></i></a>
                                        @elseif(auth()->user()?->can('ver ordenes'))
                                            <a href="{{ route('app.ordenes.show', $pendingOrder) }}">Ver pedido <i class="bx bx-chevron-right" aria-hidden="true"></i></a>
                                        @endif
                                    </div>
                                </article>
                            @empty
                                <div class="cash-cut-pending-empty"><i class="bx bx-check-circle" aria-hidden="true"></i>No hay pedidos sin cobrar.</div>
                            @endforelse
                        </div>
                    </section>

                    <section aria-labelledby="cash-cut-pending-tables-title">
                        <div class="cash-cut-blocker-section-title">
                            <div>
                                <h3 id="cash-cut-pending-tables-title">Mesas pendientes de liberar</h3>
                                <p>Una mesa ocupada o en cuenta mantiene abierto el turno.</p>
                            </div>
                            <span>{{ $blockers['tables']->count() }}</span>
                        </div>

                        <div class="cash-cut-pending-list">
                            @forelse($blockers['tables'] as $pendingTable)
                                <article class="cash-cut-pending-item cash-cut-pending-item--table">
                                    <span class="cash-cut-pending-item__channel is-table" aria-hidden="true"><i class="bx bx-chair"></i></span>
                                    <div class="cash-cut-pending-item__copy">
                                        <div>
                                            <strong>{{ $pendingTable->display_name }}</strong>
                                            <span class="cash-cut-pending-status">{{ $pendingTable->status_label }}</span>
                                        </div>
                                        <p>{{ $pendingTable->area?->name ?? 'Sin área' }}</p>
                                        <small>
                                            {{ $pendingTable->currentAssignment?->waiter?->name ?? 'Sin mesero asignado' }}
                                            · {{ $pendingTable->orders->count() }} {{ $pendingTable->orders->count() === 1 ? 'pedido abierto' : 'pedidos abiertos' }}
                                            @if($pendingTable->splits->isNotEmpty()) · {{ $pendingTable->splits->count() }} cuenta(s) dividida(s)@endif
                                        </small>
                                    </div>
                                    <div class="cash-cut-pending-item__amount">
                                        <strong>${{ number_format($pendingTable->orders->sum('total'), 2) }}</strong>
                                        @can('ver mesas')
                                            <a href="{{ route('app.mesas.ordenes', $pendingTable) }}">Abrir mesa <i class="bx bx-chevron-right" aria-hidden="true"></i></a>
                                        @endcan
                                    </div>
                                </article>
                            @empty
                                <div class="cash-cut-pending-empty"><i class="bx bx-check-circle" aria-hidden="true"></i>No hay mesas ocupadas o en cuenta.</div>
                            @endforelse
                        </div>
                    </section>
                </div>

                <footer class="cash-cut-blockers__footer">
                    <span><i class="bx bx-info-circle" aria-hidden="true"></i>Después de cobrar o liberar, presiona “Actualizar revisión”.</span>
                    <strong>{{ $blockers['count'] }} {{ $blockers['count'] === 1 ? 'pendiente bloquea' : 'pendientes bloquean' }} el cierre</strong>
                </footer>
            </section>
        @else
            <section class="cash-cut-ready" aria-live="polite">
                <span aria-hidden="true"><i class="bx bx-check-shield"></i></span>
                <div><strong>Operación lista para conciliar</strong><p>No hay pedidos sin asignar ni mesas pendientes.</p></div>
            </section>
        @endif

        <section class="cash-cut-kpis" aria-label="Resumen del turno">
            <article class="cash-cut-kpi cash-cut-kpi--primary">
                <span class="cash-cut-kpi__icon"><i class="bx bx-wallet"></i></span>
                <div><small>Efectivo esperado</small><strong>${{ number_format($this->expectedCash, 2) }}</strong><p>Saldo que debe existir en caja</p></div>
            </article>
            <article class="cash-cut-kpi cash-cut-kpi--success">
                <span class="cash-cut-kpi__icon"><i class="bx bx-trending-up"></i></span>
                <div><small>Ventas del turno</small><strong>${{ number_format($salesTotal, 2) }}</strong><p>{{ $this->orders->count() }} pedidos cobrados</p></div>
            </article>
            <article class="cash-cut-kpi cash-cut-kpi--danger">
                <span class="cash-cut-kpi__icon"><i class="bx bx-trending-down"></i></span>
                <div><small>Gastos en efectivo</small><strong>${{ number_format($this->totalExpensesCash, 2) }}</strong><p>{{ $this->expenses->count() }} movimientos registrados</p></div>
            </article>
            <article class="cash-cut-kpi cash-cut-kpi--info">
                <span class="cash-cut-kpi__icon"><i class="bx bx-credit-card"></i></span>
                <div><small>Ventas digitales</small><strong>${{ number_format($cardTotal + $transferTotal, 2) }}</strong><p>No afectan el efectivo físico</p></div>
            </article>
        </section>

        <div class="cash-cut-layout">
            <main class="cash-cut-content">
                @if($this->cashIncomes->isNotEmpty())
                <section class="app-card cash-cut-card">
                    <header class="cash-cut-card__header">
                        <div><span class="cash-cut-card__icon cash-cut-card__icon--success"><i class="bx bx-trending-up"></i></span><div><h2>Ingresos adicionales</h2><p>Efectivo agregado manualmente durante el turno.</p></div></div>
                        <span class="app-count-pill">{{ $this->cashIncomes->count() }} registros</span>
                    </header>
                    <div class="table-responsive">
                        <table class="table app-table cash-cut-table">
                            <thead><tr><th>Concepto</th><th>Categoría</th><th class="text-end">Monto</th></tr></thead>
                            <tbody>
                                @foreach($this->cashIncomes as $income)
                                    <tr>
                                        <td><strong>{{ $income->description }}</strong></td>
                                        <td>{{ $income->category_label }}</td>
                                        <td class="text-end cash-cut-positive">+${{ number_format($income->amount, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot><tr><th colspan="2">Total agregado a caja</th><th class="text-end cash-cut-positive">+${{ number_format($this->totalCashIncome, 2) }}</th></tr></tfoot>
                        </table>
                    </div>
                </section>
                @endif

                <section class="app-card cash-cut-card">
                    <header class="cash-cut-card__header">
                        <div><span class="cash-cut-card__icon"><i class="bx bx-bar-chart-alt-2"></i></span><div><h2>Ventas por área</h2><p>Desglose por canal y método de pago.</p></div></div>
                        <span class="cash-cut-helper"><i class="bx bx-info-circle"></i> Solo efectivo entra a caja</span>
                    </header>
                    <div class="table-responsive">
                        <table class="table app-table cash-cut-table">
                            <thead><tr><th>Área</th><th class="text-end cash-column">Efectivo</th><th class="text-end">Tarjeta</th><th class="text-end">Transferencia</th><th class="text-end">Total</th></tr></thead>
                            <tbody>
                                @foreach([
                                    ['icon' => 'bx-store', 'label' => 'Ventanilla', 'data' => $t['v']],
                                    ['icon' => 'bx-chair', 'label' => 'Mesas', 'data' => $t['m']],
                                    ['icon' => 'bx-cycling', 'label' => 'Domicilio', 'data' => $t['d']],
                                ] as $area)
                                    <tr>
                                        <td><span class="cash-cut-area"><i class="bx {{ $area['icon'] }}"></i><strong>{{ $area['label'] }}</strong></span></td>
                                        <td class="text-end cash-column">${{ number_format($area['data']['efectivo'], 2) }}</td>
                                        <td class="text-end app-muted">${{ number_format($area['data']['tarjeta'], 2) }}</td>
                                        <td class="text-end app-muted">${{ number_format($area['data']['transfer'], 2) }}</td>
                                        <td class="text-end app-money">${{ number_format($area['data']['total'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot><tr><th>Total del turno</th><th class="text-end cash-column">${{ number_format($this->totalCashIn, 2) }}</th><th class="text-end">${{ number_format($cardTotal, 2) }}</th><th class="text-end">${{ number_format($transferTotal, 2) }}</th><th class="text-end">${{ number_format($salesTotal, 2) }}</th></tr></tfoot>
                        </table>
                    </div>
                </section>

                @if($this->deliveryManagementEnabled)
                <section class="app-card cash-cut-card">
                    <header class="cash-cut-card__header">
                        <div><span class="cash-cut-card__icon cash-cut-card__icon--info"><i class="bx bx-cycling"></i></span><div><h2>Arqueos de delivery</h2><p>Mini cortes completados por repartidor en este turno.</p></div></div>
                        <span class="app-count-pill">{{ $this->deliverySettlements->count() }} arqueos</span>
                    </header>
                    <div class="table-responsive">
                        <table class="table app-table cash-cut-table">
                            <thead><tr><th>Repartidor</th><th class="text-end">Notas</th><th class="text-end">Efectivo esperado</th><th class="text-end">Efectivo entregado</th><th class="text-end">Transferencias</th><th class="text-end">Diferencia</th><th>Estado</th></tr></thead>
                            <tbody>
                                @forelse($this->deliverySettlements as $settlement)
                                    <tr>
                                        <td><strong>{{ $settlement->driver?->name ?? 'Usuario eliminado' }}</strong><small class="d-block app-muted">{{ $settlement->completed_at->format('g:i A') }}</small></td>
                                        <td class="text-end">{{ $settlement->orders_count }}</td>
                                        <td class="text-end cash-column">${{ number_format($settlement->expected_cash, 2) }}</td>
                                        <td class="text-end cash-column">${{ number_format($settlement->declared_cash, 2) }}</td>
                                        <td class="text-end">${{ number_format($settlement->transfer_total, 2) }}</td>
                                        <td class="text-end {{ (float)$settlement->difference === 0.0 ? 'cash-cut-positive' : 'cash-cut-negative' }}">${{ number_format($settlement->difference, 2) }}</td>
                                        <td><span class="app-status app-status--success"><i class="bx bx-check-double"></i>Arqueo completado</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7"><div class="cash-cut-empty"><span><i class="bx bx-cycling"></i></span><div><strong>Sin arqueos de delivery</strong><p>Este turno no tiene entregas conciliadas.</p></div></div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
                @else
                    @php $manualDelivery = $this->manualDeliverySummary; @endphp
                    <section class="app-card cash-cut-card">
                        <header class="cash-cut-card__header">
                            <div><span class="cash-cut-card__icon cash-cut-card__icon--info"><i class="bx bx-hand"></i></span><div><h2>Delivery en corte global</h2><p>La conciliación con los empleados se realiza manualmente; no existen mini cortes individuales.</p></div></div>
                            <span class="app-count-pill">{{ $manualDelivery['orders'] }} pedidos</span>
                        </header>
                        <div class="delivery-manual-summary">
                            <div><small>Efectivo esperado</small><strong>${{ number_format($manualDelivery['cash'], 2) }}</strong></div>
                            <div><small>Venta contabilizada</small><strong>${{ number_format($manualDelivery['total'], 2) }}</strong></div>
                        </div>
                    </section>
                @endif

                <section class="app-card cash-cut-card">
                    <header class="cash-cut-card__header">
                        <div><span class="cash-cut-card__icon cash-cut-card__icon--info"><i class="bx bx-user-check"></i></span><div><h2>Ventas por usuario</h2><p>Quién atendió y cuánto cobró durante este turno.</p></div></div>
                        <span class="cash-cut-helper"><i class="bx bx-shield-quarter"></i> Auditoría</span>
                    </header>
                    <div class="table-responsive">
                        <table class="table app-table cash-cut-table">
                            <thead><tr><th>Usuario</th><th class="text-end">Pedidos</th><th class="text-end">Ventanilla</th><th class="text-end">Mesas</th><th class="text-end">Delivery</th><th class="text-end">Efectivo</th><th class="text-end">Total</th></tr></thead>
                            <tbody>
                                @forelse($this->operatorTotals as $operator)
                                    <tr>
                                        <td><strong>{{ $operator['name'] }}</strong></td>
                                        <td class="text-end">{{ $operator['orders'] }}</td>
                                        <td class="text-end app-muted">${{ number_format($operator['areas']['ventanilla'], 2) }}</td>
                                        <td class="text-end app-muted">${{ number_format($operator['areas']['mesa'], 2) }}</td>
                                        <td class="text-end app-muted">${{ number_format($operator['areas']['delivery'], 2) }}</td>
                                        <td class="text-end cash-column">${{ number_format($operator['cash'], 2) }}</td>
                                        <td class="text-end app-money">${{ number_format($operator['total'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7"><div class="cash-cut-empty"><span><i class="bx bx-user-x"></i></span><div><strong>Sin ventas cobradas</strong><p>No hay usuarios con cobros registrados en este turno.</p></div></div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="app-card cash-cut-card">
                    <header class="cash-cut-card__header">
                        <div><span class="cash-cut-card__icon cash-cut-card__icon--danger"><i class="bx bx-receipt"></i></span><div><h2>Gastos del turno</h2><p>Solo los pagos en efectivo reducen el saldo esperado.</p></div></div>
                        <span class="app-count-pill">{{ $this->expenses->count() }} registros</span>
                    </header>
                    @if($this->expenses->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table app-table cash-cut-table">
                                <thead><tr><th>Concepto</th><th>Método</th><th class="text-end">Monto</th></tr></thead>
                                <tbody>
                                    @foreach($this->expenses as $expense)
                                        <tr>
                                            <td><strong>{{ $expense->description ?: $expense->category_label }}</strong></td>
                                            <td><span class="app-status {{ $expense->payment_method === 'cash' ? 'app-status--danger' : 'app-status--neutral' }}"><i class="bx {{ $expense->payment_method === 'cash' ? 'bx-money' : 'bx-credit-card' }}"></i>{{ $expense->payment_method === 'cash' ? 'Efectivo' : 'Digital' }}</span></td>
                                            <td class="text-end {{ $expense->payment_method === 'cash' ? 'cash-cut-negative' : 'app-muted' }}">-${{ number_format($expense->amount, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot><tr><th colspan="2">Total descontado de caja</th><th class="text-end cash-cut-negative">-${{ number_format($this->totalExpensesCash, 2) }}</th></tr></tfoot>
                            </table>
                        </div>
                    @else
                        <div class="cash-cut-empty"><span><i class="bx bx-check-shield"></i></span><div><strong>Sin gastos registrados</strong><p>No hay movimientos que descontar del efectivo esperado.</p></div></div>
                    @endif
                </section>

                <section class="app-card cash-cut-card">
                    <header class="cash-cut-card__header">
                        <div><span class="cash-cut-card__icon cash-cut-card__icon--info"><i class="bx bx-list-ul"></i></span><div><h2>Pedidos cobrados</h2><p>Historial del turno actual.</p></div></div>
                        <span class="app-count-pill">{{ $this->orders->count() }} pedidos</span>
                    </header>
                    <div class="cash-cut-orders">
                        @forelse($this->orders as $order)
                            @php $cashAmount = $order->payments->where('method', 'efectivo')->sum('amount'); @endphp
                            <article class="cash-cut-order">
                                <span class="cash-cut-order__number">{{ $order->display_folio }}</span>
                                <div class="cash-cut-order__copy"><strong>{{ $order->customer_name ?: 'Anónimo' }}</strong><small>{{ $order->type_label }} · {{ $order->created_at->format('g:i A') }}</small></div>
                                <div class="cash-cut-order__amount"><strong>${{ number_format($order->total, 2) }}</strong>@if($cashAmount > 0)<small><i class="bx bx-money"></i> Efectivo ${{ number_format($cashAmount, 2) }}</small>@endif</div>
                            </article>
                        @empty
                            <div class="cash-cut-empty"><span><i class="bx bx-receipt"></i></span><div><strong>Sin pedidos cobrados</strong><p>Este turno todavía no registra ventas pagadas.</p></div></div>
                        @endforelse
                    </div>
                </section>
            </main>

            <aside class="app-card cash-cut-reconciliation">
                <header><span class="cash-cut-card__icon"><i class="bx bx-calculator"></i></span><div><h2>Conciliación de efectivo</h2><p>Cuenta el dinero físico antes de cerrar.</p></div></header>
                <div class="cash-cut-equation">
                    <div><span>Fondo inicial</span><strong>${{ number_format($reg->initial_amount, 2) }}</strong></div>
                    <div><span><i class="bx bx-plus"></i> Ventas en efectivo</span><strong class="cash-cut-positive">+${{ number_format($this->totalCashIn, 2) }}</strong></div>
                    <div><span><i class="bx bx-plus"></i> Ingresos adicionales</span><strong class="cash-cut-positive">+${{ number_format($this->totalCashIncome, 2) }}</strong></div>
                    <div><span><i class="bx bx-minus"></i> Gastos en efectivo</span><strong class="cash-cut-negative">-${{ number_format($this->totalExpensesCash, 2) }}</strong></div>
                    <div class="cash-cut-equation__total"><span>Efectivo esperado</span><strong>${{ number_format($this->expectedCash, 2) }}</strong></div>
                </div>

                <label class="cash-cut-field">
                    <span>Efectivo contado en caja <small>Obligatorio</small></span>
                    <div class="cash-cut-money-input @error('declaredCash') is-invalid @enderror"><b>$</b><input type="number" wire:model.live="declaredCash" step="0.01" min="0" inputmode="decimal" placeholder="0.00"></div>
                </label>
                @error('declaredCash')<p class="cash-cut-error"><i class="bx bx-error-circle"></i>{{ $message }}</p>@enderror

                @if($declaredCash !== '')
                    <div class="cash-cut-difference {{ $diff == 0 ? 'is-exact' : ($diff > 0 ? 'is-over' : 'is-short') }}" aria-live="polite">
                        <span><i class="bx {{ $diff == 0 ? 'bx-check-circle' : ($diff > 0 ? 'bx-trending-up' : 'bx-trending-down') }}"></i></span>
                        <div><small>Diferencia</small><strong>{{ $diff == 0 ? 'Cuadra exacto' : ($diff > 0 ? 'Sobrante de $'.number_format(abs($diff), 2) : 'Faltante de $'.number_format(abs($diff), 2)) }}</strong></div>
                    </div>
                @endif

                <label class="cash-cut-field"><span>Notas de cierre <small>Opcional</small></span><textarea wire:model="closingNotes" rows="3" placeholder="Observaciones importantes del turno…"></textarea></label>

                @if($blockers['has_blockers'])
                    <div class="cash-cut-warning cash-cut-warning--blocked"><i class="bx bx-lock-alt"></i><p><strong>Cierre no disponible.</strong> Resuelve los pedidos y mesas mostrados en la revisión operativa.</p></div>
                    <button type="button" disabled aria-disabled="true" class="btn btn-danger cash-cut-submit">
                        <span><i class="bx bx-lock-alt"></i>{{ $blockers['count'] }} {{ $blockers['count'] === 1 ? 'pendiente por resolver' : 'pendientes por resolver' }}</span>
                    </button>
                @else
                    <div class="cash-cut-warning"><i class="bx bx-error-circle"></i><p><strong>Revisa antes de continuar.</strong> El cierre de caja no se puede deshacer.</p></div>
                    <button type="button" wire:click="confirmCut" wire:loading.attr="disabled" wire:target="confirmCut" class="btn btn-danger cash-cut-submit"><span wire:loading.remove wire:target="confirmCut"><i class="bx bx-lock-alt"></i> Revisar y cerrar caja</span><span wire:loading wire:target="confirmCut">Validando efectivo…</span></button>
                @endif
            </aside>
        </div>

        @if($showConfirm)
            <div class="cash-cut-modal-backdrop" role="presentation">
                <section class="cash-cut-modal" role="dialog" aria-modal="true" aria-labelledby="cash-cut-confirm-title">
                    <header><span><i class="bx bx-lock-alt"></i></span><div><small>Confirmación final</small><h2 id="cash-cut-confirm-title">Cerrar {{ $this->register->name }}</h2></div></header>
                    <div class="cash-cut-modal__body">
                        <p>Confirma los importes del turno. Después del cierre no podrás registrar más movimientos en esta caja.</p>
                        <dl>
                            <div><dt>Efectivo esperado</dt><dd>${{ number_format($this->expectedCash, 2) }}</dd></div>
                            <div><dt>Efectivo declarado</dt><dd>${{ number_format((float) $declaredCash, 2) }}</dd></div>
                            <div class="{{ $diff >= 0 ? 'is-positive' : 'is-negative' }}"><dt>Diferencia</dt><dd>{{ $diff >= 0 ? '+' : '' }}${{ number_format($diff, 2) }}</dd></div>
                        </dl>
                    </div>
                    <footer><button type="button" wire:click="$set('showConfirm', false)" class="btn btn-outline-secondary">Seguir revisando</button><button type="button" wire:click="generateCut" wire:loading.attr="disabled" wire:target="generateCut" class="btn btn-danger"><span wire:loading.remove wire:target="generateCut"><i class="bx bx-check"></i> Confirmar cierre</span><span wire:loading wire:target="generateCut">Cerrando caja…</span></button></footer>
                </section>
            </div>
        @endif
    @endif
</div>
