@php
    $workspaceCounts = $this->tableWorkspaceCounts;
    $workspaceTabs = [
        'all' => ['label' => 'Todas', 'icon' => 'bx-layer', 'count' => $workspaceCounts['all']],
        'service' => ['label' => 'En servicio', 'icon' => 'bx-table', 'count' => $workspaceCounts['service']],
        'kitchen' => ['label' => 'En cocina', 'icon' => 'bx-restaurant', 'count' => $workspaceCounts['kitchen']],
        'ready' => ['label' => 'Listas', 'icon' => 'bx-check-circle', 'count' => $workspaceCounts['ready']],
        'billing' => ['label' => 'Por cobrar', 'icon' => 'bx-credit-card', 'count' => $workspaceCounts['billing']],
    ];
    $legacyWorkspaceMesas = (auth()->user()?->can('cobrar mesas') && in_array($tableWorkspaceFilter, ['all', 'billing'], true))
        ? $this->mesasPendientes->filter(fn ($mesa) => ! ($mesa->active_service ?? null))
        : collect();
@endphp

<x-pos.area-panel panel="tables" title="Mesas y comandas" title-id="pos-table-workspace-title"
    eyebrow="Operación de mesas"
    description="Sigue cada comanda y cobra la cuenta desde el mismo servicio, sin perder el contexto de la mesa."
    icon="bx-dish" tone="workspace" panel-class="pos-table-workspace-panel pos-tracking-panel"
    close-label="Cerrar Mesas y comandas"
    close-action="panels.tables = false; $wire.closeTableWorkspace()">

    <x-slot:navigation>
        <nav class="pos-workspace-tabs" aria-label="Filtrar servicios de mesa" role="tablist">
            @foreach ($workspaceTabs as $filter => $tab)
                @if ($filter !== 'billing' || auth()->user()?->can('cobrar mesas'))
                    <button type="button" role="tab"
                        wire:click="setTableWorkspaceFilter('{{ $filter }}')"
                        wire:loading.attr="disabled" wire:target="setTableWorkspaceFilter"
                        class="pos-workspace-tab {{ $tableWorkspaceFilter === $filter ? 'is-active' : '' }}"
                        aria-selected="{{ $tableWorkspaceFilter === $filter ? 'true' : 'false' }}">
                        <i class="bx {{ $tab['icon'] }}" aria-hidden="true"></i>
                        <span>{{ $tab['label'] }}</span>
                        <strong>{{ $tab['count'] }}</strong>
                    </button>
                @endif
            @endforeach
        </nav>
    </x-slot:navigation>

    <x-slot:tools>
        <div class="pos-workspace-tools">
            <div class="pos-area-guidance">
                <i class="bx bx-info-circle" aria-hidden="true"></i>
                <span>Las acciones disponibles cambian según el estado del servicio y tus permisos.</span>
            </div>
            <button type="button" class="pos-btn pos-btn-secondary" wire:click="refreshTableWorkspace"
                wire:loading.attr="disabled" wire:target="refreshTableWorkspace">
                <i class="bx bx-refresh" wire:loading.class="bx-spin" wire:target="refreshTableWorkspace" aria-hidden="true"></i>
                <span>Actualizar</span>
            </button>
            @if ($tableTrackingRefreshedAt)
                <small class="pos-tracking-updated">{{ $tableTrackingRefreshedAt }}</small>
            @endif
        </div>
    </x-slot:tools>

    <x-slot:beforeBody>
        <div wire:loading.flex wire:target="openTableWorkspace,openTableTracking,openTablesBilling,refreshTableWorkspace,setTableWorkspaceFilter"
            class="pos-skeleton-list" aria-label="Actualizando servicios de mesa">
            @for ($s = 0; $s < 3; $s++)
                <div class="pos-table-skeleton"><span></span><div><i></i><i></i><i></i></div></div>
            @endfor
        </div>
    </x-slot:beforeBody>

    <x-slot:body>
        <div class="panel-body pos-area-panel__body pos-tracking-accordion"
            wire:loading.remove
            wire:target="openTableWorkspace,openTableTracking,openTablesBilling,refreshTableWorkspace,setTableWorkspaceFilter"
            x-data="{ openService: @js($this->tableWorkspaceServices->first()?->id) }">
            <span class="pos-tables-accordion" hidden aria-hidden="true"></span>
            @foreach ($this->tableWorkspaceServices as $service)
                @php
                    $primaryMesa = $service->primaryMesa ?: $service->mesas->first();
                    $activeSplit = $service->splits->first();
                    $pending = $service->orders->where('status', 'pendiente')->count();
                    $preparing = $service->orders->where('status', 'en_preparacion')->count();
                    $ready = $service->orders->whereIn('status', ['lista', 'entregada'])->count();
                    $allOrdersReady = $service->orders->isNotEmpty()
                        && $service->orders->every(fn ($order) => in_array($order->status, ['lista', 'entregada'], true));
                    $serviceTotal = $activeSplit
                        ? collect($activeSplit->split_data)->reject(fn ($account) => (bool) ($account['paid'] ?? false))->sum('total')
                        : (float) $service->orders->sum('total');
                    $pendingSplitAccounts = $activeSplit
                        ? collect($activeSplit->split_data)->reject(fn ($account) => (bool) ($account['paid'] ?? false))
                        : collect();
                @endphp

                <article class="pos-table-group pos-tracking-service pos-workspace-service is-{{ $service->status }}"
                    wire:key="workspace-service-{{ $service->id }}">
                    <header class="pos-table-group__header pos-tracking-service__header">
                        <button type="button" class="pos-tracking-service__toggle"
                            id="workspace-service-toggle-{{ $service->id }}"
                            @click="openService = openService === {{ $service->id }} ? null : {{ $service->id }}"
                            :aria-expanded="(openService === {{ $service->id }}).toString()"
                            aria-controls="workspace-service-content-{{ $service->id }}">
                            <span class="pos-tracking-service__identity">
                                <span class="pos-tracking-service__icon"><i class="bx {{ $service->is_grouped ? 'bx-group' : 'bx-table' }}"></i></span>
                                <span class="pos-tracking-service__copy">
                                    <span class="pos-workspace-service__heading">
                                        <strong>{{ $service->service_label }}</strong>
                                        <span class="pos-workspace-phase {{ $service->status === 'en_cuenta' ? 'is-billing' : 'is-service' }}">
                                            <i class="bx {{ $service->status === 'en_cuenta' ? 'bx-credit-card' : 'bx-dish' }}"></i>
                                            {{ $service->status === 'en_cuenta' ? 'Por cobrar' : 'En servicio' }}
                                        </span>
                                    </span>
                                    <small class="pos-service-opened">
                                        <span>{{ $service->opener_name_snapshot ?: 'Sin asignar' }}</span>
                                        <span><i class="bx bx-calendar"></i>{{ $service->opened_at->format('g:i A') }}</span>
                                        <span><i class="bx bx-time-five"></i>{{ $service->duration_label }} activa</span>
                                        @if ($activeSplit)
                                            <span><i class="bx bx-git-branch"></i>{{ $pendingSplitAccounts->count() }} {{ $pendingSplitAccounts->count() === 1 ? 'subcuenta pendiente' : 'subcuentas pendientes' }}</span>
                                        @endif
                                        @can('cobrar mesas')
                                            <span class="pos-workspace-total">${{ number_format($serviceTotal, 2) }}</span>
                                        @endcan
                                    </small>
                                </span>
                            </span>
                            <span class="pos-tracking-statuses" aria-label="Resumen de comandas">
                                <span class="is-pending"><i class="bx bx-time"></i>{{ $pending }} pendientes</span>
                                <span class="is-preparing"><i class="bx bx-loader-circle"></i>{{ $preparing }} preparando</span>
                                <span class="is-ready"><i class="bx bx-check"></i>{{ $ready }} listas</span>
                            </span>
                            <span class="pos-tracking-service__chevron" aria-hidden="true">
                                <i class="bx bx-chevron-down" :class="{ 'is-expanded': openService === {{ $service->id }} }"></i>
                            </span>
                        </button>
                    </header>

                    <div class="pos-tracking-service__content" id="workspace-service-content-{{ $service->id }}"
                        role="region" aria-labelledby="workspace-service-toggle-{{ $service->id }}"
                        x-show="openService === {{ $service->id }}" x-cloak
                        x-transition:enter.opacity.duration.180ms x-transition:leave.opacity.duration.120ms>
                        @if ($service->mesas->isNotEmpty())
                            <div class="pos-tracking-members" aria-label="Mesas de este servicio">
                                <small>Mesas</small>
                                @foreach ($service->mesas as $member)
                                    <span><i class="bx bx-chair"></i>{{ $member->pivot->mesa_label_snapshot ?: $member->display_name }}</span>
                                @endforeach
                            </div>
                        @endif

                        @if ($activeSplit && $serviceTotal <= 0.009)
                            <section class="pos-split-zero-state" role="status" aria-label="Subcuenta restante sin consumo">
                                <span class="pos-split-zero-state__icon"><i class="bx bx-check-circle"></i></span>
                                <div>
                                    <strong>Subcuenta restante sin consumo</strong>
                                    <p>Las subcuentas con consumo ya fueron cobradas. Cancela el saldo vacío para liberar la mesa.</p>
                                </div>
                            </section>
                        @elseif ($service->orders->isNotEmpty())
                            <div class="pos-table-group__orders">
                                @foreach ($service->orders as $tableOrder)
                                    @include('livewire.pos.partials.order-flow-card', [
                                        'flowOrder' => $tableOrder,
                                        'flowArea' => $service->service_label,
                                        'flowIcon' => 'bx-receipt',
                                        'flowSourceLabel' => $tableOrder->source === 'kiosk' ? 'Kiosco' : 'Mesero',
                                        'allowOrderPayment' => false,
                                        'showFinancialTotal' => auth()->user()?->can('cobrar mesas') ?? false,
                                    ])
                                @endforeach
                            </div>
                        @else
                            <div class="pos-tracking-empty-orders"><i class="bx bx-time-five"></i>Servicio abierto, todavía sin comandas.</div>
                        @endif

                        @if ($service->status === 'en_cuenta' && auth()->user()?->canAny(['cobrar mesas', 'reimprimir tickets']))
                            @if ($activeSplit && $serviceTotal > 0.009 && auth()->user()?->can('cobrar mesas'))
                                <section class="pos-table-split-list pos-table-split-list--primary" aria-label="Subcuentas pendientes">
                                    <div class="pos-table-split-list__title">
                                        <span><i class="bx bx-git-branch"></i> Cobrar cuenta dividida</span>
                                        <small>{{ $pendingSplitAccounts->count() }} pendientes</small>
                                    </div>
                                    @foreach ($activeSplit->split_data as $idx => $account)
                                        <div class="pos-split-account {{ ($account['paid'] ?? false) ? 'is-paid' : '' }}">
                                            <div>
                                                <strong>{{ $account['label'] ?? 'Cuenta '.($idx + 1) }}</strong>
                                                <small>{{ count($account['items'] ?? []) }} producto(s) · ${{ number_format((float) ($account['total'] ?? 0), 2) }}</small>
                                            </div>
                                            @if (! ($account['paid'] ?? false))
                                                <button type="button" wire:click="openMesaSplitPayModal({{ $activeSplit->id }}, {{ $idx }})"
                                                    wire:loading.attr="disabled" class="pos-btn pos-btn-primary" {{ $allOrdersReady ? '' : 'disabled' }}>
                                                    <i class="bx bx-dollar-circle"></i><span>{{ $allOrdersReady ? 'Cobrar' : 'Esperando cocina' }}</span>
                                                </button>
                                            @else
                                                <span class="pos-paid-chip"><i class="bx bx-check"></i>Pagada</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </section>
                            @endif

                            <footer class="pos-table-group__footer pos-workspace-service__footer">
                                <div class="pos-table-group__close-copy">
                                    @can('cobrar mesas')
                                        <strong>{{ $serviceTotal <= 0.009 ? 'Servicio sin consumo' : ($allOrdersReady ? 'Cuenta lista para cobrar' : 'Esperando comandas') }}</strong>
                                        <small>{{ $serviceTotal <= 0.009 ? 'Cancela el servicio o reabre la mesa.' : ($allOrdersReady ? 'Elige cobro completo o una subcuenta.' : 'Marca todas las órdenes como listas antes de cobrar.') }}</small>
                                    @else
                                        <strong>Cuenta vigente</strong>
                                        <small>Consulta o imprime la venta global sin modificar el servicio.</small>
                                    @endcan
                                </div>
                                <div class="pos-workspace-service__actions">
                                    @can('reimprimir tickets')
                                        <button type="button" wire:click="openActiveMesaAccountTicket({{ $service->id }})"
                                            wire:loading.attr="disabled" wire:target="openActiveMesaAccountTicket({{ $service->id }})"
                                            class="pos-btn pos-btn-secondary">
                                            <i class="bx bx-printer"></i>
                                            <span wire:loading.remove wire:target="openActiveMesaAccountTicket({{ $service->id }})">Imprimir cuenta global</span>
                                            <span wire:loading wire:target="openActiveMesaAccountTicket({{ $service->id }})">Preparando</span>
                                        </button>
                                    @endcan
                                    @can('cobrar mesas')
                                        @if ($primaryMesa)
                                            <button type="button" wire:click="reopenMesa({{ $primaryMesa->id }})" class="pos-btn pos-btn-secondary">
                                                <i class="bx bx-lock-open-alt"></i><span>Reabrir</span>
                                            </button>
                                            @if ($serviceTotal <= 0.009)
                                                <button type="button" wire:click="requestDiscardEmptyMesaAccount({{ $primaryMesa->id }})" class="pos-btn pos-btn-danger">
                                                    <i class="bx bx-x-circle"></i><span>Cancelar servicio</span>
                                                </button>
                                            @elseif (! $activeSplit)
                                                <button type="button" wire:click="openMesaPayModal({{ $primaryMesa->id }})"
                                                    class="pos-btn pos-btn-primary" {{ $allOrdersReady ? '' : 'disabled' }}>
                                                    <i class="bx bx-dollar-circle"></i><span>Cobrar mesa</span>
                                                </button>
                                            @endif
                                        @endif
                                    @endcan
                                </div>
                            </footer>
                        @elseif ($service->status === 'abierta')
                            <footer class="pos-table-group__footer pos-workspace-service__footer">
                                <div class="pos-table-group__close-copy">
                                    <strong>Servicio en curso</strong>
                                    <small>{{ $allOrdersReady ? 'Todas las comandas están listas.' : 'Continúa el seguimiento desde esta misma tarjeta.' }}</small>
                                </div>
                                @can('cerrar mesas')
                                    <button type="button" wire:click="sendTableServiceToBilling({{ $service->id }})"
                                        wire:loading.attr="disabled" wire:target="sendTableServiceToBilling({{ $service->id }})"
                                        class="pos-btn pos-btn-primary">
                                        <i class="bx bx-receipt"></i><span>Solicitar cuenta</span>
                                    </button>
                                @endcan
                            </footer>
                        @endif
                    </div>
                </article>
            @endforeach

            @foreach ($legacyWorkspaceMesas as $legacyMesa)
                @php
                    $legacyReady = $legacyMesa->orders->isNotEmpty()
                        && $legacyMesa->orders->every(fn ($order) => in_array($order->status, ['lista', 'entregada'], true));
                    $legacySplit = $legacyMesa->active_split ?? $legacyMesa->splits->first();
                    $legacyPendingSplitAccounts = $legacySplit
                        ? collect($legacySplit->split_data)->reject(fn ($account) => (bool) ($account['paid'] ?? false))
                        : collect();
                @endphp
                <article class="pos-table-group pos-workspace-service is-en_cuenta" wire:key="workspace-legacy-{{ $legacyMesa->id }}">
                    <header class="pos-table-group__header">
                        <div class="pos-table-group__identity">
                            <span><i class="bx bx-table"></i></span>
                            <div>
                                <strong>{{ $legacyMesa->display_name }}</strong>
                                <small>{{ $legacyMesa->area?->name ?: 'Área general' }} · Servicio anterior</small>
                            </div>
                        </div>
                        <div class="pos-table-group__total">
                            <strong>${{ number_format((float) $legacyMesa->mesa_total, 2) }}</strong>
                            <small>
                                @if ($legacySplit)
                                    {{ $legacyPendingSplitAccounts->count().' '.($legacyPendingSplitAccounts->count() === 1 ? 'subcuenta pendiente' : 'subcuentas pendientes') }}
                                @else
                                    Cuenta por cobrar
                                @endif
                            </small>
                        </div>
                    </header>
                    @if ($legacySplit && (float) $legacyMesa->mesa_total <= 0.009)
                        <section class="pos-split-zero-state" aria-label="Subcuenta restante sin consumo">
                            <span><i class="bx bx-receipt"></i></span>
                            <div>
                                <strong>Subcuenta restante sin consumo</strong>
                                <p>El seguimiento sigue activo, pero no existe consumo pendiente por cobrar.</p>
                            </div>
                        </section>
                    @elseif ($legacyMesa->orders->isNotEmpty())
                        <div class="pos-table-group__orders">
                            @foreach ($legacyMesa->orders as $legacyOrder)
                                @include('livewire.pos.partials.order-flow-card', [
                                    'flowOrder' => $legacyOrder,
                                    'flowArea' => $legacyMesa->display_name,
                                    'flowIcon' => 'bx-receipt',
                                    'flowSourceLabel' => 'Mesa',
                                    'allowOrderPayment' => false,
                                ])
                            @endforeach
                        </div>
                    @endif
                    <footer class="pos-table-group__footer pos-workspace-service__footer">
                        <div class="pos-table-group__close-copy">
                            <strong>{{ (float) $legacyMesa->mesa_total <= 0.009 ? 'Servicio sin consumo' : 'Cuenta anterior recuperada' }}</strong>
                            <small>{{ $legacyReady ? 'Lista para cobrar.' : 'Las comandas deben estar listas antes del cobro.' }}</small>
                        </div>
                        <div class="pos-workspace-service__actions">
                            <button type="button" wire:click="reopenMesa({{ $legacyMesa->id }})" class="pos-btn pos-btn-secondary">
                                <i class="bx bx-lock-open-alt"></i><span>Reabrir</span>
                            </button>
                            @if ((float) $legacyMesa->mesa_total <= 0.009)
                                <button type="button" wire:click="requestDiscardEmptyMesaAccount({{ $legacyMesa->id }})" class="pos-btn pos-btn-danger">
                                    <i class="bx bx-x-circle"></i><span>Cancelar servicio</span>
                                </button>
                            @else
                                <button type="button" wire:click="openMesaPayModal({{ $legacyMesa->id }})" class="pos-btn pos-btn-primary" {{ $legacyReady ? '' : 'disabled' }}>
                                    <i class="bx bx-dollar-circle"></i><span>Cobrar mesa</span>
                                </button>
                            @endif
                        </div>
                    </footer>
                </article>
            @endforeach

            @if ($this->tableWorkspaceServices->isEmpty() && $legacyWorkspaceMesas->isEmpty())
                <div class="pos-area-empty">
                    <span><i class="bx bx-check-circle"></i></span>
                    <h3>No hay servicios en esta etapa</h3>
                    <p>Prueba otro filtro o actualiza para consultar los cambios más recientes.</p>
                </div>
            @endif
        </div>
    </x-slot:body>
</x-pos.area-panel>
