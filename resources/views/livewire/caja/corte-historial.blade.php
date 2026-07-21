<div class="app-page cash-history-page">
    <header class="app-page-header">
        <div class="app-page-heading">
            <span class="app-page-icon cash-history-page__icon"><i class="bx bx-calculator"></i></span>
            <div><div class="app-eyebrow">Caja · Auditoría</div><h1 class="app-page-title">Historial de cortes</h1><p class="app-page-subtitle">Revisa cada cierre, sus diferencias y el resumen financiero del turno.</p></div>
        </div>
        <div class="app-page-actions"><a href="{{ route('app.historial-ventas') }}" class="btn btn-primary"><i class="bx bx-history"></i> Historial de ventas</a><a href="{{ route('app.caja') }}" class="btn btn-outline-secondary"><i class="bx bx-arrow-back"></i> Caja</a></div>
    </header>

    <section class="app-card cash-history-search">
        <div><h2 class="app-card-title">Buscar un cierre</h2><p class="app-card-description">Localiza por folio o nombre de la caja.</p></div>
        <label class="cash-history-search__field"><i class="bx bx-search"></i><span class="visually-hidden">Buscar corte</span><input type="search" wire:model.live.debounce.300ms="search" placeholder="CORTE-0001 o Caja principal"></label>
    </section>

    <section class="cash-history-list app-card">
        <div class="app-card-header"><div><h2 class="app-card-title">Cierres registrados</h2><p class="app-card-description">Selecciona un corte para consultar ventas, gastos y responsables.</p></div><span class="app-count-pill">{{ $this->cuts->total() }} cortes</span></div>
        @forelse($this->cuts as $cut)
            @php $diff=(float)$cut->difference; $cash=(float)$cut->v_efectivo+$cut->m_efectivo+$cut->d_efectivo; $digital=(float)$cut->v_tarjeta+$cut->m_tarjeta+$cut->d_tarjeta+$cut->v_transfer+$cut->m_transfer+$cut->d_transfer; @endphp
            <article class="cash-history-row">
                <div class="cash-history-row__mark"><i class="bx bx-lock-alt"></i></div>
                <div class="cash-history-row__main"><div class="cash-history-row__title"><strong>{{ $cut->folio }}</strong><span class="app-status app-status--neutral">{{ $cut->cashRegister->name }}</span>@if($diff===0.0)<span class="app-status app-status--success">Cuadrado</span>@elseif($diff>0)<span class="app-status app-status--info">Sobrante ${{ number_format(abs($diff),2) }}</span>@else<span class="app-status app-status--danger">Faltante ${{ number_format(abs($diff),2) }}</span>@endif</div><p>{{ $cut->cashRegister->opened_at?->format('d/m/Y H:i') }} – {{ $cut->generated_at?->format('d/m/Y H:i') }} · Cerró <strong>{{ $cut->generator?->name ?? 'Usuario no disponible' }}</strong></p></div>
                <div class="cash-history-row__numbers"><div><small>Efectivo</small><strong>${{ number_format($cash,2) }}</strong></div><div><small>Digital</small><strong>${{ number_format($digital,2) }}</strong></div></div>
                <div class="cash-history-row__actions"><a href="{{ route('app.caja.corte.detalle', $cut) }}" class="btn btn-sm btn-primary"><i class="bx bx-show"></i><span>Detalle</span></a><a href="{{ route('app.caja.corte.print', $cut) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Reimprimir corte"><i class="bx bx-printer"></i></a></div>
            </article>
        @empty
            <div class="app-empty-state"><span class="app-empty-icon"><i class="bx bx-calculator"></i></span><h3>Sin cortes registrados</h3><p>Cuando cierres una caja, su resumen aparecerá aquí.</p></div>
        @endforelse
        @if($this->cuts->hasPages())<div class="cash-history-pagination">{{ $this->cuts->links() }}</div>@endif
    </section>
</div>
