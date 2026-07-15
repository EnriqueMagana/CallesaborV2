<div class="app-page reservations-page">

    {{-- Header --}}
    <header class="app-page-header">
        <div class="app-page-heading">
            <span class="app-page-icon" aria-hidden="true"><i class="bx bx-calendar"></i></span>
            <div>
                <div class="app-eyebrow">Operación · Agenda</div>
                <h1 class="app-page-title">Reservaciones</h1>
                <p class="app-page-subtitle">Organiza la ocupación del restaurante y da seguimiento a cada visita.</p>
            </div>
        </div>
        <div class="app-page-actions">
            <button type="button" wire:click="openNew('{{ now()->format('Y-m-d') }}')" class="btn btn-primary">
                <i class="bx bx-plus me-1" aria-hidden="true"></i>Nueva reserva
            </button>
        </div>
    </header>

    {{-- Leyenda de colores --}}
    <div class="app-legend" aria-label="Estados de las reservaciones">
        @foreach(['pendiente'=>['#f59e0b','Pendiente'],'confirmada'=>['#10b981','Confirmada'],'completada'=>['#6366f1','Completada'],'cancelada'=>['#ef4444','Cancelada']] as $s=>[$color,$label])
            <span class="app-legend-item">
                <span class="app-legend-dot" style="color:{{ $color }};background:{{ $color }}" aria-hidden="true"></span>
                {{ $label }}
            </span>
        @endforeach
    </div>

    {{-- Calendario — siempre ancho completo --}}
    <section class="card app-card" aria-label="Calendario de reservaciones">
        <div class="card-body p-3 p-md-4">
            <div id="reservations-calendar" data-lwid="{{ $this->getId() }}"></div>
        </div>
    </section>

    {{-- ══════════════════════════════════════
         MODAL: Nueva / Editar reserva
    ══════════════════════════════════════ --}}
    @if($panelMode === 'new' || $panelMode === 'edit')
    <div class="modal app-modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true"
         style="background:rgba(0,0,0,.45)"
         wire:click.self="closePanel">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"
             style="max-width:480px" @click.stop>
            <div class="modal-content">

                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">
                        @if($panelMode === 'new')
                            <i class="bx bx-calendar-plus me-1 text-primary"></i> Nueva reserva
                        @else
                            <i class="bx bx-edit me-1 text-warning"></i> Editar reserva
                        @endif
                    </h5>
                    <button wire:click="closePanel" type="button" class="btn-close" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">

                    {{-- Buscar cliente existente --}}
                    <div class="mb-3 position-relative">
                        <label class="form-label fw-semibold" style="font-size:.82rem">Buscar cliente existente</label>
                        <input type="text" wire:model.live.debounce.250ms="customerSearch"
                               class="form-control form-control-sm"
                               placeholder="Nombre o teléfono…"
                               autocomplete="off">
                        @if($this->customerSuggestions->count())
                            <div class="border rounded bg-white shadow-sm position-absolute w-100"
                                 style="z-index:1060;top:calc(100% + 2px);max-height:180px;overflow-y:auto">
                                @foreach($this->customerSuggestions as $c)
                                    <div wire:click="selectCustomer({{ $c->id }})"
                                         class="px-3 py-2 border-bottom"
                                         style="cursor:pointer;font-size:.82rem"
                                         onmouseover="this.style.background='#f5f5ff'"
                                         onmouseout="this.style.background=''">
                                        <div class="fw-semibold">{{ $c->name }}</div>
                                        @if($c->phone)
                                            <div class="text-muted" style="font-size:.75rem">{{ $c->phone }}</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Nombre --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="font-size:.82rem">
                            Nombre <span class="text-danger">*</span>
                        </label>
                        <input type="text" wire:model="customerName"
                               class="form-control form-control-sm @error('customerName') is-invalid @enderror"
                               placeholder="A nombre de…">
                        @error('customerName')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Teléfono --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="font-size:.82rem">Teléfono</label>
                        <input type="tel" wire:model="customerPhone"
                               class="form-control form-control-sm"
                               placeholder="Opcional">
                    </div>

                    {{-- Fecha + Hora --}}
                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label fw-semibold" style="font-size:.82rem">
                                Fecha <span class="text-danger">*</span>
                            </label>
                            <input type="date" wire:model="reservedDate"
                                   class="form-control form-control-sm @error('reservedDate') is-invalid @enderror">
                            @error('reservedDate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-5">
                            <label class="form-label fw-semibold" style="font-size:.82rem">
                                Hora <span class="text-danger">*</span>
                            </label>
                            <input type="time" wire:model="reservedTime"
                                   class="form-control form-control-sm @error('reservedTime') is-invalid @enderror">
                            @error('reservedTime')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    {{-- Asistentes --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="font-size:.82rem">
                            Personas <span class="text-danger">*</span>
                        </label>
                        <div class="input-group input-group-sm" style="max-width:160px">
                            <button type="button" wire:click="decrementGuests"
                                    class="btn btn-outline-secondary px-3">−</button>
                            <input type="number" wire:model="guests" min="1"
                                   class="form-control text-center @error('guests') is-invalid @enderror">
                            <button type="button" wire:click="incrementGuests"
                                    class="btn btn-outline-secondary px-3">+</button>
                        </div>
                        @error('guests')<div class="text-danger mt-1" style="font-size:.75rem">{{ $message }}</div>@enderror
                    </div>

                    {{-- Notas --}}
                    <div class="mb-1">
                        <label class="form-label fw-semibold" style="font-size:.82rem">Notas</label>
                        <textarea wire:model="notes" class="form-control form-control-sm"
                                  rows="2" placeholder="Ocasión especial, alergias…"></textarea>
                    </div>

                </div>

                <div class="modal-footer border-0 pt-0">
                    <button wire:click="closePanel" class="btn btn-outline-secondary btn-sm">
                        Cancelar
                    </button>
                    <button wire:click="{{ $panelMode === 'edit' ? 'update' : 'save' }}"
                            wire:loading.attr="disabled"
                            wire:target="{{ $panelMode === 'edit' ? 'update' : 'save' }}"
                            class="btn btn-primary btn-sm">
                        <span wire:loading wire:target="{{ $panelMode === 'edit' ? 'update' : 'save' }}"
                              class="spinner-border spinner-border-sm me-1"></span>
                        {{ $panelMode === 'edit' ? 'Guardar cambios' : 'Crear reserva' }}
                    </button>
                </div>

            </div>
        </div>
    </div>
    @endif

    {{-- ══════════════════════════════════════
         MODAL: Detalle de reserva
    ══════════════════════════════════════ --}}
    @if($panelMode === 'detail' && $this->selectedReservation)
    @php
        $r = $this->selectedReservation;
        $badgeMap = [
            'pendiente'  => 'bg-label-warning',
            'confirmada' => 'bg-label-success',
            'completada' => 'bg-label-primary',
            'cancelada'  => 'bg-label-danger',
        ];
    @endphp
    <div class="modal app-modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true"
         style="background:rgba(0,0,0,.45)"
         wire:click.self="closePanel">
        <div class="modal-dialog modal-dialog-centered"
             style="max-width:440px" @click.stop>
            <div class="modal-content">

                <div class="modal-header border-0 pb-0">
                    <div>
                        <span class="badge {{ $badgeMap[$r->status] ?? 'bg-label-secondary' }} mb-1">
                            {{ $r->status_label }}
                        </span>
                        <h5 class="modal-title fw-bold mb-0">{{ $r->customer_name }}</h5>
                    </div>
                    <button wire:click="closePanel" type="button" class="btn-close ms-auto" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <div class="d-flex flex-column gap-2" style="font-size:.88rem">
                        @if($r->customer_phone)
                            <div class="d-flex align-items-center gap-2">
                                <i class="bx bx-phone text-muted"></i>
                                <span>{{ $r->customer_phone }}</span>
                            </div>
                        @endif
                        <div class="d-flex align-items-center gap-2">
                            <i class="bx bx-calendar text-muted"></i>
                            <span>{{ $r->reserved_at->translatedFormat('l d \d\e F Y') }}</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <i class="bx bx-time text-muted"></i>
                            <span>{{ $r->reserved_at->format('g:i A') }}</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <i class="bx bx-group text-muted"></i>
                            <span>{{ $r->guests }} {{ $r->guests === 1 ? 'persona' : 'personas' }}</span>
                        </div>
                        @if($r->notes)
                            <div class="d-flex align-items-start gap-2">
                                <i class="bx bx-note text-muted mt-1"></i>
                                <span class="text-muted">{{ $r->notes }}</span>
                            </div>
                        @endif
                    </div>

                    @if($r->status === 'cancelada' && $r->cancellation_reason)
                        <div class="alert alert-danger py-2 mt-3 mb-0" style="font-size:.82rem">
                            <strong>Motivo de cancelación:</strong> {{ $r->cancellation_reason }}
                        </div>
                    @endif

                    {{-- Form cancelación inline --}}
                    @if($showCancelForm)
                        <div class="mt-3 p-3 bg-light rounded border" style="font-size:.84rem">
                            <label class="form-label fw-semibold mb-1">Motivo (opcional)</label>
                            <textarea wire:model="cancelReason" class="form-control form-control-sm mb-2"
                                      rows="2" placeholder="Motivo de cancelación…"></textarea>
                            <div class="d-flex gap-2">
                                <button wire:click="cancel" class="btn btn-danger btn-sm flex-grow-1">
                                    Confirmar cancelación
                                </button>
                                <button wire:click="$set('showCancelForm', false)"
                                        class="btn btn-outline-secondary btn-sm">Atrás</button>
                            </div>
                        </div>
                    @endif
                </div>

                @if($r->status !== 'cancelada' && $r->status !== 'completada' && !$showCancelForm)
                <div class="modal-footer border-0 pt-0 flex-wrap gap-2">
                    @if($r->status === 'pendiente')
                        <button wire:click="changeStatus('confirmada')" class="btn btn-success btn-sm">
                            <i class="bx bx-check me-1"></i> Confirmar
                        </button>
                    @endif
                    @if($r->status === 'confirmada')
                        <button wire:click="changeStatus('completada')" class="btn btn-primary btn-sm">
                            <i class="bx bx-check-double me-1"></i> Completada
                        </button>
                    @endif
                    <button wire:click="startEdit" class="btn btn-outline-warning btn-sm">
                        <i class="bx bx-edit me-1"></i> Editar
                    </button>
                    <button wire:click="$set('showCancelForm', true)"
                            class="btn btn-outline-danger btn-sm">
                        <i class="bx bx-x-circle me-1"></i> Cancelar
                    </button>
                </div>
                @endif

            </div>
        </div>
    </div>
    @endif

    {{-- FullCalendar v6 (CSS auto-inyectado por el build global) --}}
    @once
        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/locales/es.global.min.js"></script>
    @endonce

    

    <script>
    (function() {
        let calendar = null;

        function getComponent() {
            const el = document.getElementById('reservations-calendar');
            return el ? Livewire.find(el.dataset.lwid) : null;
        }

        function initCalendar() {
            if (typeof FullCalendar === 'undefined') {
                setTimeout(initCalendar, 100);
                return;
            }
            const el = document.getElementById('reservations-calendar');
            if (!el) return;
            if (calendar) { calendar.destroy(); calendar = null; }

            const isMobile = window.innerWidth < 576;

            calendar = new FullCalendar.Calendar(el, {
                locale: 'es',
                initialView: isMobile ? 'listWeek' : 'dayGridMonth',
                headerToolbar: {
                    left:   'prev,next today',
                    center: 'title',
                    right:  isMobile
                        ? 'listWeek,dayGridMonth'
                        : 'dayGridMonth,timeGridWeek,listWeek'
                },
                buttonText: {
                    today:   'Hoy',
                    month:   'Mes',
                    week:    'Semana',
                    day:     'Día',
                    list:    'Lista',
                },
                height: 'auto',
                eventDisplay: 'block',
                dayMaxEvents: 3,
                nowIndicator: true,
                events: function(info, successCallback, failureCallback) {
                    fetch(`/app/reservas/events?start=${info.startStr}&end=${info.endStr}`)
                        .then(r => r.json())
                        .then(successCallback)
                        .catch(failureCallback);
                },
                dateClick: function(info) {
                    const cmp = getComponent();
                    if (cmp) cmp.call('openNew', info.dateStr);
                },
                eventClick: function(info) {
                    const cmp = getComponent();
                    if (cmp) cmp.call('openDetail', parseInt(info.event.id));
                },
            });

            calendar.render();
        }

        document.addEventListener('livewire:initialized', () => {
            initCalendar();
            Livewire.on('reservation-saved', () => {
                if (calendar) calendar.refetchEvents();
            });
        });

        document.addEventListener('livewire:navigated', () => {
            calendar = null;
            initCalendar();
        });

        // Relayout al redimensionar
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                if (calendar) calendar.updateSize();
            }, 200);
        });
    })();
    </script>
</div>


