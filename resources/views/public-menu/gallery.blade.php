<x-public-menu.info-layout
    :business="$business"
    :opening-status="$openingStatus"
    title="Galería"
    subtitle="Conoce nuestros platillos, espacios y la experiencia del restaurante."
    icon="bx-images"
>
    <div class="menu-container info-gallery">
        @forelse($galleryImages as $item)
            <figure>
                <img src="{{ Storage::url($item['path']) }}" alt="{{ $item['caption'] ?: 'Fotografía de '.$business->business_name }}" width="760" height="570" loading="{{ $loop->first ? 'eager' : 'lazy' }}" decoding="async">
                @if($item['caption'])
                    <figcaption>{{ $item['caption'] }}</figcaption>
                @endif
            </figure>
        @empty
            <div class="info-empty"><span><i class="bx bx-images" aria-hidden="true"></i></span><h2>La galería estará disponible pronto</h2><p>Estamos preparando nuevas fotografías para ti.</p><a href="{{ route('public.menu') }}">Volver al menú</a></div>
        @endforelse
    </div>
</x-public-menu.info-layout>
