<x-public-menu.info-layout
    :business="$business"
    :menu-settings="$menuSettings"
    :opening-status="$openingStatus"
    title="Contacto y redes"
    subtitle="Encuentra nuestros canales oficiales y la información para visitarnos."
    icon="bx-message-rounded-dots"
>
    <div class="menu-container info-contact info-contact--enhanced">
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

        <section class="info-contact__details" aria-labelledby="contact-details-title">
            <div class="info-section-heading">
                <span><i class="bx bx-conversation" aria-hidden="true"></i></span>
                <div>
                    <small>Contacto directo</small>
                    <h2 id="contact-details-title">Estamos para ayudarte</h2>
                    <p>Elige el medio que prefieras para comunicarte con nosotros.</p>
                </div>
            </div>
            <div class="info-contact__list">
                @if ($business->phone)
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $business->phone) }}">
                        <i class="bx bx-phone" aria-hidden="true"></i>
                        <span><small>Teléfono</small><strong>{{ $business->phone }}</strong></span>
                        <i class="bx bx-right-arrow-alt" aria-hidden="true"></i>
                    </a>
                @endif
                @if ($business->whatsapp)
                    <a href="https://wa.me/{{ preg_replace('/\D/', '', $business->whatsapp) }}" target="_blank" rel="noopener noreferrer">
                        <i class="bx bxl-whatsapp" aria-hidden="true"></i>
                        <span><small>WhatsApp</small><strong>{{ $business->whatsapp }}</strong></span>
                        <i class="bx bx-link-external" aria-hidden="true"></i>
                    </a>
                @endif
                @if ($business->email)
                    <a href="mailto:{{ $business->email }}">
                        <i class="bx bx-envelope" aria-hidden="true"></i>
                        <span><small>Correo</small><strong>{{ $business->email }}</strong></span>
                        <i class="bx bx-right-arrow-alt" aria-hidden="true"></i>
                    </a>
                @endif
                @if (! $business->phone && ! $business->whatsapp && ! $business->email)
                    <div class="info-contact__empty">
                        <i class="bx bx-message-rounded-x" aria-hidden="true"></i>
                        <span><small>Próximamente</small><strong>Estamos configurando nuestros medios de contacto.</strong></span>
                    </div>
                @endif
            </div>
        </section>

        <section class="info-social-grid" aria-labelledby="social-title">
            <div class="info-section-heading">
                <span><i class="bx bx-share-alt" aria-hidden="true"></i></span>
                <div>
                    <small>Comunidad</small>
                    <h2 id="social-title">Síguenos</h2>
                    <p>Descubre novedades, platillos y momentos del restaurante.</p>
                </div>
            </div>
            <div>
                @if ($business->instagram_url)<a href="{{ $business->instagram_url }}" target="_blank" rel="noopener noreferrer"><i class="bx bxl-instagram" aria-hidden="true"></i><span>Instagram</span><i class="bx bx-link-external" aria-hidden="true"></i></a>@endif
                @if ($business->facebook_url)<a href="{{ $business->facebook_url }}" target="_blank" rel="noopener noreferrer"><i class="bx bxl-facebook" aria-hidden="true"></i><span>Facebook</span><i class="bx bx-link-external" aria-hidden="true"></i></a>@endif
                @if ($business->tiktok_url)<a href="{{ $business->tiktok_url }}" target="_blank" rel="noopener noreferrer"><i class="bx bxl-tiktok" aria-hidden="true"></i><span>TikTok</span><i class="bx bx-link-external" aria-hidden="true"></i></a>@endif
                @if (! $business->instagram_url && ! $business->facebook_url && ! $business->tiktok_url)<p class="info-social-grid__empty">Las redes sociales todavía no están configuradas.</p>@endif
            </div>
        </section>
    </div>
</x-public-menu.info-layout>
