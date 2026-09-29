<x-public-menu.info-layout
    :business="$business"
    :menu-settings="$menuSettings"
    :opening-status="$openingStatus"
    title="Contacto"
    subtitle="Elige el medio que prefieras para comunicarte directamente con nosotros."
    icon="bx-message-rounded-dots"
>
    <div class="menu-container info-standalone info-standalone--contact" data-legacy-contact-router
        data-social-url="{{ route('public.social') }}" data-location-url="{{ route('public.location') }}">
        <section class="info-contact__details" aria-labelledby="contact-details-title">
            <div class="info-section-heading">
                <span><i class="bx bx-conversation" aria-hidden="true"></i></span>
                <div>
                    <small>Contacto directo</small>
                    <h2 id="contact-details-title">Estamos para ayudarte</h2>
                    <p>Llámanos, escríbenos por WhatsApp o envíanos un correo.</p>
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
    </div>
</x-public-menu.info-layout>
