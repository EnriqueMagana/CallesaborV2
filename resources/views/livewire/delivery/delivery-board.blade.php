@php
    $orders = $this->orders;
    $canManageAll = $this->canManageAll();
    $canTakeOrders = $this->canTakeOrders();
    $canCompleteOrders = $this->canCompleteOrders();
    $canReassign = $this->canReassignOrders();
    $canSeeTeamDeliveries = $canManageAll || $canReassign;
    $drivers = $canReassign ? $this->drivers : collect();
    $bank = $orders->filter(
        fn($order) => !$order->deliveryAssignment &&
            in_array($order->status, ['pendiente', 'en_preparacion', 'lista', 'pagada'], true),
    );
    $assigned = $orders
        ->filter(fn($order) => $order->deliveryAssignment?->status === 'asignado')
        ->when(!$canSeeTeamDeliveries, fn($items) => $items->where('deliveryAssignment.driver_id', auth()->id()));
    $driverOrders = $canReassign
        ? $orders->filter(fn($order) => $order->deliveryAssignment?->status === 'asignado')
            ->groupBy('deliveryAssignment.driver_id')
        : collect();
    $reassigningOrder = $canReassign ? $this->reassigningOrder : null;
    $currentDriver = $reassigningOrder?->deliveryAssignment?->driver;
    $targetDrivers = $reassigningOrder ? $drivers->where('id', '!=', $currentDriver?->id) : collect();
    $delivered = $orders
        ->filter(fn($order) => $order->deliveryAssignment?->status === 'entregado')
        ->when(!$canManageAll, fn($items) => $items->where('deliveryAssignment.driver_id', auth()->id()))
        ->sortByDesc(fn($order) => $order->deliveryAssignment->delivered_at);
    $paymentMethodMeta = [
        'efectivo' => ['Efectivo', 'bx-money'],
        'transferencia' => ['Transferencia', 'bx-transfer'],
        'tarjeta' => ['Tarjeta', 'bx-credit-card'],
    ];
    $reconciliationRows = $delivered->map(function ($order) {
        $payments = $order->payments
            ->groupBy(fn($payment) => $payment->method === 'contra_entrega' ? 'efectivo' : $payment->method)
            ->map(fn($items) => (float) $items->sum('amount'));

        return [
            'order' => $order,
            'payments' => $payments,
            'paid_total' => (float) $payments->sum(),
        ];
    });
    $paymentTotals = collect($paymentMethodMeta)->mapWithKeys(
        fn($meta, $method) => [
            $method => (float) $reconciliationRows->sum(fn($row) => $row['payments']->get($method, 0)),
        ],
    );
    $reconciliationTotal = (float) $paymentTotals->sum();
    $searchValue = fn($order) => str(
        implode(' ', [
            $order->display_folio,
            $order->display_name,
            $order->customer_phone,
            $order->customer_address,
            $order->customer_references,
            $order->delivery_method_label,
            $order->origin_label,
        ]),
    )
        ->ascii()
        ->lower()
        ->squish()
        ->toString();
    $searchIndex = [
        'available' => $bank->map($searchValue)->values()->all(),
        'assigned' => $assigned->map($searchValue)->values()->all(),
        'delivered' => $delivered->map($searchValue)->values()->all(),
    ];
@endphp

