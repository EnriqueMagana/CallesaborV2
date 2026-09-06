<x-public-menu.info-layout
    :business="$business"
    :menu-settings="$menuSettings"
    :opening-status="$openingStatus"
    title="Horarios"
    subtitle="Consulta nuestros días y horas de atención antes de visitarnos."
    icon="bx-time-five"
>
    <div class="menu-container info-hours info-hours--enhanced">
        <div class="info-hours__aside">
            <section class="info-status-card info-status-card--enhanced info-status-card--{{ $hoursTimeline['state'] }}"
                aria-labelledby="current-hours-title"
                data-hours-timeline
                data-hours-state="{{ $hoursTimeline['state'] }}"
                data-hours-timezone="{{ $hoursTimeline['timezone'] }}"
                data-hours-business-date="{{ $hoursTimeline['business_date'] }}"
                data-hours-day-enabled="{{ $hoursTimeline['day_enabled'] ? 'true' : 'false' }}"
                data-hours-opens-at="{{ $hoursTimeline['opens_iso'] }}"
                data-hours-closes-at="{{ $hoursTimeline['closes_iso'] }}">
                <div class="info-current-time">
                    <span class="info-current-time__icon" aria-hidden="true"><i class="bx bx-time-five"></i></span>
                    <div>
                        <small>Hora actual · {{ $hoursTimeline['timezone_label'] }}</small>
                        <time datetime="{{ $hoursTimeline['now_iso'] }}" data-hours-clock>{{ $hoursTimeline['clock_label'] }}</time>
                        <span data-hours-date>{{ $hoursTimeline['date_label'] }}</span>
                    </div>
                </div>

                <div class="info-status-card__heading">
                    <span class="info-status-card__icon" aria-hidden="true"><i class="bx {{ $hoursTimeline['state'] === 'open' ? 'bx-check-circle' : ($hoursTimeline['state'] === 'upcoming' ? 'bx-time' : 'bx-moon') }}" data-hours-status-icon></i></span>
                    <div>
                        <small>Estado actual</small>
                        <h2 id="current-hours-title" data-hours-status-label>{{ $hoursTimeline['status_label'] }}</h2>
                        <p data-hours-status-detail>{{ $hoursTimeline['status_detail'] }}</p>
                    </div>
                </div>

                @if($hoursTimeline['day_enabled'])
                    <div class="info-service-progress">
                        <div class="info-service-progress__heading">
                            <span data-hours-progress-label>{{ match($hoursTimeline['state']) {
                                'open' => 'Jornada en curso',
                                'upcoming' => 'La jornada comienza pronto',
                                default => 'Jornada finalizada',
                            } }}</span>
                            <strong data-hours-progress-percent>{{ (int) round($hoursTimeline['progress'] * 100) }}%</strong>
                        </div>
                        <div class="info-service-progress__track" role="progressbar"
                            aria-label="Progreso de la jornada de {{ strtolower($hoursTimeline['day_label']) }}"
                            aria-valuemin="0" aria-valuemax="100"
                            aria-valuenow="{{ (int) round($hoursTimeline['progress'] * 100) }}"
                            aria-valuetext="{{ $hoursTimeline['status_label'] }}"
                            data-hours-progressbar>
                            <span style="--hours-progress: {{ $hoursTimeline['progress'] }}" data-hours-progress-fill></span>
                        </div>
                        <div class="info-service-progress__times">
                            <div>
                                <span><i class="bx bx-sun" aria-hidden="true"></i> Apertura</span>
                                <time datetime="{{ $hoursTimeline['opens_at'] }}">{{ $hoursTimeline['opens_label'] }}</time>
                            </div>
                            <div>
                                <span>Cierre <i class="bx bx-moon" aria-hidden="true"></i></span>
                                <time datetime="{{ $hoursTimeline['closes_at'] }}">{{ $hoursTimeline['closes_label'] }}</time>
                            </div>
                        </div>
                    </div>
                    <p class="info-status-card__note">
                        <i class="bx bx-calendar-check" aria-hidden="true"></i>
                        Jornada de {{ strtolower($hoursTimeline['day_label']) }}{{ $hoursTimeline['is_overnight'] ? ' · termina al día siguiente' : '' }}
                    </p>
                @else
                    <div class="info-service-progress info-service-progress--inactive">
                        <div class="info-service-progress__empty"><i class="bx bx-calendar-x" aria-hidden="true"></i><span><strong>Sin jornada programada hoy</strong><small>Revisa abajo cuál es nuestro próximo día de servicio.</small></span></div>
                    </div>
                @endif
            </section>

            @if($business->full_address)
                @if($business->map_link)
                    <a class="info-address info-address--link" href="{{ $business->map_link }}" target="_blank" rel="noopener noreferrer"><i class="bx bx-map" aria-hidden="true"></i><div><small>Nuestra ubicación</small><strong>{{ $business->full_address }}</strong></div><i class="bx bx-link-external" aria-hidden="true"></i></a>
                @else
                    <aside class="info-address"><i class="bx bx-map" aria-hidden="true"></i><div><small>Nuestra ubicación</small><strong>{{ $business->full_address }}</strong></div></aside>
                @endif
            @endif
        </div>

        <section class="info-schedule" aria-labelledby="weekly-hours-title">
            <div class="info-section-heading">
                <span><i class="bx bx-calendar" aria-hidden="true"></i></span>
                <div>
                    <h2 id="weekly-hours-title">Semana completa</h2>
                    <p>Horario actualizado desde la administración del restaurante.</p>
                </div>
            </div>
            <dl class="info-schedule__list">
                @foreach($weeklySchedule as $day)
                    <div class="{{ $day['enabled'] ? 'is-open' : 'is-closed' }} {{ $day['is_today'] ? 'is-today' : '' }}">
                        <dt>
                            <span>{{ $day['label'] }}</span>
                            @if($day['is_today'])<small>Hoy</small>@endif
                        </dt>
                        <dd>
                            @if($day['enabled'])
                                <div class="info-schedule__time">
                                    <span><i class="bx bx-sun" aria-hidden="true"></i> Abre</span>
                                    <time datetime="{{ $day['opens'] }}">{{ $day['opens_label'] }}</time>
                                </div>
                                <i class="bx bx-right-arrow-alt info-schedule__arrow" aria-hidden="true"></i>
                                <div class="info-schedule__time">
                                    <span><i class="bx bx-moon" aria-hidden="true"></i> Cierra</span>
                                    <time datetime="{{ $day['closes'] }}">{{ $day['closes_label'] }}</time>
                                </div>
                                @if($day['is_overnight'])<small class="info-schedule__overnight">Día siguiente</small>@endif
                            @else
                                <span class="info-schedule__closed"><i class="bx bx-minus-circle" aria-hidden="true"></i> Cerrado</span>
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
            <p class="info-schedule__notice"><i class="bx bx-info-circle" aria-hidden="true"></i> Los horarios pueden cambiar en días festivos.</p>
        </section>
    </div>
</x-public-menu.info-layout>
