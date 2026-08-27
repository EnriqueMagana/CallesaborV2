<div class="promotions-page app-page-shell">
    <header class="app-page-hero promotions-hero">
        <div class="app-page-heading">
            <span class="app-page-icon" aria-hidden="true"><i class="bx bx-purchase-tag-alt"></i></span>
            <div>
                <div class="app-eyebrow">Ventas · Campañas</div>
                <h1 class="app-page-title">Promociones</h1>
                <p class="app-page-subtitle">Programa combos, descuentos y novedades para el POS y el menú digital.</p>
            </div>
        </div>
        @can('crear promociones')
            <button type="button" class="promotions-primary" wire:click="openCreate" wire:loading.attr="disabled" wire:target="openCreate">
                <i class="bx bx-plus" aria-hidden="true"></i><span>Nueva promoción</span>
            </button>
        @endcan
    </header>

    <nav class="promotions-view-tabs" aria-label="Vistas de promociones">
        <button type="button" wire:click="showCatalog" @class(['is-active' => $activeView === 'catalog']) aria-pressed="{{ $activeView === 'catalog' ? 'true' : 'false' }}"><i class="bx bx-grid-alt" aria-hidden="true"></i><span>Catálogo</span></button>
        <button type="button" wire:click="showCalendar" @class(['is-active' => $activeView === 'calendar']) aria-pressed="{{ $activeView === 'calendar' ? 'true' : 'false' }}"><i class="bx bx-calendar" aria-hidden="true"></i><span>Calendario</span></button>
    </nav>

    <section class="promotions-summary" aria-label="Resumen de promociones">
        <article><span class="is-purple"><i class="bx bx-layer"></i></span><div><small>Registradas</small><strong>{{ $this->promotions->count() }}</strong></div></article>
        <article><span class="is-green"><i class="bx bx-broadcast"></i></span><div><small>Disponibles hoy</small><strong>{{ $this->promotions->filter(fn($promotion) => $promotion->isScheduledFor(now()))->count() }}</strong></div></article>
        <article><span class="is-blue"><i class="bx bx-mobile-alt"></i></span><div><small>Menú digital</small><strong>{{ $this->promotions->where('show_on_digital_menu', true)->count() }}</strong></div></article>
    </section>

    @if($activeView === 'calendar')
        @php
            $calendarDate = \Carbon\CarbonImmutable::createFromFormat('Y-m-d', $calendarMonth.'-01');
        @endphp
        <section class="promotion-calendar" aria-labelledby="promotion-calendar-title">
            <header class="promotion-calendar__toolbar">
                <div><span class="app-eyebrow">Programación</span><h2 id="promotion-calendar-title">{{ ucfirst($calendarDate->translatedFormat('F Y')) }}</h2><p>Consulta qué campañas y productos estarán disponibles cada día.</p></div>
                <div class="promotion-calendar__actions" aria-label="Cambiar mes"><button type="button" wire:click="previousMonth" aria-label="Mes anterior"><i class="bx bx-chevron-left"></i></button><button type="button" wire:click="goToCurrentMonth">Hoy</button><button type="button" wire:click="nextMonth" aria-label="Mes siguiente"><i class="bx bx-chevron-right"></i></button></div>
            </header>
            <div class="promotion-calendar__weekdays" aria-hidden="true">@foreach(['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $day)<span>{{ $day }}</span>@endforeach</div>
            <div class="promotion-calendar__grid">
                @foreach($this->calendarDays as $calendarDay)
                    <article @class(['promotion-calendar__day', 'is-outside' => ! $calendarDay['current_month'], 'is-today' => $calendarDay['today'], 'has-events' => $calendarDay['promotions']->isNotEmpty()])>
                        <header><span>{{ $calendarDay['date']->translatedFormat('D') }}</span><strong>{{ $calendarDay['date']->day }}</strong></header>
                        <div class="promotion-calendar__events">
                            @foreach($calendarDay['promotions'] as $promotion)
                                @can('editar promociones')
                                    <button type="button" class="promotion-calendar__event is-{{ $promotion->presentation_type }}" wire:click="openEdit({{ $promotion->id }})" title="Editar {{ $promotion->name }}"><i class="bx {{ $promotion->presentationIcon() }}"></i><span><strong>{{ $promotion->name }}</strong><small>{{ $promotion->isProductLaunch() ? $promotion->primaryProduct?->name : $promotion->groups->flatMap->products->pluck('name')->take(2)->join(', ') }}</small></span></button>
                                @else
                                    <div class="promotion-calendar__event is-{{ $promotion->presentation_type }}"><i class="bx {{ $promotion->presentationIcon() }}"></i><span><strong>{{ $promotion->name }}</strong><small>{{ $promotion->isProductLaunch() ? $promotion->primaryProduct?->name : $promotion->groups->flatMap->products->pluck('name')->take(2)->join(', ') }}</small></span></div>
                                @endcan
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @else
        <section class="promotions-directory">
            <header><div><span class="app-eyebrow">Catálogo promocional</span><h2>Campañas configuradas</h2><p>Las tarjetas públicas respetan automáticamente la vigencia y los días elegidos.</p></div></header>
            <div class="promotions-grid">
                @forelse($this->promotions as $promotion)
                    @php
                        $availableNow = $promotion->isScheduledFor(now());
                        $cardImage = $promotion->image ?: ($promotion->isProductLaunch() ? $promotion->primaryProduct?->image : null);
                        $cardPrice = $promotion->isProductLaunch() ? $promotion->primaryProduct?->price : $promotion->price;
                    @endphp
                    <article class="promotion-card is-{{ $promotion->presentation_type }}" wire:key="promotion-{{ $promotion->id }}">
                        <div class="promotion-card__media">
                            @if($cardImage)<img src="{{ Storage::url($cardImage) }}" alt="{{ $promotion->name }}" width="520" height="300" loading="lazy">@else<span aria-hidden="true"><i class="bx bx-image-alt"></i></span>@endif
                            <span class="promotion-type-badge"><i class="bx {{ $promotion->presentationIcon() }}"></i>{{ $promotion->presentationLabel() }}</span>
                            <span class="promotion-status {{ $availableNow ? 'is-live' : 'is-scheduled' }}"><i class="bx {{ $availableNow ? 'bx-broadcast' : 'bx-time-five' }}"></i>{{ $availableNow ? 'Disponible hoy' : 'Programada' }}</span>
                        </div>
                        <div class="promotion-card__body">
                            <div class="promotion-card__title"><div><h3>{{ $promotion->name }}</h3><p>{{ $promotion->short_description ?: \Illuminate\Support\Str::limit($promotion->description ?: 'Sin resumen público.', 160) }}</p></div><strong>${{ number_format((float) $cardPrice, 2) }}</strong></div>
                            <div class="promotion-card__contents"><i class="bx {{ $promotion->isProductLaunch() ? 'bx-star' : 'bx-bowl-hot' }}"></i><span>{{ $promotion->isProductLaunch() ? ($promotion->primaryProduct?->name ?: 'Producto no disponible') : $promotion->groups->flatMap->products->pluck('name')->unique()->take(4)->join(' · ') }}</span></div>
                            <div class="promotion-card__meta"><span><i class="bx bx-calendar"></i>{{ $promotion->starts_on->format('d/m/Y') }} — {{ $promotion->ends_on?->format('d/m/Y') ?? 'Sin fecha final' }}</span><span><i class="bx bx-repeat"></i>{{ $promotion->weekdayLabel() }}</span><span><i class="bx {{ $promotion->isProductLaunch() ? 'bx-layout' : 'bx-layer' }}"></i>{{ $promotion->isProductLaunch() ? 'Tarjeta de novedad' : $promotion->groups->count().' '.($promotion->groups->count() === 1 ? 'grupo' : 'grupos') }}</span></div>
                            <div class="promotion-card__channels">@if($promotion->show_on_pos)<span><i class="bx bx-store-alt"></i>POS</span>@endif @if($promotion->show_on_digital_menu)<span><i class="bx bx-mobile-alt"></i>Menú digital</span>@endif @unless($promotion->is_active)<span class="is-muted"><i class="bx bx-pause-circle"></i>Pausada</span>@endunless</div>
                        </div>
                        <footer>
                            @can('editar promociones')<button type="button" wire:click="toggleActive({{ $promotion->id }})" aria-label="{{ $promotion->is_active ? 'Pausar' : 'Activar' }} {{ $promotion->name }}"><i class="bx {{ $promotion->is_active ? 'bx-pause' : 'bx-play' }}"></i><span>{{ $promotion->is_active ? 'Pausar' : 'Activar' }}</span></button><button type="button" wire:click="openEdit({{ $promotion->id }})"><i class="bx bx-edit-alt"></i><span>Editar</span></button>@endcan
                            @can('eliminar promociones')<button type="button" class="is-danger" wire:click="delete({{ $promotion->id }})" wire:confirm="¿Eliminar esta promoción? Las ventas históricas conservarán su detalle." aria-label="Eliminar {{ $promotion->name }}"><i class="bx bx-trash"></i><span>Eliminar</span></button>@endcan
                        </footer>
                    </article>
                @empty
                    <div class="promotions-empty"><span><i class="bx bx-purchase-tag-alt"></i></span><h3>Aún no hay promociones</h3><p>Crea una campaña y define qué puede elegir el cliente en cada grupo.</p>@can('crear promociones')<button type="button" class="promotions-primary" wire:click="openCreate">Crear primera promoción</button>@endcan</div>
                @endforelse
            </div>
        </section>
    @endif

    @if($showEditor)
        <div class="app-modal-backdrop promotion-modal-backdrop" aria-hidden="true"></div>
        <div class="app-modal-layer promotion-modal-layer" role="dialog" aria-modal="true" aria-labelledby="promotion-editor-title" aria-describedby="promotion-editor-description" wire:key="promotion-editor-modal" wire:click.self="closeEditor" wire:keydown.escape.window="closeEditor">
            <section class="promotion-modal promotion-wizard">
                <header class="promotion-modal__header"><div><span class="app-eyebrow">{{ $editingId ? 'Actualizar campaña' : 'Nueva campaña' }}</span><h2 id="promotion-editor-title">{{ $editingId ? 'Editar campaña' : 'Crear campaña' }}</h2><p id="promotion-editor-description">Paso {{ $wizardStep }} de 4 · {{ ['Objetivo','Contenido','Diseño y vigencia','Revisar y publicar'][$wizardStep-1] }}</p></div><button type="button" wire:click="closeEditor" aria-label="Cerrar" autofocus><i class="bx bx-x"></i></button></header>
                <ol class="promotion-wizard__steps" aria-label="Progreso de creación">
                    @foreach([1=>['bx-bullseye','Objetivo'],2=>['bx-food-menu','Contenido'],3=>['bx-image-alt','Publicación'],4=>['bx-check-shield','Revisión']] as $step=>$stepData)
                        <li @class(['is-active'=>$wizardStep===$step,'is-complete'=>$wizardStep>$step]) @if($wizardStep===$step) aria-current="step" @endif><span><i class="bx {{ $stepData[0] }}"></i></span><strong>{{ $stepData[1] }}</strong></li>
                    @endforeach
                </ol>
                <div class="promotion-modal__body">
                    @if($errors->any())<div class="promotion-error-summary" role="alert"><i class="bx bx-error-circle"></i><span><strong>Revisa la información indicada.</strong><small>{{ $errors->first() }}</small></span></div>@endif

                    @if($wizardStep === 1)
                        <section class="promotion-form-section">
                            <header><span><i class="bx bx-bullseye"></i></span><div><h3>¿Qué deseas publicar?</h3><p>Esta decisión define las reglas y el formato que verá el cliente.</p></div></header>
                            <div class="promotion-type-options promotion-type-options--wizard" role="radiogroup" aria-label="Objetivo comercial">
                                @foreach(['promotion'=>['bx-gift','Promoción o combo','Banner horizontal con precio especial y grupos de selección.'],'discount'=>['bx-purchase-tag','Descuento','Banner horizontal que comunica un porcentaje de ahorro.'],'new'=>['bx-star','Producto nuevo','Tarjeta independiente que hereda imagen, precio y personalización.']] as $value=>$option)
                                    <label><input type="radio" wire:model.live="presentationType" value="{{ $value }}"><span><i class="bx {{ $option[0] }}"></i><strong>{{ $option[1] }}</strong><small>{{ $option[2] }}</small><b>{{ $value === 'new' ? 'Tarjeta de producto' : 'Banner promocional' }}</b></span></label>
                                @endforeach
                            </div>
                            @error('presentationType')<small class="field-error">{{ $message }}</small>@enderror
                        </section>
                    @elseif($wizardStep === 2)
                        @if($presentationType === 'new')
                            <section class="promotion-form-section">
                                <header><span><i class="bx bx-star"></i></span><div><h3>Selecciona el producto nuevo</h3><p>Debe existir en Productos; su precio, ingredientes y agregables seguirán siendo la fuente de verdad.</p></div></header>
                                <fieldset class="promotion-products promotion-products--primary"><legend>Producto que se destacará <b>*</b></legend><div>@foreach($this->products as $product)<label><input type="radio" wire:model.live="primaryProductId" value="{{ $product->id }}"><span>@if($product->image)<img src="{{ Storage::url($product->image) }}" alt="" width="46" height="46">@else<i class="bx bx-dish"></i>@endif<strong>{{ $product->name }}</strong><small>{{ $product->category?->name ?: 'Sin categoría' }}</small></span></label>@endforeach</div>@error('primaryProductId')<small class="field-error">{{ $message }}</small>@enderror</fieldset>
                                <div class="promotion-form-grid"><label><span>Título público <b>*</b></span><input type="text" wire:model="name" maxlength="120">@error('name')<small class="field-error">{{ $message }}</small>@enderror</label><label><span>Precio heredado</span><input type="number" wire:model="price" readonly aria-readonly="true"><small>Se sincroniza con el producto.</small></label><label class="is-full"><span>Resumen para la tarjeta <b>*</b></span><input type="text" wire:model="shortDescription" maxlength="160">@error('shortDescription')<small class="field-error">{{ $message }}</small>@enderror</label><label class="is-full"><span>Descripción completa</span><textarea wire:model="description" rows="3" maxlength="500"></textarea></label></div>
                            </section>
                        @else
                            <section class="promotion-form-section">
                                <header><span><i class="bx bx-gift"></i></span><div><h3>Oferta y contenido</h3><p>Configura el precio final y qué puede seleccionar el cliente.</p></div></header>
                                <div class="promotion-form-grid {{ $presentationType === 'discount' ? 'is-three' : '' }}"><label><span>Título <b>*</b></span><input type="text" wire:model="name" maxlength="120" placeholder="Ej. Jueves de hamburguesas">@error('name')<small class="field-error">{{ $message }}</small>@enderror</label><label><span>Precio promocional <b>*</b></span><input type="number" wire:model="price" min="0.01" step="0.01">@error('price')<small class="field-error">{{ $message }}</small>@enderror</label>@if($presentationType === 'discount')<label><span>Descuento <b>*</b></span><div class="promotion-input-suffix"><input type="number" wire:model="discountPercentage" min="1" max="99"><span>%</span></div>@error('discountPercentage')<small class="field-error">{{ $message }}</small>@enderror</label>@endif<label class="is-full"><span>Resumen del banner <b>*</b></span><input type="text" wire:model="shortDescription" maxlength="160">@error('shortDescription')<small class="field-error">{{ $message }}</small>@enderror</label><label class="is-full"><span>Condiciones o descripción</span><textarea wire:model="description" rows="3" maxlength="500"></textarea></label></div>
                                <header class="promotion-groups-heading"><span><i class="bx bx-layer"></i></span><div><h3>Grupos de selección</h3><p>Ejemplo: muestra tres hamburguesas y permite elegir dos.</p></div><button type="button" wire:click="addGroup"><i class="bx bx-plus"></i>Añadir grupo</button></header>
                                <div class="promotion-groups">@foreach($groups as $index=>$group)<article class="promotion-group" wire:key="promotion-group-{{ $index }}"><header><strong>Grupo {{ $index+1 }}</strong>@if(count($groups)>1)<button type="button" wire:click="removeGroup({{ $index }})" aria-label="Eliminar grupo {{ $index+1 }}"><i class="bx bx-trash"></i></button>@endif</header><div class="promotion-form-grid is-three"><label><span>Nombre <b>*</b></span><input type="text" wire:model="groups.{{ $index }}.name" placeholder="Elige tus productos">@error("groups.$index.name")<small class="field-error">{{ $message }}</small>@enderror</label><label><span>Mínimo <b>*</b></span><input type="number" wire:model="groups.{{ $index }}.min_selections" min="0" max="99"></label><label><span>Máximo <b>*</b></span><input type="number" wire:model="groups.{{ $index }}.max_selections" min="1" max="99"></label></div><fieldset class="promotion-products"><legend>Productos disponibles <b>*</b></legend><div>@foreach($this->products as $product)<label><input type="checkbox" wire:model="groups.{{ $index }}.product_ids" value="{{ $product->id }}"><span>@if($product->image)<img src="{{ Storage::url($product->image) }}" alt="" width="38" height="38">@else<i class="bx bx-dish"></i>@endif<strong>{{ $product->name }}</strong><small>{{ $product->category?->name ?: 'Sin categoría' }}</small></span></label>@endforeach</div>@error("groups.$index.product_ids")<small class="field-error">{{ $message }}</small>@enderror</fieldset></article>@endforeach</div>
                            </section>
                        @endif
                    @elseif($wizardStep === 3)
                        <section class="promotion-form-section">
                            <header><span><i class="bx bx-image-alt"></i></span><div><h3>{{ $presentationType === 'new' ? 'Imagen de la novedad' : 'Banner promocional' }}</h3><p>{{ $presentationType === 'new' ? 'Es opcional: si no cargas otra, se usará la imagen del producto.' : 'Usa una imagen horizontal 3:1; es obligatoria para publicar promociones.' }}</p></div></header>
                            <label class="promotion-upload"><span>{{ $presentationType === 'new' ? 'Imagen alternativa' : 'Imagen horizontal del banner' }}</span><input type="file" wire:model="image" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG o WEBP, máximo 4 MB. Recomendado: 1200 × 400 px.</small>@if($image)<span class="promotion-current-image"><img src="{{ $image->temporaryUrl() }}" alt="Vista previa de la nueva imagen" width="180" height="72">Nueva imagen seleccionada</span>@elseif($currentImage)<span class="promotion-current-image"><img src="{{ Storage::url($currentImage) }}" alt="Imagen actual" width="180" height="72">Imagen actual</span>@endif @error('image')<small class="field-error">{{ $message }}</small>@enderror</label>
                        </section>
                        <section class="promotion-form-section"><header><span><i class="bx bx-calendar-event"></i></span><div><h3>Vigencia y publicación</h3><p>Sin fecha final continuará indefinidamente; los días permiten campañas como “solo jueves”.</p></div></header><div class="promotion-form-grid"><label><span>Fecha de lanzamiento <b>*</b></span><input type="date" wire:model="startsOn">@error('startsOn')<small class="field-error">{{ $message }}</small>@enderror</label><label><span>Fecha de fin</span><input type="date" wire:model="endsOn" min="{{ $startsOn ?: now()->toDateString() }}">@error('endsOn')<small class="field-error">{{ $message }}</small>@enderror</label></div><fieldset class="promotion-weekdays"><legend>Días disponibles <small>Sin selección significa todos los días.</small></legend><div>@foreach([1=>'Lun',2=>'Mar',3=>'Mié',4=>'Jue',5=>'Vie',6=>'Sáb',7=>'Dom'] as $value=>$label)<label><input type="checkbox" wire:model="weekdays" value="{{ $value }}"><span>{{ $label }}</span></label>@endforeach</div></fieldset><div class="promotion-channel-grid">@if($presentationType !== 'new')<label><input type="checkbox" wire:model="showOnPos"><span><i class="bx bx-store-alt"></i><strong>Punto de venta</strong><small>Disponible como promoción para caja.</small></span></label>@endif<label><input type="checkbox" wire:model="showOnDigitalMenu"><span><i class="bx bx-mobile-alt"></i><strong>Menú digital</strong><small>Visible en {{ $presentationType === 'new' ? 'Nuevos productos' : 'Promociones' }}.</small></span></label><label><input type="checkbox" wire:model="isActive"><span><i class="bx bx-broadcast"></i><strong>Publicar campaña</strong><small>También respetará fechas y días.</small></span></label></div>@error('channels')<p class="field-error">{{ $message }}</p>@enderror</section>
                    @else
                        @php
                            $reviewProduct = $primaryProductId ? $this->products->firstWhere('id', (int) $primaryProductId) : null;
                        @endphp
                        <section class="promotion-review"><div class="promotion-review__visual">@if($image)<img src="{{ $image->temporaryUrl() }}" alt="Vista previa de campaña">@elseif($currentImage)<img src="{{ Storage::url($currentImage) }}" alt="Imagen de campaña">@elseif($reviewProduct?->image)<img src="{{ Storage::url($reviewProduct->image) }}" alt="{{ $reviewProduct->name }}">@else<span><i class="bx bx-image-alt"></i></span>@endif</div><div class="promotion-review__content"><span class="promotion-type-badge"><i class="bx {{ $presentationType === 'new' ? 'bx-star' : ($presentationType === 'discount' ? 'bx-purchase-tag' : 'bx-gift') }}"></i>{{ $presentationType === 'new' ? 'Nuevo producto' : ($presentationType === 'discount' ? 'Descuento' : 'Promoción') }}</span><h3>{{ $name ?: 'Campaña sin título' }}</h3><p>{{ $shortDescription }}</p><dl><div><dt>Formato</dt><dd>{{ $presentationType === 'new' ? 'Tarjeta en Nuevos productos' : 'Banner en Promociones' }}</dd></div><div><dt>Vigencia</dt><dd>{{ $startsOn }} — {{ $endsOn ?: 'Sin fecha final' }}</dd></div><div><dt>Días</dt><dd>{{ empty($weekdays) ? 'Todos los días' : collect($weekdays)->map(fn($day)=>[1=>'Lun',2=>'Mar',3=>'Mié',4=>'Jue',5=>'Vie',6=>'Sáb',7=>'Dom'][(int)$day] ?? '')->filter()->join(', ') }}</dd></div><div><dt>Publicación</dt><dd>{{ $isActive ? 'Publicada' : 'Borrador pausado' }}</dd></div></dl></div></section>
                    @endif
                </div>
                <footer class="promotion-modal__footer"><button type="button" class="promotions-secondary" wire:click="closeEditor">Cancelar</button><span class="promotion-modal__footer-spacer"></span>@if($wizardStep>1)<button type="button" class="promotions-secondary" wire:click="previousWizardStep"><i class="bx bx-left-arrow-alt"></i>Anterior</button>@endif @if($wizardStep<4)<button type="button" class="promotions-primary" wire:click="nextWizardStep">Continuar<i class="bx bx-right-arrow-alt"></i></button>@else<button type="button" class="promotions-primary" wire:click="save" wire:loading.attr="disabled" wire:target="save,image"><span wire:loading.remove wire:target="save"><i class="bx bx-check"></i>Publicar campaña</span><span wire:loading wire:target="save"><i class="bx bx-loader-alt bx-spin"></i>Publicando</span></button>@endif</footer>
            </section>
        </div>
    @endif
</div>
