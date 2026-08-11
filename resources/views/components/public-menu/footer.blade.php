@props(['business'])

<footer class="menu-footer">
    <div class="menu-container menu-footer__inner">
        <div class="menu-footer__brand">
            @if($business->logo_path)
                <img src="{{ Storage::url($business->logo_path) }}" alt="Logo de {{ $business->business_name }}" width="64" height="64" loading="lazy">
            @else
                <span><i class="bx bx-restaurant" aria-hidden="true"></i></span>
            @endif
            <div><strong>{{ $business->business_name }}</strong><p>Menú informativo. Disponibilidad y precios sujetos a cambios.</p></div>
        </div>
        <nav class="menu-socials" aria-label="Redes sociales y contacto">
            @if($business->whatsapp)<a href="https://wa.me/{{ preg_replace('/\D/', '', $business->whatsapp) }}" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp"><i class="bx bxl-whatsapp" aria-hidden="true"></i></a>@endif
            @if($business->instagram_url)<a href="{{ $business->instagram_url }}" target="_blank" rel="noopener noreferrer" aria-label="Instagram"><i class="bx bxl-instagram" aria-hidden="true"></i></a>@endif
            @if($business->facebook_url)<a href="{{ $business->facebook_url }}" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><i class="bx bxl-facebook" aria-hidden="true"></i></a>@endif
            @if($business->tiktok_url)<a href="{{ $business->tiktok_url }}" target="_blank" rel="noopener noreferrer" aria-label="TikTok"><i class="bx bxl-tiktok" aria-hidden="true"></i></a>@endif
        </nav>
        <small>© {{ date('Y') }} {{ $business->business_name }}</small>
    </div>
</footer>
