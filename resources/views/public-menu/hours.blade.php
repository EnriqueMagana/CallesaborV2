<x-public-menu.info-layout
    :business="$business"
    :opening-status="$openingStatus"
    title="Horarios"
    subtitle="Consulta nuestros días y horas de atención antes de visitarnos."
    icon="bx-time-five"
>
    <div class="menu-container info-hours">
        <section class="info-status-card info-status-card--{{ $openingStatus['is_open'] ? 'open' : 'closed' }}">
            <span aria-hidden="true"><i class="bx {{ $openingStatus['is_open'] ? 'bx-check-circle' : 'bx-moon' }}"></i></span>
            <div><small>Estado actual</small><h2>{{ $openingStatus['label'] }}</h2><p>{{ $openingStatus['detail'] }}</p></div>
        </section>
        <section class="info-schedule" aria-labelledby="weekly-hours-title">
            <div class="info-section-heading"><span><i class="bx bx-calendar" aria-hidden="true"></i></span><div><h2 id="weekly-hours-title">Semana completa</h2><p>Los horarios pueden cambiar en días festivos.</p></div></div>
            <dl>
                @foreach($business->business_hours ?: \App\Models\BusinessSetting::DEFAULT_HOURS as $day)
                    <div class="{{ $day['enabled'] ? 'is-open' : 'is-closed' }}">
                        <dt>{{ $day['label'] }}</dt>
                        <dd>@if($day['enabled'])<span>Abierto</span><strong>{{ $day['opens'] }} – {{ $day['closes'] }}</strong>@else<span>Cerrado</span>@endif</dd>
                    </div>
                @endforeach
            </dl>
        </section>
        @if($business->full_address)
            @if($business->map_link)
                <a class="info-address info-address--link" href="{{ $business->map_link }}" target="_blank" rel="noopener noreferrer"><i class="bx bx-map" aria-hidden="true"></i><div><small>Nuestra ubicación</small><strong>{{ $business->full_address }}</strong></div><i class="bx bx-link-external" aria-hidden="true"></i></a>
            @else
                <aside class="info-address"><i class="bx bx-map" aria-hidden="true"></i><div><small>Nuestra ubicación</small><strong>{{ $business->full_address }}</strong></div></aside>
            @endif
        @endif
    </div>
</x-public-menu.info-layout>
