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
            <section class="info-status-card info-status-card--enhanced info-status-card--{{ $openingStatus['is_open'] ? 'open' : 'closed' }}" aria-labelledby="current-hours-title">
                <div class="info-status-card__heading">
                    <span class="info-status-card__icon" aria-hidden="true"><i class="bx {{ $openingStatus['is_open'] ? 'bx-check-circle' : 'bx-moon' }}"></i></span>
                    <div>
                        <small>Estado actual</small>
                        <h2 id="current-hours-title">{{ $openingStatus['label'] }}</h2>
                        <p>{{ $openingStatus['detail'] }}</p>
                    </div>
                </div>

                @if($openingStatus['opens_at'] && $openingStatus['closes_at'])
                    <div class="info-status-window" aria-label="Horario {{ $openingStatus['day_label'] }}: abre a las {{ $openingStatus['opens_at'] }} y cierra a las {{ $openingStatus['closes_at'] }}">
                        <div>
                            <span><i class="bx bx-sun" aria-hidden="true"></i> Abre</span>
                            <time datetime="{{ $openingStatus['opens_at'] }}">{{ $openingStatus['opens_at'] }}</time>
                        </div>
                        <i class="bx bx-right-arrow-alt" aria-hidden="true"></i>
                        <div>
                            <span><i class="bx bx-moon" aria-hidden="true"></i> Cierra</span>
                            <time datetime="{{ $openingStatus['closes_at'] }}">{{ $openingStatus['closes_at'] }}</time>
                        </div>
                    </div>
                    <p class="info-status-card__note">
                        <i class="bx bx-calendar-check" aria-hidden="true"></i>
                        Horario de {{ strtolower($openingStatus['day_label']) }}{{ $openingStatus['closes_next_day'] ? ' · cierre al día siguiente' : '' }}
                    </p>
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
                                    <time datetime="{{ $day['opens'] }}">{{ $day['opens'] }}</time>
                                </div>
                                <i class="bx bx-right-arrow-alt info-schedule__arrow" aria-hidden="true"></i>
                                <div class="info-schedule__time">
                                    <span><i class="bx bx-moon" aria-hidden="true"></i> Cierra</span>
                                    <time datetime="{{ $day['closes'] }}">{{ $day['closes'] }}</time>
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
