@props(['business'])

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
