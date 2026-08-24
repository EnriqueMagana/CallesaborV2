<div class="digital-menu-page">
    @if (session('success'))
        <div class="biz-toast" role="status" aria-live="polite"><i class="bx bx-check-circle"></i>{{ session('success') }}</div>
    @endif

    <header class="digital-menu-header">
        <span class="digital-menu-header__icon"><i class="bx bx-mobile-alt"></i></span>
        <div>
            <span class="biz-eyebrow">Restaurante · Experiencia pública</span>
            <h1>Menú digital</h1>
            <p>Ordena la portada, los favoritos, las categorías y la galería desde una sola fuente.</p>
        </div>
        <a href="{{ route('public.menu') }}" target="_blank" rel="noopener noreferrer">
            <i class="bx bx-show"></i><span>Vista pública</span><i class="bx bx-link-external"></i>
        </a>
    </header>

    <form wire:submit="save" class="digital-menu-editor">
        <nav class="digital-menu-nav" aria-label="Secciones del menú digital">
            @foreach ([
                'overview' => ['bx-slider-alt', 'General', 'Visibilidad y color'],
                'banners' => ['bx-slideshow', 'Banners', 'Carrusel de portada'],
                'featured' => ['bx-star', 'Favoritos', 'Ranking de productos'],
                'categories' => ['bx-category', 'Categorías', 'Estilo de navegación'],
                'gallery' => ['bx-images', 'Galería', 'Fotos públicas'],
            ] as $key => $item)
                <button type="button" wire:click="setSection('{{ $key }}')"
                    class="{{ $activeSection === $key ? 'is-active' : '' }}"
                    aria-pressed="{{ $activeSection === $key ? 'true' : 'false' }}">
                    <i class="bx {{ $item[0] }}"></i><span><strong>{{ $item[1] }}</strong><small>{{ $item[2] }}</small></span><i class="bx bx-chevron-right"></i>
                </button>
            @endforeach
        </nav>

        <section class="digital-menu-panel">
            @if ($activeSection === 'overview')
                <header class="digital-menu-section-heading">
                    <span>01</span><div><h2>Composición general</h2><p>Activa únicamente las secciones que aportan valor al cliente.</p></div>
                </header>
                <div class="digital-menu-toggle-grid">
                    @foreach ([
                        ['showBanners', 'bx-slideshow', 'Carrusel de banners', 'Presentación visual superior.'],
                        ['showFeatured', 'bx-trophy', 'Favoritos ordenados', 'Ranking manual con posiciones.'],
                        ['showCategories', 'bx-category-alt', 'Navegación por categorías', 'Accesos rápidos al catálogo.'],
                        ['showGallery', 'bx-images', 'Galería pública', 'Fotos en portada, menú y galería.'],
                    ] as $toggle)
                        <label class="digital-menu-toggle">
                            <input type="checkbox" wire:model.live="{{ $toggle[0] }}">
                            <span class="digital-menu-toggle__icon"><i class="bx {{ $toggle[1] }}"></i></span>
                            <span><strong>{{ $toggle[2] }}</strong><small>{{ $toggle[3] }}</small></span>
                            <span class="digital-menu-switch" aria-hidden="true"></span>
                        </label>
                    @endforeach
                </div>
                <div class="digital-menu-color">
                    <label for="digital-menu-color" style="--preview-color: {{ $primaryColor }}">
                        <i class="bx bx-palette"></i><span><strong>Color principal</strong><small>Botones, rangos, foco y estados activos.</small></span>
                    </label>
                    <div><input id="digital-menu-color" type="color" wire:model.live="primaryColor"><code>{{ strtoupper($primaryColor) }}</code></div>
                </div>
                @error('primaryColor')<p class="biz-form-error" role="alert">{{ $message }}</p>@enderror
            @elseif ($activeSection === 'banners')
                <header class="digital-menu-section-heading">
                    <span>02</span><div><h2>Carrusel de banners</h2><p>La posición superior define el orden de aparición.</p></div>
                    <strong>{{ count($bannerPaths) + count($bannerUploads) }}/{{ $this->maxBanners }}</strong>
                </header>
                <div class="digital-menu-inline-settings">
                    <label><input type="checkbox" wire:model.live="showBanners"><span><strong>Mostrar carrusel</strong><small>Si está desactivado se conserva el encabezado sin fotografías.</small></span></label>
                    <label><input type="checkbox" wire:model.live="autoplayBanners"><span><strong>Reproducción automática</strong><small>Siempre incluye controles manuales y pausa.</small></span></label>
                    <label><span><strong>Duración por banner</strong><small>Entre 3 y 12 segundos.</small></span><select wire:model="bannerIntervalSeconds">@for($second = 3; $second <= 12; $second++)<option value="{{ $second }}">{{ $second }} s</option>@endfor</select></label>
                </div>
                <div class="digital-menu-media-list">
                    @foreach ($bannerPaths as $index => $item)
                        <article wire:key="banner-saved-{{ $index }}">
                            <div class="digital-menu-media-list__preview">
                                <img src="{{ Storage::url($item['path']) }}" alt="{{ $item['alt'] ?: 'Banner '.($index + 1) }}">
                                <b>{{ $index + 1 }}</b>
                            </div>
                            <label><span>Descripción accesible</span><input type="text" wire:model.blur="bannerPaths.{{ $index }}.alt" maxlength="120" placeholder="Ej. Hamburguesa especial de temporada"></label>
                            <div class="digital-menu-order-actions">
                                <button type="button" wire:click="moveBanner({{ $index }}, -1)" @disabled($loop->first) aria-label="Mover banner hacia arriba"><i class="bx bx-up-arrow-alt"></i></button>
                                <button type="button" wire:click="moveBanner({{ $index }}, 1)" @disabled($loop->last) aria-label="Mover banner hacia abajo"><i class="bx bx-down-arrow-alt"></i></button>
                                <button type="button" wire:click="removeBanner({{ $index }})" aria-label="Quitar banner"><i class="bx bx-trash"></i></button>
                            </div>
                        </article>
                    @endforeach
                    @foreach ($bannerUploads as $index => $upload)
                        <article class="is-pending" wire:key="banner-pending-{{ $index }}">
                            <div class="digital-menu-media-list__preview"><img src="{{ $upload->temporaryUrl() }}" alt="Vista previa de banner nuevo"><b>Nuevo</b></div>
                            <label><span>Descripción accesible</span><input type="text" wire:model.blur="bannerUploadAlts.{{ $index }}" maxlength="120" placeholder="Describe la promoción o fotografía"></label>
                            <div class="digital-menu-order-actions"><button type="button" wire:click="removePendingBanner({{ $index }})" aria-label="Quitar banner nuevo"><i class="bx bx-x"></i></button></div>
                        </article>
                    @endforeach
                </div>
                @if (count($bannerPaths) + count($bannerUploads) < $this->maxBanners)
                    <label class="digital-menu-upload"><input type="file" wire:model.live="bannerUploads" accept="image/png,image/jpeg,image/webp" multiple><i class="bx bx-cloud-upload"></i><span><strong>Subir banners</strong><small>Formato horizontal recomendado 1600 × 640 px · máximo 6 MB.</small></span></label>
                @endif
                @error('bannerUploads')<p class="biz-form-error" role="alert">{{ $message }}</p>@enderror
                @error('bannerUploads.*')<p class="biz-form-error" role="alert">{{ $message }}</p>@enderror
            @elseif ($activeSection === 'featured')
                <header class="digital-menu-section-heading">
                    <span>03</span><div><h2>Favoritos con ranking</h2><p>El primer producto mostrará el número 1; usa las flechas para decidir 2, 3 y siguientes.</p></div>
                    <strong>{{ count($featuredProductIds) }}/{{ $this->maxFeatured }}</strong>
                </header>
                <label class="digital-menu-section-switch"><input type="checkbox" wire:model.live="showFeatured"><span><strong>Mostrar favoritos</strong><small>Oculta o publica toda la sección sin perder el orden.</small></span><span class="digital-menu-switch"></span></label>
                <div class="digital-menu-ranking" aria-label="Orden actual de favoritos">
                    @forelse ($this->selectedProducts as $index => $product)
                        <article wire:key="ranked-product-{{ $product->id }}">
                            <b>{{ $index + 1 }}</b>
                            <span class="digital-menu-ranking__image">@if($product->image)<img src="{{ Storage::url($product->image) }}" alt="">@else<i class="bx bx-dish"></i>@endif</span>
                            <span><strong>{{ $product->name }}</strong><small>{{ $product->category?->name ?? 'Sin categoría' }} · ${{ number_format((float) $product->price, 2) }}</small></span>
                            <div class="digital-menu-order-actions">
                                <button type="button" wire:click="moveFeatured({{ $index }}, -1)" @disabled($loop->first) aria-label="Subir {{ $product->name }}"><i class="bx bx-up-arrow-alt"></i></button>
                                <button type="button" wire:click="moveFeatured({{ $index }}, 1)" @disabled($loop->last) aria-label="Bajar {{ $product->name }}"><i class="bx bx-down-arrow-alt"></i></button>
                                <button type="button" wire:click="toggleFeaturedProduct({{ $product->id }})" aria-label="Quitar {{ $product->name }}"><i class="bx bx-x"></i></button>
                            </div>
                        </article>
                    @empty
                        <div class="digital-menu-empty"><i class="bx bx-star"></i><strong>Aún no hay favoritos</strong><span>Selecciona productos abajo; su orden se conservará.</span></div>
                    @endforelse
                </div>
                <div class="digital-menu-products">
                    @foreach ($this->availableProducts as $product)
                        @php($selected = in_array($product->id, array_map('intval', $featuredProductIds), true))
                        <button type="button" wire:click="toggleFeaturedProduct({{ $product->id }})" class="{{ $selected ? 'is-selected' : '' }}" aria-pressed="{{ $selected ? 'true' : 'false' }}" wire:key="available-product-{{ $product->id }}">
                            <span>@if($product->image)<img src="{{ Storage::url($product->image) }}" alt="">@else<i class="bx bx-dish"></i>@endif</span>
                            <span><strong>{{ $product->name }}</strong><small>{{ $product->category?->name ?? 'Sin categoría' }}</small></span><i class="bx {{ $selected ? 'bx-check' : 'bx-plus' }}"></i>
                        </button>
                    @endforeach
                </div>
                @error('featuredProductIds')<p class="biz-form-error" role="alert">{{ $message }}</p>@enderror
            @elseif ($activeSection === 'categories')
                <header class="digital-menu-section-heading">
                    <span>04</span><div><h2>Presentación de categorías</h2><p>El catálogo conserva sus categorías; aquí decides cómo se navegan.</p></div>
                </header>
                <label class="digital-menu-section-switch"><input type="checkbox" wire:model.live="showCategories"><span><strong>Mostrar accesos de categorías</strong><small>Permite saltar rápidamente a cada grupo de productos.</small></span><span class="digital-menu-switch"></span></label>
                <fieldset class="digital-menu-style-options">
                    <legend>Estilo de categorías</legend>
                    <label class="{{ $categoryStyle === 'cards' ? 'is-selected' : '' }}">
                        <input type="radio" wire:model.live="categoryStyle" value="cards"><span class="digital-menu-style-preview digital-menu-style-preview--cards"><i class="bx bx-grid-alt"></i><i class="bx bx-dish"></i><i class="bx bx-drink"></i></span><span><strong>Tarjetas compactas</strong><small>Icono, nombre y cantidad dentro de una tarjeta.</small></span><i class="bx bx-check-circle"></i>
                    </label>
                    <label class="{{ $categoryStyle === 'circles' ? 'is-selected' : '' }}">
                        <input type="radio" wire:model.live="categoryStyle" value="circles"><span class="digital-menu-style-preview digital-menu-style-preview--circles"><i class="bx bx-grid-alt"></i><i class="bx bx-dish"></i><i class="bx bx-drink"></i></span><span><strong>Círculos visuales</strong><small>Estilo móvil como la referencia, usando imagen o icono.</small></span><i class="bx bx-check-circle"></i>
                    </label>
                </fieldset>
            @else
                <header class="digital-menu-section-heading">
                    <span>05</span><div><h2>Galería pública</h2><p>Publica fotografías y decide si la sección aparece para los clientes.</p></div>
                    <strong>{{ count($galleryPaths) + count($galleryUploads) }}/{{ $this->maxGalleryImages }}</strong>
                </header>
                <label class="digital-menu-section-switch"><input type="checkbox" wire:model.live="showGallery"><span><strong>Galería activa</strong><small>Controla portada, menú, pie de página y página de galería.</small></span><span class="digital-menu-switch"></span></label>
                <div class="digital-menu-media-list digital-menu-media-list--gallery">
                    @foreach ($galleryPaths as $index => $item)
                        <article wire:key="digital-gallery-{{ $index }}">
                            <div class="digital-menu-media-list__preview"><img src="{{ Storage::url($item['path']) }}" alt="{{ $item['caption'] ?: 'Fotografía '.($index + 1) }}"><b>{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</b></div>
                            <label><span>Pie de foto</span><input type="text" wire:model.blur="galleryPaths.{{ $index }}.caption" maxlength="120" placeholder="Ej. Terraza principal"></label>
                            <div class="digital-menu-order-actions">
                                <button type="button" wire:click="moveGalleryImage({{ $index }}, -1)" @disabled($loop->first) aria-label="Mover fotografía hacia arriba"><i class="bx bx-up-arrow-alt"></i></button>
                                <button type="button" wire:click="moveGalleryImage({{ $index }}, 1)" @disabled($loop->last) aria-label="Mover fotografía hacia abajo"><i class="bx bx-down-arrow-alt"></i></button>
                                <button type="button" wire:click="removeGalleryImage({{ $index }})" aria-label="Quitar fotografía"><i class="bx bx-trash"></i></button>
                            </div>
                        </article>
                    @endforeach
                    @foreach ($galleryUploads as $index => $upload)
                        <article class="is-pending" wire:key="digital-gallery-pending-{{ $index }}">
                            <div class="digital-menu-media-list__preview"><img src="{{ $upload->temporaryUrl() }}" alt="Vista previa de nueva fotografía"><b>Nueva</b></div>
                            <label><span>Pie de foto</span><input type="text" wire:model.blur="galleryUploadCaptions.{{ $index }}" maxlength="120" placeholder="Describe la fotografía"></label>
                            <div class="digital-menu-order-actions"><button type="button" wire:click="removePendingGalleryImage({{ $index }})" aria-label="Quitar fotografía nueva"><i class="bx bx-x"></i></button></div>
                        </article>
                    @endforeach
                </div>
                @if (count($galleryPaths) + count($galleryUploads) < $this->maxGalleryImages)
                    <label class="digital-menu-upload"><input type="file" wire:model.live="galleryUploads" accept="image/png,image/jpeg,image/webp" multiple><i class="bx bx-images"></i><span><strong>Agregar fotografías</strong><small>JPG, PNG o WebP · máximo 6 MB por imagen.</small></span></label>
                @endif
                @error('galleryUploads')<p class="biz-form-error" role="alert">{{ $message }}</p>@enderror
                @error('galleryUploads.*')<p class="biz-form-error" role="alert">{{ $message }}</p>@enderror
            @endif

            <footer class="digital-menu-actions">
                <span><i class="bx bx-info-circle"></i>Los cambios se publican al guardar.</span>
                <button type="submit" wire:loading.attr="disabled" wire:target="save,bannerUploads,galleryUploads"><span wire:loading.remove wire:target="save"><i class="bx bx-save"></i>Guardar menú digital</span><span wire:loading wire:target="save">Guardando…</span></button>
            </footer>
        </section>
    </form>
</div>
