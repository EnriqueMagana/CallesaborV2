<div class="container-xxl flex-grow-1 container-p-y">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold mb-0">Historial de cortes</h4>
            <small class="text-muted">Todos los cierres de caja registrados</small>
        </div>
        <a href="{{ route('app.caja') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bx bx-arrow-back me-1"></i> Caja
        </a>
    </div>

    {{-- Buscador --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body py-3">
            <div class="input-group" style="max-width:360px">
                <span class="input-group-text bg-transparent border-end-0">
                    <i class="bx bx-search text-muted"></i>
                </span>
                <input type="text" wire:model.live.debounce.300ms="search"
                       class="form-control border-start-0"
                       placeholder="Buscar por folio o nombre de caja…">
            </div>
        </div>
    </div>

    {{-- Lista --}}
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            @forelse($this->cuts as $cut)
                @php
                    $totalEfectivo = $cut->v_efectivo + $cut->m_efectivo + $cut->d_efectivo;
                    $totalDigital  = $cut->v_tarjeta + $cut->v_transfer
                                   + $cut->m_tarjeta + $cut->m_transfer
                                   + $cut->d_tarjeta + $cut->d_transfer;
                    $diff = (float) $cut->difference;
                @endphp
                <div class="d-flex align-items-center gap-3 px-4 py-3 border-bottom"
                     style="transition:background .1s" onmouseover="this.style.background='#f8f8ff'" onmouseout="this.style.background=''">

                    {{-- Ícono --}}
                    <div style="width:42px;height:42px;border-radius:10px;background:rgba(105,108,255,.1);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <i class="bx bx-calculator" style="color:var(--bs-primary);font-size:1.2rem"></i>
                    </div>

                    {{-- Info principal --}}
                    <div class="flex-grow-1" style="min-width:0">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="fw-bold" style="font-size:.9rem">{{ $cut->folio }}</span>
                            <span class="badge bg-label-secondary" style="font-size:.67rem">{{ $cut->cashRegister->name }}</span>
                            @if($diff > 0)
                                <span class="badge bg-label-info" style="font-size:.67rem">Sobrante ${{ number_format(abs($diff),2) }}</span>
                            @elseif($diff < 0)
                                <span class="badge bg-label-danger" style="font-size:.67rem">Faltante ${{ number_format(abs($diff),2) }}</span>
                            @else
                                <span class="badge bg-label-success" style="font-size:.67rem">Cuadrado</span>
                            @endif
                        </div>
                        <div class="text-muted mt-1" style="font-size:.75rem">
                            {{ $cut->generated_at->format('d/m/Y g:i A') }}
                            · Cerrado por <strong>{{ $cut->generator->name }}</strong>
                        </div>
                    </div>

                    {{-- Totales --}}
                    <div class="text-end d-none d-md-block" style="min-width:160px">
                        <div class="fw-bold text-success" style="font-size:.88rem">
                            Efectivo ${{ number_format($totalEfectivo,2) }}
                        </div>
                        <div class="text-muted" style="font-size:.72rem">
                            Digital ${{ number_format($totalDigital,2) }}
                        </div>
                    </div>

                    {{-- Acciones --}}
                    <div class="d-flex gap-2 flex-shrink-0">
                        <a href="{{ route('app.caja.corte.detalle', $cut) }}" class="btn btn-sm btn-outline-primary">
                            <i class="bx bx-show me-1"></i> Ver
                        </a>
                        <a href="{{ route('app.caja.corte.print', $cut) }}" target="_blank"
                           class="btn btn-sm btn-outline-secondary" title="Reimprimir">
                            <i class="bx bx-printer"></i>
                        </a>
                    </div>
                </div>
            @empty
                <div class="text-center py-5 text-muted">
                    <i class="bx bx-calculator" style="font-size:2.5rem;opacity:.25;display:block;margin-bottom:8px"></i>
                    <span style="font-size:.85rem">Sin cortes registrados</span>
                </div>
            @endforelse
        </div>

        @if($this->cuts->hasPages())
            <div class="card-footer bg-transparent">
                {{ $this->cuts->links() }}
            </div>
        @endif
    </div>

</div>
