@props(['business', 'locationMap'])

<section class="info-location" aria-labelledby="location-title">
    <div class="info-section-heading info-location__heading">
        <span><i class="bx bx-map-alt" aria-hidden="true"></i></span>
        <div>
            <small>Visítanos</small>
            <h2 id="location-title">Encuentra el camino más fácil</h2>
            <p>Consulta nuestra ubicación y abre la ruta desde donde estés.</p>
        </div>
    </div>

    @if ($locationMap['embed_url'])
        <div class="info-location__map is-map-loading" data-contact-map-shell>
            <div class="info-location__map-loader" aria-hidden="true">
                <i class="bx bx-map-pin"></i>
                <span>Cargando mapa</span>
            </div>
            <iframe
                src="{{ $locationMap['embed_url'] }}"
                title="Ubicación de {{ $business->business_name }}"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
                allowfullscreen
                data-contact-map
            ></iframe>
        </div>
    @else
        <div class="info-location__empty">
            <span><i class="bx bx-map-pin" aria-hidden="true"></i></span>
            <div>
                <strong>Ubicación pendiente de configurar</strong>
                <p>Comunícate con nosotros y con gusto te ayudaremos a llegar.</p>
            </div>
        </div>
    @endif

    <div class="info-location__footer">
        <div class="info-location__address">
            <span><i class="bx bx-current-location" aria-hidden="true"></i></span>
            <div>
                <small>Nuestro destino</small>
                <strong>{{ $locationMap['destination'] ?: 'Solicita la ubicación por teléfono o WhatsApp.' }}</strong>
                @if ($locationMap['place_url'] && $locationMap['place_url'] !== $locationMap['directions_url'])
                    <a class="info-location__place-link" href="{{ $locationMap['place_url'] }}" target="_blank"
                        rel="noopener noreferrer">Ver punto guardado <i class="bx bx-link-external" aria-hidden="true"></i></a>
                @endif
            </div>
        </div>
        @if ($locationMap['directions_url'])
            <a class="info-location__directions" href="{{ $locationMap['directions_url'] }}" target="_blank"
                rel="noopener noreferrer" aria-label="Abrir en Google Maps la ruta hacia {{ $business->business_name }}"
                data-directions-link>
                <i class="bx bx-navigation" aria-hidden="true"></i>
                <span><strong data-directions-label>Cómo llegar</strong><small data-directions-help>Usar mi ubicación actual</small></span>
                <i class="bx bx-link-external" aria-hidden="true"></i>
            </a>
        @endif
    </div>
</section>