<main id="delivery-content" class="delivery-page delivery-bank" tabindex="-1" x-data="{
    tab: $wire.entangle('tab', false),
    highlightOrderId: @js($highlightOrderId),
    query: '',
    toasts: [],
    searchIndex: @js($searchIndex),
    normalize(value) { return (value || '').toString().normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim(); },
    matches(value) { return this.normalize(value).includes(this.normalize(this.query)); },
    filteredCount(tab) { return (this.searchIndex[tab] || []).filter(value => this.matches(value)).length; },
    changeTab(nextTab) {
        this.tab = nextTab;
        this.$nextTick(() => this.$refs[nextTab + 'Panel']?.focus());
    },
    notify(detail) {
        const toast = { id: Date.now(), type: detail.type || 'info', message: detail.message };
        this.toasts.push(toast);
        setTimeout(() => this.toasts = this.toasts.filter(item => item.id !== toast.id), 3800);
    },
    focusNotifiedOrder() {
        if (!this.highlightOrderId) return;
        this.$nextTick(() => {
            const card = document.getElementById('delivery-order-' + this.highlightOrderId);
            card?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            card?.focus({ preventScroll: true });
        });
    }
}"
    x-init="focusNotifiedOrder()" x-on:notify.window="notify($event.detail)">
    <a href="#delivery-orders" class="delivery-skip-link">Saltar al banco de pedidos</a>

    <header class="delivery-bank__topbar">
        <div>
            <span class="delivery-eyebrow"><i class="bx bx-cycling" aria-hidden="true"></i> Operación de delivery</span>
            <h1>Banco de pedidos</h1>
            <p>Elige una entrega, asígnatela y actualiza únicamente los momentos que tú controlas.</p>
        </div>
        <div class="delivery-bank__topbar-actions">
            @if ($this->activeRegister)
                <span class="delivery-shift">
                    <i class="bx bx-radio-circle-marked" aria-hidden="true"></i>
                    <span><small>Turno activo</small><strong>{{ $this->activeRegister->name }}</strong></span>
                </span>
            @endif
            <button type="button" class="delivery-btn delivery-btn--refresh" wire:click="refreshBoard"
                wire:loading.attr="disabled" wire:target="refreshBoard" aria-describedby="delivery-refresh-help">
                <span wire:loading.remove wire:target="refreshBoard"><i class="bx bx-refresh" aria-hidden="true"></i>
                    Actualizar</span>
                <span wire:loading wire:target="refreshBoard"><i class="bx bx-loader-alt bx-spin"
                        aria-hidden="true"></i> Consultando…</span>
            </button>
        </div>
    </header>

    @if (!$this->activeRegister)
        <section class="delivery-empty delivery-empty--shift" aria-labelledby="delivery-no-shift-title">
            <span><i class="bx bx-lock-alt" aria-hidden="true"></i></span>
            <h2 id="delivery-no-shift-title">No hay una caja abierta</h2>
            <p>El banco de pedidos estará disponible cuando comience el siguiente turno.</p>
            <button type="button" class="delivery-btn delivery-btn--primary" wire:click="refreshBoard"
                wire:loading.attr="disabled" wire:target="refreshBoard"><i class="bx bx-refresh"></i> Comprobar
                nuevamente</button>
        </section>
    @else
        <section id="delivery-orders" class="delivery-bank__workspace" aria-labelledby="delivery-orders-title"
            wire:loading.attr="aria-busy" wire:target="refreshBoard">
            <header class="delivery-bank__workspace-header">
                <div>
                    <span class="delivery-bank__workspace-icon"><i class="bx bx-package" aria-hidden="true"></i></span>
                    <div>
                        <h2 id="delivery-orders-title">Pedidos del turno</h2>
                        <p>Las tarjetas se expanden sin salir del banco.</p>
                    </div>
                </div>
                <label class="delivery-search" for="delivery-search-input"
                    x-show="!['reconciliation', 'drivers'].includes(tab)" x-cloak>
                    <span>Buscar pedido</span>
                    <div>
                        <i class="bx bx-search" aria-hidden="true"></i>
                        <input id="delivery-search-input" x-ref="searchInput" type="search"
                            x-model.debounce.120ms="query" placeholder="Folio, cliente o dirección" autocomplete="off">
                        <button type="button" x-show="query" x-cloak x-on:click="query = ''; $refs.searchInput.focus()"
                            aria-label="Limpiar búsqueda">
                            <i class="bx bx-x" aria-hidden="true"></i>
                        </button>
                    </div>
                </label>
            </header>

            <nav class="delivery-bank__tabs" role="tablist" aria-label="Estados del banco de pedidos">
                <button id="delivery-tab-available" type="button" role="tab"
                    aria-controls="delivery-panel-available" x-on:click="changeTab('available')"
                    x-bind:class="tab === 'available' && 'is-active'" x-bind:aria-selected="tab === 'available'">
                    <i class="bx bx-store-alt" aria-hidden="true"></i><span>Banco</span><b>{{ $bank->count() }}</b>
                </button>
                <button id="delivery-tab-assigned" type="button" role="tab" aria-controls="delivery-panel-assigned"
                    x-on:click="changeTab('assigned')" x-bind:class="tab === 'assigned' && 'is-active'"
                    x-bind:aria-selected="tab === 'assigned'">
                    <i class="bx bx-user-check" aria-hidden="true"></i>
                    <span>{{ $canManageAll ? 'Asignados' : 'Mis pedidos' }}</span><b>{{ $assigned->count() }}</b>
                </button>
                <button id="delivery-tab-delivered" type="button" role="tab"
                    aria-controls="delivery-panel-delivered" x-on:click="changeTab('delivered')"
                    x-bind:class="tab === 'delivered' && 'is-active'" x-bind:aria-selected="tab === 'delivered'">
                    <i class="bx bx-check-double"
                        aria-hidden="true"></i><span>Entregados</span><b>{{ $delivered->count() }}</b>
                </button>
                @if ($canReassign)
                    <button id="delivery-tab-drivers" type="button" role="tab"
                        aria-controls="delivery-panel-drivers" x-on:click="changeTab('drivers')"
                        x-bind:class="tab === 'drivers' && 'is-active'" x-bind:aria-selected="tab === 'drivers'">
                        <i class="bx bx-group" aria-hidden="true"></i>
                        <span>Repartidores</span><b>{{ $drivers->count() }}</b>
                    </button>
                @endif
                <button id="delivery-tab-reconciliation" type="button" role="tab"
                    aria-controls="delivery-panel-reconciliation" x-on:click="changeTab('reconciliation')"
                    x-bind:class="tab === 'reconciliation' && 'is-active'"
                    x-bind:aria-selected="tab === 'reconciliation'">
                    <i class="bx bx-calculator" aria-hidden="true"></i>
                    <span>{{ $canManageAll ? 'Arqueo' : 'Mi arqueo' }}</span><b>{{ $delivered->count() }}</b>
                </button>
            </nav>

            @error('delivery')
                <div class="delivery-alert" role="alert">
                    <i class="bx bx-error-circle" aria-hidden="true"></i><span>{{ $message }}</span>
                    <button type="button" wire:click="dismissDeliveryError" aria-label="Cerrar aviso"><i
                            class="bx bx-x" aria-hidden="true"></i></button>
                </div>
            @enderror

            <div class="delivery-loading" wire:loading.grid wire:target="refreshBoard" role="status"
                aria-live="polite" aria-label="Actualizando banco de pedidos">
                <span class="visually-hidden">Actualizando banco de pedidos</span>
                @foreach (range(1, 3) as $skeleton)
                    <article class="delivery-skeleton-card" aria-hidden="true">
                        <header class="delivery-skeleton-card__header">
                            <div><span class="delivery-skeleton-line is-folio"></span></div>
                            <span class="delivery-skeleton-line is-status"></span>
                        </header>
                        <div class="delivery-skeleton-card__address">
                            <span class="delivery-skeleton-block is-icon"></span>
                            <div><span class="delivery-skeleton-line is-label"></span><span
                                    class="delivery-skeleton-line is-address"></span></div>
                        </div>
                        <footer class="delivery-skeleton-card__actions"><span
                                class="delivery-skeleton-block is-button"></span><span
                                class="delivery-skeleton-block is-button"></span></footer>
                    </article>
                @endforeach
            </div>

            @foreach ([
        'available' => [$bank, 'El banco está libre', 'Los pedidos de kiosco y ventanilla aparecerán aquí desde que sean capturados.', 'bx-package'],
        'assigned' => [$assigned, 'No tienes pedidos asignados', 'Toma una tarjeta del banco para comenzar.', 'bx-user-check'],
        'delivered' => [$delivered, 'Todavía no hay pedidos entregados', 'Las entregas confirmadas durante este turno aparecerán aquí.', 'bx-check-shield'],
    ] as $panel => [$panelOrders, $emptyTitle, $emptyCopy, $emptyIcon])
                <div id="delivery-panel-{{ $panel }}" class="delivery-tab-panel" role="tabpanel"
                    tabindex="-1" aria-labelledby="delivery-tab-{{ $panel }}"
                    x-ref="{{ $panel }}Panel" x-show="tab === '{{ $panel }}'" x-cloak
                    wire:loading.remove wire:target="refreshBoard">
                    <div class="delivery-bank__grid">
                        @foreach ($panelOrders as $order)
                            <x-delivery.order-card :order="$order" :takeable="$panel === 'available'" :show-driver="in_array($panel, ['assigned', 'delivered'], true)"
                                :can-take="$canTakeOrders" :can-complete="$canCompleteOrders" :can-manage-all="$canManageAll"
                                :can-reassign="$canReassign"
                                :highlighted="$highlightOrderId === $order->id" />
                        @endforeach
                    </div>

                    @if ($panelOrders->isEmpty())
                        <section class="delivery-empty delivery-empty--list">
                            <span><i class="bx {{ $emptyIcon }}" aria-hidden="true"></i></span>
                            <h3>{{ $emptyTitle }}</h3>
                            <p>{{ $emptyCopy }}</p>
                        </section>
                    @else
                        <section class="delivery-empty delivery-empty--list delivery-empty--search"
                            x-show="query && filteredCount('{{ $panel }}') === 0" x-cloak>
                            <span><i class="bx bx-search-alt" aria-hidden="true"></i></span>
                            <h3>No encontramos coincidencias</h3>
                            <p>Prueba con otro folio, cliente, teléfono o dirección.</p>
                            <button type="button" class="delivery-btn delivery-btn--secondary"
                                x-on:click="query = ''">Limpiar búsqueda</button>
                        </section>
                    @endif
                </div>
            @endforeach

            @if ($canReassign)
                <div id="delivery-panel-drivers" class="delivery-tab-panel" role="tabpanel" tabindex="-1"
                    aria-labelledby="delivery-tab-drivers" x-ref="driversPanel" x-show="tab === 'drivers'" x-cloak
                    wire:loading.remove wire:target="refreshBoard">
                    <section class="delivery-dispatch" aria-labelledby="delivery-dispatch-title">
                        <header class="delivery-dispatch__header">
                            <span><i class="bx bx-transfer-alt" aria-hidden="true"></i></span>
                            <div>
                                <span>Despacho del turno</span>
                                <h3 id="delivery-dispatch-title">Repartidores y pedidos asignados</h3>
                                <p>Reasigna solo cuando exista una confusi&oacute;n o un cambio operativo. El pedido conserva su estado, productos, total y pagos.</p>
                            </div>
                        </header>

                        <div class="delivery-dispatch__grid">
                            @foreach ($drivers as $driver)
                                @php($activeDriverOrders = $driverOrders->get($driver->id, collect()))
                                <article class="delivery-driver-card">
                                    <header>
                                        <span class="delivery-driver-card__avatar">
                                            @if ($driver->avatar)
                                                <img src="{{ Storage::url($driver->avatar) }}" alt="Foto de {{ $driver->name }}" loading="lazy">
                                            @else
                                                {{ mb_strtoupper(mb_substr($driver->name, 0, 1)) }}
                                            @endif
                                        </span>
                                        <div>
                                            <strong>{{ $driver->name }}</strong>
                                            <small>{{ $activeDriverOrders->count() }} {{ $activeDriverOrders->count() === 1 ? 'pedido activo' : 'pedidos activos' }}</small>
                                        </div>
                                        <span class="delivery-driver-card__status" aria-label="Cuenta activa"><i class="bx bx-check" aria-hidden="true"></i></span>
                                    </header>

                                    <div class="delivery-driver-card__orders">
                                        @forelse ($activeDriverOrders as $order)
                                            <div class="delivery-driver-order">
                                                <div>
                                                    <strong>{{ $order->display_folio }}</strong>
                                                    <span>{{ $order->display_name }}</span>
                                                    <small>{{ $order->status_label }} &middot; ${{ number_format($order->total, 2) }}</small>
                                                </div>
                                                <button type="button" class="delivery-btn delivery-btn--secondary"
                                                    wire:click="openReassign({{ $order->id }})"
                                                    wire:loading.attr="disabled" wire:target="openReassign({{ $order->id }})">
                                                    <i class="bx bx-transfer-alt" aria-hidden="true"></i> Reasignar
                                                </button>
                                            </div>
                                        @empty
                                            <div class="delivery-driver-card__empty">
                                                <i class="bx bx-check-circle" aria-hidden="true"></i>
                                                <span>Sin entregas asignadas</span>
                                            </div>
                                        @endforelse
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        @if ($drivers->isEmpty())
                            <section class="delivery-empty delivery-empty--list">
                                <span><i class="bx bx-user-x" aria-hidden="true"></i></span>
                                <h3>No hay repartidores disponibles</h3>
                                <p>Asigna a un usuario el permiso &ldquo;Entregar delivery&rdquo; para mostrarlo en este panel.</p>
                            </section>
                        @endif
                    </section>
                </div>
            @endif

            <div id="delivery-panel-reconciliation" class="delivery-tab-panel" role="tabpanel" tabindex="-1"
                aria-labelledby="delivery-tab-reconciliation" x-ref="reconciliationPanel"
                x-show="tab === 'reconciliation'" x-cloak wire:loading.remove wire:target="refreshBoard">
                <section class="delivery-reconciliation" aria-labelledby="delivery-reconciliation-title">
                    <header class="delivery-reconciliation__header">
                        <div>
                            <span class="delivery-reconciliation__icon">
                                <i class="bx bx-receipt" aria-hidden="true"></i>
                            </span>
                            <div>
                                <span class="delivery-reconciliation__eyebrow">
                                    {{ $canManageAll ? 'Resumen del equipo' : 'Resumen personal' }}
                                </span>
                                <h3 id="delivery-reconciliation-title">
                                    {{ $canManageAll ? 'Arqueo informativo de delivery' : 'Mi arqueo de entregas' }}
                                </h3>
                                <p>Consulta las notas entregadas y cómo fueron pagadas durante este turno.</p>
                            </div>
                        </div>
                        <span class="delivery-reconciliation__readonly">
                            <i class="bx bx-show" aria-hidden="true"></i> Solo lectura
                        </span>
                    </header>

                    <div class="delivery-reconciliation__metrics" aria-label="Totales del arqueo">
                        <article class="delivery-reconciliation-metric is-notes">
                            <span><i class="bx bx-receipt" aria-hidden="true"></i></span>
                            <div>
                                <small>Notas entregadas</small>
                                <strong>{{ $reconciliationRows->count() }}</strong>
                                <p>Total registrado: ${{ number_format($reconciliationTotal, 2) }}</p>
                            </div>
                        </article>
                        @foreach ($paymentMethodMeta as $method => [$methodLabel, $methodIcon])
                            <article class="delivery-reconciliation-metric is-{{ $method }}">
                                <span><i class="bx {{ $methodIcon }}" aria-hidden="true"></i></span>
                                <div>
                                    <small>{{ $methodLabel }}</small>
                                    <strong>${{ number_format($paymentTotals->get($method, 0), 2) }}</strong>
                                    <p>
                                        {{ $reconciliationRows->filter(fn($row) => $row['payments']->get($method, 0) > 0)->count() }}
                                        {{ $reconciliationRows->filter(fn($row) => $row['payments']->get($method, 0) > 0)->count() === 1 ? 'nota' : 'notas' }}
                                    </p>
                                </div>
                            </article>
                        @endforeach
                    </div>

                    <div class="delivery-reconciliation__notice">
                        <i class="bx bx-info-circle" aria-hidden="true"></i>
                        <p><strong>Contra entrega siempre se contabiliza como efectivo.</strong> El método de pago se
                            define en ventanilla; desde esta vista no puede modificarse.</p>
                    </div>

                    @if ($reconciliationRows->isEmpty())
                        <section class="delivery-empty delivery-empty--list delivery-reconciliation__empty">
                            <span><i class="bx bx-receipt" aria-hidden="true"></i></span>
                            <h3>Aún no hay notas para mostrar</h3>
                            <p>Las entregas que completes durante este turno aparecerán aquí con su forma de pago.</p>
                        </section>
                    @else
                        <div class="delivery-reconciliation__notes">
                            <header>
                                <div>
                                    <h4>Detalle de notas</h4>
                                    <p>{{ $canManageAll ? 'Entregas de todos los repartidores' : 'Tus entregas confirmadas' }}
                                    </p>
                                </div>
                                <strong>${{ number_format($reconciliationTotal, 2) }}</strong>
                            </header>

                            <div class="delivery-reconciliation__table-wrap">
                                <table class="delivery-reconciliation__table">
                                    <thead>
                                        <tr>
                                            <th scope="col">Nota</th>
                                            <th scope="col">Entrega</th>
                                            @if ($canManageAll)
                                                <th scope="col">Repartidor</th>
                                            @endif
                                            <th scope="col">Forma de pago</th>
                                            <th scope="col">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($reconciliationRows as $row)
                                            @php($order = $row['order'])
                                            <tr>
                                                <td data-label="Nota">
                                                    <strong>{{ $order->display_folio }}</strong>
                                                    <small>{{ $order->display_name }}</small>
                                                </td>
                                                <td data-label="Entrega">
                                                    <time
                                                        datetime="{{ optional($order->deliveryAssignment->delivered_at)->toIso8601String() }}">
                                                        {{ optional($order->deliveryAssignment->delivered_at)->format('H:i') ?? 'Sin hora' }}
                                                    </time>
                                                    <small>{{ $order->origin_label }}</small>
                                                </td>
                                                @if ($canManageAll)
                                                    <td data-label="Repartidor">
                                                        <strong>{{ $order->deliveryAssignment->driver?->name ?? 'Sin repartidor' }}</strong>
                                                    </td>
                                                @endif
                                                <td data-label="Forma de pago">
                                                    <div class="delivery-reconciliation__payment-list">
                                                        @forelse ($row['payments'] as $method => $amount)
                                                            @php([$methodLabel, $methodIcon] = $paymentMethodMeta[$method] ?? [str($method)->headline(), 'bx-wallet'])
                                                            <span class="is-{{ $method }}">
                                                                <i class="bx {{ $methodIcon }}"
                                                                    aria-hidden="true"></i>
                                                                {{ $methodLabel }}
                                                                <b>${{ number_format($amount, 2) }}</b>
                                                            </span>
                                                        @empty
                                                            <span class="is-missing"><i class="bx bx-error-circle"
                                                                    aria-hidden="true"></i> Sin pago registrado</span>
                                                        @endforelse
                                                    </div>
                                                </td>
                                                <td data-label="Total" class="delivery-reconciliation__amount">
                                                    <strong>${{ number_format($row['paid_total'], 2) }}</strong>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </section>
            </div>
        </section>
    @endif

    @if ($confirmingDeliveryOrderId)
        <div class="delivery-confirm-layer" role="presentation" x-data
            x-on:keydown.escape.window="$wire.cancelDeliveryConfirmation()" x-init="$nextTick(() => $refs.cancelDelivery.focus())">
            <button type="button" class="delivery-modal-layer__backdrop" wire:click="cancelDeliveryConfirmation"
                aria-label="Cancelar confirmación"></button>
            <section class="delivery-confirm" role="alertdialog" aria-modal="true"
                aria-labelledby="delivery-confirm-title" aria-describedby="delivery-confirm-copy">
                <span class="delivery-confirm__icon"><i class="bx bx-check-double" aria-hidden="true"></i></span>
                <span class="delivery-confirm__eyebrow">Confirmación final</span>
                <h2 id="delivery-confirm-title">¿El cliente recibió el pedido?</h2>
                <p id="delivery-confirm-copy">Se registrará la hora de entrega y el pedido pasará al historial del
                    turno.</p>
                <div>
                    <button x-ref="cancelDelivery" type="button" class="delivery-btn delivery-btn--secondary"
                        wire:click="cancelDeliveryConfirmation">Todavía no</button>
                    <button type="button" class="delivery-btn delivery-btn--success" wire:click="markDelivered"
                        wire:loading.attr="disabled" wire:target="markDelivered">
                        <span wire:loading.remove wire:target="markDelivered"><i class="bx bx-check-double"
                                aria-hidden="true"></i> Sí, fue entregado</span>
                        <span wire:loading wire:target="markDelivered"><i class="bx bx-loader-alt bx-spin"
                                aria-hidden="true"></i> Registrando…</span>
                    </button>
                </div>
            </section>
        </div>
    @endif

    @if ($reassigningOrder)
        <div class="delivery-confirm-layer" role="presentation" x-data
            x-on:keydown.escape.window="$wire.closeReassign()"
            x-init="$nextTick(() => $refs.reassignDriver?.focus())">
            <button type="button" class="delivery-modal-layer__backdrop" wire:click="closeReassign"
                aria-label="Cerrar reasignaciÃ³n"></button>
            <section class="delivery-reassign-modal" role="dialog" aria-modal="true"
                aria-labelledby="delivery-reassign-title" aria-describedby="delivery-reassign-copy">
                <header class="delivery-reassign-modal__header">
                    <span><i class="bx bx-transfer-alt" aria-hidden="true"></i></span>
                    <div>
                        <small>Correcci&oacute;n operativa</small>
                        <h2 id="delivery-reassign-title">Reasignar {{ $reassigningOrder->display_folio }}</h2>
                        <p id="delivery-reassign-copy">El contenido, el total, los pagos y el estado actual no cambiar&aacute;n.</p>
                    </div>
                    <button type="button" wire:click="closeReassign" aria-label="Cerrar">
                        <i class="bx bx-x" aria-hidden="true"></i>
                    </button>
                </header>

                <div class="delivery-reassign-modal__body">
                    <div class="delivery-reassign-route" aria-label="Cambio de repartidor">
                        <div>
                            <small>Repartidor actual</small>
                            <strong>{{ $currentDriver?->name ?? 'Usuario eliminado' }}</strong>
                        </div>
                        <i class="bx bx-right-arrow-alt" aria-hidden="true"></i>
                        <div>
                            <small>Pedido</small>
                            <strong>{{ $reassigningOrder->display_name }}</strong>
                        </div>
                    </div>

                    <label class="delivery-reassign-field">
                        <span>Nuevo repartidor <b aria-hidden="true">*</b></span>
                        <select x-ref="reassignDriver" wire:model="reassignDriverId" @disabled($targetDrivers->isEmpty())>
                            <option value="">Selecciona una persona</option>
                            @foreach ($targetDrivers as $driver)
                                <option value="{{ $driver->id }}">{{ $driver->name }} &middot; {{ $driverOrders->get($driver->id, collect())->count() }} activos</option>
                            @endforeach
                        </select>
                        @error('reassignDriverId')<small class="delivery-field-error">{{ $message }}</small>@enderror
                    </label>

                    <label class="delivery-reassign-field">
                        <span>Motivo de la reasignaci&oacute;n <b aria-hidden="true">*</b></span>
                        <textarea wire:model="reassignReason" rows="3" maxlength="500"
                            placeholder="Ej. El pedido fue tomado por el repartidor equivocado."></textarea>
                        <small>Este motivo formar&aacute; parte del historial de la entrega.</small>
                        @error('reassignReason')<small class="delivery-field-error">{{ $message }}</small>@enderror
                    </label>

                    @if ($targetDrivers->isEmpty())
                        <div class="delivery-alert" role="alert">
                            <i class="bx bx-error-circle" aria-hidden="true"></i>
                            <span>No existe otro usuario activo con permiso para entregar delivery.</span>
                        </div>
                    @endif
                </div>

                <footer class="delivery-reassign-modal__footer">
                    <button type="button" class="delivery-btn delivery-btn--secondary" wire:click="closeReassign">Cancelar</button>
                    <button type="button" class="delivery-btn delivery-btn--primary" wire:click="confirmReassign"
                        wire:loading.attr="disabled" wire:target="confirmReassign" @disabled($targetDrivers->isEmpty())>
                        <span wire:loading.remove wire:target="confirmReassign"><i class="bx bx-transfer-alt" aria-hidden="true"></i> Confirmar reasignaci&oacute;n</span>
                        <span wire:loading wire:target="confirmReassign"><i class="bx bx-loader-alt bx-spin" aria-hidden="true"></i> Reasignando&hellip;</span>
                    </button>
                </footer>
            </section>
        </div>
    @endif

    <div class="delivery-toasts" aria-live="polite" aria-atomic="true">
        <template x-for="toast in toasts" :key="toast.id">
            <div class="delivery-toast" :class="'is-' + toast.type" role="status">
                <i class="bx bx-check-circle" aria-hidden="true"></i><span x-text="toast.message"></span>
                <button type="button" x-on:click="toasts = toasts.filter(item => item.id !== toast.id)"
                    aria-label="Cerrar aviso"><i class="bx bx-x" aria-hidden="true"></i></button>
            </div>
        </template>
    </div>
</main>
