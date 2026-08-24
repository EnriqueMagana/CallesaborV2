@props(['business', 'menuSettings' => null])

<footer class="menu-footer menu-footer--restaurant" id="pie-de-pagina">
    <div class="menu-container menu-footer__top">
        <section class="menu-footer__about menu-footer__brand" aria-labelledby="footer-brand-title">
            <a class="menu-footer__logo" href="{{ route('public.home') }}" aria-label="Inicio de {{ $business->business_name }}">
                @if($business->logo_path)
                    <img src="{{ Storage::url($business->logo_path) }}" alt="Logo de {{ $business->business_name }}" width="150" height="86" loading="lazy">
                @else
                    <img src="{{ asset('assets/img/restaurant/logo_light.png') }}" alt="Logo de {{ $business->business_name }}" width="150" height="86" loading="lazy">
                @endif
            </a>
            <h2 id="footer-brand-title">{{ $business->business_name }}</h2>
            <p>Sabores auténticos, platillos preparados con cuidado y una experiencia pensada para disfrutar y compartir.</p>
            <nav class="menu-socials" aria-label="Redes sociales y contacto">
                @if($business->whatsapp)<a href="https://wa.me/{{ preg_replace('/\D/', '', $business->whatsapp) }}" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp"><i class="bx bxl-whatsapp" aria-hidden="true"></i></a>@endif
                @if($business->instagram_url)<a href="{{ $business->instagram_url }}" target="_blank" rel="noopener noreferrer" aria-label="Instagram"><i class="bx bxl-instagram" aria-hidden="true"></i></a>@endif
                @if($business->facebook_url)<a href="{{ $business->facebook_url }}" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><i class="bx bxl-facebook" aria-hidden="true"></i></a>@endif
                @if($business->tiktok_url)<a href="{{ $business->tiktok_url }}" target="_blank" rel="noopener noreferrer" aria-label="TikTok"><i class="bx bxl-tiktok" aria-hidden="true"></i></a>@endif
            </nav>
        </section>

        <nav class="menu-footer__column" aria-labelledby="footer-links-title">
            <h2 id="footer-links-title">Enlaces</h2>
            <ul>
                <li><a href="{{ route('public.home') }}"><i class="bx bx-chevron-right" aria-hidden="true"></i>Inicio</a></li>
                <li><a href="{{ route('public.menu') }}"><i class="bx bx-chevron-right" aria-hidden="true"></i>Nuestro menú</a></li>
                @if(! $menuSettings || $menuSettings->show_gallery)
                    <li><a href="{{ route('public.gallery') }}"><i class="bx bx-chevron-right" aria-hidden="true"></i>Galería</a></li>
                @endif
                <li><a href="{{ route('public.contact') }}"><i class="bx bx-chevron-right" aria-hidden="true"></i>Contáctanos</a></li>
                @if($business->whatsapp)
                    <li><a href="https://wa.me/{{ preg_replace('/\D/', '', $business->whatsapp) }}" target="_blank" rel="noopener noreferrer"><i class="bx bxl-whatsapp" aria-hidden="true"></i>Escríbenos por WhatsApp</a></li>
                @endif
            </ul>
        </nav>

        <section class="menu-footer__column" aria-labelledby="footer-contact-title">
            <h2 id="footer-contact-title">Información de contacto</h2>
            <ul class="menu-footer__contact">
                @if($business->full_address)
                    <li><i class="bx bx-map" aria-hidden="true"></i><span>{{ $business->full_address }}</span></li>
                @endif
                @if($business->phone)
                    <li><i class="bx bx-phone" aria-hidden="true"></i><a href="tel:{{ preg_replace('/[^0-9+]/', '', $business->phone) }}">{{ $business->phone }}</a></li>
                @endif
                @if($business->email)
                    <li><i class="bx bx-envelope" aria-hidden="true"></i><a href="mailto:{{ $business->email }}">{{ $business->email }}</a></li>
                @endif
                <li><i class="bx bx-time-five" aria-hidden="true"></i><a href="{{ route('public.hours') }}">Consultar horarios</a></li>
            </ul>
        </section>
    </div>

    <div class="menu-container menu-footer__bottom">
        <p>© {{ date('Y') }} <strong>{{ $business->business_name }}</strong>. Todos los derechos reservados.</p>
        <a href="{{ route('public.menu') }}">Ver menú <i class="bx bx-right-arrow-alt" aria-hidden="true"></i></a>
    </div>
</footer>
