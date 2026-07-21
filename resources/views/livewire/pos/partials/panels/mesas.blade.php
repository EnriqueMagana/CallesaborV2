<div class="pos-overlay-panel" :class="panels.mesas ? 'show' : ''">
    <div class="pos-overlay-backdrop" @click="panels.mesas = false"></div>
    <section class="pos-panel pos-area-panel pos-tables-panel" role="dialog" aria-modal="true" aria-labelledby="pos-tables-title">
        <header class="panel-header pos-area-panel__header">
            <span class="pos-area-panel__mark is-tables"><i class="bx bx-table"></i></span>
            <div>
                <span class="pos-area-panel__eyebrow">Área operativa</span>
                <h2 id="pos-tables-title">Mesas</h2>
                <p>Cada mesa agrupa sus órdenes. La última nota pagada libera la mesa.</p>
            </div>
            <button type="button" class="btn-panel-close" @click="panels.mesas = false" aria-label="Cerrar Mesas"><i class="bx bx-x"></i></button>
        </header>

        <div class="pos-area-panel__tools">
            <div class="pos-area-guidance"><i class="bx bx-info-circle"></i><span>Imprime cada orden para enviarla a preparación; después márcala lista antes de cobrar.</span></div>
            <div class="pos-area-summary"><strong>{{ $this->mesasPendientes->count() }}</strong><span>mesas por cobrar</span></div>
        </div>

        <div wire:loading.flex wire:target="reopenMesa,discardEmptyMesaAccount,openMesaPayModal,confirmMesaPayment" class="pos-skeleton-list" aria-label="Cargando mesas">
            @for($s = 0; $s < 2; $s++)
                <div class="pos-table-skeleton"><span></span><div><i></i><i></i><i></i></div></div>
            @endfor
        </div>
        <div wire:loading.class="is-loading" wire:target="reopenMesa,discardEmptyMesaAccount,openMesaPayModal,confirmMesaPayment" class="panel-body pos-area-panel__body">
            @forelse ($this->mesasPendientes as $mesa)
                @php $allOrdersReady = $mesa->orders->isNotEmpty() && $mesa->orders->every(fn ($order) => in_array($order->status, ['lista', 'entregada'], true)); @endphp
                <article class="pos-table-group" wire:key="pos-table-{{ $mesa->id }}">
                    <header class="pos-table-group__header">
                        <div class="pos-table-group__identity">
                            <span><i class="bx bx-table"></i></span>
                            <div>
                                <strong>{{ $mesa->display_name }}</strong>
                                <small>{{ $mesa->area?->name ?: 'Área general' }}@if($mesa->currentAssignment) · {{ $mesa->currentAssignment->waiter?->name }}@endif</small>
                            </div>
                        </div>
                        <div class="pos-table-group__total">
                            <strong>${{ number_format($mesa->mesa_total, 2) }}</strong>
                            <small>{{ $mesa->orders->count() }} {{ $mesa->orders->count() === 1 ? 'orden activa' : 'órdenes activas' }}</small>
                        </div>
                    </header>

                    @if ($mesa->active_split && (float) $mesa->mesa_total > 0.009)
                        <section class="pos-table-split-list pos-table-split-list--primary" aria-label="Subcuentas pendientes de {{ $mesa->display_name }}">
                            <div class="pos-table-split-list__title">
                                <span><i class="bx bx-split"></i> Cobrar cuenta dividida</span>
                                <small>{{ collect($mesa->active_split->split_data)->filter(fn ($account) => !($account['paid'] ?? false))->count() }} pendientes</small>
                            </div>
                            @foreach ($mesa->active_split->split_data as $idx => $account)
                                <div class="pos-split-account {{ ($account['paid'] ?? false) ? 'is-paid' : '' }}">
                                    <div>
                                        <strong>{{ $account['label'] ?? 'Cuenta '.($idx + 1) }}</strong>
                                        <small>{{ count($account['items'] ?? []) }} producto(s) · ${{ number_format((float) ($account['total'] ?? 0), 2) }}</small>
                                    </div>
                                    @if (!($account['paid'] ?? false))
                                        @if ($allOrdersReady)
                                            <button type="button" wire:click="openMesaSplitPayModal({{ $mesa->active_split->id }}, {{ $idx }})" wire:loading.attr="disabled" wire:target="openMesaSplitPayModal" class="pos-btn pos-btn-primary" aria-label="Cobrar {{ $account['label'] ?? 'Cuenta '.($idx + 1) }} por ${{ number_format((float) ($account['total'] ?? 0), 2) }}">
                                                <i class="bx bx-dollar-circle"></i><span>Cobrar</span>
                                            </button>
                                        @else
                                            <span class="pos-paid-chip is-pending" role="status"><i class="bx bx-time-five"></i>Esperando lista</span>
                                        @endif
                                    @else
                                        <span class="pos-paid-chip"><i class="bx bx-check"></i>Pagada</span>
                                    @endif
                                </div>
                            @endforeach
                        </section>
                    @endif

                    <div class="pos-table-group__orders">
                        @foreach ($mesa->orders as $tableOrder)
                            @include('livewire.pos.partials.order-flow-card', [
                                'flowOrder' => $tableOrder,
                                'flowArea' => 'Mesa '.$mesa->number,
                                'flowIcon' => 'bx-receipt',
                                'flowSourceLabel' => $tableOrder->source === 'kiosk' ? 'Kiosco' : 'Mesero',
                                'allowOrderPayment' => false,
                            ])
                        @endforeach
                    </div>

                    <footer class="pos-table-group__footer">
                        @if ((float) $mesa->mesa_total <= 0.009)
                            <div class="pos-table-group__close-copy">
                                <strong>Cuenta en cero</strong>
                                <small>No genera movimiento de caja.</small>
                            </div>
                            <div class="pos-table-group__footer-actions">
                                <button type="button" wire:click="reopenMesa({{ $mesa->id }})" class="pos-btn pos-btn-secondary">
                                    <i class="bx bx-lock-open-alt"></i> Reabrir mesa
                                </button>
                                <button type="button" wire:click="requestDiscardEmptyMesaAccount({{ $mesa->id }})" class="pos-btn pos-btn-danger">
                                    <i class="bx bx-trash"></i> Eliminar cuenta
                                </button>
                            </div>
                        @else
                        <button type="button" wire:click="reopenMesa({{ $mesa->id }})" class="pos-btn pos-btn-ghost pos-reopen-table">
                            <i class="bx bx-lock-open-alt"></i> Reabrir mesa
                        </button>
                        @if (!$mesa->active_split)
                            <div class="pos-table-group__close-copy">
                                <strong>Cobro conjunto</strong>
                                <small>{{ $allOrdersReady ? 'Todas las órdenes están listas.' : 'Primero marca todas las órdenes como listas.' }}</small>
                            </div>
                            <button type="button" wire:click="openMesaPayModal({{ $mesa->id }})" class="pos-btn pos-btn-primary" {{ $allOrdersReady ? '' : 'disabled' }}>
                                <i class="bx bx-dollar-circle"></i>Cobrar mesa completa
                            </button>
                        @endif
                        @endif
                    </footer>
                </article>
            @empty
                <div class="pos-area-empty">
                    <span><i class="bx bx-check-circle"></i></span>
                    <h3>No hay mesas abiertas</h3>
                    <p>Los pedidos de mesa, incluidos los del kiosco, aparecerán agrupados aquí.</p>
                </div>
            @endforelse
        </div>
    </section>
</div>
