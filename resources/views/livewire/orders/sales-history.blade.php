<div class="app-page sales-audit-page" data-sales-audit-root data-audit-state="{{ $hasSearched ? 'ready' : 'idle' }}">
    <header class="app-page-header sales-audit-hero">
        <div class="app-page-heading">
            <span class="app-page-icon sales-audit-hero__icon" aria-hidden="true"><i class="bx bx-line-chart"></i></span>
            <div>
                <div class="app-eyebrow">Auditoría · Ventas</div>
                <h1 class="app-page-title">Historial de ventas</h1>
                <p class="app-page-subtitle">Analiza pedidos, canales, tiempos, importes, descuentos, productos y clientes sin depender de una caja abierta.</p>
            </div>
        </div>
        <div class="sales-audit-hero__badge"><i class="bx bx-shield-quarter" aria-hidden="true"></i><span><strong>Consulta independiente</strong><small>No requiere turno activo</small></span></div>
    </header>

    <form wire:submit="runAudit" class="app-card sales-audit-filter" aria-labelledby="sales-audit-filter-title">
        <div class="sales-audit-filter__heading">
            <div><span class="sales-audit-section-label">Configura la consulta</span><h2 id="sales-audit-filter-title" class="app-card-title">¿Qué deseas auditar?</h2><p class="app-card-description">Los resultados, indicadores y gráficas se cargarán al aplicar los filtros.</p></div>
            @if($hasSearched)<span class="sales-audit-filter__applied"><i class="bx bx-check" aria-hidden="true"></i> Filtros aplicados</span>@endif
        </div>

        @if($errors->any())
            <div class="sales-audit-errors" role="alert"><i class="bx bx-error-circle" aria-hidden="true"></i><div><strong>Revisa los filtros</strong><span>{{ $errors->first() }}</span></div></div>
        @endif

        <div class="sales-audit-filter__grid">
            <div class="sales-audit-field sales-audit-field--wide">
                <label for="sales-audit-search">Buscar pedido o cliente</label>
                <div class="sales-audit-input-icon"><i class="bx bx-search" aria-hidden="true"></i><input id="sales-audit-search" wire:model="search" type="search" class="form-control" placeholder="Folio, cliente, teléfono, mesa o usuario"></div>
            </div>
            <div class="sales-audit-field">
                <label for="sales-audit-period">Periodo</label>
                <select id="sales-audit-period" wire:model.live="datePreset" class="form-select">
                    <option value="today">Hoy</option><option value="last_7_days">Últimos 7 días</option><option value="last_30_days">Últimos 30 días</option><option value="last_90_days">Últimos 90 días</option><option value="custom">Rango personalizado</option><option value="all">Todo el historial</option>
                </select>
            </div>
            <div class="sales-audit-field">
                <label for="sales-audit-channel">Canal</label>
                <select id="sales-audit-channel" wire:model="typeFilter" class="form-select"><option value="">Todos los canales</option><option value="mesa">Mesa</option><option value="delivery">Delivery</option><option value="ventanilla">Ventanilla</option><option value="pick_up">Pick-up</option></select>
            </div>
            <div class="sales-audit-field">
                <label for="sales-audit-status">Estado</label>
                <select id="sales-audit-status" wire:model="statusFilter" class="form-select"><option value="">Todos los estados</option><option value="accounted">Ventas contabilizadas</option><option value="pagada">Pagadas</option><option value="cancelada">Canceladas</option><option value="pendiente">Pendientes</option><option value="en_preparacion">En preparación</option><option value="lista">Listas</option><option value="en_reparto">En reparto</option><option value="entregada">Entregadas</option></select>
            </div>
            <div class="sales-audit-field">
                <label for="sales-audit-product">Producto</label>
                <select id="sales-audit-product" wire:model="productId" class="form-select"><option value="">Todos los productos</option>@foreach($this->products as $product)<option value="{{ $product->product_id }}">{{ $product->product_name }}</option>@endforeach</select>
            </div>
            @if($this->canViewFinancials)
                <div class="sales-audit-field">
                    <label for="sales-audit-price">Importe total</label>
                    <select id="sales-audit-price" wire:model.live="priceRange" class="form-select"><option value="">Cualquier importe</option><option value="under_200">Menos de &#36;200</option><option value="200_499">&#36;200 a &#36;499.99</option><option value="500_999">&#36;500 a &#36;999.99</option><option value="1000_plus">&#36;1,000 o más</option><option value="custom">Rango personalizado</option></select>
                </div>
            @endif
            <div class="sales-audit-field">
                <label for="sales-audit-register">Caja / turno</label>
                <select id="sales-audit-register" wire:model="cashRegisterId" class="form-select"><option value="">Todas las cajas</option>@foreach($this->registers as $register)<option value="{{ $register->id }}">{{ $register->name }} · {{ $register->is_open ? 'Abierta' : optional($register->closed_at)->format('d/m/Y') }}</option>@endforeach</select>
            </div>
            <div class="sales-audit-field">
                <label for="sales-audit-sort">Ordenar resultados</label>
                <select id="sales-audit-sort" wire:model="sortBy" class="form-select"><option value="newest">Más recientes</option><option value="oldest">Más antiguos</option>@if($this->canViewFinancials)<option value="total_desc">Mayor venta</option><option value="total_asc">Menor venta</option><option value="discount_desc">Mayor descuento</option>@endif<option value="duration_desc">Mayor tiempo de atención</option></select>
            </div>
            <div class="sales-audit-field">
                <label for="sales-audit-page-size">Filas por página</label>
                <select id="sales-audit-page-size" wire:model="perPage" class="form-select"><option value="10">10 registros</option><option value="20">20 registros</option><option value="50">50 registros</option></select>
            </div>
        </div>

        @if($datePreset === 'custom')
            <div class="sales-audit-custom-range" wire:key="custom-date-range">
                <div class="sales-audit-field"><label for="sales-audit-from">Desde</label><input id="sales-audit-from" wire:model="dateFrom" type="date" class="form-control">@error('dateFrom')<span class="sales-audit-field__error">{{ $message }}</span>@enderror</div>
                <div class="sales-audit-field"><label for="sales-audit-to">Hasta</label><input id="sales-audit-to" wire:model="dateTo" type="date" class="form-control">@error('dateTo')<span class="sales-audit-field__error">{{ $message }}</span>@enderror</div>
            </div>
        @endif
        @if($this->canViewFinancials && $priceRange === 'custom')
            <div class="sales-audit-custom-range" wire:key="custom-price-range">
                <div class="sales-audit-field"><label for="sales-audit-minimum">Importe mínimo</label><div class="sales-audit-money-input"><span>&#36;</span><input id="sales-audit-minimum" wire:model="minimumTotal" type="number" min="0" step="0.01" class="form-control" inputmode="decimal"></div>@error('minimumTotal')<span class="sales-audit-field__error">{{ $message }}</span>@enderror</div>
                <div class="sales-audit-field"><label for="sales-audit-maximum">Importe máximo</label><div class="sales-audit-money-input"><span>&#36;</span><input id="sales-audit-maximum" wire:model="maximumTotal" type="number" min="0" step="0.01" class="form-control" inputmode="decimal"></div>@error('maximumTotal')<span class="sales-audit-field__error">{{ $message }}</span>@enderror</div>
            </div>
        @endif
        <div class="sales-audit-filter__actions">
            <button type="submit" class="btn btn-primary sales-audit-primary-action" wire:loading.attr="disabled" wire:target="runAudit"><span wire:loading.remove wire:target="runAudit"><i class="bx bx-search-alt-2" aria-hidden="true"></i> Generar auditoría</span><span wire:loading wire:target="runAudit"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Analizando ventas…</span></button>
            @if($hasSearched)<button type="button" wire:click="clearAudit" class="btn btn-outline-secondary"><i class="bx bx-reset" aria-hidden="true"></i> Nueva consulta</button>@endif
        </div>
    </form>

    @if(!$hasSearched)
        <section class="app-card sales-audit-welcome" aria-labelledby="sales-audit-welcome-title">
            <span class="sales-audit-welcome__icon" aria-hidden="true"><i class="bx bx-search-alt"></i></span>
            <div><span class="sales-audit-section-label">Consulta bajo demanda</span><h2 id="sales-audit-welcome-title">El historial está listo para auditar</h2><p>Configura los filtros y selecciona <strong>Generar auditoría</strong>. No se muestran órdenes automáticamente para que cada revisión empiece con un alcance claro.</p></div>
            <ul aria-label="Datos disponibles"><li><i class="bx bx-check-circle" aria-hidden="true"></i> Mesa, delivery, ventanilla y pick-up</li><li><i class="bx bx-check-circle" aria-hidden="true"></i> Tiempos, importes y descuentos</li><li><i class="bx bx-check-circle" aria-hidden="true"></i> Productos y mejores clientes</li></ul>
        </section>
    @else
        <div wire:loading.flex wire:target="runAudit,clearAudit,gotoPage,nextPage,previousPage" class="sales-audit-loading" role="status"><span class="spinner-border" aria-hidden="true"></span><span>Actualizando auditoría…</span></div>
        <section class="sales-audit-kpis" aria-label="Indicadores de la auditoría">
            <article><span class="sales-audit-kpi__icon"><i class="bx bx-receipt" aria-hidden="true"></i></span><div><small>Órdenes encontradas</small><strong>{{ number_format($this->summary['orders']) }}</strong><span>{{ number_format($this->summary['paid_orders']) }} contabilizadas</span></div></article>
            @if($this->canViewFinancials)
                <article class="is-success"><span class="sales-audit-kpi__icon"><i class="bx bx-dollar-circle" aria-hidden="true"></i></span><div><small>Ventas netas</small><strong>&#36;{{ number_format($this->summary['sales'], 2) }}</strong><span>Solo ventas contabilizadas</span></div></article>
                <article><span class="sales-audit-kpi__icon"><i class="bx bx-purchase-tag" aria-hidden="true"></i></span><div><small>Descuentos</small><strong>&#36;{{ number_format($this->summary['discounts'], 2) }}</strong><span>Promociones + descuentos</span></div></article>
                <article><span class="sales-audit-kpi__icon"><i class="bx bx-trending-up" aria-hidden="true"></i></span><div><small>Ticket promedio</small><strong>&#36;{{ number_format($this->summary['average_ticket'], 2) }}</strong><span>Por venta contabilizada</span></div></article>
            @else
                <article><span class="sales-audit-kpi__icon"><i class="bx bx-lock-alt" aria-hidden="true"></i></span><div><small>Información financiera</small><strong>Restringida</strong><span>Requiere permiso financiero</span></div></article>
            @endif
            <article class="is-warning"><span class="sales-audit-kpi__icon"><i class="bx bx-time-five" aria-hidden="true"></i></span><div><small>Tiempo promedio</small><strong>{{ $this->summary['average_minutes'] !== null ? $this->summary['average_minutes'].' min' : 'Sin dato' }}</strong><span>Creación a cierre o cobro</span></div></article>
            <article class="is-danger"><span class="sales-audit-kpi__icon"><i class="bx bx-x-circle" aria-hidden="true"></i></span><div><small>Cancelaciones</small><strong>{{ number_format($this->summary['cancelled']) }}</strong><span>Dentro del alcance</span></div></article>
        </section>

        @if($this->canViewFinancials)
            <aside class="sales-audit-cost-note" role="note"><i class="bx bx-info-circle" aria-hidden="true"></i><div><strong>Costo histórico aún no disponible</strong><span>Las partidas conservan precio y descuentos, pero no el costo unitario vigente al momento de la venta. Mostrar un costo actual alteraría la auditoría; por eso no se estima.</span></div></aside>
        @endif

        @php
            $auditChartData = [
                'canViewFinancials' => $this->canViewFinancials,
                'trend' => $this->analytics['trend'],
                'products' => $this->analytics['products'],
                'customers' => $this->analytics['customers'],
            ];
            $auditChartKey = md5(json_encode($auditChartData));
        @endphp
        <script type="application/json" data-sales-audit-data wire:key="sales-audit-data-{{ $auditChartKey }}">{!! json_encode($auditChartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
        @include('livewire.orders.partials.sales-audit-charts')
        @include('livewire.orders.partials.sales-audit-results')
    @endif
</div>
