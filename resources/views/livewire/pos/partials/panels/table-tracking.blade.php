<x-pos.area-panel panel="tracking" title="Seguimiento de mesas" title-id="pos-tracking-title"
    eyebrow="Operación en vivo"
    description="Envía comandas a cocina, confirma su preparación y reimprime cuando sea necesario."
    icon="bx-radar" tone="tracking" panel-class="pos-tracking-panel"
    close-label="Cerrar Seguimiento" close-action="panels.tracking = false; $wire.closeTableTracking()">
        <x-slot:tools>
        <div class="pos-tracking-tools">
            <div class="pos-area-guidance"><i class="bx bx-restaurant"></i>
                <span>Este panel es exclusivamente operativo; los cobros se realizan en Cobrar mesas.</span>
            </div>
            <button type="button" class="pos-btn pos-btn-secondary" wire:click="refreshTableTracking"
                wire:loading.attr="disabled" wire:target="refreshTableTracking">
                <i class="bx bx-refresh" wire:loading.class="bx-spin" wire:target="refreshTableTracking"></i>
                Actualizar
            </button>
            @if ($tableTrackingRefreshedAt)
                <small class="pos-tracking-updated">Actualizado {{ $tableTrackingRefreshedAt }}</small>
            @endif
        </div>
        </x-slot:tools>

        <x-slot:beforeBody>
        <div wire:loading.flex wire:target="openTableTracking"
            class="pos-skeleton-list" aria-label="Actualizando seguimiento">
            @for ($s = 0; $s < 2; $s++)
                <div class="pos-table-skeleton"><span></span>
                    <div><i></i><i></i><i></i></div>
                </div>
            @endfor
        </div>
        </x-slot:beforeBody>

        <x-slot:body>
        <div class="panel-body pos-area-panel__body pos-tracking-accordion" wire:loading.remove
            x-data="{ openService: @js($this->tableTrackingServices->first()?->id) }"
            wire:target="openTableTracking">
            @forelse ($this->tableTrackingServices as $service)
                @php
                    $pending = $service->orders->where('status', 'pendiente')->count();
                    $preparing = $service->orders->where('status', 'en_preparacion')->count();
                    $ready = $service->orders->whereIn('status', ['lista', 'entregada'])->count();
                @endphp
                <article class="pos-table-group pos-tracking-service" wire:key="tracking-service-{{ $service->id }}">
                    <header class="pos-table-group__header pos-tracking-service__header">
                        <button type="button" class="pos-tracking-service__toggle"
                            id="tracking-service-toggle-{{ $service->id }}"
                            @click="openService = openService === {{ $service->id }} ? null : {{ $service->id }}"
                            :aria-expanded="(openService === {{ $service->id }}).toString()"
                            aria-controls="tracking-service-content-{{ $service->id }}">
                            <span class="pos-tracking-service__identity">
                                <span class="pos-tracking-service__icon"><i
                                        class="bx {{ $service->is_grouped ? 'bx-group' : 'bx-table' }}"></i></span>
                                <span class="pos-tracking-service__copy">
                                    <strong>{{ $service->service_label }}</strong>
                                    <small class="pos-service-opened">
                                        <span>Abrió {{ $service->opener_name_snapshot ?: 'Sin asignar' }}</span>
                                        <span><i class="bx bx-time-five"></i>{{ $service->opened_at->format('g:i A') }}</span>
                                        <span>{{ $service->duration_label }} activa</span>
                                    </small>
                                </span>
                            </span>
                            <span class="pos-tracking-statuses" aria-label="Resumen de comandas">
                                <span class="is-pending">{{ $pending }} pendientes</span>
                                <span class="is-preparing">{{ $preparing }} preparando</span>
                                <span class="is-ready">{{ $ready }} listas</span>
                            </span>
                            <span class="pos-tracking-service__chevron" aria-hidden="true">
                                <i class="bx bx-chevron-down"
                                    :class="{ 'is-expanded': openService === {{ $service->id }} }"></i>
                            </span>
                        </button>
                    </header>

                    <div class="pos-tracking-service__content"
                        id="tracking-service-content-{{ $service->id }}"
                        role="region" aria-labelledby="tracking-service-toggle-{{ $service->id }}"
                        x-show="openService === {{ $service->id }}" x-cloak
                        x-transition:enter.opacity.duration.180ms
                        x-transition:leave.opacity.duration.120ms>
                        @if ($service->mesas->isNotEmpty())
                            <div class="pos-tracking-members" aria-label="Mesas ocupadas por este servicio">
                                <small>Mesas ocupadas</small>
                                @foreach ($service->mesas as $member)
                                    <span><i
                                            class="bx bx-chair"></i>{{ $member->pivot->mesa_label_snapshot ?: $member->display_name }}</span>
                                @endforeach
                            </div>
                        @endif

                        @if ($service->orders->isNotEmpty())
                            <div class="pos-table-group__orders">
                                @foreach ($service->orders as $tableOrder)
                                    @include('livewire.pos.partials.order-flow-card', [
                                        'flowOrder' => $tableOrder,
                                        'flowArea' => $service->service_label,
                                        'flowIcon' => 'bx-receipt',
                                        'flowSourceLabel' => $tableOrder->source === 'kiosk' ? 'Kiosco' : 'Mesero',
                                        'allowOrderPayment' => false,
                                        'showFinancialTotal' => false,
                                    ])
                                @endforeach
                            </div>
                        @else
                            <div class="pos-tracking-empty-orders"><i class="bx bx-time-five"></i>Servicio abierto,
                                todavía sin comandas.</div>
                        @endif
                    </div>
                </article>
            @empty
                <div class="pos-area-empty">
                    <span><i class="bx bx-check-circle"></i></span>
                    <h3>Sin servicios de mesa activos</h3>
                    <p>Al abrir o recibir una orden de mesa aparecerá aquí como una sola unidad operativa.</p>
                </div>
            @endforelse
        </div>
        </x-slot:body>
</x-pos.area-panel>
