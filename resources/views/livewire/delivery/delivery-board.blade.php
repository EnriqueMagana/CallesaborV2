@php
    $orders = $this->orders;
    $canManageAll = $this->canManageAll();
    $canTakeOrders = $this->canTakeOrders();
    $canCompleteOrders = $this->canCompleteOrders();
    $available = $orders->filter(fn($order) => !$order->deliveryAssignment && in_array($order->status, ['lista', 'pagada'], true));
    $upcoming = $orders->filter(fn($order) => !$order->deliveryAssignment && in_array($order->status, ['pendiente', 'en_preparacion'], true));
    $assigned = $orders->filter(fn($order) => $order->deliveryAssignment?->status === 'asignado')
        ->when(!$canManageAll, fn($items) => $items->where('deliveryAssignment.driver_id', auth()->id()));
    $delivered = $orders->filter(fn($order) => $order->deliveryAssignment?->status === 'entregado')
        ->when(!$canManageAll, fn($items) => $items->where('deliveryAssignment.driver_id', auth()->id()))
        ->sortByDesc(fn($order) => $order->deliveryAssignment->delivered_at);
    $searchValue = fn($order) => str(implode(' ', [
        $order->display_folio,
        $order->display_name,
        $order->customer_phone,
        $order->customer_address,
        $order->customer_references,
        $order->delivery_method_label,
    ]))->ascii()->lower()->squish()->toString();
    $searchIndex = [
        'available' => $available->map($searchValue)->values()->all(),
        'assigned' => $assigned->map($searchValue)->values()->all(),
        'delivered' => $delivered->map($searchValue)->values()->all(),
    ];
@endphp

