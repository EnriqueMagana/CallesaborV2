<div class="app-page sales-history-page">
    <header class="app-page-header sales-history-header">
        <div class="app-page-heading">
            <span class="app-page-icon sales-history-icon"><i class="bx bx-history"></i></span>
            <div><div class="app-eyebrow">Auditoría · Ventas</div><h1 class="app-page-title">Historial de ventas</h1><p class="app-page-subtitle">Consulta cada pedido de las cajas cerradas, quién lo atendió, cobró o canceló.</p></div>
        </div>
        <div class="app-page-actions"><a href="{{ route('app.ordenes') }}" class="btn btn-outline-secondary"><i class="bx bx-receipt"></i> Caja abierta</a></div>
    </header>

    <section class="sales-history-summary" aria-label="Resumen histórico">
        <article><span><i class="bx bx-receipt"></i></span><div><small>Pedidos encontrados</small><strong>{{ number_format($this->summary['orders']) }}</strong></div></article>
        @if($this->canViewFinancials)
            <article class="is-success"><span><i class="bx bx-money"></i></span><div><small>Ventas cobradas</small><strong>${{ number_format($this->summary['sales'], 2) }}</strong></div></article>
        @else
            <article><span><i class="bx bx-lock-alt"></i></span><div><small>Importes financieros</small><strong>Restringidos</strong></div></article>
        @endif
        <article class="is-warning"><span><i class="bx bx-time-five"></i></span><div><small>Pendientes</small><strong>{{ number_format($this->summary['open']) }}</strong></div></article>
        <article class="is-danger"><span><i class="bx bx-x-circle"></i></span><div><small>Canceladas</small><strong>{{ number_format($this->summary['cancelled']) }}</strong></div></article>
    </section>

    <section class="app-card sales-history-filters">
        <div class="sales-history-filters__heading"><div><h2 class="app-card-title">Filtrar auditoría</h2><p class="app-card-description">Selecciona una caja para reconstruir todo su turno.</p></div><i class="bx bx-filter-alt"></i></div>
        <div class="row g-3 align-items-end">
            <div class="col-sm-6 col-xl-3"><label class="form-label">Buscar</label><input wire:model.live.debounce.350ms="search" type="search" class="form-control" placeholder="Folio, cliente, usuario o caja"></div>
            <div class="col-sm-6 col-xl-3"><label class="form-label">Caja cerrada</label><select wire:model.live="cashRegisterId" class="form-select"><option value="">Todas las cajas</option>@foreach($this->registers as $register)<option value="{{ $register->id }}">{{ $register->name }} · {{ optional($register->closed_at)->format('d/m/Y') }}</option>@endforeach</select></div>
            <div class="col-sm-6 col-xl-2"><label class="form-label">Estado</label><select wire:model.live="statusFilter" class="form-select"><option value="">Todos</option><option value="pagada">Pagada</option><option value="cancelada">Cancelada</option><option value="pendiente">Pendiente</option><option value="en_preparacion">Preparando</option><option value="lista">Lista</option></select></div>
            <div class="col-sm-6 col-xl-2"><label class="form-label">Área</label><select wire:model.live="typeFilter" class="form-select"><option value="">Todas</option><option value="ventanilla">Ventanilla</option><option value="pick_up">Pick-up</option><option value="mesa">Mesas</option><option value="delivery">Delivery</option></select></div>
            <div class="col-sm-6 col-xl-1"><label class="form-label">Desde</label><input wire:model.live="dateFrom" type="date" class="form-control"></div>
            <div class="col-sm-6 col-xl-1"><button type="button" wire:click="clearFilters" class="btn btn-outline-secondary w-100" title="Limpiar filtros"><i class="bx bx-reset"></i></button></div>
        </div>
    </section>

    <section class="app-card sales-history-table-card">
        <div class="app-card-header"><div><h2 class="app-card-title">Registro detallado</h2><p class="app-card-description">Cada fila conserva el folio, caja, responsables, horarios, pagos y motivo de cancelación.</p></div><span class="app-count-pill">{{ $this->orders->total() }} registros</span></div>
        @forelse($this->orders as $order)
            @php $statusClass = match($order->status){'pagada'=>'success','cancelada'=>'danger','lista'=>'success','en_preparacion'=>'info',default=>'warning'}; @endphp
            <article class="sales-history-row {{ $expandedOrderId === $order->id ? 'is-expanded' : '' }}" wire:key="sales-history-{{ $order->id }}">
                <button type="button" class="sales-history-row__main" wire:click="toggleOrder({{ $order->id }})" aria-expanded="{{ $expandedOrderId === $order->id ? 'true' : 'false' }}">
                    <span class="sales-history-row__folio">#{{ $order->display_folio }}<small>ID {{ $order->id }}</small></span>
                    <span class="sales-history-row__identity"><strong>{{ $order->display_name }}</strong><small>{{ $order->type_label }} · {{ $order->created_at->format('d/m/Y H:i') }}</small></span>
                    <span class="sales-history-row__cell"><small>Caja</small><strong>{{ $order->cashRegister?->name ?? '—' }}</strong></span>
                    <span class="sales-history-row__cell"><small>Atendió</small><strong>{{ $order->seller?->name ?? '—' }}</strong></span>
                    @if($this->canViewFinancials)
                        <span class="sales-history-row__amount"><small>Total</small><strong>${{ number_format($order->total, 2) }}</strong></span>
                    @else
                        <span class="sales-history-row__amount"><small>Importe</small><strong>Restringido</strong></span>
                    @endif
                    <span class="app-status app-status--{{ $statusClass }}">{{ $order->status_label }}</span><i class="bx {{ $expandedOrderId === $order->id ? 'bx-chevron-up' : 'bx-chevron-down' }} sales-history-row__chevron"></i>
                </button>
                @if($expandedOrderId === $order->id)
                    <div class="sales-history-row__details">
                        <div><small>Creada</small><strong>{{ $order->created_at->format('d/m/Y H:i:s') }}</strong></div>
                        <div><small>Cobro registrado</small><strong>{{ $order->paid_at?->format('d/m/Y H:i:s') ?? 'Sin cobro' }}</strong></div>
                        <div><small>Teléfono</small><strong>{{ $order->customer_phone ?: '—' }}</strong></div>
                        @if($this->canViewFinancials)
                            <div><small>Métodos de pago</small><strong>{{ $order->payments->map(fn($payment) => ucfirst($payment->method).' $'.number_format($payment->amount, 2))->join(' · ') ?: 'Sin pagos' }}</strong></div>
                        @endif
                        @if($order->status === 'cancelada')<div class="is-danger"><small>Canceló</small><strong>{{ $order->cancelledBy?->name ?? 'Usuario no disponible' }}</strong><small>{{ $order->cancelled_at?->format('d/m/Y H:i:s') }} · {{ $order->cancellation_reason ?: 'Sin motivo registrado' }}</small></div>@endif
                        @if($order->notes)<div class="sales-history-row__note"><small>Notas</small><strong>{{ $order->notes }}</strong></div>@endif
                        @can('ver ordenes')
                            <a href="{{ route('app.ordenes.show', $order) }}" class="btn btn-sm btn-outline-primary"><i class="bx bx-show"></i> Ver orden completa</a>
                        @endcan
                    </div>
                @endif
            </article>
        @empty
            <div class="app-empty-state"><span class="app-empty-icon"><i class="bx bx-history"></i></span><h3>Sin ventas históricas</h3><p>No hay órdenes de cajas cerradas que coincidan con los filtros.</p></div>
        @endforelse
        @if($this->orders->hasPages())<div class="px-4 py-3 border-top">{{ $this->orders->links() }}</div>@endif
    </section>
</div>
