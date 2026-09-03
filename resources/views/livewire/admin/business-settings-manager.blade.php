<div class="business-settings-page">
    @if (session('success'))
        <div class="biz-toast" role="status" aria-live="polite"><i class="bx bx-check-circle"></i>{{ session('success') }}
        </div>
    @endif

    <header class="biz-page-header">
        <div class="biz-page-header__mark"><i class="bx bx-buildings"></i></div>
        <div><span class="biz-eyebrow">Administración · Identidad y navegación</span>
            <h1>Configuración del negocio</h1>
            <p>Centraliza la sucursal, los documentos térmicos y la estructura del sistema.</p>
        </div>
        <span class="biz-branch-chip"><i class="bx bx-map-pin"></i>1 sucursal</span>
    </header>

    <nav class="biz-tabs {{ $canManageBusiness ? 'biz-tabs--three' : '' }}" aria-label="Secciones de configuración">
        @if ($canManageBusiness)
            <button type="button" wire:click="setTab('business')"
                class="{{ $activeTab === 'business' ? 'is-active' : '' }}"
                aria-current="{{ $activeTab === 'business' ? 'page' : 'false' }}"><i
                    class="bx bx-store"></i><span><strong>Datos del negocio</strong><small>Identidad, horarios y
                        medios</small></span></button>
            <button type="button" wire:click="setTab('tickets')"
                class="{{ $activeTab === 'tickets' ? 'is-active' : '' }}"
                aria-current="{{ $activeTab === 'tickets' ? 'page' : 'false' }}"><i
                    class="bx bx-receipt"></i><span><strong>Ticket Maker</strong><small>Plantillas y
                        previsualización</small></span></button>
        @endif
        @can('ver menu sidebar')
            <button type="button" wire:click="setTab('menu')" class="{{ $activeTab === 'menu' ? 'is-active' : '' }}"
                aria-current="{{ $activeTab === 'menu' ? 'page' : 'false' }}"><i
                    class="bx bx-list-ul"></i><span><strong>Menú lateral</strong><small>Jerarquía, iconos y
                        permisos</small></span></button>
        @endcan
    </nav>

    @if ($activeTab === 'business' && $canManageBusiness)
        <div class="business-editor">
            <aside class="business-editor__sections" aria-label="Apartados de datos del negocio">
                <div><span class="biz-eyebrow">Configuración</span>
                    <h2>Datos de la sucursal</h2>
                    <p>Completa cada grupo y guarda todos los cambios al finalizar.</p>
                </div>
                @foreach ([
        'identity' => ['01', 'bx-id-card', 'Identidad comercial', 'Nombre, plataforma y RFC'],
        'contact' => ['02', 'bx-map', 'Contacto y ubicación', 'Canales y domicilio'],
        'hours' => ['03', 'bx-time-five', 'Horarios', 'Apertura por día'],
        'social' => ['04', 'bx-share-alt', 'Redes sociales', 'Instagram, Facebook y TikTok'],
        'visual' => ['05', 'bx-image', 'Identidad visual', 'Logos de la sucursal'],
        'homepage' => ['06', 'bx-layout', 'Página principal', 'Titulares y presentación'],
    ] as $sectionKey => $section)
                    <button type="button" wire:click="setBusinessSection('{{ $sectionKey }}')"
                        class="{{ $businessSection === $sectionKey ? 'is-active' : '' }}"
                        aria-pressed="{{ $businessSection === $sectionKey ? 'true' : 'false' }}">
                        <span>{{ $section[0] }}</span><i
                            class="bx {{ $section[1] }}"></i><span><strong>{{ $section[2] }}</strong><small>{{ $section[3] }}</small></span><i
                            class="bx bx-chevron-right"></i>
                    </button>
                @endforeach
            </aside>

            <form wire:submit="saveBusiness" class="business-editor__form">
                @if ($businessSection === 'identity')
                    <div class="biz-section-heading">
                        <div><span>01</span>
                            <div>
                                <h2>Identidad comercial</h2>
                                <p>Información visible en la plataforma, accesos y documentos.</p>
                            </div>
                        </div>
                    </div>
                    <div class="biz-form-grid">
                        <x-business.field label="Nombre comercial" for="business-name" :error="$errors->first('businessName')"><input
                                id="business-name" type="text" wire:model.blur="businessName"
                                placeholder="Ej. Calle Sabor Centro" autocomplete="organization"
                                required></x-business.field>
                        <x-business.field label="Nombre de la plataforma" for="platform-name"
                            hint="Se mostrará en encabezados y accesos." :error="$errors->first('platformName')"><input id="platform-name"
                                type="text" wire:model.blur="platformName" placeholder="Ej. Calle Sabor POS"
                                required></x-business.field>
                        <x-business.field label="Razón social" for="legal-name"><input id="legal-name" type="text"
                                wire:model.blur="legalName" placeholder="Ej. Alimentos Calle Sabor, S.A. de C.V."
                                autocomplete="organization"></x-business.field>
                        <x-business.field label="RFC" for="business-rfc"><input id="business-rfc" type="text"
                                wire:model.blur="rfc" placeholder="Ej. ACS010101AB1" maxlength="20"
                                class="text-uppercase"></x-business.field>
                    </div>
                    <div class="business-section-note"><i class="bx bx-info-circle"></i><span><strong>Una sola fuente de
                                identidad</strong><small>Estos datos se reutilizan en el sidebar, POS, kiosco y tickets
                                configurados.</small></span></div>
                @elseif($businessSection === 'contact')
                    <div class="biz-section-heading">
                        <div><span>02</span>
                            <div>
                                <h2>Contacto y ubicación</h2>
                                <p>Información para clientes, delivery y documentos fiscales.</p>
                            </div>
                        </div>
                    </div>
                    <div class="biz-form-grid">
                        <x-business.field label="Teléfono" for="business-phone"><input id="business-phone"
                                type="tel" wire:model.blur="phone" placeholder="Ej. 55 1234 5678"
                                autocomplete="tel"></x-business.field>
                        <x-business.field label="WhatsApp" for="business-whatsapp"><input id="business-whatsapp"
                                type="tel" wire:model.blur="whatsapp"
                                placeholder="Ej. 55 9876 5432"></x-business.field>
                        <x-business.field label="Correo electrónico" for="business-email" :error="$errors->first('email')"><input
                                id="business-email" type="email" wire:model.blur="email"
                                placeholder="contacto@negocio.com" autocomplete="email"></x-business.field>
                        <x-business.field label="Sitio web" for="business-web" :error="$errors->first('website')"><input
                                id="business-web" type="url" wire:model.blur="website"
                                placeholder="https://www.negocio.com"></x-business.field>
                        <x-business.field label="Ubicación para Google Maps" for="business-address"
                            hint="Escribe la dirección completa; se convertirá automáticamente en un enlace de Maps."
                            full>
                            <div class="biz-input-with-icon"><i class="bx bx-map-pin"></i><input
                                    id="business-address" type="text" wire:model.blur="address"
                                    placeholder="Ej. C. 33 185A, Ticul, 97860 Ticul, Yuc."
                                    autocomplete="street-address"></div>
                        </x-business.field>
                        <x-business.field label="Ciudad o municipio (opcional)" for="business-city"><input
                                id="business-city" type="text" wire:model.blur="city" placeholder="Ej. Ticul"
                                autocomplete="address-level2"></x-business.field>
                        <x-business.field label="Estado (opcional)" for="business-state"><input id="business-state"
                                type="text" wire:model.blur="state" placeholder="Ej. Yucatán"
                                autocomplete="address-level1"></x-business.field>
                        <x-business.field label="Código postal" for="business-postal"><input id="business-postal"
                                type="text" wire:model.blur="postalCode" placeholder="Ej. 44100"
                                autocomplete="postal-code" maxlength="10" inputmode="numeric"></x-business.field>
                        <x-business.field label="Enlace personalizado de Google Maps (opcional)" for="business-maps"
                            hint="Solo úsalo si deseas reemplazar el enlace generado desde la dirección."
                            :error="$errors->first('mapsUrl')" full>
                            <div class="biz-input-with-icon"><i class="bx bx-link"></i><input id="business-maps"
                                    type="url" wire:model.blur="mapsUrl"
                                    placeholder="https://maps.app.goo.gl/..." inputmode="url"></div>
                        </x-business.field>
                    </div>
                @elseif($businessSection === 'hours')
                    <div class="biz-section-heading">
                        <div><span>03</span>
                            <div>
                                <h2>Horarios de la sucursal</h2>
                                <p>Configura la semana con controles claros y una respuesta inmediata.</p>
                            </div>
                        </div>
                    </div>
                    <div class="business-hours-summary">
                        <span><i class="bx bx-calendar-check"></i></span>
                        <div><strong>{{ collect($businessHours)->where('enabled', true)->count() }} días
                                abiertos</strong><small>El estado público se calcula automáticamente usando estos
                                horarios.</small></div>
                        <div class="business-hours-presets" aria-label="Configuraciones rápidas">
                            <button type="button" wire:click="setHoursPreset('weekdays')">Lun–Vie</button>
                            <button type="button" wire:click="setHoursPreset('everyday')">Todos</button>
                            <button type="button" wire:click="setHoursPreset('closed')">Cerrar todos</button>
                        </div>
                    </div>
                    <fieldset class="business-hours">
                        <legend class="visually-hidden">Horario semanal</legend>
                        @foreach ($businessHours as $index => $day)
                            <article class="business-hour {{ $day['enabled'] ? 'is-open' : '' }}"
                                wire:key="business-hour-{{ $day['key'] }}">
                                <label class="business-hour__switch" for="business-hour-{{ $day['key'] }}"><input
                                        id="business-hour-{{ $day['key'] }}" type="checkbox"
                                        wire:model.live="businessHours.{{ $index }}.enabled"><span
                                        aria-hidden="true"></span><span><strong>{{ $day['label'] }}</strong><small>{{ $day['enabled'] ? 'Sucursal abierta' : 'Sin servicio' }}</small></span></label>
                                @if ($day['enabled'])
                                    <div class="business-hour__range">
                                        <label><span>Abre</span><input type="time"
                                                wire:model.blur="businessHours.{{ $index }}.opens"
                                                aria-label="Hora de apertura del {{ $day['label'] }}"></label>
                                        <i class="bx bx-right-arrow-alt" aria-hidden="true"></i>
                                        <label><span>Cierra</span><input type="time"
                                                wire:model.blur="businessHours.{{ $index }}.closes"
                                                aria-label="Hora de cierre del {{ $day['label'] }}"></label>
                                    </div>
                                    <span class="business-hour__status"><i
                                            class="bx bx-check-circle"></i>Disponible</span>
                                @else
                                    <span class="business-hour__closed"><i class="bx bx-moon"></i>Cerrado</span>
                                @endif
                            </article>
                        @endforeach
                    </fieldset>
                    @error('businessHours.*')
                        <p class="biz-form-error" role="alert">{{ $message }}</p>
                    @enderror
                @elseif($businessSection === 'social')
                    <div class="biz-section-heading">
                        <div><span>04</span>
                            <div>
                                <h2>Redes sociales</h2>
                                <p>Enlaces oficiales que aparecerán al pie del menú público.</p>
                            </div>
                        </div>
                    </div>
                    <div class="biz-form-grid">
                        <x-business.field label="Instagram" for="business-instagram"
                            hint="Incluye https:// y el perfil completo." :error="$errors->first('instagramUrl')"><input
                                id="business-instagram" type="url" wire:model.blur="instagramUrl"
                                placeholder="https://instagram.com/tu-negocio"></x-business.field>
                        <x-business.field label="Facebook" for="business-facebook" :error="$errors->first('facebookUrl')"><input
                                id="business-facebook" type="url" wire:model.blur="facebookUrl"
                                placeholder="https://facebook.com/tu-negocio"></x-business.field>
                        <x-business.field label="TikTok" for="business-tiktok" :error="$errors->first('tiktokUrl')"><input
                                id="business-tiktok" type="url" wire:model.blur="tiktokUrl"
                                placeholder="https://tiktok.com/@tu-negocio"></x-business.field>
                        <div class="business-social-note"><i class="bx bxl-whatsapp"></i><span><strong>WhatsApp ya
                                    está conectado</strong><small>Se toma del número configurado en “Contacto y
                                    ubicación”.</small></span></div>
                    </div>
                    <div class="business-section-note"><i class="bx bx-link-external"></i><span><strong>Solo se
                                muestran canales configurados</strong><small>Los enlaces se abren en una pestaña nueva
                                con protección de privacidad.</small></span></div>
                @elseif($businessSection === 'visual')
                    <div class="biz-section-heading">
                        <div><span>05</span>
                            <div>
                                <h2>Identidad visual</h2>
                                <p>Previsualización inmediata y archivos optimizados para cada contexto.</p>
                            </div>
                        </div>
                    </div>
                    <div class="biz-media-grid">
                        <x-business.media-upload title="Logo principal" description="PNG, JPG o WebP. Máximo 4 MB."
                            model="logoUpload" :path="$logoPath" :upload="$logoUpload" />
                        <x-business.media-upload title="Logo para tickets"
                            description="Recomendado: monocromático y alto contraste." model="ticketLogoUpload"
                            :path="$ticketLogoPath" :upload="$ticketLogoUpload" />
                    </div>
                    @foreach (['logoUpload', 'ticketLogoUpload'] as $uploadError)
                        @error($uploadError)
                            <p class="biz-form-error" role="alert">{{ $message }}</p>
                        @enderror
                    @endforeach
                    <div class="business-section-note"><i class="bx bx-image-alt"></i><span><strong>Los banners ahora se administran en Menú digital</strong><small>Este apartado conserva únicamente los logos de identidad y de impresión.</small></span>
                    </div>
                @elseif($businessSection === 'appearance')
                    <div class="biz-section-heading">
                        <div><span>06</span>
                            <div>
                                <h2>Color principal del menú</h2>
                                <p>Personaliza los acentos sobre el fondo blanco de la vitrina pública.</p>
                            </div>
                        </div>
                    </div>
                    <div class="business-color-editor">
                        <label for="primary-color-picker" class="business-color-editor__preview"
                            style="--preview-color: {{ $primaryColor }}">
                            <span><i class="bx bx-palette"></i></span>
                            <strong>Vista previa del acento</strong>
                            <small>Botones, estados, etiquetas y foco accesible.</small>
                        </label>
                        <div>
                            <x-business.field label="Selecciona un color" for="primary-color-picker"
                                :error="$errors->first('primaryColor')">
                                <input id="primary-color-picker" type="color" wire:model.live="primaryColor"
                                    aria-describedby="primary-color-value">
                            </x-business.field>
                            <code id="primary-color-value">{{ strtoupper($primaryColor) }}</code>
                        </div>
                    </div>
                    <div class="business-section-note"><i class="bx bx-accessibility"></i><span><strong>La interfaz
                                mantiene fondo blanco y texto oscuro</strong><small>El color se usa como acento para
                                conservar claridad y una lectura cómoda.</small></span></div>
                @elseif($businessSection === 'homepage')
                    <div class="biz-section-heading">
                        <div><span>07</span>
                            <div>
                                <h2>Contenido de la página principal</h2>
                                <p>Edita los mensajes principales sin tocar código. Los campos vacíos conservan los
                                    textos predeterminados.</p>
                            </div>
                        </div>
                    </div>
                    <div class="biz-form-grid">
                        <x-business.field label="Titular principal" for="home-headline" hint="Máximo 180 caracteres."
                            :error="$errors->first('homeHeadline')" full>
                            <textarea id="home-headline" wire:model.blur="homeHeadline" maxlength="180" rows="3"
                                placeholder="Sabores que convierten una comida en un recuerdo."></textarea>
                        </x-business.field>
                        <x-business.field label="Descripción del hero" for="home-description" :error="$errors->first('homeDescription')" full>
                            <textarea id="home-description" wire:model.blur="homeDescription" maxlength="600" rows="4"
                                placeholder="Presenta la propuesta de tu restaurante y anima a explorar el menú."></textarea>
                        </x-business.field>
                        <x-business.field label="Etiqueta de introducción" for="home-intro-kicker"
                            :error="$errors->first('homeIntroKicker')"><input id="home-intro-kicker" type="text"
                                wire:model.blur="homeIntroKicker" maxlength="80"
                                placeholder="Hospitalidad con sabor local"></x-business.field>
                        <x-business.field label="Título de introducción" for="home-intro-title" :error="$errors->first('homeIntroTitle')"
                            full>
                            <textarea id="home-intro-title" wire:model.blur="homeIntroTitle" maxlength="180" rows="3"
                                placeholder="Una experiencia auténtica, pensada para disfrutarse sin prisa."></textarea>
                        </x-business.field>
                        <x-business.field label="Texto de introducción" for="home-intro-description"
                            :error="$errors->first('homeIntroDescription')" full>
                            <textarea id="home-intro-description" wire:model.blur="homeIntroDescription" maxlength="600" rows="4"
                                placeholder="Cuenta qué hace especial la visita a tu restaurante."></textarea>
                        </x-business.field>
                    </div>
                    <div class="business-section-note"><i class="bx bx-show"></i><span><strong>Contenido conectado a
                                la portada</strong><small>Logo, color, imágenes, productos, horarios y redes continúan
                                administrándose desde sus apartados actuales.</small></span></div>
                @elseif($businessSection === 'gallery')
                    <div class="biz-section-heading">
                        <div><span>07</span>
                            <div>
                                <h2>Galería pública</h2>
                                <p>Organiza hasta {{ \App\Livewire\Admin\BusinessSettingsManager::MAX_GALLERY_IMAGES }}
                                    fotografías con un breve pie de foto.</p>
                            </div>
                        </div>
                    </div>
                    <div class="business-gallery-toolbar">
                        <div><i class="bx bx-images"></i><span><strong>{{ count($galleryPaths) + count($galleryUploads) }}
                                    de
                                    {{ \App\Livewire\Admin\BusinessSettingsManager::MAX_GALLERY_IMAGES }}</strong><small>Fotografías
                                    utilizadas</small></span></div>
                        <p><i class="bx bx-info-circle"></i>El pie de foto también se utiliza como descripción
                            accesible en la página pública.</p>
                    </div>
                    <div class="business-gallery" aria-live="polite">
                        @foreach ($galleryPaths as $index => $item)
                            <article class="business-gallery__item" wire:key="gallery-saved-{{ $index }}">
                                <div class="business-gallery__media">
                                    <span class="business-gallery__skeleton" aria-hidden="true"></span>
                                    <img src="{{ Storage::url($item['path']) }}"
                                        alt="{{ $item['caption'] ?: 'Fotografía ' . ($index + 1) . ' de la galería' }}"
                                        loading="lazy" decoding="async">
                                    <span
                                        class="business-gallery__number">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                                    <button type="button" wire:click="removeGalleryImage({{ $index }})"
                                        wire:confirm="¿Eliminar esta imagen de la galería?"
                                        aria-label="Eliminar fotografía {{ $index + 1 }}"><i
                                            class="bx bx-trash"></i></button>
                                </div>
                                <label><span>Pie de foto</span><input type="text"
                                        wire:model.blur="galleryPaths.{{ $index }}.caption" maxlength="120"
                                        placeholder="Ej. Terraza principal al atardecer"></label>
                            </article>
                        @endforeach
                        @foreach ($galleryUploads as $index => $upload)
                            <article class="business-gallery__item is-pending"
                                wire:key="gallery-new-{{ $index }}">
                                <div class="business-gallery__media">
                                    <span class="business-gallery__skeleton" aria-hidden="true"></span>
                                    <img src="{{ $upload->temporaryUrl() }}" alt="Vista previa de nueva fotografía">
                                    <span class="business-gallery__pending-label">Nueva</span>
                                    <button type="button"
                                        wire:click="removePendingGalleryImage({{ $index }})"
                                        aria-label="Quitar nueva fotografía {{ $index + 1 }}"><i
                                            class="bx bx-x"></i></button>
                                </div>
                                <label><span>Pie de foto</span><input type="text"
                                        wire:model.blur="galleryUploadCaptions.{{ $index }}" maxlength="120"
                                        placeholder="Describe brevemente la fotografía"></label>
                            </article>
                        @endforeach
                        <article class="business-gallery__loading-card" wire:loading.grid wire:target="galleryUploads"
                            aria-label="Cargando fotografías">
                            <span class="business-gallery__skeleton" aria-hidden="true"></span>
                            <div><span></span><span></span></div>
                        </article>
                    </div>
                    @if (count($galleryPaths) + count($galleryUploads) < \App\Livewire\Admin\BusinessSettingsManager::MAX_GALLERY_IMAGES)
                        <label class="business-gallery-upload">
                            <input type="file" wire:model.live="galleryUploads"
                                accept="image/png,image/jpeg,image/webp" multiple>
                            <i class="bx bx-images"></i>
                            <span><strong>Agregar fotografías</strong><small>JPG, PNG o WebP · máximo 6 MB por imagen ·
                                    puedes seleccionar varias</small></span>
                        </label>
                    @endif
                    @error('galleryUploads')
                        <p class="biz-form-error" role="alert">{{ $message }}</p>
                    @enderror
                    @error('galleryUploads.*')
                        <p class="biz-form-error" role="alert">{{ $message }}</p>
                    @enderror
                    @error('galleryPaths.*.caption')
                        <p class="biz-form-error" role="alert">{{ $message }}</p>
                    @enderror
                    @error('galleryUploadCaptions.*')
                        <p class="biz-form-error" role="alert">{{ $message }}</p>
                    @enderror
                    @if (count($galleryPaths) || count($galleryUploads))
                        <button type="button" class="biz-primary-button business-gallery-save"
                            wire:click="saveGallery" wire:loading.attr="disabled"
                            wire:target="saveGallery,galleryUploads">
                            <span wire:loading.remove wire:target="saveGallery"><i
                                    class="bx bx-cloud-upload"></i>Guardar galería y pies de foto</span>
                            <span wire:loading wire:target="saveGallery">Guardando galería…</span>
                        </button>
                    @endif
                @else
                    <div class="biz-section-heading">
                        <div><span>08</span>
                            <div>
                                <h2>Productos destacados</h2>
                                <p>Elige hasta 8 opciones para la sección “Favoritos de la casa”.</p>
                            </div>
                        </div>
                    </div>
                    <div class="business-featured-products">
                        @forelse($this->availablePublicProducts as $product)
                            <label wire:key="featured-product-{{ $product->id }}">
                                <input type="checkbox" wire:model="featuredProductIds" value="{{ $product->id }}">
                                <span class="business-featured-products__image">
                                    @if ($product->image)
                                    <img src="{{ Storage::url($product->image) }}" alt="">@else<i
                                            class="bx bx-dish"></i>
                                    @endif
                                </span>
                                <span><strong>{{ $product->name }}</strong><small>{{ $product->category?->name ?? 'Sin categoría' }}
                                        · ${{ number_format((float) $product->price, 2) }}</small></span>
                                <span class="business-featured-products__star" aria-hidden="true"><i
                                        class="bx bxs-star"></i></span>
                            </label>
                        @empty
                            <div class="business-section-note"><i class="bx bx-info-circle"></i><span><strong>Aún no
                                        hay productos activos</strong><small>Activa productos desde el módulo Menú para
                                        poder destacarlos.</small></span></div>
                        @endforelse
                    </div>
                    @error('featuredProductIds')
                        <p class="biz-form-error" role="alert">{{ $message }}</p>
                    @enderror
                    @error('featuredProductIds.*')
                        <p class="biz-form-error" role="alert">{{ $message }}</p>
                    @enderror
                @endif

                <footer class="biz-form-actions">
                    <p><i class="bx bx-cloud-upload"></i>Los cambios se aplicarán después de guardar.</p><button
                        type="submit" class="biz-primary-button" wire:loading.attr="disabled"
                        wire:target="saveBusiness,logoUpload,ticketLogoUpload,bannerUpload,galleryUploads"><span
                            wire:loading.remove wire:target="saveBusiness"><i class="bx bx-save"></i>Guardar
                            configuración</span><span wire:loading
                            wire:target="saveBusiness">Guardando…</span></button>
                </footer>
            </form>
        </div>
    @elseif($activeTab === 'tickets' && $canManageBusiness)
        <div class="ticket-maker">
            <aside class="ticket-maker__types" aria-label="Tipos de ticket">
                <div><span class="biz-eyebrow">Plantillas</span>
                    <h2>Documentos</h2>
                    <p>Cada tipo conserva su propia configuración.</p>
                </div>
                @foreach ($ticketTypes as $key => $type)
                    <button type="button" wire:click="selectType('{{ $key }}')"
                        class="{{ $selectedType === $key ? 'is-active' : '' }}"
                        aria-pressed="{{ $selectedType === $key ? 'true' : 'false' }}"><i
                            class="bx {{ $type['icon'] }}"></i><span>{{ $type['name'] }}</span><i
                            class="bx bx-chevron-right"></i></button>
                @endforeach
            </aside>
            <form wire:submit="saveTemplate" class="ticket-maker__editor">
                <header>
                    <div><span class="biz-eyebrow">Editor por bloques</span>
                        <h2>{{ $ticketTypes[$selectedType]['name'] }}</h2>
                    </div><button type="button" wire:click="resetTemplate" class="biz-ghost-button"><i
                            class="bx bx-reset"></i>Restaurar</button>
                </header>
                @if ($selectedType === 'customer')
                    <div class="ticket-context-note" role="note">
                        <i class="bx bx-info-circle"></i>
                        <span><strong>Cuenta del cliente</strong><small>Se usa para cuentas de mesa y documentos entregados al cliente. Las ventas directas del mostrador usan la plantilla Ventanilla.</small></span>
                    </div>
                @elseif($selectedType === 'counter')
                    <div class="ticket-context-note" role="note">
                        <i class="bx bx-info-circle"></i>
                        <span><strong>Venta de ventanilla</strong><small>Se usa en ventas directas y pedidos para recoger cobrados o reimpresos desde el POS.</small></span>
                    </div>
                @elseif ($selectedType === 'cash_cut')
                    <div class="ticket-context-note" role="note">
                        <i class="bx bx-info-circle"></i>
                        <span><strong>Estructura completa del corte</strong><small>La vista previa usa datos de ejemplo.
                                Al imprimir se mostrarán los importes, canales, formas de pago y conciliación reales de
                                la caja.</small></span>
                    </div>
                @elseif($selectedType === 'inventory_purchase')
                    <div class="ticket-context-note" role="note">
                        <i class="bx bx-info-circle"></i>
                        <span><strong>Ticket de compra y recepción</strong><small>Este diseño se usa en la vista previa,
                                impresión y reimpresión del módulo de inventarios. Puedes ordenar u ocultar el folio,
                                los insumos, las indicaciones y el pie.</small></span>
                    </div>
                @endif
                <fieldset class="ticket-settings-group">
                    <legend>Tipografía global</legend>
                    <p class="ticket-group-help">Se aplica al encabezado, datos, mesa, delivery, pagos y totales de todos los tickets. Los productos conservan su formato y los productos de cocina mantienen sus controles independientes.</p>
                    <div class="ticket-format-grid">
                        <x-business.field label="Fuente global" for="global-ticket-font">
                            <select id="global-ticket-font" wire:model.live="globalTicketFontFamily">
                                @foreach (\App\Livewire\Admin\BusinessSettingsManager::GLOBAL_TICKET_FONTS as $fontKey => $fontLabel)
                                    <option value="{{ $fontKey }}">{{ $fontLabel }}</option>
                                @endforeach
                            </select>
                        </x-business.field>
                        <x-business.field label="Tamaño global" for="global-ticket-font-size"
                            hint="No modifica el tamaño de los productos.">
                            <select id="global-ticket-font-size" wire:model.live="globalTicketFontSize">
                                @for ($size = 9; $size <= 16; $size++)
                                    <option value="{{ $size }}">{{ $size }} px</option>
                                @endfor
                            </select>
                        </x-business.field>
                        <div class="d-flex align-items-end">
                            <button type="button" class="biz-ghost-button" wire:click="saveGlobalTicketTypography"
                                wire:loading.attr="disabled" wire:target="saveGlobalTicketTypography">
                                <span wire:loading.remove wire:target="saveGlobalTicketTypography"><i class="bx bx-font"></i>Aplicar a todos</span>
                                <span wire:loading wire:target="saveGlobalTicketTypography">Aplicando…</span>
                            </button>
                        </div>
                    </div>
                </fieldset>
                <fieldset class="ticket-settings-group">
                    <legend>Formato de impresión</legend>
                    <div class="ticket-format-grid"><x-business.field label="Ancho" for="paper-width"><select
                                id="paper-width" wire:model.live="paperWidth">
                                <option value="80">80 mm</option>
                                <option value="58">58 mm</option>
                            </select>
                            </x-business.field><x-business.field label="Margen" for="ticket-margin"><select
                                id="ticket-margin" wire:model.live="marginMm">
                                @for ($margin = 2; $margin <= 6; $margin++)
                                    <option value="{{ $margin }}">{{ $margin }} mm</option>
                                @endfor
                            </select>
                        </x-business.field><x-business.field label="DPI de impresora" for="printer-dpi"
                            hint="Auto respeta el controlador. 203 DPI es el estándar habitual en impresoras térmicas POS.">
                            <select id="printer-dpi" wire:model.live="printerDpi">
                                @foreach (\App\Livewire\Admin\BusinessSettingsManager::PRINTER_DPI_OPTIONS as $dpi => $label)
                                    <option value="{{ $dpi }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-business.field></div>
                    <div class="ticket-toggle-grid"><label><input type="checkbox" wire:model.live="showLogo"><span><i
                                    class="bx bx-image"></i><strong>Mostrar logo</strong><small>Usa el logo térmico
                                    configurado.</small></span></label>
                        @if ($selectedType !== 'inventory_purchase')
                            <label><input type="checkbox" wire:model.live="showQr"><span><i
                                        class="bx bx-qr"></i><strong>QR de seguimiento</strong><small>Solo aparece si
                                        existe enlace.</small></span></label>
                        @endif
                        <label>
                            <input type="checkbox"
                                wire:model.live="showRfc"><span><strong>RFC</strong></span></label><label><input
                                type="checkbox"
                                wire:model.live="showPhone"><span><strong>Teléfono</strong></span></label><label><input
                                type="checkbox"
                                wire:model.live="showAddress"><span><strong>Dirección</strong></span></label>
                    </div>
                    @if ($showQr && $selectedType !== 'inventory_purchase')
                        <x-business.field label="Texto bajo el QR" for="qr-label" full><input id="qr-label"
                                type="text" wire:model.live.debounce.300ms="qrLabel"
                                placeholder="Escanea para consultar tu pedido" maxlength="120"></x-business.field>
                    @endif
                    @if ($showLogo)
                        <x-business.field label="Tamaño del logo" for="logo-width" hint="Se guarda de forma independiente para esta plantilla.">
                            <select id="logo-width" wire:model.live="logoWidthMm">
                                @foreach ([12, 18, 24, 30, 36, 42, 48, 54] as $width)
                                    <option value="{{ $width }}">{{ $width }} mm</option>
                                @endforeach
                            </select>
                        </x-business.field>
                    @endif
                    @if ($selectedType === 'kitchen_area')
                        <div class="ticket-context-note" role="note">
                            <i class="bx bx-font"></i>
                            <span><strong>Lectura de productos en cocina</strong><small>Estos ajustes solo cambian productos, modificadores y notas; el resto de la comanda conserva la tipografía general.</small></span>
                        </div>
                        <div class="ticket-format-grid">
                            <x-business.field label="Fuente de productos" for="item-font-family">
                                <select id="item-font-family" wire:model.live="itemFontFamily">
                                    @foreach (\App\Livewire\Admin\BusinessSettingsManager::KITCHEN_ITEM_FONTS as $fontKey => $fontLabel)
                                        <option value="{{ $fontKey }}">{{ $fontLabel }}</option>
                                    @endforeach
                                </select>
                            </x-business.field>
                            <x-business.field label="Tamaño de productos" for="item-font-size">
                                <select id="item-font-size" wire:model.live="itemFontSize">
                                    @for ($size = 12; $size <= 28; $size++)
                                        <option value="{{ $size }}">{{ $size }} px</option>
                                    @endfor
                                </select>
                            </x-business.field>
                        </div>
                    @endif
                </fieldset>
                <fieldset class="ticket-settings-group">
                    <legend>Orden y visibilidad de bloques</legend>
                    <p class="ticket-group-help">Usa las flechas para ordenar sin depender de arrastrar.</p>
                    <div class="ticket-block-list">
                        @foreach ($blocks as $index => $block)
                            <article class="ticket-block {{ $block['enabled'] ?? false ? 'is-enabled' : '' }}"
                                wire:key="ticket-block-{{ $selectedType }}-{{ $block['key'] }}"><span
                                    class="ticket-block__handle"><i class="bx bx-grid-vertical"></i></span>
                                <div>
                                    <strong>{{ $block['label'] }}</strong><small>{{ $block['enabled'] ?? false ? 'Visible en el ticket' : 'Oculto' }}</small>
                                </div>
                                <div class="ticket-block__actions"><button type="button"
                                        wire:click="moveBlock({{ $index }}, -1)" @disabled($loop->first)
                                        aria-label="Subir {{ $block['label'] }}"><i
                                            class="bx bx-up-arrow-alt"></i></button><button type="button"
                                        wire:click="moveBlock({{ $index }}, 1)" @disabled($loop->last)
                                        aria-label="Bajar {{ $block['label'] }}"><i
                                            class="bx bx-down-arrow-alt"></i></button><button type="button"
                                        wire:click="toggleBlock({{ $index }})" class="ticket-block__toggle"
                                        aria-pressed="{{ $block['enabled'] ?? false ? 'true' : 'false' }}"><i
                                            class="bx {{ $block['enabled'] ?? false ? 'bx-show' : 'bx-hide' }}"></i>{{ $block['enabled'] ?? false ? 'Visible' : 'Oculto' }}</button>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </fieldset>
                <x-business.field label="Mensaje del pie" for="footer-text" hint="Máximo 240 caracteres." full>
                    <textarea id="footer-text" wire:model.live.debounce.300ms="footerText" placeholder="Ej. ¡Gracias por tu preferencia!"
                        maxlength="240" rows="3"></textarea>
                </x-business.field>
                <footer class="biz-form-actions">
                    <p><i class="bx bx-printer"></i>Predeterminado POS: 80 mm y DPI automático (203 DPI habitual).</p><button type="submit"
                        class="biz-primary-button" wire:loading.attr="disabled" wire:target="saveTemplate"><span
                            wire:loading.remove wire:target="saveTemplate"><i class="bx bx-save"></i>Guardar
                            plantilla</span><span wire:loading wire:target="saveTemplate">Guardando…</span></button>
                </footer>
            </form>
            <aside class="ticket-maker__preview" aria-label="Vista previa del ticket">
                <header>
                    <div><span class="biz-eyebrow">Vista previa</span>
                        <h2>{{ $paperWidth }} mm</h2>
                    </div><span class="ticket-live-chip"><i class="bx bx-radio-circle-marked"></i>En vivo</span>
                </header>
                <div class="ticket-preview-stage" wire:loading.class="is-loading"
                    wire:target="selectType,toggleBlock,moveBlock,paperWidth,marginMm,printerDpi,globalTicketFontFamily,globalTicketFontSize,showLogo,showQr,logoWidthMm,itemFontFamily,itemFontSize"><iframe
                        title="Vista previa de {{ $ticketTypes[$selectedType]['name'] }}"
                        srcdoc="{{ $this->previewHtml }}"></iframe></div>
            </aside>
        </div>
    @else
        <livewire:admin.sidebar-menu-manager />
    @endif
</div>
