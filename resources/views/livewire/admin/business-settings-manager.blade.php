<div class="business-settings-page">
    @if(session('success'))
        <div class="biz-toast" role="status" aria-live="polite"><i class="bx bx-check-circle"></i>{{ session('success') }}</div>
    @endif

    <header class="biz-page-header">
        <div class="biz-page-header__mark"><i class="bx bx-buildings"></i></div>
        <div><span class="biz-eyebrow">Administración · Identidad y navegación</span><h1>Configuración del negocio</h1><p>Centraliza la sucursal, los documentos térmicos y la estructura del sistema.</p></div>
        <span class="biz-branch-chip"><i class="bx bx-map-pin"></i>1 sucursal</span>
    </header>

    <nav class="biz-tabs {{ $canManageBusiness ? 'biz-tabs--three' : '' }}" aria-label="Secciones de configuración">
        @if($canManageBusiness)
        <button type="button" wire:click="setTab('business')" class="{{ $activeTab === 'business' ? 'is-active' : '' }}" aria-current="{{ $activeTab === 'business' ? 'page' : 'false' }}"><i class="bx bx-store"></i><span><strong>Datos del negocio</strong><small>Identidad, horarios y medios</small></span></button>
        <button type="button" wire:click="setTab('tickets')" class="{{ $activeTab === 'tickets' ? 'is-active' : '' }}" aria-current="{{ $activeTab === 'tickets' ? 'page' : 'false' }}"><i class="bx bx-receipt"></i><span><strong>Ticket Maker</strong><small>Plantillas y previsualización</small></span></button>
        @endif
        @can('ver menu sidebar')
        <button type="button" wire:click="setTab('menu')" class="{{ $activeTab === 'menu' ? 'is-active' : '' }}" aria-current="{{ $activeTab === 'menu' ? 'page' : 'false' }}"><i class="bx bx-list-ul"></i><span><strong>Menú lateral</strong><small>Jerarquía, iconos y permisos</small></span></button>
        @endcan
    </nav>

    @if($activeTab === 'business' && $canManageBusiness)
        <div class="business-editor">
            <aside class="business-editor__sections" aria-label="Apartados de datos del negocio">
                <div><span class="biz-eyebrow">Configuración</span><h2>Datos de la sucursal</h2><p>Completa cada grupo y guarda todos los cambios al finalizar.</p></div>
                @foreach([
                    'identity' => ['01','bx-id-card','Identidad comercial','Nombre, plataforma y RFC'],
                    'contact' => ['02','bx-map','Contacto y ubicación','Canales y domicilio'],
                    'hours' => ['03','bx-time-five','Horarios','Apertura por día'],
                    'visual' => ['04','bx-image','Identidad visual','Logos y banner'],
                ] as $sectionKey => $section)
                    <button type="button" wire:click="setBusinessSection('{{ $sectionKey }}')" class="{{ $businessSection === $sectionKey ? 'is-active' : '' }}" aria-pressed="{{ $businessSection === $sectionKey ? 'true' : 'false' }}">
                        <span>{{ $section[0] }}</span><i class="bx {{ $section[1] }}"></i><span><strong>{{ $section[2] }}</strong><small>{{ $section[3] }}</small></span><i class="bx bx-chevron-right"></i>
                    </button>
                @endforeach
            </aside>

            <form wire:submit="saveBusiness" class="business-editor__form">
                @if($businessSection === 'identity')
                    <div class="biz-section-heading"><div><span>01</span><div><h2>Identidad comercial</h2><p>Información visible en la plataforma, accesos y documentos.</p></div></div></div>
                    <div class="biz-form-grid">
                        <x-business.field label="Nombre comercial" for="business-name" :error="$errors->first('businessName')"><input id="business-name" type="text" wire:model.blur="businessName" placeholder="Ej. Calle Sabor Centro" autocomplete="organization" required></x-business.field>
                        <x-business.field label="Nombre de la plataforma" for="platform-name" hint="Se mostrará en encabezados y accesos." :error="$errors->first('platformName')"><input id="platform-name" type="text" wire:model.blur="platformName" placeholder="Ej. Calle Sabor POS" required></x-business.field>
                        <x-business.field label="Razón social" for="legal-name"><input id="legal-name" type="text" wire:model.blur="legalName" placeholder="Ej. Alimentos Calle Sabor, S.A. de C.V." autocomplete="organization"></x-business.field>
                        <x-business.field label="RFC" for="business-rfc"><input id="business-rfc" type="text" wire:model.blur="rfc" placeholder="Ej. ACS010101AB1" maxlength="20" class="text-uppercase"></x-business.field>
                    </div>
                    <div class="business-section-note"><i class="bx bx-info-circle"></i><span><strong>Una sola fuente de identidad</strong><small>Estos datos se reutilizan en el sidebar, POS, kiosco y tickets configurados.</small></span></div>
                @elseif($businessSection === 'contact')
                    <div class="biz-section-heading"><div><span>02</span><div><h2>Contacto y ubicación</h2><p>Información para clientes, delivery y documentos fiscales.</p></div></div></div>
                    <div class="biz-form-grid">
                        <x-business.field label="Teléfono" for="business-phone"><input id="business-phone" type="tel" wire:model.blur="phone" placeholder="Ej. 55 1234 5678" autocomplete="tel"></x-business.field>
                        <x-business.field label="WhatsApp" for="business-whatsapp"><input id="business-whatsapp" type="tel" wire:model.blur="whatsapp" placeholder="Ej. 55 9876 5432"></x-business.field>
                        <x-business.field label="Correo electrónico" for="business-email" :error="$errors->first('email')"><input id="business-email" type="email" wire:model.blur="email" placeholder="contacto@negocio.com" autocomplete="email"></x-business.field>
                        <x-business.field label="Sitio web" for="business-web" :error="$errors->first('website')"><input id="business-web" type="url" wire:model.blur="website" placeholder="https://www.negocio.com"></x-business.field>
                        <x-business.field label="Calle, número y colonia" for="business-address" full><input id="business-address" type="text" wire:model.blur="address" placeholder="Ej. Av. Principal 123, Col. Centro" autocomplete="street-address"></x-business.field>
                        <x-business.field label="Ciudad o municipio" for="business-city"><input id="business-city" type="text" wire:model.blur="city" placeholder="Ej. Guadalajara" autocomplete="address-level2"></x-business.field>
                        <x-business.field label="Estado" for="business-state"><input id="business-state" type="text" wire:model.blur="state" placeholder="Ej. Jalisco" autocomplete="address-level1"></x-business.field>
                        <x-business.field label="Código postal" for="business-postal"><input id="business-postal" type="text" wire:model.blur="postalCode" placeholder="Ej. 44100" autocomplete="postal-code" maxlength="10" inputmode="numeric"></x-business.field>
                    </div>
                @elseif($businessSection === 'hours')
                    <div class="biz-section-heading"><div><span>03</span><div><h2>Horarios de la sucursal</h2><p>Define qué días opera el negocio y su horario habitual.</p></div></div></div>
                    <div class="business-hours" role="group" aria-label="Horario semanal">
                        @foreach($businessHours as $index => $day)
                            <article class="business-hour {{ $day['enabled'] ? 'is-open' : '' }}" wire:key="business-hour-{{ $day['key'] }}">
                                <label class="business-hour__switch"><input type="checkbox" wire:model.live="businessHours.{{ $index }}.enabled"><span></span><strong>{{ $day['label'] }}</strong></label>
                                @if($day['enabled'])
                                    <label><span>Abre</span><input type="time" wire:model="businessHours.{{ $index }}.opens" aria-label="Hora de apertura del {{ $day['label'] }}"></label>
                                    <i class="bx bx-right-arrow-alt" aria-hidden="true"></i>
                                    <label><span>Cierra</span><input type="time" wire:model="businessHours.{{ $index }}.closes" aria-label="Hora de cierre del {{ $day['label'] }}"></label>
                                    <span class="business-hour__status"><i class="bx bx-check-circle"></i>Abierto</span>
                                @else
                                    <span class="business-hour__closed"><i class="bx bx-moon"></i>Cerrado</span>
                                @endif
                            </article>
                        @endforeach
                    </div>
                    @error('businessHours.*')<p class="biz-form-error" role="alert">{{ $message }}</p>@enderror
                @else
                    <div class="biz-section-heading"><div><span>04</span><div><h2>Identidad visual</h2><p>Previsualización inmediata y archivos optimizados para cada contexto.</p></div></div></div>
                    <div class="biz-media-grid">
                        <x-business.media-upload title="Logo principal" description="PNG, JPG o WebP. Máximo 4 MB." model="logoUpload" :path="$logoPath" :upload="$logoUpload" />
                        <x-business.media-upload title="Logo para tickets" description="Recomendado: monocromático y alto contraste." model="ticketLogoUpload" :path="$ticketLogoPath" :upload="$ticketLogoUpload" />
                        <x-business.media-upload title="Banner" description="Imagen horizontal para kiosco y comunicación." model="bannerUpload" :path="$bannerPath" :upload="$bannerUpload" />
                    </div>
                    @foreach(['logoUpload','ticketLogoUpload','bannerUpload'] as $uploadError)@error($uploadError)<p class="biz-form-error" role="alert">{{ $message }}</p>@enderror @endforeach
                    <div class="business-section-note"><i class="bx bx-image-alt"></i><span><strong>La vista previa aparece antes de guardar</strong><small>Mientras el navegador procesa el archivo verás un skeleton. El espacio queda reservado para evitar saltos visuales.</small></span></div>
                @endif

                <footer class="biz-form-actions"><p><i class="bx bx-cloud-upload"></i>Los cambios se aplicarán después de guardar.</p><button type="submit" class="biz-primary-button" wire:loading.attr="disabled" wire:target="saveBusiness,logoUpload,ticketLogoUpload,bannerUpload"><span wire:loading.remove wire:target="saveBusiness"><i class="bx bx-save"></i>Guardar configuración</span><span wire:loading wire:target="saveBusiness">Guardando…</span></button></footer>
            </form>
        </div>
    @elseif($activeTab === 'tickets' && $canManageBusiness)
        <div class="ticket-maker">
            <aside class="ticket-maker__types" aria-label="Tipos de ticket"><div><span class="biz-eyebrow">Plantillas</span><h2>Documentos</h2><p>Cada tipo conserva su propia configuración.</p></div>@foreach($ticketTypes as $key => $type)<button type="button" wire:click="selectType('{{ $key }}')" class="{{ $selectedType === $key ? 'is-active' : '' }}" aria-pressed="{{ $selectedType === $key ? 'true' : 'false' }}"><i class="bx {{ $type['icon'] }}"></i><span>{{ $type['name'] }}</span><i class="bx bx-chevron-right"></i></button>@endforeach</aside>
            <form wire:submit="saveTemplate" class="ticket-maker__editor">
                <header><div><span class="biz-eyebrow">Editor por bloques</span><h2>{{ $ticketTypes[$selectedType]['name'] }}</h2></div><button type="button" wire:click="resetTemplate" class="biz-ghost-button"><i class="bx bx-reset"></i>Restaurar</button></header>
                @if($selectedType === 'cash_cut')
                    <div class="ticket-context-note" role="note">
                        <i class="bx bx-info-circle"></i>
                        <span><strong>Estructura completa del corte</strong><small>La vista previa usa datos de ejemplo. Al imprimir se mostrarán los importes, canales, formas de pago y conciliación reales de la caja.</small></span>
                    </div>
                @elseif($selectedType === 'inventory_purchase')
                    <div class="ticket-context-note" role="note">
                        <i class="bx bx-info-circle"></i>
                        <span><strong>Ticket de compra y recepción</strong><small>Este diseño se usa en la vista previa, impresión y reimpresión del módulo de inventarios. Puedes ordenar u ocultar el folio, los insumos, las indicaciones y el pie.</small></span>
                    </div>
                @endif
                <fieldset class="ticket-settings-group"><legend>Formato de impresión</legend><div class="ticket-format-grid"><x-business.field label="Ancho" for="paper-width"><select id="paper-width" wire:model.live="paperWidth"><option value="80">80 mm</option><option value="58">58 mm</option></select></x-business.field><x-business.field label="Tamaño de letra" for="font-size"><select id="font-size" wire:model.live="fontSize">@for($size=9;$size<=16;$size++)<option value="{{ $size }}">{{ $size }} px</option>@endfor</select></x-business.field><x-business.field label="Margen" for="ticket-margin"><select id="ticket-margin" wire:model.live="marginMm">@for($margin=2;$margin<=6;$margin++)<option value="{{ $margin }}">{{ $margin }} mm</option>@endfor</select></x-business.field></div>
                    <div class="ticket-toggle-grid"><label><input type="checkbox" wire:model.live="showLogo"><span><i class="bx bx-image"></i><strong>Mostrar logo</strong><small>Usa el logo térmico configurado.</small></span></label>@if($selectedType !== 'inventory_purchase')<label><input type="checkbox" wire:model.live="showQr"><span><i class="bx bx-qr"></i><strong>QR de seguimiento</strong><small>Solo aparece si existe enlace.</small></span></label>@endif<label><input type="checkbox" wire:model.live="showRfc"><span><strong>RFC</strong></span></label><label><input type="checkbox" wire:model.live="showPhone"><span><strong>Teléfono</strong></span></label><label><input type="checkbox" wire:model.live="showAddress"><span><strong>Dirección</strong></span></label></div>
                    @if($showQr && $selectedType !== 'inventory_purchase')<x-business.field label="Texto bajo el QR" for="qr-label" full><input id="qr-label" type="text" wire:model.live.debounce.300ms="qrLabel" placeholder="Escanea para consultar tu pedido" maxlength="120"></x-business.field>@endif
                </fieldset>
                <fieldset class="ticket-settings-group"><legend>Orden y visibilidad de bloques</legend><p class="ticket-group-help">Usa las flechas para ordenar sin depender de arrastrar.</p><div class="ticket-block-list">@foreach($blocks as $index => $block)<article class="ticket-block {{ ($block['enabled'] ?? false) ? 'is-enabled' : '' }}" wire:key="ticket-block-{{ $selectedType }}-{{ $block['key'] }}"><span class="ticket-block__handle"><i class="bx bx-grid-vertical"></i></span><div><strong>{{ $block['label'] }}</strong><small>{{ ($block['enabled'] ?? false) ? 'Visible en el ticket' : 'Oculto' }}</small></div><div class="ticket-block__actions"><button type="button" wire:click="moveBlock({{ $index }}, -1)" @disabled($loop->first) aria-label="Subir {{ $block['label'] }}"><i class="bx bx-up-arrow-alt"></i></button><button type="button" wire:click="moveBlock({{ $index }}, 1)" @disabled($loop->last) aria-label="Bajar {{ $block['label'] }}"><i class="bx bx-down-arrow-alt"></i></button><button type="button" wire:click="toggleBlock({{ $index }})" class="ticket-block__toggle" aria-pressed="{{ ($block['enabled'] ?? false) ? 'true' : 'false' }}"><i class="bx {{ ($block['enabled'] ?? false) ? 'bx-show' : 'bx-hide' }}"></i>{{ ($block['enabled'] ?? false) ? 'Visible' : 'Oculto' }}</button></div></article>@endforeach</div></fieldset>
                <x-business.field label="Mensaje del pie" for="footer-text" hint="Máximo 240 caracteres." full><textarea id="footer-text" wire:model.live.debounce.300ms="footerText" placeholder="Ej. ¡Gracias por tu preferencia!" maxlength="240" rows="3"></textarea></x-business.field>
                <footer class="biz-form-actions"><p><i class="bx bx-printer"></i>Predeterminado recomendado: 80 mm.</p><button type="submit" class="biz-primary-button" wire:loading.attr="disabled" wire:target="saveTemplate"><span wire:loading.remove wire:target="saveTemplate"><i class="bx bx-save"></i>Guardar plantilla</span><span wire:loading wire:target="saveTemplate">Guardando…</span></button></footer>
            </form>
            <aside class="ticket-maker__preview" aria-label="Vista previa del ticket"><header><div><span class="biz-eyebrow">Vista previa</span><h2>{{ $paperWidth }} mm</h2></div><span class="ticket-live-chip"><i class="bx bx-radio-circle-marked"></i>En vivo</span></header><div class="ticket-preview-stage" wire:loading.class="is-loading" wire:target="selectType,toggleBlock,moveBlock,paperWidth,fontSize,marginMm,showLogo,showQr"><iframe title="Vista previa de {{ $ticketTypes[$selectedType]['name'] }}" srcdoc="{{ $this->previewHtml }}"></iframe></div></aside>
        </div>
    @else
        <livewire:admin.sidebar-menu-manager />
    @endif
</div>
