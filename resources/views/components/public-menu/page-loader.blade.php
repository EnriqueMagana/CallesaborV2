@props(['business'])

<div class="home-preloader" data-home-preloader role="status" aria-live="polite"
    aria-label="Cargando {{ $business->business_name }}">
    <div class="home-preloader__chase" aria-hidden="true">
        @for ($dot = 0; $dot < 6; $dot++)
            <span></span>
        @endfor
    </div>
</div>
