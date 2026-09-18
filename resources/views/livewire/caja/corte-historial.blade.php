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
            <article class="cash-history-row {{ $cut->is_reopened ? 'is-reopened' : '' }}" wire:key="cash-cut-{{ $cut->id }}">
                <div class="cash-history-row__mark"><i class="bx {{ $cut->is_reopened ? 'bx-lock-open-alt' : 'bx-lock-alt' }}" aria-hidden="true"></i></div>
                <div class="cash-history-row__main">
                    <div class="cash-history-row__title"><strong>{{ $cut->folio }}</strong><span class="app-status app-status--neutral">{{ $cut->cashRegister->name }}</span>
                        @if($cut->is_reopened)<span class="app-status app-status--warning"><i class="bx bx-revision" aria-hidden="true"></i> Anulado por reapertura</span>
                        @elseif($diff===0.0)<span class="app-status app-status--success">Cuadrado</span>@elseif($diff>0)<span class="app-status app-status--info">Sobrante ${{ number_format(abs($diff),2) }}</span>@else<span class="app-status app-status--danger">Faltante ${{ number_format(abs($diff),2) }}</span>@endif
                    </div>
                    <p>{{ \App\Support\BusinessTime::format($cut->cashRegister->opened_at) }} – {{ \App\Support\BusinessTime::format($cut->generated_at) }} · Cerró <strong>{{ $cut->generator?->name ?? 'Usuario no disponible' }}</strong></p>
                    @if($cut->is_reopened)
                        <p class="cash-history-row__reopen">Reabrió <strong>{{ $cut->reopener?->name ?? 'Usuario no disponible' }}</strong> el {{ \App\Support\BusinessTime::format($cut->reopened_at) }} · «{{ $cut->reopen_reason }}»</p>
                    @endif
                </div>
                <div class="cash-history-row__numbers"><div><small>Efectivo</small><strong>${{ number_format($cash,2) }}</strong></div><div><small>Digital</small><strong>${{ number_format($digital,2) }}</strong></div></div>
                <div class="cash-history-row__actions">
                    <a href="{{ route('app.caja.corte.detalle', $cut) }}" class="btn btn-sm btn-primary"><i class="bx bx-show"></i><span>Detalle</span></a>
                    <a href="{{ route('app.caja.corte.print', $cut) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Reimprimir corte" aria-label="Reimprimir corte {{ $cut->folio }}"><i class="bx bx-printer"></i></a>
                    @if($this->reopenableCutId === $cut->id)
                        <button type="button" class="btn btn-sm btn-outline-warning" wire:click="openReopen({{ $cut->id }})" aria-label="Reabrir la caja {{ $cut->cashRegister->name }}"><i class="bx bx-lock-open-alt"></i><span>Reabrir</span></button>
                    @endif
                </div>
            </article>
        @empty
            <div class="app-empty-state"><span class="app-empty-icon"><i class="bx bx-calculator"></i></span><h3>Sin cortes registrados</h3><p>Cuando cierres una caja, su resumen aparecerá aquí.</p></div>
        @endforelse
        @if($this->cuts->hasPages())<div class="cash-history-pagination">{{ $this->cuts->links() }}</div>@endif
    </section>

    @if($this->reopenCut)
        @php($reopening = $this->reopenCut)
        <div class="app-modal-backdrop cash-reopen-backdrop" wire:click="closeReopen" aria-hidden="true"></div>
        <div class="app-modal-layer cash-reopen-layer" role="dialog" aria-modal="true" aria-labelledby="cash-reopen-title" aria-describedby="cash-reopen-description"
            wire:key="cash-reopen-modal" wire:click.self="closeReopen" wire:keydown.escape.window="closeReopen">
            <section class="cash-reopen-modal">
                <header>
                    <span class="cash-reopen-modal__icon" aria-hidden="true"><i class="bx bx-lock-open-alt"></i></span>
                    <div>
                        <span class="app-eyebrow">Super admin · Caja</span>
                        <h2 id="cash-reopen-title">Reabrir «{{ $reopening->cashRegister->name }}»</h2>
                        <p id="cash-reopen-description">Corte {{ $reopening->folio }} · {{ \App\Support\BusinessTime::format($reopening->generated_at) }}</p>
                    </div>
                    <button type="button" class="cash-reopen-modal__close" wire:click="closeReopen" aria-label="Cancelar reapertura"><i class="bx bx-x"></i></button>
                </header>

                <ul class="cash-reopen-modal__effects">
                    <li><i class="bx bx-revision" aria-hidden="true"></i><span>El corte <strong>{{ $reopening->folio }}</strong> queda <strong>anulado</strong>, no se borra: seguirá en este historial con tu nombre y el motivo.</span></li>
                    <li><i class="bx bx-store-alt" aria-hidden="true"></i><span>La caja vuelve a estar abierta: el POS registrará ventas en ella y deberá cerrarse con un <strong>nuevo corte</strong> y nuevo folio.</span></li>
                    <li><i class="bx bx-cycling" aria-hidden="true"></i><span>El efectivo contra entrega que el cierre dio por recibido vuelve a quedar pendiente del repartidor.</span></li>
                </ul>

                <div class="cash-reopen-modal__field">
                    <label for="cash-reopen-reason">Motivo de la reapertura <small>Obligatorio</small></label>
                    <textarea id="cash-reopen-reason" rows="3" wire:model="reopenReason" maxlength="1000"
                        placeholder="Ej. Se cerró antes de recibir el efectivo del último repartidor"
                        aria-invalid="{{ $errors->has('reopenReason') ? 'true' : 'false' }}" aria-describedby="cash-reopen-reason-help"></textarea>
                    @error('reopenReason')<p class="cash-reopen-modal__error" role="alert">{{ $message }}</p>@else<p id="cash-reopen-reason-help" class="cash-reopen-modal__help">Queda registrado en la auditoría del corte.</p>@enderror
                </div>

                <footer>
                    <button type="button" class="btn btn-outline-secondary" wire:click="closeReopen">Cancelar</button>
                    <button type="button" class="btn btn-warning" wire:click="confirmReopen" wire:loading.attr="disabled" wire:target="confirmReopen">
                        <i class="bx bx-lock-open-alt" wire:loading.remove wire:target="confirmReopen"></i>
                        <i class="bx bx-loader-alt bx-spin" wire:loading wire:target="confirmReopen"></i>
                        <span>Reabrir caja</span>
                    </button>
                </footer>
            </section>
        </div>
    @endif
</div>
