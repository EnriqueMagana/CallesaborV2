<x-public-menu.info-layout
    :business="$business"
    :opening-status="$openingStatus"
    title="Contacto y redes"
    subtitle="Encuentra nuestros canales oficiales y la información para visitarnos."
    icon="bx-message-rounded-dots"
>
    <div class="menu-container info-contact">
        <section class="info-contact__details">
            <div class="info-section-heading"><span><i class="bx bx-store" aria-hidden="true"></i></span><div><h2>Información del restaurante</h2><p>Comunícate únicamente a través de estos medios oficiales.</p></div></div>
            <div class="info-contact__list">
                @if($business->phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $business->phone) }}"><i class="bx bx-phone"></i><span><small>Teléfono</small><strong>{{ $business->phone }}</strong></span><i class="bx bx-right-arrow-alt"></i></a>@endif
                @if($business->whatsapp)<a href="https://wa.me/{{ preg_replace('/\D/', '', $business->whatsapp) }}" target="_blank" rel="noopener noreferrer"><i class="bx bxl-whatsapp"></i><span><small>WhatsApp</small><strong>{{ $business->whatsapp }}</strong></span><i class="bx bx-link-external"></i></a>@endif
                @if($business->email)<a href="mailto:{{ $business->email }}"><i class="bx bx-envelope"></i><span><small>Correo</small><strong>{{ $business->email }}</strong></span><i class="bx bx-right-arrow-alt"></i></a>@endif
                @if($business->full_address)
                    @if($business->map_link)<a href="{{ $business->map_link }}" target="_blank" rel="noopener noreferrer"><i class="bx bx-map"></i><span><small>Dirección</small><strong>{{ $business->full_address }}</strong></span><i class="bx bx-link-external"></i></a>
                    @else<div><i class="bx bx-map"></i><span><small>Dirección</small><strong>{{ $business->full_address }}</strong></span></div>@endif
                @endif
            </div>
        </section>
        <section class="info-social-grid" aria-labelledby="social-title">
            <div class="info-section-heading"><span><i class="bx bx-share-alt" aria-hidden="true"></i></span><div><h2 id="social-title">Síguenos</h2><p>Novedades y contenido en nuestras redes.</p></div></div>
            <div>
                @if($business->instagram_url)<a href="{{ $business->instagram_url }}" target="_blank" rel="noopener noreferrer"><i class="bx bxl-instagram"></i><span>Instagram</span><i class="bx bx-link-external"></i></a>@endif
                @if($business->facebook_url)<a href="{{ $business->facebook_url }}" target="_blank" rel="noopener noreferrer"><i class="bx bxl-facebook"></i><span>Facebook</span><i class="bx bx-link-external"></i></a>@endif
                @if($business->tiktok_url)<a href="{{ $business->tiktok_url }}" target="_blank" rel="noopener noreferrer"><i class="bx bxl-tiktok"></i><span>TikTok</span><i class="bx bx-link-external"></i></a>@endif
                @if(! $business->instagram_url && ! $business->facebook_url && ! $business->tiktok_url)<p class="info-social-grid__empty">Las redes sociales todavía no están configuradas.</p>@endif
            </div>
        </section>
    </div>
</x-public-menu.info-layout>
