@php
    $reprintResults = $reprintType === 'mesas'
        ? $this->mesaServiceHistory
        : $this->recentOrders;
    $reprintResultLabel = $reprintType === 'mesas' ? 'servicios' : 'pedidos';
@endphp

<x-pos.area-panel panel="reprint" title="Reimprimir tickets" title-id="pos-reprint-title"
    eyebrow="Documentos" description="Cocina imprime productos por área; Cliente muestra productos, precios y total."
    icon="bx-printer" tone="reprint" panel-class="pos-reprint-panel" body-class="pos-reprint-results-shell"
    close-label="Cerrar Reimpresión">
        <x-slot:navigation>
        <div class="pos-reprint-tabs" role="tablist" aria-label="Filtrar pedidos por área">
            @foreach (['ventanilla' => ['Ventanilla', 'bx-store-alt'], 'delivery' => ['Delivery', 'bx-cycling'], 'mesas' => ['Mesas', 'bx-table']] as $type => [$label, $icon])
                <button type="button" wire:click="$set('reprintType', '{{ $type }}')"
                    class="pos-reprint-tab {{ $reprintType === $type ? 'is-active' : '' }}" role="tab" aria-selected="{{ $reprintType === $type ? 'true' : 'false' }}">
                    <i class="bx {{ $icon }}"></i>{{ $label }}
                </button>
            @endforeach
        </div>
        </x-slot:navigation>

        <x-slot:tools>
            <label class="pos-area-search">
                <i class="bx bx-search"></i>
                <span class="visually-hidden">Buscar orden para reimprimir</span>
                <input type="search" class="pos-input" wire:model.live.debounce.300ms="reprintSearch" placeholder="Número de pedido o cliente">
            </label>
            <div class="pos-reprint-result-count" aria-live="polite">
                <strong>{{ $reprintResults->count() }}</strong>
                <span>{{ $reprintResults->count() === 1 ? rtrim($reprintResultLabel, 's') : $reprintResultLabel }}</span>
            </div>
        </x-slot:tools>

        <div class="pos-reprint-results" role="list" tabindex="0"
             aria-label="Resultados disponibles para reimpresión">
            @if ($reprintType === 'mesas')
                @forelse ($reprintResults as $service)
                    <article class="pos-reprint-table-group {{ $service->status === 'liberada' ? 'is-audit' : '' }}"
                             wire:key="mesa-history-{{ $service->id }}" role="listitem">
                        <header>
                            <div>
                                <span><i class="bx {{ $service->status === 'liberada' ? 'bx-error-circle' : ($service->is_grouped ? 'bx-group' : 'bx-table') }}"></i></span>
                                <div>
                                    <strong>{{ $service->service_label }}</strong>
                                    <small>{{ $service->status === 'liberada' ? 'Liberada sin cobro' : 'Servicio pagado' }} · {{ $service->opened_at->format('H:i') }}–{{ $service->closed_at?->format('H:i') }} · {{ $service->opener_name_snapshot ?: 'Sin responsable' }}</small>
                                </div>
                            </div>
                            <strong>${{ number_format((float) $service->total_snapshot, 2) }}</strong>
                        </header>
                        <div class="pos-history-meta">
                            <span><i class="bx bx-receipt"></i>{{ $service->orders->count() }} órdenes</span>
                            <span><i class="bx bx-chair"></i>{{ $service->mesas->map(fn ($mesa) => $mesa->pivot->mesa_label_snapshot ?: $mesa->display_name)->implode(', ') }}</span>
                            @php
                                $paidAccounts = $service->splits->flatMap(fn ($split) => collect($split->split_data ?? [])->filter(fn ($account) => $account['paid'] ?? false));
                            @endphp
                            @if ($paidAccounts->isNotEmpty())
                                <span><i class="bx bx-split"></i>{{ $paidAccounts->count() }} subcuentas cobradas</span>
                            @endif
                            @if ($service->status === 'liberada')
                                <span class="is-audit-reason"><i class="bx bx-note"></i>{{ $service->close_reason }}</span>
                            @endif
                        </div>
                        <div class="pos-reprint-actions">
                            <button type="button" wire:click="openMesaServiceHistoryTicket({{ $service->id }})" @click="panels.reprint = false" class="pos-btn pos-btn-primary">
                                <i class="bx bx-receipt"></i>{{ $service->status === 'liberada' ? 'Ver auditoría' : 'Ticket consolidado' }}
                            </button>
                        </div>
                    </article>
                @empty
                    <div class="pos-area-empty"><span><i class="bx bx-printer"></i></span><h3>Sin servicios finalizados</h3><p>El histórico muestra únicamente servicios pagados o liberaciones auditadas de la caja actual.</p></div>
                @endforelse
            @else
                @forelse ($reprintResults as $ro)
                    <article class="pos-reprint-order-card" wire:key="reprint-order-{{ $ro->id }}" role="listitem">
                        <div class="pos-reprint-order-card__identity">
                            <span><i class="bx {{ $reprintType === 'delivery' ? 'bx-cycling' : 'bx-store-alt' }}"></i></span>
                            <div><strong>Orden #{{ $ro->display_folio }}</strong><small>{{ $ro->customer_name ?: 'Cliente sin nombre' }} · {{ $ro->created_at->format('H:i') }}</small><b>${{ number_format($ro->total, 2) }}</b></div>
                        </div>
                        <div class="pos-reprint-actions">
                            <button type="button" wire:click="openReprintModal({{ $ro->id }})" @click="panels.reprint = false" class="pos-btn pos-btn-secondary"><i class="bx bx-receipt"></i>Cliente</button>
                            <button type="button" wire:click="reprintKitchenOrder({{ $ro->id }})" @click="panels.reprint = false" class="pos-btn pos-btn-primary"><i class="bx bx-dish"></i>Cocina</button>
                        </div>
                    </article>
                @empty
                    <div class="pos-area-empty"><span><i class="bx bx-printer"></i></span><h3>Sin pedidos para reimprimir</h3><p>No encontramos tickets de hoy con ese criterio.</p></div>
                @endforelse
            @endif
        </div>
</x-pos.area-panel>
