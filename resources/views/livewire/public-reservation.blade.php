<div class="reservation-entry"
    x-data='{
        open: $wire.entangle("isOpen"),
        step: $wire.entangle("step"),
        selectedDate: $wire.entangle("selectedDate"),
        selectedTime: $wire.entangle("selectedTime"),
        guests: $wire.entangle("guests"),
        acceptWaitlist: $wire.entangle("acceptWaitlist"),
        availability: @json($this->availabilityByDate),
        clientError: "",
        get slots() { return this.availability[this.selectedDate] || []; },
        get selectedSlot() { return this.slots.find(slot => slot.time === this.selectedTime) || null; },
        tablesNeeded(slot, guests) {
            if (!slot || !slot.enforced) return 0;
            const states = new Map([[0, []]]);
            (slot.table_capacities || []).forEach((capacity, index) => {
                [...states.entries()].forEach(([sum, indexes]) => {
                    const nextSum = sum + Number(capacity);
                    const candidate = [...indexes, index];
                    if (!states.has(nextSum) || candidate.length < states.get(nextSum).length) states.set(nextSum, candidate);
                });
            });
            const match = [...states.entries()].filter(([sum]) => sum >= Number(guests)).sort((a, b) => a[0] - b[0] || a[1].length - b[1].length)[0];
            return match ? match[1].length : (slot.table_capacities || []).length + 1;
        },
        canFitSlot(slot) {
            if (!slot || !slot.enforced) return true;
            return Number(slot.remaining_seats) >= Number(this.guests)
                && Number(slot.remaining_tables) >= this.tablesNeeded(slot, this.guests);
        },
        get waitlistRequired() { return this.selectedSlot && !this.canFitSlot(this.selectedSlot); },
        capacityLabel(slot) {
            if (!slot.enforced) return "Disponible";
            if (!this.canFitSlot(slot)) return "Lista de espera";
            const places = Number(slot.remaining_seats) === 1 ? "1 lugar" : `${slot.remaining_seats} lugares`;
            const tables = Number(slot.remaining_tables) === 1 ? "1 mesa" : `${slot.remaining_tables} mesas`;
            return `${places} · ${tables}`;
        },
        formatDate(date) {
            return new Intl.DateTimeFormat("es-MX", { day: "numeric", month: "short" }).format(new Date(`${date}T12:00:00`));
        },
        chooseDate(date) {
            this.selectedDate = date;
            this.selectedTime = "";
            this.acceptWaitlist = false;
            this.clientError = "";
        },
        chooseTime(time) {
            this.selectedTime = time;
            this.acceptWaitlist = false;
            this.clientError = "";
        },
        nextFromDate() {
            if (!this.selectedDate) { this.clientError = "Selecciona una fecha disponible."; return; }
            this.clientError = "";
            this.step = 2;
        },
        nextFromTime() {
            if (!this.selectedTime) { this.clientError = "Selecciona un horario para continuar."; return; }
            if (this.waitlistRequired && !this.acceptWaitlist) { this.clientError = "Elige otra hora o acepta la lista de espera."; return; }
            this.clientError = "";
            this.step = 3;
        }
    }'>
    <button type="button" class="reservation-launch" @click="open = true; $nextTick(() => $refs.closeButton?.focus())" aria-haspopup="dialog">
        <span><i class="bx bx-calendar-check" aria-hidden="true"></i></span>
        <span><small>Reserva en pocos pasos</small><strong>Reservar una mesa</strong></span>
        <i class="bx bx-right-arrow-alt" aria-hidden="true"></i>
    </button>

    <div class="reservation-modal" x-show="open" x-cloak x-transition.opacity.duration.180ms role="dialog" aria-modal="true" aria-labelledby="reservation-modal-title" @keydown.escape.window="open = false" x-effect="document.body.classList.toggle('has-reservation-modal', open)">
        <button type="button" class="reservation-modal__scrim" @click="open = false" tabindex="-1" aria-label="Cerrar reservación"></button>
        <section class="reservation-modal__sheet">
            @if($confirmationCode)
                <div class="reservation-success" role="status" aria-live="polite">
                    <span><i class="bx {{ $acceptWaitlist ? 'bx-time-five' : 'bx-check' }}" aria-hidden="true"></i></span>
                    <small>{{ $acceptWaitlist ? 'Solicitud en lista de espera' : 'Solicitud registrada' }}</small>
                    <h2 id="reservation-modal-title">{{ $acceptWaitlist ? 'Te avisaremos si se libera una mesa' : 'Tu mesa está un paso más cerca' }}</h2>
                    <p>El restaurante revisará la solicitud y podrá contactarte para confirmarla.</p>
                    <div><span>Código de seguimiento</span><strong>{{ $confirmationCode }}</strong></div>
                    <button type="button" class="reservation-primary" @click="open = false">Listo, cerrar</button>
                </div>
            @else
                <header class="reservation-modal__header">
                    <span class="reservation-modal__icon"><i class="bx bx-calendar-heart" aria-hidden="true"></i></span>
                    <div><small>Reservación en línea</small><h2 id="reservation-modal-title">Elige tu mesa</h2></div>
                    <button type="button" x-ref="closeButton" @click="open = false" aria-label="Cerrar reservación"><i class="bx bx-x" aria-hidden="true"></i></button>
                </header>

                <ol class="reservation-progress" aria-label="Progreso de la reservación">
                    <li :class="{ 'is-active': step === 1, 'is-complete': step > 1 }"><span><i class="bx" :class="step > 1 ? 'bx-check' : 'bx-calendar'"></i></span><small>Fecha</small></li>
                    <li :class="{ 'is-active': step === 2, 'is-complete': step > 2 }"><span><i class="bx" :class="step > 2 ? 'bx-check' : 'bx-time-five'"></i></span><small>Hora y personas</small></li>
                    <li :class="{ 'is-active': step === 3 }"><span><i class="bx bx-user"></i></span><small>Tus datos</small></li>
                </ol>

                <div class="reservation-modal__body">
                    <div class="reservation-panel" x-show="step === 1" x-transition.opacity.duration.180ms>
                        <div class="reservation-panel__heading"><span><i class="bx bx-calendar"></i></span><div><small>Paso 1 de 3</small><h3>¿Qué día nos visitas?</h3><p>Solo mostramos fechas en las que el restaurante está abierto.</p></div></div>
                        @if(count($this->dateOptions))
                            <div class="reservation-dates">
                                @foreach($this->dateOptions as $date => $option)
                                    <button type="button" @click="chooseDate('{{ $date }}')" :class="{ 'is-selected': selectedDate === '{{ $date }}' }" :aria-pressed="selectedDate === '{{ $date }}'" title="{{ $option['label'] }}">
                                        <small>{{ $option['weekday'] }}</small><strong>{{ $option['day'] }}</strong><span>{{ $option['month'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @else
                            <div class="reservation-empty"><i class="bx bx-calendar-x"></i><p>No hay fechas disponibles durante las próximas semanas.</p></div>
                        @endif
                        @error('selectedDate')<p class="reservation-error" role="alert">{{ $message }}</p>@enderror
                    </div>

                    <div class="reservation-panel" x-show="step === 2" x-cloak x-transition.opacity.duration.180ms>
                        <div class="reservation-panel__heading"><span><i class="bx bx-time-five"></i></span><div><small>Paso 2 de 3</small><h3>Selecciona la hora</h3><p>Consulta lugares y mesas disponibles para tu grupo.</p></div></div>
                        <div class="reservation-times reservation-times--capacity">
                            <template x-for="slot in slots" :key="slot.time">
                                <button type="button" @click="chooseTime(slot.time)" :class="{ 'is-selected': selectedTime === slot.time, 'is-full': !canFitSlot(slot), 'is-limited': slot.enforced && canFitSlot(slot) && slot.remaining_seats <= 4 }" :aria-pressed="selectedTime === slot.time">
                                    <span class="reservation-time__icon"><i class="bx bx-time-five"></i></span>
                                    <span class="reservation-time__copy"><strong x-text="slot.label"></strong><small x-text="capacityLabel(slot)"></small></span>
                                    <span class="reservation-time__state"><i class="bx" :class="selectedTime === slot.time ? 'bx-check' : (!canFitSlot(slot) ? 'bx-hourglass' : 'bx-chevron-right')"></i></span>
                                </button>
                            </template>
                            <p x-show="slots.length === 0">No encontramos horarios para esta fecha.</p>
                        </div>
                        @error('selectedTime')<p class="reservation-error" role="alert">{{ $message }}</p>@enderror
                        <div class="reservation-guests">
                            <span><i class="bx bx-group"></i><span><strong>¿Cuántas personas son?</strong><small>La disponibilidad se ajusta al instante</small></span></span>
                            <div><button type="button" @click="guests = Math.max(1, Number(guests) - 1); acceptWaitlist = false" aria-label="Restar una persona" :disabled="guests <= 1"><i class="bx bx-minus"></i></button><output x-text="guests" aria-live="polite"></output><button type="button" @click="guests = Math.min(20, Number(guests) + 1); acceptWaitlist = false" aria-label="Agregar una persona" :disabled="guests >= 20"><i class="bx bx-plus"></i></button></div>
                        </div>
                        <div class="reservation-capacity-alert" x-show="waitlistRequired" x-cloak>
                            <span><i class="bx bx-info-circle"></i></span>
                            <div><strong>Este horario no tiene capacidad para <span x-text="guests"></span> personas</strong><p>En este momento quedan <b x-text="selectedSlot?.remaining_seats ?? 0"></b> lugares y <b x-text="selectedSlot?.remaining_tables ?? 0"></b> mesas. Puedes elegir otra hora o solicitar la lista de espera.</p><label><input type="checkbox" x-model="acceptWaitlist"><span><i class="bx bx-hourglass"></i> Sí, deseo esperar si se libera una mesa</span></label></div>
                        </div>
                    </div>

                    <form id="reservation-customer-form" wire:submit="submit" class="reservation-panel reservation-details" x-show="step === 3" x-cloak x-transition.opacity.duration.180ms>
                        <div class="reservation-panel__heading"><span><i class="bx bx-user"></i></span><div><small>Paso 3 de 3</small><h3>¿A nombre de quién?</h3><p>Usaremos estos datos para confirmar tu solicitud.</p></div></div>
                        <div class="reservation-summary">
                            <span><i class="bx bx-calendar"></i><span><small>Fecha</small><strong x-text="formatDate(selectedDate)"></strong></span></span>
                            <span><i class="bx bx-time-five"></i><span><small>Hora</small><strong x-text="selectedSlot?.label || selectedTime"></strong></span></span>
                            <span><i class="bx bx-group"></i><span><small>Personas</small><strong x-text="guests"></strong></span></span>
                        </div>
                        <div class="reservation-waitlist-chip" x-show="waitlistRequired"><i class="bx bx-hourglass"></i> Solicitud en lista de espera</div>
                        <div class="reservation-fields">
                            <label><span>Nombre de la reservación *</span><div><i class="bx bx-user"></i><input type="text" wire:model="customerName" autocomplete="name" maxlength="100" required placeholder="Nombre completo"></div>@error('customerName')<small role="alert">{{ $message }}</small>@enderror</label>
                            <label><span>Teléfono *</span><div><i class="bx bx-phone"></i><input type="tel" wire:model="customerPhone" autocomplete="tel" maxlength="30" required placeholder="55 1234 5678"></div>@error('customerPhone')<small role="alert">{{ $message }}</small>@enderror</label>
                            <label><span>Correo electrónico</span><div><i class="bx bx-envelope"></i><input type="email" wire:model="customerEmail" autocomplete="email" maxlength="160" placeholder="nombre@correo.com"></div>@error('customerEmail')<small role="alert">{{ $message }}</small>@enderror</label>
                            <label><span>Ocasión</span><div><i class="bx bx-party"></i><select wire:model="occasion"><option value="">Visita casual</option><option>Cumpleaños</option><option>Aniversario</option><option>Reunión familiar</option><option>Reunión de trabajo</option><option>Otra celebración</option></select></div></label>
                            <label class="is-wide"><span>Indicaciones adicionales</span><div><i class="bx bx-note"></i><textarea wire:model="notes" rows="2" maxlength="500" placeholder="Alergias, accesibilidad u otro detalle"></textarea></div></label>
                            <label class="reservation-honeypot" aria-hidden="true">Sitio web<input type="text" wire:model="website" tabindex="-1" autocomplete="off"></label>
                        </div>
                        <p class="reservation-privacy"><i class="bx bx-shield-quarter"></i> Tus datos se usarán únicamente para gestionar esta reservación.</p>
                    </form>
                    <p class="reservation-error reservation-error--client" x-show="clientError" x-text="clientError" role="alert"></p>
                </div>

                <footer class="reservation-modal__footer">
                    <template x-if="step === 1"><button type="button" class="reservation-primary" @click="nextFromDate()" :disabled="!selectedDate">Elegir horario <i class="bx bx-right-arrow-alt"></i></button></template>
                    <template x-if="step === 2"><div class="reservation-modal__footer-actions"><button type="button" class="reservation-back" @click="step = 1"><i class="bx bx-left-arrow-alt"></i> Atrás</button><button type="button" class="reservation-primary" @click="nextFromTime()" :disabled="!selectedTime || (waitlistRequired && !acceptWaitlist)">Continuar <i class="bx bx-right-arrow-alt"></i></button></div></template>
                    <template x-if="step === 3"><div class="reservation-modal__footer-actions"><button type="button" class="reservation-back" @click="step = 2"><i class="bx bx-left-arrow-alt"></i> Atrás</button><button type="submit" form="reservation-customer-form" class="reservation-primary" wire:loading.attr="disabled" wire:target="submit"><span wire:loading.remove wire:target="submit" x-text="waitlistRequired ? 'Solicitar espera' : 'Solicitar mesa'"></span><span wire:loading wire:target="submit">Registrando…</span><i class="bx bx-check"></i></button></div></template>
                </footer>
            @endif
        </section>
    </div>
</div>
