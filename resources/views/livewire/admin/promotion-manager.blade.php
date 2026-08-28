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
                            <div class="promotion-card__meta"><span><i class="bx bx-calendar"></i>{{ $promotion->starts_on->format('d/m/Y') }} — {{ $promotion->ends_on?->format('d/m/Y') ?? 'Sin fecha final' }}</span><span><i class="bx bx-repeat"></i>{{ $promotion->scheduleSummary() }}</span><span><i class="bx {{ $promotion->isProductLaunch() ? 'bx-layout' : 'bx-layer' }}"></i>{{ $promotion->isProductLaunch() ? 'Tarjeta de novedad' : $promotion->groups->count().' '.($promotion->groups->count() === 1 ? 'grupo' : 'grupos') }}</span></div>
                            <div class="promotion-card__channels">@if($promotion->show_on_pos)<span><i class="bx bx-store-alt"></i>POS</span>@endif @if($promotion->show_on_kiosk)<span><i class="bx bx-devices"></i>Kiosco</span>@endif @if($promotion->show_on_digital_menu)<span><i class="bx bx-mobile-alt"></i>Menú digital</span>@endif @if(!$promotion->isProductLaunch() || $promotion->hasAutomaticPricingRule())<span><i class="bx bx-map-pin"></i>{{ $promotion->fulfillmentSummary() }}</span>@endif @unless($promotion->is_active)<span class="is-muted"><i class="bx bx-pause-circle"></i>Pausada</span>@endunless</div>
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
                @php
                    $wizardSteps = $presentationType === 'new'
                        ? [1=>['bx-bullseye','Objetivo'],3=>['bx-edit-alt','Contenido'],4=>['bx-calendar-event','Publicación'],5=>['bx-check-shield','Revisión']]
                        : [1=>['bx-bullseye','Objetivo'],2=>['bx-purchase-tag-alt','Beneficio'],3=>['bx-edit-alt','Contenido'],4=>['bx-calendar-event','Publicación'],5=>['bx-check-shield','Revisión']];
                    $wizardStepIds = array_keys($wizardSteps);
                    $wizardPosition = max(1, (int) array_search($wizardStep, $wizardStepIds, true) + 1);
                    $wizardTitle = $wizardSteps[$wizardStep][1] ?? 'Revisión';
                @endphp
                <header class="promotion-modal__header"><div><span class="app-eyebrow">{{ $editingId ? 'Actualizar campaña' : 'Nueva campaña' }}</span><h2 id="promotion-editor-title">{{ $editingId ? 'Editar campaña' : 'Crear campaña' }}</h2><p id="promotion-editor-description">Paso {{ $wizardPosition }} de {{ count($wizardSteps) }} · {{ $wizardTitle }}</p></div><button type="button" wire:click="closeEditor" aria-label="Cerrar" autofocus><i class="bx bx-x"></i></button></header>
                <ol class="promotion-wizard__steps" aria-label="Progreso de creación" style="--wizard-step-count:{{ count($wizardSteps) }}">
                    @foreach($wizardSteps as $step=>$stepData)
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
                        <section class="promotion-form-section">
                            <header><span><i class="bx bx-purchase-tag-alt"></i></span><div><h3>¿Cómo se calculará el beneficio?</h3><p>Selecciona una sola mecánica. El sistema aplicará y validará el precio automáticamente.</p></div></header>
                            <div class="promotion-mechanic-options" role="radiogroup" aria-label="Mecánica de precio">
                                @php $mechanics = $presentationType === 'discount'
                                    ? ['percentage_discount'=>['bx-purchase-tag','Descuento porcentual','Elige el producto y el porcentaje que se descontará de su precio base.'],'fixed_product_price'=>['bx-dollar-circle','Precio especial','Sustituye temporalmente el precio base por un importe menor.']]
                                    : ['fixed_price'=>['bx-dollar-circle','Combo a precio fijo','Selecciona qué productos incluye y define un precio final.'],'two_for_one'=>['bx-copy','Promoción 2 × 1','Compra una unidad y recibe otra gratis.'],'second_half'=>['bx-trending-down','Segundo a mitad','Compra una unidad y la segunda tiene 50% de descuento.'],'custom_quantity'=>['bx-slider-alt','Promoción por cantidad','Configura cuántas unidades compra y cuántas reciben descuento.']]; @endphp
                                @foreach($mechanics as $value=>$option)<label><input type="radio" wire:model.live="pricingMechanic" value="{{ $value }}"><span><i class="bx {{ $option[0] }}"></i><strong>{{ $option[1] }}</strong><small>{{ $option[2] }}</small></span></label>@endforeach
                            </div>
                            @error('pricingMechanic')<small class="field-error">{{ $message }}</small>@enderror
                        </section>

                        @if($pricingMechanic !== 'fixed_price')
                            <section class="promotion-form-section">
                                <header><span><i class="bx bx-dish"></i></span><div><h3>Producto que recibirá el beneficio</h3><p>Sus ingredientes y agregables se conservan; el descuento solo afecta el precio base.</p></div></header>
                                <fieldset class="promotion-products promotion-products--primary"><legend>Selecciona un producto <b>*</b></legend><div>@foreach($this->products as $product)<label><input type="radio" wire:model.live="primaryProductId" value="{{ $product->id }}"><span>@if($product->image)<img src="{{ Storage::url($product->image) }}" alt="" width="46" height="46">@else<i class="bx bx-dish"></i>@endif<strong>{{ $product->name }}</strong><small>{{ $product->category?->name ?: 'Sin categoría' }}</small></span></label>@endforeach</div>@error('primaryProductId')<small class="field-error">{{ $message }}</small>@enderror</fieldset>
                                @if($pricingMechanic === 'percentage_discount')
                                    <div class="promotion-form-grid is-three"><label><span>Porcentaje de descuento <b>*</b></span><div class="promotion-input-suffix"><input type="number" wire:model.live.blur="discountPercentage" min="1" max="99"><span>%</span></div>@error('discountPercentage')<small class="field-error">{{ $message }}</small>@enderror</label><label><span>Precio resultante</span><input type="number" value="{{ $price }}" readonly aria-readonly="true"><small>Calculado desde el precio actual.</small></label></div>
                                @elseif($pricingMechanic === 'fixed_product_price')
                                    <div class="promotion-form-grid"><label><span>Precio especial del producto <b>*</b></span><input type="number" wire:model="price" min="0.01" step="0.01" placeholder="0.00"><small>Debe ser menor que el precio actual.</small>@error('price')<small class="field-error">{{ $message }}</small>@enderror</label><div class="promotion-rule-summary"><i class="bx bx-check-shield"></i><span><strong>El producto costará ${{ number_format((float) ($price ?: 0), 2) }}</strong><small>Ingredientes y agregables conservan su precio.</small></span></div></div>
                                @elseif(in_array($pricingMechanic,['two_for_one','second_half','custom_quantity'],true))
                                    @if($pricingMechanic === 'custom_quantity')<div class="promotion-form-grid is-three"><label><span>Compra <b>*</b></span><input type="number" wire:model="buyQuantity" min="1" max="99"></label><label><span>Recibe <b>*</b></span><input type="number" wire:model="rewardQuantity" min="1" max="99"></label><label><span>Descuento de recompensa <b>*</b></span><div class="promotion-input-suffix"><input type="number" wire:model="rewardDiscountPercentage" min="1" max="100"><span>%</span></div></label></div>@endif
                                    <div class="promotion-form-grid"><label><span>Máximo de aplicaciones por pedido</span><input type="number" wire:model="maxApplicationsPerOrder" min="1" max="99" placeholder="Sin límite"><small>Vacío permite repetir el beneficio.</small></label><div class="promotion-rule-summary"><i class="bx bx-check-shield"></i><span><strong>Compra {{ $buyQuantity }} y recibe {{ $rewardQuantity }} al {{ $rewardDiscountPercentage }}%</strong><small>No se descuentan ingredientes ni agregables.</small></span></div></div>
                                @endif
                            </section>
                        @endif

                        @if($pricingMechanic === 'fixed_price')
                        <section class="promotion-form-section">
                                <header class="promotion-groups-heading"><span><i class="bx bx-layer"></i></span><div><h3>¿Qué productos incluye la promoción?</h3><p>Crea grupos, define cuántos puede elegir el cliente y asigna el precio final del combo.</p></div><button type="button" wire:click="addGroup"><i class="bx bx-plus"></i>Añadir grupo</button></header>
                                <div class="promotion-form-grid promotion-fixed-price"><label><span>Precio final del combo <b>*</b></span><input type="number" wire:model="price" min="0.01" step="0.01">@error('price')<small class="field-error">{{ $message }}</small>@enderror</label></div>
                                <div class="promotion-groups">@foreach($groups as $index=>$group)<article class="promotion-group" wire:key="promotion-group-{{ $index }}"><header><strong>Grupo {{ $index+1 }}</strong>@if(count($groups)>1)<button type="button" wire:click="removeGroup({{ $index }})" aria-label="Eliminar grupo {{ $index+1 }}"><i class="bx bx-trash"></i></button>@endif</header><div class="promotion-form-grid is-three"><label><span>Nombre <b>*</b></span><input type="text" wire:model="groups.{{ $index }}.name" placeholder="Elige tus productos">@error("groups.$index.name")<small class="field-error">{{ $message }}</small>@enderror</label><label><span>Mínimo <b>*</b></span><input type="number" wire:model="groups.{{ $index }}.min_selections" min="0" max="99"></label><label><span>Máximo <b>*</b></span><input type="number" wire:model="groups.{{ $index }}.max_selections" min="1" max="99"></label></div><fieldset class="promotion-products"><legend>Productos disponibles <b>*</b></legend><div>@foreach($this->products as $product)<label><input type="checkbox" wire:model="groups.{{ $index }}.product_ids" value="{{ $product->id }}"><span>@if($product->image)<img src="{{ Storage::url($product->image) }}" alt="" width="38" height="38">@else<i class="bx bx-dish"></i>@endif<strong>{{ $product->name }}</strong><small>{{ $product->category?->name ?: 'Sin categoría' }}</small></span></label>@endforeach</div>@error("groups.$index.product_ids")<small class="field-error">{{ $message }}</small>@enderror</fieldset></article>@endforeach</div>
                        </section>
                        @endif
                    @elseif($wizardStep === 3)
                        @if($presentationType === 'new')
                            <section class="promotion-form-section">
                                <header><span><i class="bx bx-dish"></i></span><div><h3>Selecciona el producto nuevo</h3><p>La tarjeta conservará su precio, ingredientes, variantes y agregables actuales.</p></div></header>
                                <fieldset class="promotion-products promotion-products--primary"><legend>Producto que deseas presentar <b>*</b></legend><div>@foreach($this->products as $product)<label><input type="radio" wire:model.live="primaryProductId" value="{{ $product->id }}"><span>@if($product->image)<img src="{{ Storage::url($product->image) }}" alt="" width="46" height="46">@else<i class="bx bx-dish"></i>@endif<strong>{{ $product->name }}</strong><small>{{ $product->category?->name ?: 'Sin categoría' }} · ${{ number_format($product->price, 2) }}</small></span></label>@endforeach</div>@error('primaryProductId')<small class="field-error">{{ $message }}</small>@enderror</fieldset>
                            </section>
                        @endif
                        <section class="promotion-form-section">
                            <header><span><i class="bx bx-edit-alt"></i></span><div><h3>Contenido visible para el cliente</h3><p>Define el título y el mensaje que acompañarán {{ $presentationType === 'new' ? 'la tarjeta del producto' : 'el banner de la campaña' }}.</p></div></header>
                            <div class="promotion-form-grid"><label><span>Título público <b>*</b></span><input type="text" wire:model.live.blur="name" maxlength="120" placeholder="Ej. Jueves de hamburguesas">@error('name')<small class="field-error">{{ $message }}</small>@enderror</label><label class="is-full"><span>Resumen <b>*</b></span><input type="text" wire:model.live.blur="shortDescription" maxlength="160" placeholder="Mensaje breve que se verá en la tarjeta">@error('shortDescription')<small class="field-error">{{ $message }}</small>@enderror</label><label class="is-full"><span>Descripción completa</span><textarea wire:model.live.blur="description" rows="3" maxlength="500" placeholder="Información adicional para el detalle de la campaña"></textarea></label></div>
                        </section>
                        <section class="promotion-form-section">
                            <header><span><i class="bx bx-image-alt"></i></span><div><h3>{{ $presentationType === 'promotion' ? 'Banner promocional' : 'Imagen de la tarjeta' }}</h3><p>{{ $presentationType === 'promotion' ? 'Usa una imagen horizontal 3:1; será el fondo del banner.' : 'Es opcional: si no cargas otra imagen, se utilizará la del producto seleccionado.' }}</p></div></header>
                            <label class="promotion-upload" for="promotion-campaign-image"><span>{{ $presentationType === 'promotion' ? 'Imagen horizontal del banner' : 'Imagen alternativa del producto' }}</span><input id="promotion-campaign-image" type="file" wire:model.live="image" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG o WEBP, máximo 4 MB. Recomendado: {{ $presentationType === 'promotion' ? '1200 × 400 px' : '800 × 800 px' }}.</small>@error('image')<small class="field-error">{{ $message }}</small>@enderror</label>
                            <div @class(['promotion-upload-preview','is-product'=>$presentationType !== 'promotion']) aria-live="polite" wire:loading.remove wire:target="image">
                                @if($image)
                                    <img src="{{ $image->temporaryUrl() }}" alt="Vista previa del banner seleccionado">
                                    <span><i class="bx bx-check-circle"></i>Vista previa del banner</span>
                                @elseif($currentImage)
                                    <img src="{{ Storage::url($currentImage) }}" alt="Vista previa del banner actual">
                                    <span><i class="bx bx-image"></i>Imagen guardada actualmente</span>
                                @else
                                    <div><i class="bx bx-image-add"></i><strong>La imagen aparecerá aquí</strong><small>{{ $presentationType === 'promotion' ? 'Así se mostrará la proporción horizontal de la campaña.' : 'La tarjeta conservará una proporción compacta y centrada.' }}</small></div>
                                @endif
                            </div>
                            <div class="promotion-upload-preview is-loading" wire:loading.grid wire:target="image" role="status"><i class="bx bx-loader-alt bx-spin"></i><strong>Preparando previsualización…</strong></div>
                        </section>
                    @elseif($wizardStep === 4)
                        @if($pricingMechanic !== 'catalog_price')
                            <section class="promotion-form-section promotion-service-rules">
                                <header><span><i class="bx bx-map-pin"></i></span><div><h3>Modalidades y condiciones</h3><p>Define en qué tipos de pedido se puede usar. La validación se repetirá al confirmar la venta.</p></div></header>
                                <fieldset class="promotion-fulfillment">
                                    <legend>Modalidades válidas <b>*</b><small>Selecciona al menos una opción.</small></legend>
                                    <div>
                                        @foreach(['dine_in'=>['bx-restaurant','Comer aquí','Exclusivo para pedidos atendidos por meseros.'],'takeaway'=>['bx-shopping-bag','Para llevar','Venta inmediata preparada desde el punto de venta.'],'pickup'=>['bx-store-alt','Pasar a buscar','Pedido solicitado para recoger posteriormente.'],'delivery'=>['bx-cycling','Entrega a domicilio','Pedido enviado mediante el flujo de delivery.']] as $mode=>$data)
                                            <label><input type="checkbox" wire:model="fulfillmentModes" value="{{ $mode }}"><span><i class="bx {{ $data[0] }}"></i><strong>{{ $data[1] }}</strong><small>{{ $data[2] }}</small></span></label>
                                        @endforeach
                                    </div>
                                    @error('fulfillmentModes')<p class="field-error">{{ $message }}</p>@enderror
                                </fieldset>
                                <label class="promotion-terms"><span>Términos y condiciones visibles</span><textarea wire:model="termsAndConditions" rows="3" maxlength="1000" placeholder="Ej. Válido hasta agotar existencias. No acumulable con otras promociones."></textarea><small>Se mostrarán en el menú digital, kiosco y selector de la promoción.</small>@error('termsAndConditions')<small class="field-error">{{ $message }}</small>@enderror</label>
                                <div class="promotion-channel-grid"><label><input type="checkbox" wire:model="showOnKiosk"><span><i class="bx bx-devices"></i><strong>Kiosco</strong><small>Visible después de que el cliente elija su modalidad.</small></span></label></div>
                            </section>
                        @endif
                        <section class="promotion-form-section promotion-schedule">
                            <header><span><i class="bx bx-calendar-event"></i></span><div><h3>¿Cuándo estará disponible?</h3><p>La vigencia limita la campaña; la recurrencia define en qué fechas se activa dentro de ella.</p></div></header>
                            <div class="promotion-schedule-options" role="radiogroup" aria-label="Tipo de programación">
                                @foreach(['date_range'=>['bx-calendar','Rango de fechas','Disponible todos los días del periodo.'],'weekdays'=>['bx-calendar-week','Días de la semana','Ej. martes y jueves.'],'monthly'=>['bx-calendar-star','Una vez al mes','Elige un día del mes.']] as $value=>$option)<label><input type="radio" wire:model.live="scheduleType" value="{{ $value }}"><span><i class="bx {{ $option[0] }}"></i><strong>{{ $option[1] }}</strong><small>{{ $option[2] }}</small></span></label>@endforeach
                            </div>
                            <div class="promotion-form-grid"><label><span>Fecha de inicio <b>*</b></span><input type="date" wire:model="startsOn">@error('startsOn')<small class="field-error">{{ $message }}</small>@enderror</label><label><span>Fecha de fin</span><input type="date" wire:model="endsOn" min="{{ $startsOn ?: now()->toDateString() }}"><small>Vacía significa sin fecha final.</small>@error('endsOn')<small class="field-error">{{ $message }}</small>@enderror</label></div>
                            @if($scheduleType === 'weekdays')<fieldset class="promotion-weekdays"><legend>Selecciona los días <b>*</b><small>La campaña solo se activará esos días.</small></legend><div>@foreach([1=>'Lun',2=>'Mar',3=>'Mié',4=>'Jue',5=>'Vie',6=>'Sáb',7=>'Dom'] as $value=>$label)<label><input type="checkbox" wire:model="weekdays" value="{{ $value }}"><span>{{ $label }}</span></label>@endforeach</div>@error('weekdays')<small class="field-error">{{ $message }}</small>@enderror</fieldset>@endif
                            @if($scheduleType === 'monthly')<div class="promotion-form-grid"><label><span>Día de cada mes <b>*</b></span><input type="number" wire:model="monthlyDay" min="1" max="31"><small>Si el mes no contiene ese día, la campaña no se activa.</small>@error('monthlyDay')<small class="field-error">{{ $message }}</small>@enderror</label></div>@endif
                            <div class="promotion-channel-grid">@if($pricingMechanic !== 'catalog_price')<label><input type="checkbox" wire:model="showOnPos"><span><i class="bx bx-store-alt"></i><strong>Punto de venta</strong><small>Para llevar, pasar a buscar y delivery; comedor se atiende con meseros.</small></span></label>@endif<label><input type="checkbox" wire:model="showOnDigitalMenu"><span><i class="bx bx-mobile-alt"></i><strong>Menú digital</strong><small>Visible en {{ $presentationType === 'new' ? 'Nuevos productos' : 'Promociones' }}.</small></span></label><label><input type="checkbox" wire:model="isActive"><span><i class="bx bx-broadcast"></i><strong>Publicar campaña</strong><small>También respetará fechas y recurrencia.</small></span></label></div>@error('channels')<p class="field-error">{{ $message }}</p>@enderror
                        </section>
                    @else
                        @php
                            $reviewProduct = $primaryProductId ? $this->products->firstWhere('id', (int) $primaryProductId) : null;
                            $reviewImage = $image ? $image->temporaryUrl() : ($currentImage ? Storage::url($currentImage) : ($reviewProduct?->image ? Storage::url($reviewProduct->image) : null));
                            $mechanicLabel = ['catalog_price'=>'Precio normal','fixed_price'=>'Combo a precio fijo','fixed_product_price'=>'Precio especial $'.number_format((float)($price ?: 0),2),'percentage_discount'=>($discountPercentage ?: 0).'% de descuento','two_for_one'=>'2 × 1','second_half'=>'Segundo al 50%','custom_quantity'=>"Compra $buyQuantity y recibe $rewardQuantity con $rewardDiscountPercentage% de descuento"][$pricingMechanic] ?? $pricingMechanic;
                            $scheduleLabel = $scheduleType === 'monthly' ? "Cada mes, el día $monthlyDay" : ($scheduleType === 'weekdays' ? collect($weekdays)->map(fn($day)=>[1=>'Lun',2=>'Mar',3=>'Mié',4=>'Jue',5=>'Vie',6=>'Sáb',7=>'Dom'][(int)$day] ?? '')->filter()->join(', ') : 'Todos los días del rango');
                            $modalitiesLabel = collect($fulfillmentModes)->map(fn($mode)=>['dine_in'=>'Comer aquí','takeaway'=>'Para llevar','pickup'=>'Pasar a buscar','delivery'=>'Entrega a domicilio'][$mode] ?? '')->filter()->join(', ');
                            $includedProducts = $pricingMechanic === 'fixed_price'
                                ? collect($groups)->flatMap(fn($group)=>collect($group['product_ids'] ?? [])->map(fn($id)=>$this->products->firstWhere('id',(int)$id)?->name))->filter()->unique()->values()
                                : collect([$reviewProduct?->name])->filter();
                            $reviewOriginalPrice = (float) ($reviewProduct?->price ?? 0);
                            $reviewDiscountPercent = $pricingMechanic === 'percentage_discount'
                                ? (int) ($discountPercentage ?: 0)
                                : ($presentationType === 'discount' && $reviewOriginalPrice > 0 ? max(1,min(99,(int)round((1-((float)$price/$reviewOriginalPrice))*100))) : 0);
                        @endphp
                        <section class="promotion-review-mockup">
                            <header class="promotion-review-mockup__header"><div><span><i class="bx bx-show"></i></span><div><h3>Así lo verá el cliente</h3><p>Cambia el dispositivo para comprobar la tarjeta antes de publicarla.</p></div></div><div class="promotion-device-switcher" role="radiogroup" aria-label="Tamaño de la vista previa">@foreach(['mobile'=>['bx-mobile-alt','Móvil'],'tablet'=>['bx-tab','Tableta'],'desktop'=>['bx-desktop','Escritorio']] as $device=>$data)<label><input type="radio" wire:model.live="previewDevice" value="{{ $device }}"><span><i class="bx {{ $data[0] }}"></i>{{ $data[1] }}</span></label>@endforeach</div></header>
                            <div class="promotion-preview-stage">
                                <div class="promotion-device-frame is-{{ $previewDevice }}" aria-label="Vista previa {{ $previewDevice }}">
                                    <div class="promotion-device-frame__bar"><i></i><i></i><i></i><span>Menú digital</span></div>
                                    <div class="promotion-device-frame__screen">
                                        <div class="promotion-preview-heading"><small>{{ $presentationType === 'new' ? 'RECIÉN LLEGADOS' : ($presentationType === 'discount' ? 'PRECIOS ESPECIALES' : 'BENEFICIOS POR TIEMPO LIMITADO') }}</small><strong>{{ $presentationType === 'new' ? 'Nuevos productos' : ($presentationType === 'discount' ? 'Descuentos' : 'Promociones') }}</strong></div>
                                        @if($presentationType === 'new')
                                            <div class="promotion-preview-new-grid"><article class="promotion-preview-new-card"><div class="promotion-preview-new-card__image">@if($reviewImage)<img src="{{ $reviewImage }}" alt="Vista previa de {{ $name }}">@else<i class="bx bx-image-alt"></i>@endif<span><i class="bx bx-star"></i>Nuevo</span></div><div><small>{{ $reviewProduct?->category?->name ?: 'Producto nuevo' }}</small><h4>{{ $name ?: 'Nombre del producto' }}</h4><strong>${{ number_format((float) ($reviewProduct?->price ?? $price ?: 0), 2) }}</strong><button type="button" tabindex="-1" aria-hidden="true"><i class="bx bx-plus"></i></button></div></article><article class="promotion-preview-new-card is-placeholder" aria-hidden="true"><div></div><span></span><span></span></article></div>
                                        @elseif($presentationType === 'discount')
                                            <div class="promotion-preview-new-grid promotion-preview-discount-grid"><article class="promotion-preview-new-card promotion-preview-discount-card"><div class="promotion-preview-new-card__image">@if($reviewImage)<img src="{{ $reviewImage }}" alt="Vista previa de {{ $reviewProduct?->name ?: $name }}">@else<i class="bx bx-image-alt"></i>@endif<button type="button" tabindex="-1" aria-hidden="true"><i class="bx bx-plus"></i></button></div><div><span class="promotion-preview-discount-price"><strong>${{ number_format((float)$price,2) }}</strong><b><i class="bx bxs-hot"></i>-{{ $reviewDiscountPercent }}%</b></span><del class="promotion-preview-discount-original">${{ number_format($reviewOriginalPrice,2) }}</del></div></article><article class="promotion-preview-new-card is-placeholder" aria-hidden="true"><div></div><span></span><span></span></article></div>
                                        @else
                                            <article class="promotion-preview-banner" @if($reviewImage) style="background-image:linear-gradient(90deg,rgba(12,25,17,.94) 0%,rgba(12,25,17,.68) 48%,rgba(12,25,17,.08) 100%),url('{{ $reviewImage }}')" @endif>
                                                <div><span class="promotion-preview-banner__badge"><i class="bx {{ $presentationType === 'discount' ? 'bx-purchase-tag' : 'bx-gift' }}"></i>{{ $mechanicLabel }}</span><h4>{{ $name ?: 'Nombre de la campaña' }}</h4><p>{{ $shortDescription ?: 'El resumen de la campaña aparecerá aquí.' }}</p>@if($pricingMechanic !== 'fixed_price')<small><i class="bx bx-dish"></i>{{ $reviewProduct?->name }}</small>@elseif($includedProducts->isNotEmpty())<small><i class="bx bx-bowl-hot"></i>Incluye {{ $includedProducts->join(', ') }}</small>@endif<div class="promotion-preview-banner__bottom"><b>{{ $scheduleLabel }}</b><strong><small>{{ in_array($pricingMechanic,['fixed_price','fixed_product_price'],true) ? 'PRECIO PROMO' : 'PRECIO BASE' }}</small>${{ number_format((float) $price, 2) }}</strong></div></div>
                                            </article>
                                            <div class="promotion-preview-dots" aria-hidden="true"><i></i><i class="is-active"></i><i></i></div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="promotion-review-facts"><article><i class="bx bx-purchase-tag-alt"></i><span><small>{{ $presentationType === 'discount' ? 'Tipo de descuento' : ($presentationType === 'new' ? 'Presentación' : 'Tipo de promoción') }}</small><strong>{{ $mechanicLabel }}</strong></span></article>@if($presentationType !== 'new')<article><i class="bx bx-dish"></i><span><small>Productos incluidos</small><strong>{{ $includedProducts->join(', ') ?: 'Sin productos seleccionados' }}</strong></span></article>@endif<article><i class="bx bx-calendar-event"></i><span><small>Publicación</small><strong>{{ $scheduleLabel }} · {{ $startsOn }} — {{ $endsOn ?: 'Sin fecha final' }}</strong></span></article>@if($pricingMechanic !== 'catalog_price')<article><i class="bx bx-map-pin"></i><span><small>Modalidades</small><strong>{{ $modalitiesLabel }}</strong></span></article>@endif</div>
                        </section>
                    @endif
                </div>
                <footer class="promotion-modal__footer"><button type="button" class="promotions-secondary" wire:click="closeEditor">Cancelar</button><span class="promotion-modal__footer-spacer"></span>@if($wizardStep>1)<button type="button" class="promotions-secondary" wire:click="previousWizardStep"><i class="bx bx-left-arrow-alt"></i>Anterior</button>@endif @if($wizardStep<5)<button type="button" class="promotions-primary" wire:click="nextWizardStep">Continuar<i class="bx bx-right-arrow-alt"></i></button>@else<button type="button" class="promotions-primary" wire:click="save" wire:loading.attr="disabled" wire:target="save,image"><span wire:loading.remove wire:target="save"><i class="bx bx-check"></i>Publicar campaña</span><span wire:loading wire:target="save"><i class="bx bx-loader-alt bx-spin"></i>Publicando</span></button>@endif</footer>
            </section>
        </div>
    @endif
</div>