<main id="delivery-content" class="delivery-page" tabindex="-1" x-data="{
    tab: $wire.entangle('tab', false),
    query: '',
    lastTriggerId: '',
    toasts: [],
    searchIndex: @js($searchIndex),
    normalize(value) { return (value || '').toString().normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim(); },
    matches(value) { return this.normalize(value).includes(this.normalize(this.query)); },
    filteredCount(tab) { return (this.searchIndex[tab] || []).filter(value => this.matches(value)).length; },
    changeTab(nextTab) { this.tab = nextTab; this.$nextTick(() => this.$refs[nextTab + 'Panel']?.focus()); },
    notify(detail) {
        const toast = { id: Date.now(), type: detail.type || 'info', message: detail.message };
        this.toasts.push(toast);
        setTimeout(() => this.toasts = this.toasts.filter(item => item.id !== toast.id), 3800);
    }
}" x-on:notify.window="notify($event.detail)" x-on:delivery-detail-closed.window="$nextTick(() => document.getElementById(lastTriggerId)?.focus())">
    <a href="#delivery-orders" class="delivery-skip-link">Saltar a los pedidos</a>

    <header class="delivery-hero">
        <div class="delivery-hero__copy">
            <span class="delivery-eyebrow"><i class="bx bx-cycling" aria-hidden="true"></i> Entregas del turno actual</span>
            <h1>Control de delivery</h1>
            <p>Todo lo necesario para recoger, ubicar y completar cada entrega sin perder de vista la dirección.</p>
            <div class="delivery-hero__flow" aria-label="Flujo operativo">
                <span><i class="bx bx-package"></i> Pedido listo</span><i class="bx bx-chevron-right"></i>
                <span><i class="bx bx-hand"></i> Tomar entrega</span><i class="bx bx-chevron-right"></i>
                <span><i class="bx bx-check-double"></i> Confirmar entrega</span>
            </div>
        </div>
        <div class="delivery-hero__actions">
            @if($this->activeRegister)
                <span class="delivery-shift"><i class="bx bx-radio-circle-marked" aria-hidden="true"></i><span><small>Turno activo</small><strong>{{ $this->activeRegister->name }}</strong></span></span>
            @endif
            <button type="button" class="delivery-btn delivery-btn--refresh" wire:click="refreshBoard" wire:loading.attr="disabled" wire:target="refreshBoard" aria-describedby="delivery-refresh-help">
                <span wire:loading.remove wire:target="refreshBoard"><i class="bx bx-refresh" aria-hidden="true"></i> Actualizar pedidos</span>
                <span wire:loading wire:target="refreshBoard"><i class="bx bx-loader-alt bx-spin" aria-hidden="true"></i> Consultando…</span>
            </button>
        </div>
    </header>

    @if(!$this->activeRegister)
        <section class="delivery-empty delivery-empty--shift" aria-labelledby="delivery-no-shift-title">
            <span><i class="bx bx-lock-alt" aria-hidden="true"></i></span>
            <h2 id="delivery-no-shift-title">Aún no hay una caja abierta</h2>
            <p>Las entregas aparecerán aquí cuando comience el siguiente turno. No necesitas recargar toda la página.</p>
            <button type="button" class="delivery-btn delivery-btn--primary" wire:click="refreshBoard" wire:loading.attr="disabled" wire:target="refreshBoard"><i class="bx bx-refresh"></i> Comprobar nuevamente</button>
        </section>
    @else
        <section class="delivery-kpis" aria-label="Resumen del turno">
            <article><span class="is-ready"><i class="bx bx-package" aria-hidden="true"></i></span><div><small>Listos para tomar</small><strong>{{ $available->count() }}</strong><em>Disponibles ahora</em></div></article>
            <article><span class="is-route"><i class="bx bx-cycling" aria-hidden="true"></i></span><div><small>{{ $canManageAll ? 'Equipo en ruta' : 'Mis entregas' }}</small><strong>{{ $assigned->count() }}</strong><em>Asignados</em></div></article>
            <article><span class="is-waiting"><i class="bx bx-bowl-hot" aria-hidden="true"></i></span><div><small>En preparación</small><strong>{{ $upcoming->count() }}</strong><em>Próximamente</em></div></article>
            <article><span class="is-delivered"><i class="bx bx-check-shield" aria-hidden="true"></i></span><div><small>Entregados</small><strong>{{ $delivered->count() }}</strong><em>En este turno</em></div></article>
        </section>

        <section id="delivery-orders" class="delivery-workspace" aria-labelledby="delivery-orders-title" wire:loading.attr="aria-busy" wire:target="refreshBoard">
            <header class="delivery-workspace__heading">
                <div><span class="delivery-workspace__icon"><i class="bx bx-map-alt" aria-hidden="true"></i></span><div><h2 id="delivery-orders-title">Pedidos de delivery</h2><p>La búsqueda y las pestañas funcionan sin consultar nuevamente al servidor.</p></div></div>
                <span class="delivery-local-badge"><i class="bx bx-bolt-circle"></i> Filtro inmediato</span>
            </header>

            <div class="delivery-toolbar">
                <nav class="delivery-tabs" role="tablist" aria-label="Estados de delivery">
                    <button id="delivery-tab-available" type="button" role="tab" aria-controls="delivery-panel-available" x-on:click="changeTab('available')" x-bind:class="tab === 'available' && 'is-active'" x-bind:aria-selected="tab === 'available'"><i class="bx bx-package"></i><span>Disponibles</span><b>{{ $available->count() }}</b></button>
                    <button id="delivery-tab-assigned" type="button" role="tab" aria-controls="delivery-panel-assigned" x-on:click="changeTab('assigned')" x-bind:class="tab === 'assigned' && 'is-active'" x-bind:aria-selected="tab === 'assigned'"><i class="bx bx-cycling"></i><span>{{ $canManageAll ? 'En ruta' : 'Mis entregas' }}</span><b>{{ $assigned->count() }}</b></button>
                    <button id="delivery-tab-delivered" type="button" role="tab" aria-controls="delivery-panel-delivered" x-on:click="changeTab('delivered')" x-bind:class="tab === 'delivered' && 'is-active'" x-bind:aria-selected="tab === 'delivered'"><i class="bx bx-check-double"></i><span>Entregados</span><b>{{ $delivered->count() }}</b></button>
                </nav>
                <label class="delivery-search" for="delivery-search-input">
                    <span>Buscar entregas</span>
                    <div><i class="bx bx-search" aria-hidden="true"></i><input id="delivery-search-input" x-ref="searchInput" type="search" x-model.debounce.120ms="query" placeholder="Folio, cliente, teléfono o dirección" autocomplete="off"><button type="button" x-show="query" x-cloak x-on:click="query = ''; $refs.searchInput.focus()" aria-label="Limpiar búsqueda"><i class="bx bx-x" aria-hidden="true"></i></button></div>
                    <small>Filtra localmente; escribir aquí no genera peticiones.</small>
                </label>
            </div>

            @error('delivery')<div class="delivery-alert" role="alert"><i class="bx bx-error-circle" aria-hidden="true"></i><span>{{ $message }}</span><button type="button" wire:click="dismissDeliveryError" aria-label="Cerrar aviso"><i class="bx bx-x" aria-hidden="true"></i></button></div>@enderror

            <div class="delivery-loading" wire:loading.grid wire:target="refreshBoard" role="status" aria-live="polite" aria-label="Actualizando pedidos de delivery">
                <span class="visually-hidden">Actualizando pedidos de delivery</span>
                @foreach(range(1, 3) as $skeleton)
                    <article class="delivery-skeleton-card" aria-hidden="true">
                        <header class="delivery-skeleton-card__header">
                            <div><span class="delivery-skeleton-line is-folio"></span><span class="delivery-skeleton-line is-time"></span></div>
                            <span class="delivery-skeleton-line is-status"></span>
                        </header>
                        <div class="delivery-skeleton-card__address">
                            <span class="delivery-skeleton-block is-icon"></span>
                            <div><span class="delivery-skeleton-line is-label"></span><span class="delivery-skeleton-line is-address"></span><span class="delivery-skeleton-line is-reference"></span></div>
                        </div>
                        <div class="delivery-skeleton-card__meta">
                            @foreach(range(1, 4) as $meta)
                                <div><span class="delivery-skeleton-line is-label"></span><span class="delivery-skeleton-line is-value"></span></div>
                            @endforeach
                        </div>
                        <footer class="delivery-skeleton-card__actions"><span class="delivery-skeleton-block is-button"></span><span class="delivery-skeleton-block is-button"></span></footer>
                    </article>
                @endforeach
            </div>

            @foreach([
                'available' => [$available, 'No hay pedidos listos para tomar', 'Cuando cocina termine un pedido aparecerá en esta sección.', 'bx-package'],
                'assigned' => [$assigned, 'No hay entregas en ruta', 'Los pedidos que tomes aparecerán aquí hasta que confirmes la entrega.', 'bx-cycling'],
                'delivered' => [$delivered, 'Aún no hay entregas completadas', 'Las entregas confirmadas durante este turno se conservarán aquí.', 'bx-check-shield'],
            ] as $panel => [$panelOrders, $emptyTitle, $emptyCopy, $emptyIcon])
                <div id="delivery-panel-{{ $panel }}" class="delivery-tab-panel" role="tabpanel" tabindex="-1" aria-labelledby="delivery-tab-{{ $panel }}" x-ref="{{ $panel }}Panel" x-show="tab === '{{ $panel }}'" x-cloak wire:loading.remove wire:target="refreshBoard">
                    <div class="delivery-grid">
                        @foreach($panelOrders as $order)
                            <x-delivery.order-card :order="$order" :takeable="$panel === 'available'" :show-driver="$panel !== 'available'" :can-take="$canTakeOrders" :can-complete="$canCompleteOrders" :can-manage-all="$canManageAll" />
                        @endforeach
                    </div>

                    @if($panelOrders->isEmpty())
                        <section class="delivery-empty delivery-empty--list"><span><i class="bx {{ $emptyIcon }}"></i></span><h3>{{ $emptyTitle }}</h3><p>{{ $emptyCopy }}</p></section>
                    @else
                        <section class="delivery-empty delivery-empty--list delivery-empty--search" x-show="query && filteredCount('{{ $panel }}') === 0" x-cloak><span><i class="bx bx-search-alt"></i></span><h3>No encontramos coincidencias</h3><p>Prueba con otro folio, nombre, teléfono o parte de la dirección.</p><button type="button" class="delivery-btn delivery-btn--secondary" x-on:click="query = ''">Limpiar búsqueda</button></section>
                    @endif
                </div>
            @endforeach

            @if($upcoming->isNotEmpty())
                <section class="delivery-upcoming" x-show="tab === 'available' && !query" x-cloak>
                    <div class="delivery-section-heading"><div><span><i class="bx bx-bowl-hot"></i></span><div><h2>Próximos pedidos</h2><p>Puedes consultar el detalle, pero se asignan únicamente cuando estén listos.</p></div></div><b>{{ $upcoming->count() }}</b></div>
                    <div class="delivery-upcoming__list">
                        @foreach($upcoming as $order)
                            <button id="delivery-upcoming-{{ $order->id }}" type="button" x-on:click="lastTriggerId = 'delivery-upcoming-{{ $order->id }}'" wire:click="openOrder({{ $order->id }})"><span><strong>#{{ $order->display_folio }} · {{ $order->display_name }}</strong><small>{{ $order->customer_address }}</small></span><x-delivery.status-pill :status="$order->status" /><i class="bx bx-chevron-right"></i></button>
                        @endforeach
                    </div>
                </section>
            @endif

            <p id="delivery-refresh-help" class="delivery-manual-note" aria-live="polite"><i class="bx bx-pointer"></i><span>Sin actualización automática. Usa <strong>Actualizar pedidos</strong> cuando quieras consultar cambios.@if($lastCheckedAt) Última consulta: {{ $lastCheckedAt }}.@endif</span></p>
        </section>
    @endif

    <div class="delivery-detail-loading" wire:loading.flex wire:target="openOrder" role="status" aria-live="assertive"><div><span class="delivery-detail-loading__icon"></span><b></b><i></i><i></i><i></i><small>Abriendo detalle del pedido…</small></div></div>

    @if($this->selectedOrder)
        <x-delivery.order-detail :order="$this->selectedOrder" :can-complete="$canCompleteOrders" :can-manage-all="$canManageAll" />
    @endif

    @if($confirmingDeliveryOrderId)
        <div class="delivery-confirm-layer" role="presentation" x-data x-on:keydown.escape.window="$wire.cancelDeliveryConfirmation()" x-init="$nextTick(() => $refs.cancelDelivery.focus())">
            <button type="button" class="delivery-modal-layer__backdrop" wire:click="cancelDeliveryConfirmation" aria-label="Cancelar confirmación"></button>
            <section class="delivery-confirm" role="alertdialog" aria-modal="true" aria-labelledby="delivery-confirm-title" aria-describedby="delivery-confirm-copy">
                <span class="delivery-confirm__icon"><i class="bx bx-check-double"></i></span><span class="delivery-confirm__eyebrow">Confirmación final</span>
                <h2 id="delivery-confirm-title">¿El cliente ya recibió el pedido?</h2>
                <p id="delivery-confirm-copy">Esta acción registra la hora de entrega y mueve el pedido al historial del turno.</p>
                <div><button x-ref="cancelDelivery" type="button" class="delivery-btn delivery-btn--secondary" wire:click="cancelDeliveryConfirmation">Todavía no</button><button type="button" class="delivery-btn delivery-btn--success" wire:click="markDelivered" wire:loading.attr="disabled" wire:target="markDelivered"><span wire:loading.remove wire:target="markDelivered"><i class="bx bx-check-double"></i> Sí, fue entregado</span><span wire:loading wire:target="markDelivered"><i class="bx bx-loader-alt bx-spin"></i> Registrando…</span></button></div>
            </section>
        </div>
    @endif

    <div class="delivery-toasts" aria-live="polite" aria-atomic="true">
        <template x-for="toast in toasts" :key="toast.id"><div class="delivery-toast" :class="'is-' + toast.type" role="status"><i class="bx bx-check-circle"></i><span x-text="toast.message"></span><button type="button" x-on:click="toasts = toasts.filter(item => item.id !== toast.id)" aria-label="Cerrar aviso"><i class="bx bx-x"></i></button></div></template>
    </div>
</main>
