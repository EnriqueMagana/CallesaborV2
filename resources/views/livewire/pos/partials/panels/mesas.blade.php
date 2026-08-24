<x-pos.area-panel panel="mesas" title="Cobrar mesas" title-id="pos-tables-title"
    eyebrow="Área de cobro" description="Consulta consumos, retoma la mesa si hace falta y cobra la cuenta completa o dividida."
    icon="bx-table" tone="tables" panel-class="pos-tables-panel" close-label="Cerrar Mesas"
    close-action="panels.mesas = false; $wire.closeTablesBilling()">
        <x-slot:tools>
            <div class="pos-area-guidance"><i class="bx bx-credit-card"></i>
                <span>Selecciona la cuenta que vas a cobrar. La última cuenta pagada libera las mesas del servicio.</span>
            </div>
            <div class="pos-area-summary"><strong>{{ $this->mesasPendientes->count() }}</strong><span>mesas por cobrar</span></div>
        </x-slot:tools>

        <x-slot:beforeBody>
        <div wire:loading.flex wire:target="openTablesBilling"
            class="pos-skeleton-list" aria-label="Consultando cuentas de mesas">
            @for ($s = 0; $s < 2; $s++)
                <div class="pos-table-skeleton"><span></span><div><i></i><i></i><i></i></div></div>
            @endfor
        </div>
        </x-slot:beforeBody>

        <x-slot:body>
        <div class="panel-body pos-area-panel__body pos-tables-accordion" wire:loading.remove
            wire:target="openTablesBilling"
            x-data="{ openMesa: @js($this->mesasPendientes->first()?->id) }">
            @forelse ($this->mesasPendientes as $mesa)
                @php
                    $allOrdersReady = $mesa->orders->isNotEmpty() && $mesa->orders->every(fn ($order) => in_array($order->status, ['lista', 'entregada'], true));
                    $pendingSplitAccounts = $mesa->active_split
                        ? collect($mesa->active_split->split_data)->reject(fn ($account) => (bool) ($account['paid'] ?? false))
                        : collect();
                @endphp
                <article class="pos-table-group pos-table-billing-group" wire:key="pos-table-{{ $mesa->id }}">
                    <header class="pos-table-group__header pos-table-billing-group__header">
                        <button type="button" class="pos-table-billing-toggle"
                            id="billing-table-toggle-{{ $mesa->id }}"
                            @click="openMesa = openMesa === {{ $mesa->id }} ? null : {{ $mesa->id }}"
                            :aria-expanded="(openMesa === {{ $mesa->id }}).toString()"
                            aria-controls="billing-table-content-{{ $mesa->id }}">
                        <div class="pos-table-group__identity">
                            <span><i class="bx bx-table"></i></span>
                            <div>
                                <strong>{{ $mesa->operational_label ?? $mesa->display_name }}</strong>
                                <small>{{ $mesa->area?->name ?: 'Área general' }}@if($mesa->currentAssignment) · {{ $mesa->currentAssignment->waiter?->name }}@endif</small>
                            </div>
                        </div>
                        <div class="pos-table-group__total">
                            <strong>${{ number_format($mesa->mesa_total, 2) }}</strong>
                            @if ($mesa->active_split)
                                <small>{{ $pendingSplitAccounts->count() }} {{ $pendingSplitAccounts->count() === 1 ? 'subcuenta pendiente' : 'subcuentas pendientes' }}</small>
                            @else
                                <small>{{ $mesa->orders->count() }}
                                    {{ $mesa->orders->count() === 1 ? 'orden activa' : 'órdenes activas' }}</small>
                            @endif
                        </div>
                        <span class="pos-table-billing-toggle__chevron" aria-hidden="true">
                            <i class="bx bx-chevron-down" :class="{ 'is-expanded': openMesa === {{ $mesa->id }} }"></i>
                        </span>
                        </button>
                    </header>

                    <div class="pos-table-billing-group__content"
                        id="billing-table-content-{{ $mesa->id }}"
                        role="region" aria-labelledby="billing-table-toggle-{{ $mesa->id }}"
                        x-show="openMesa === {{ $mesa->id }}" x-cloak
                        x-transition:enter.opacity.duration.180ms
                        x-transition:leave.opacity.duration.120ms>
                    @if ($mesa->active_split && (float) $mesa->mesa_total > 0.009)
                        <section class="pos-table-split-list pos-table-split-list--primary" aria-label="Subcuentas pendientes de {{ $mesa->display_name }}">
                            <div class="pos-table-split-list__title">
                                <span><i class="bx bx-git-branch"></i> Cobrar cuenta dividida</span>
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
                    @elseif ($mesa->active_split)
                        <section class="pos-split-zero-state" role="status" aria-label="Subcuenta restante sin consumo">
                            <span class="pos-split-zero-state__icon"><i class="bx bx-check-circle"></i></span>
                            <div>
                                <strong>Subcuenta restante sin consumo</strong>
                                <p>Las subcuentas con consumo ya fueron cobradas. No existe un monto adicional por
                                    cobrar en esta mesa.</p>
                            </div>
                        </section>
                    @endif

                    @unless ($mesa->active_split)
                        <div class="pos-table-group__orders">
                            @foreach ($mesa->orders as $tableOrder)
                                @include('livewire.pos.partials.order-flow-card', [
                                    'flowOrder' => $tableOrder,
                                    'flowArea' => $mesa->operational_label ?? $mesa->display_name,
                                    'flowIcon' => 'bx-receipt',
                                    'flowSourceLabel' => $tableOrder->source === 'kiosk' ? 'Kiosco' : 'Mesero',
                                    'allowOrderPayment' => false,
                                    'showOperationalStatus' => false,
                                    'showKitchenActions' => false,
                                ])
                            @endforeach
                        </div>
                    @endunless

                    <footer class="pos-table-group__footer">
                        @if ((float) $mesa->mesa_total <= 0.009)
                            <div class="pos-table-group__close-copy">
                                <strong>Servicio sin consumo</strong>
                                <small>Puede cancelarse sin generar venta ni movimiento de caja.</small>
                            </div>
                            <div class="pos-table-group__footer-actions">
                                <button type="button" wire:click="reopenMesa({{ $mesa->id }})" class="pos-btn pos-btn-secondary">
                                    <i class="bx bx-lock-open-alt"></i> Reabrir mesa
                                </button>
                                <button type="button" wire:click="requestDiscardEmptyMesaAccount({{ $mesa->id }})" class="pos-btn pos-btn-danger">
                                    <i class="bx bx-x-circle"></i> Cancelar servicio
                                </button>
                            </div>
                        @else
                        <button type="button" wire:click="reopenMesa({{ $mesa->id }})"
                            class="pos-btn pos-btn-secondary pos-reopen-table">
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
                    </div>
                </article>
            @empty
                <div class="pos-area-empty">
                    <span><i class="bx bx-check-circle"></i></span>
                    <h3>No hay cuentas pendientes</h3>
                    <p>Las mesas enviadas a cobro, incluso las que no tuvieron consumo, aparecerán aquí.</p>
                </div>
            @endforelse
        </div>
        </x-slot:body>
</x-pos.area-panel>
