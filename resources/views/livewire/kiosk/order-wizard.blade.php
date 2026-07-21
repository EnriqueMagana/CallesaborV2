<main class="kiosk-shell">
    <header class="kiosk-header">
        <a class="kiosk-brand" href="{{ route('kiosk.order', $terminalToken) }}" wire:navigate>
            <x-business.brand-mark :settings="$businessSettings" class="kiosk-brand-mark" />
            <span>
                <strong>{{ $businessSettings?->business_name ?? config('app.name') }}</strong>
                <small>Ordena fácil, disfruta pronto</small>
            </span>
        </a>

        @if ($step > 1 && $step < 6)
            <div class="kiosk-progress" aria-label="Progreso del pedido">
                @foreach ([2 => 'Te ayudamos', 3 => 'Menú', 4 => 'Personaliza', 5 => 'Confirma'] as $number => $label)
                    <div class="kiosk-progress-item {{ $step >= $number ? 'is-active' : '' }}">
                        <span>{{ $number - 1 }}</span><small>{{ $label }}</small>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($step >= 3 && $step < 6)
            <button class="kiosk-cart-indicator" type="button" wire:click="reviewOrder" aria-label="Ver mi pedido">
                <i class="bx bx-shopping-bag"></i>
                <span>{{ $this->cartCount }}</span>
                <strong>${{ number_format($this->cartTotal, 2) }}</strong>
            </button>
        @endif
    </header>

    @if ($step === 1)
        <section class="kiosk-welcome">
            <div class="kiosk-welcome-copy">
                <span class="kiosk-eyebrow">Kiosco de autoservicio</span>
                <h1>{{ $this->terminal->welcome_title }}</h1>
                <p>{{ $this->terminal->welcome_message }}</p>
                <div class="kiosk-trust-row">
                    <span><i class="bx bx-star"></i> 100% personalizado</span>
                    <span><i class="bx bx-qr-scan"></i> Seguimiento por QR</span>
                </div>
            </div>
            <div class="kiosk-fulfillment-grid">
                @if ($this->terminal->allow_dine_in)
                    <button class="kiosk-choice-card" type="button" wire:click="chooseFulfillment('dine_in')">
                        <span class="kiosk-choice-icon"><i class="bx bx-restaurant"></i></span>
                        <span><strong>Comer aquí</strong><small>Disfruta tu orden en el restaurante</small></span>
                        <i class="bx bx-right-arrow-alt"></i>
                    </button>
                @endif
                @if ($this->terminal->allow_takeaway)
                    <button class="kiosk-choice-card" type="button" wire:click="chooseFulfillment('takeaway')">
                        <span class="kiosk-choice-icon"><i class="bx bx-shopping-bag"></i></span>
                        <span><strong>Para llevar</strong><small>Preparamos todo para que continúes</small></span>
                        <i class="bx bx-right-arrow-alt"></i>
                    </button>
                @endif
                @if ($this->terminal->allow_delivery)
                    <button class="kiosk-choice-card kiosk-choice-card-delivery" type="button"
                        wire:click="chooseFulfillment('delivery')">
                        <span class="kiosk-choice-icon"><i class="bx bx-cycling"></i></span>
                        <span><strong>Para domicilio</strong><small>Lo llevamos a la dirección que nos indiques</small></span>
                        <i class="bx bx-right-arrow-alt"></i>
                    </button>
                @endif
            </div>
        </section>
    @elseif($step === 2)
        <section class="kiosk-recommendations">
            <div class="kiosk-recommendation-head">
                <button class="kiosk-back-button" type="button" wire:click="$set('step', 1)"><i
                        class="bx bx-left-arrow-alt"></i> Volver</button>
                <span class="kiosk-eyebrow">Paso 1 de 4 · Te ayudamos a elegir</span>
                <h1>¿Qué quieres comer hoy?</h1>
                <p>Toca la opción que más se te antoje. Después podrás revisar todos los productos y personalizarlos.
                </p>
            </div>
            <div class="kiosk-recommendation-grid">
                @foreach ($this->categories as $category)
                    @php $coverProduct = $category->products->first(fn($product) => filled($product->image)); @endphp
                    <button type="button" class="kiosk-recommendation-card"
                        wire:click="chooseRecommendation({{ $category->id }})">
                        <span class="kiosk-recommendation-media">
                            @if ($coverProduct)
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($coverProduct->image) }}"
                                    alt="Productos de {{ $category->name }}" width="360" height="220"
                                    loading="lazy">
                            @else
                                <i class="bx {{ $category->icon ?: 'bx-food-menu' }}"></i>
                            @endif
                            <b>{{ $category->products->count() }}
                                {{ $category->products->count() === 1 ? 'opción' : 'opciones' }}</b>
                        </span>
                        <span class="kiosk-recommendation-copy">
                            <span><strong>{{ $category->name }}</strong><i class="bx bx-right-arrow-alt"></i></span>
                            <small>{{ $category->description ?: 'Descubre nuestras opciones de ' . $category->name . '.' }}</small>
                            <b>Ver recomendaciones</b>
                        </span>
                    </button>
                @endforeach
                <button type="button" class="kiosk-recommendation-card kiosk-recommendation-all"
                    wire:click="chooseRecommendation">
                    <span class="kiosk-recommendation-media"><i class="bx bx-grid-alt"></i><b>Todos los
                            productos</b></span>
                    <span class="kiosk-recommendation-copy"><span><strong>Quiero ver todo</strong><i
                                class="bx bx-right-arrow-alt"></i></span><small>Explora el menú completo sin filtrar por
                            tipo de comida.</small><b>Abrir menú completo</b></span>
                </button>
            </div>
            <div class="kiosk-recommendation-help"><i class="bx bx-help-circle"></i>
                <div><strong>¿No estás seguro?</strong><span>Elige “Quiero ver todo”. Siempre podrás cambiar de
                        categoría dentro del menú.</span></div>
            </div>
        </section>
    @elseif($step === 3)
        <section class="kiosk-menu-layout">
            <div class="kiosk-catalog">
                <button class="kiosk-back-button" type="button" wire:click="$set('step', 2)"><i
                        class="bx bx-left-arrow-alt"></i> Cambiar recomendación</button>
                <div class="kiosk-catalog-head">
                    <div>
                        <span class="kiosk-eyebrow">Paso 2 de 4 · {{ $recommendationName }}</span>
                        <h1>{{ $recommendationName === 'Todo el menú' ? 'Explora todo el menú' : 'Te recomendamos ' . $recommendationName }}
                        </h1>
                    </div>
                    <label class="kiosk-search">
                        <i class="bx bx-search"></i>
                        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar en el menú"
                            aria-label="Buscar en el menú">
                    </label>
                </div>

                <nav class="kiosk-categories" aria-label="Categorías">
                    <button type="button" class="{{ $categoryFilter === null ? 'is-active' : '' }}"
                        wire:click="$set('categoryFilter', null)">Todo</button>
                    @foreach ($this->categories as $category)
                        <button type="button" class="{{ $categoryFilter === $category->id ? 'is-active' : '' }}"
                            wire:click="$set('categoryFilter', {{ $category->id }})">
                            @if ($category->icon)
                                <i class="bx {{ $category->icon }}"></i>
                            @endif
                            {{ $category->name }}
                        </button>
                    @endforeach
                </nav>

                <div class="kiosk-products" wire:loading.class="is-loading">
                    @forelse($this->products as $product)
                        <button class="kiosk-product-card" type="button" wire:click="openProduct({{ $product->id }})"
                            wire:key="kiosk-product-{{ $product->id }}">
                            <span class="kiosk-product-media">
                                @if ($product->image)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($product->image) }}"
                                        alt="{{ $product->name }}">
                                @else
                                    <i class="bx bx-food-menu"></i>
                                @endif
                                @if ($product->is_customizable || $product->addon_groups_count || $product->ingredients_count)
                                    <small><i class="bx bx-slider-alt"></i> Personalizable</small>
                                @endif
                            </span>
                            <span class="kiosk-product-info">
                                <strong>{{ $product->name }}</strong>
                                <small>{{ Str::limit($product->description ?: 'Preparado al momento para ti.', 72) }}</small>
                                <span>${{ number_format($product->price, 2) }} <i class="bx bx-plus"></i></span>
                            </span>
                        </button>
                    @empty
                        <div class="kiosk-empty-state">
                            <i class="bx bx-search-alt"></i>
                            <h2>No encontramos productos</h2>
                            <p>Prueba otra categoría o cambia tu búsqueda.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <aside class="kiosk-cart-panel">
                <div class="kiosk-cart-title">
                    <span><i class="bx bx-shopping-bag"></i></span>
                    <div><small>Tu selección</small>
                        <h2>Mi pedido</h2>
                    </div>
                    <b>{{ $this->cartCount }}</b>
                </div>
                <div class="kiosk-cart-lines">
                    @forelse($cart as $line)
                        <article class="kiosk-cart-line" wire:key="cart-{{ $line['id'] }}">
                            <div class="kiosk-cart-line-head">
                                <strong>{{ $line['product_name'] }}</strong>
                                <b>${{ number_format($line['subtotal'], 2) }}</b>
                            </div>
                            @if ($line['addon_names'] || $line['ingredient_names'])
                                <small>{{ implode(' · ', array_merge($line['addon_names'], $line['ingredient_names'])) }}</small>
                            @endif
                            <div class="kiosk-quantity">
                                <button type="button" wire:click="changeCartQuantity('{{ $line['id'] }}', -1)"
                                    aria-label="Quitar uno"><i class="bx bx-minus"></i></button>
                                <span>{{ $line['quantity'] }}</span>
                                <button type="button" wire:click="changeCartQuantity('{{ $line['id'] }}', 1)"
                                    aria-label="Agregar uno"><i class="bx bx-plus"></i></button>
                            </div>
                        </article>
                    @empty
                        <div class="kiosk-cart-empty"><i class="bx bx-basket"></i>
                            <p>Tu pedido está vacío</p><small>Toca un producto para agregarlo.</small>
                        </div>
                    @endforelse
                </div>
                @error('cart')
                    <p class="kiosk-error"><i class="bx bx-error-circle"></i>{{ $message }}</p>
                @enderror
                <div class="kiosk-cart-total">
                    <span>Total</span><strong>${{ number_format($this->cartTotal, 2) }}</strong>
                </div>
                <button class="kiosk-primary-button" type="button" wire:click="reviewOrder"
                    @disabled(empty($cart))>
                    Revisar pedido <i class="bx bx-right-arrow-alt"></i>
                </button>
            </aside>
        </section>
    @elseif($step === 4 && $this->product)
        <section class="kiosk-customizer">
            <button class="kiosk-back-button" type="button" wire:click="cancelCustomization"><i
                    class="bx bx-left-arrow-alt"></i> Volver al menú</button>
            <div class="kiosk-customizer-grid">
                <aside class="kiosk-customizer-summary">
                    <div class="kiosk-customizer-media">
                        @if ($this->product->image)
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($this->product->image) }}"
                                alt="{{ $this->product->name }}">
                        @else
                            <i class="bx bx-food-menu"></i>
                        @endif
                    </div>
                    <span class="kiosk-eyebrow">Paso 3 de 4</span>
                    <h1>{{ $this->product->name }}</h1>
                    <p>{{ $this->product->description ?: 'Hazlo exactamente como te gusta.' }}</p>
                    <strong>Desde ${{ number_format($this->product->price, 2) }}</strong>
                </aside>
                <div class="kiosk-options-panel">
                    <div class="kiosk-options-heading">
                        <div>
                            <h2>Personaliza tu elección</h2>
                            <p>Completa las opciones marcadas como obligatorias.</p>
                        </div><span><i class="bx bx-slider-alt"></i></span>
                    </div>
                    @error('customization')
                        <p class="kiosk-error kiosk-error-block"><i class="bx bx-error-circle"></i>{{ $message }}
                        </p>
                    @enderror

                    @foreach ($this->product->addonGroups as $group)
                        @php
                            $groupAddonIds = $group->addons->pluck('id')->map(fn($id) => (int) $id)->all();
                            $selectedCount = collect($groupAddonIds)->sum(
                                fn($id) => (int) ($addonQuantities[$id] ?? 0),
                            );
                            $minimum = $group->is_required
                                ? max(1, (int) $group->min_selections)
                                : (int) $group->min_selections;
                            $maximum = max(1, (int) $group->max_selections);
                        @endphp
                        <section class="kiosk-option-group">
                            <div class="kiosk-option-group-title">
                                <div>
                                    <h3>{{ $group->name }}</h3>
                                    <p>{{ $group->is_required ? 'Debes elegir' : 'Puedes elegir' }}
                                        {{ $minimum === $maximum ? $maximum : 'de ' . $minimum . ' a ' . $maximum }} en
                                        total. Usa los botones menos y más.</p>
                                </div>
                                <span class="{{ $selectedCount >= $minimum ? 'is-complete' : '' }}">
                                    <i
                                        class="bx {{ $selectedCount >= $minimum ? 'bx-check' : 'bx-error-circle' }}"></i>
                                    {{ $selectedCount >= $minimum ? 'Listo' : 'Obligatorio' }}
                                </span>
                            </div>
                            <div class="kiosk-selection-counter {{ $selectedCount >= $minimum ? 'is-complete' : '' }}"
                                aria-live="polite">
                                <div>
                                    <strong>{{ $selectedCount }} de {{ $maximum }}</strong>
                                    <span>{{ $selectedCount === 1 ? 'complemento seleccionado' : 'complementos seleccionados' }}</span>
                                </div>
                                <div class="kiosk-selection-progress"><span
                                        class="kiosk-selection-progress-step {{ $selectedCount >= 1 ? 'is-filled' : '' }}"></span>
                                    @for ($selectionStep = 2; $selectionStep <= $maximum; $selectionStep++)
                                        <span
                                            class="kiosk-selection-progress-step {{ $selectedCount >= $selectionStep ? 'is-filled' : '' }}"></span>
                                    @endfor
                                </div>
                            </div>
                            <div class="kiosk-option-list">
                                @foreach ($group->addons as $addon)
                                    @php $addonQuantity = (int) ($addonQuantities[$addon->id] ?? 0); @endphp
                                    <article class="kiosk-option {{ $addonQuantity > 0 ? 'is-selected' : '' }}">
                                        <div class="kiosk-option-media">
                                            @if ($addon->image)
                                                <img src="{{ \Illuminate\Support\Facades\Storage::url($addon->image) }}"
                                                    alt="Imagen de {{ $addon->name }}" width="112"
                                                    height="96" loading="lazy">
                                            @else
                                                <span><i class="bx bx-food-menu"></i><small>Sin imagen</small></span>
                                            @endif
                                            @if ($addonQuantity > 0)
                                                <b class="kiosk-selected-label"><i class="bx bx-check"></i>
                                                    Seleccionado</b>
                                            @endif
                                        </div>
                                        <div class="kiosk-option-content">
                                            <div class="kiosk-option-copy">
                                                <strong>{{ $addon->name }}</strong>
                                                <small>{{ $addon->description ?: 'Agrega este complemento a tu producto.' }}</small>
                                                <b>{{ $addon->extra_price > 0 ? '+$' . number_format($addon->extra_price, 2) . ' cada uno' : 'Sin costo extra' }}</b>
                                            </div>
                                            <div class="kiosk-addon-quantity"
                                                aria-label="Cantidad de {{ $addon->name }}">
                                                <button type="button"
                                                    wire:click="changeAddonQuantity({{ $group->id }}, {{ $addon->id }}, -1)"
                                                    @disabled($addonQuantity === 0 || ($group->is_required && $group->addons->count() === 1 && $addonQuantity === 1))
                                                    aria-label="Quitar una unidad de {{ $addon->name }}"><i
                                                        class="bx bx-minus"></i><span>Quitar</span></button>
                                                <div><small>Cantidad</small><strong>{{ $addonQuantity }}</strong></div>
                                                <button type="button"
                                                    wire:click="changeAddonQuantity({{ $group->id }}, {{ $addon->id }}, 1)"
                                                    @disabled($addonQuantity >= $maximum || ($selectedCount >= $maximum && $maximum > 1))
                                                    aria-label="Agregar una unidad de {{ $addon->name }}"><i
                                                        class="bx bx-plus"></i><span>Agregar</span></button>
                                            </div>
                                            <p class="kiosk-option-status {{ $addonQuantity > 0 ? 'is-selected' : '' }}"
                                                aria-live="polite">
                                                <i
                                                    class="bx {{ $addonQuantity > 0 ? 'bx-check-circle' : 'bx-circle' }}"></i>
                                                {{ $addonQuantity > 0 ? 'Has elegido ' . $addonQuantity . ' de este complemento' : 'No seleccionado' }}
                                            </p>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @endforeach

                    @if ($this->product->ingredients->isNotEmpty())
                        @php
                            $ingredientTotal = array_sum(array_map('intval', $selectedIngredients));
                            $ingredientMinimum = (int) $this->product->min_ingredients;
                            $ingredientMaximum = (int) ($this->product->max_ingredients ?? 0);
                        @endphp
                        <section class="kiosk-option-group">
                            <div class="kiosk-option-group-title">
                                <div>
                                    <h3>Ingredientes extra</h3>
                                    <p>{{ $ingredientMinimum > 0 ? 'Debes agregar al menos ' . $ingredientMinimum . '.' : 'Agrega solo los que quieras.' }}
                                        {{ $ingredientMaximum > 0 ? 'Puedes elegir hasta ' . $ingredientMaximum . ' en total.' : 'No hay límite de cantidad.' }}
                                    </p>
                                </div>
                                <span
                                    class="{{ $ingredientMinimum > 0 && $ingredientTotal >= $ingredientMinimum ? 'is-complete' : '' }}">
                                    <i
                                        class="bx {{ $ingredientMinimum > 0 ? ($ingredientTotal >= $ingredientMinimum ? 'bx-check' : 'bx-error-circle') : 'bx-info-circle' }}"></i>
                                    {{ $ingredientMinimum > 0 ? ($ingredientTotal >= $ingredientMinimum ? 'Listo' : 'Obligatorio') : 'Opcional' }}
                                </span>
                            </div>
                            <div class="kiosk-ingredient-counter {{ $ingredientTotal > 0 ? 'has-selection' : '' }}"
                                aria-live="polite">
                                <div>
                                    <strong>{{ $ingredientMaximum > 0 ? $ingredientTotal . ' de ' . $ingredientMaximum : $ingredientTotal }}</strong>
                                    <span>{{ $ingredientTotal === 1 ? 'ingrediente agregado' : 'ingredientes agregados' }}</span>
                                </div>
                                @if ($ingredientMaximum > 0 && $ingredientMaximum <= 12)
                                    <div class="kiosk-selection-progress" aria-hidden="true">
                                        @for ($ingredientStep = 1; $ingredientStep <= $ingredientMaximum; $ingredientStep++)
                                            <span
                                                class="kiosk-selection-progress-step {{ $ingredientTotal >= $ingredientStep ? 'is-filled' : '' }}"></span>
                                        @endfor
                                    </div>
                                @endif
                            </div>
                            <div class="kiosk-ingredient-list">
                                @foreach ($this->product->ingredients as $ingredient)
                                    @php $ingredientQuantity = (int) ($selectedIngredients[$ingredient->id] ?? 0); @endphp
                                    <article
                                        class="kiosk-ingredient {{ $ingredientQuantity > 0 ? 'is-selected' : '' }}">
                                        <div class="kiosk-ingredient-copy">
                                            <span class="kiosk-ingredient-media">
                                                @if ($ingredient->image)
                                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($ingredient->image) }}"
                                                        alt="Imagen de {{ $ingredient->name }}" width="56"
                                                    height="56" loading="lazy">@else<i class="bx bx-leaf"></i>
                                                @endif
                                            </span>
                                            <span>
                                                <strong>{{ $ingredient->name }}</strong>
                                                <small>{{ $ingredient->extra_price > 0 ? '+$' . number_format($ingredient->extra_price, 2) . ' cada uno' : 'Sin costo extra' }}</small>
                                                <b>{{ $ingredientQuantity > 0 ? 'Seleccionado · ' . $ingredientQuantity : 'No seleccionado' }}</b>
                                            </span>
                                        </div>
                                        <div class="kiosk-ingredient-quantity"
                                            aria-label="Cantidad de {{ $ingredient->name }}">
                                            <button type="button"
                                                wire:click="changeIngredient({{ $ingredient->id }}, -1)"
                                                @disabled($ingredientQuantity === 0)
                                                aria-label="Quitar una unidad de {{ $ingredient->name }}"><i
                                                    class="bx bx-minus"></i><span>Quitar</span></button>
                                            <div><small>Cantidad</small><strong>{{ $ingredientQuantity }}</strong>
                                            </div>
                                            <button type="button"
                                                wire:click="changeIngredient({{ $ingredient->id }}, 1)"
                                                @disabled($ingredientMaximum > 0 && $ingredientTotal >= $ingredientMaximum)
                                                aria-label="Agregar una unidad de {{ $ingredient->name }}"><i
                                                    class="bx bx-plus"></i><span>Agregar</span></button>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    <label class="kiosk-field"><span>Indicaciones especiales <small>Opcional</small></span>
                        <textarea wire:model="itemNotes" maxlength="300" placeholder="Ej. sin cebolla, salsa aparte…"></textarea>
                    </label>
                    <div class="kiosk-customizer-action">
                        <div class="kiosk-quantity kiosk-quantity-large">
                            <button type="button"
                                wire:click="$set('itemQuantity', {{ max(1, $itemQuantity - 1) }})"><i
                                    class="bx bx-minus"></i></button>
                            <span>{{ $itemQuantity }}</span>
                            <button type="button"
                                wire:click="$set('itemQuantity', {{ min(99, $itemQuantity + 1) }})"><i
                                    class="bx bx-plus"></i></button>
                        </div>
                        <button class="kiosk-primary-button" type="button" wire:click="addCustomizedProduct">Agregar
                            al pedido <i class="bx bx-plus"></i></button>
                    </div>
                </div>
            </div>
        </section>
    @elseif($step === 5)
        <section class="kiosk-review">
            <div class="kiosk-review-head">
                <button class="kiosk-back-button" type="button" wire:click="$set('step', 3)"><i
                        class="bx bx-left-arrow-alt"></i> Seguir ordenando</button>
                <span class="kiosk-eyebrow">Paso 4 de 4</span>
                <h1>Revisa y confirma</h1>
                <p>{{ $fulfillment === 'delivery' ? 'Confirma tus datos para llevar el pedido hasta tu domicilio.' : 'Usaremos tu nombre para avisarte cuando el pedido esté listo.' }}
                </p>
            </div>
            <div class="kiosk-review-grid">
                <div class="kiosk-customer-card">
                    <div class="kiosk-section-title"><span><i class="bx bx-user"></i></span>
                        <div>
                            <h2>¿A nombre de quién?</h2>
                            <p>Solo pedimos lo necesario para entregar tu orden.</p>
                        </div>
                    </div>
                    <label class="kiosk-field"><span>Nombre para llamar</span><input type="text"
                            wire:model="customerName" maxlength="120" autocomplete="name"
                            placeholder="Escribe tu nombre"></label>
                    @error('customerName')
                        <p class="kiosk-error"><i class="bx bx-error-circle"></i>{{ $message }}</p>
                    @enderror
                    <label class="kiosk-field"><span>Teléfono
                            <small>{{ $fulfillment === 'delivery' || $this->terminal->require_customer_phone ? 'Obligatorio' : 'Opcional' }}</small></span><input
                            type="tel" wire:model="customerPhone" maxlength="30" autocomplete="tel"
                            inputmode="tel" placeholder="Ej. 55 1234 5678"></label>
                    @error('customerPhone')
                        <p class="kiosk-error"><i class="bx bx-error-circle"></i>{{ $message }}</p>
                    @enderror
                    @if ($fulfillment === 'dine_in')
                        <section class="kiosk-table-picker" aria-labelledby="kiosk-table-title">
                            <div class="kiosk-delivery-heading">
                                <span><i class="bx bx-table"></i></span>
                                <div>
                                    <h3 id="kiosk-table-title">¿En qué mesa estás?</h3>
                                    <p>Busca el número visible sobre tu mesa y tócala para seleccionarla.</p>
                                </div>
                            </div>
                            @forelse ($this->kioskTableGroups as $areaName => $areaTables)
                                <section class="kiosk-table-area" aria-labelledby="kiosk-area-{{ \Illuminate\Support\Str::slug($areaName) }}">
                                    <div class="kiosk-table-area__title">
                                        <span><i class="bx bx-map-pin"></i></span>
                                        <div><h4 id="kiosk-area-{{ \Illuminate\Support\Str::slug($areaName) }}">{{ $areaName }}</h4><small>{{ $areaTables->count() }} disponibles</small></div>
                                    </div>
                                    <div class="kiosk-table-grid" role="radiogroup" aria-label="Mesas disponibles en {{ $areaName }}">
                                @foreach ($areaTables as $mesa)
                                    <button type="button"
                                        class="kiosk-table-choice {{ $selectedMesaId === $mesa->id ? 'is-selected' : '' }}"
                                        wire:click="$set('selectedMesaId', {{ $mesa->id }})"
                                        role="radio" aria-checked="{{ $selectedMesaId === $mesa->id ? 'true' : 'false' }}">
                                        <span>Mesa</span>
                                        <strong>{{ $mesa->number }}</strong>
                                        <small>{{ $mesa->area?->name ?: 'Área general' }}</small>
                                        @if ($mesa->active_orders_count > 0)
                                            <b>{{ $mesa->active_orders_count }} {{ $mesa->active_orders_count === 1 ? 'orden' : 'órdenes' }}</b>
                                        @endif
                                    </button>
                                @endforeach
                                    </div>
                                </section>
                            @empty
                                <p class="kiosk-error kiosk-error-block"><i class="bx bx-error-circle"></i>No hay mesas disponibles en este momento.</p>
                            @endforelse
                            @error('selectedMesaId')
                                <p class="kiosk-error"><i class="bx bx-error-circle"></i>{{ $message }}</p>
                            @enderror
                        </section>
                    @endif
                    @if ($fulfillment === 'delivery')
                        <section class="kiosk-delivery-fields" aria-labelledby="kiosk-delivery-title">
                            <div class="kiosk-delivery-heading">
                                <span><i class="bx bx-map"></i></span>
                                <div>
                                    <h3 id="kiosk-delivery-title">¿A dónde llevamos tu pedido?</h3>
                                    <p>Escribe la dirección como aparece en tu domicilio.</p>
                                </div>
                            </div>
                            <div class="kiosk-delivery-grid">
                                <div>
                                    <label class="kiosk-field"><span>Calle y número
                                            <small>Obligatorio</small></span><input type="text"
                                            wire:model="deliveryStreet" maxlength="180" autocomplete="street-address"
                                            placeholder="Ej. Av. Reforma 120, interior 3"></label>
                                    @error('deliveryStreet')
                                        <p class="kiosk-error"><i class="bx bx-error-circle"></i>{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label class="kiosk-field"><span>Colonia o zona
                                            <small>Obligatorio</small></span><input type="text"
                                            wire:model="deliveryNeighborhood" maxlength="120"
                                            autocomplete="address-level3" placeholder="Ej. Centro"></label>
                                    @error('deliveryNeighborhood')
                                        <p class="kiosk-error"><i class="bx bx-error-circle"></i>{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                            <label class="kiosk-field"><span>Referencias para llegar <small>Opcional</small></span>
                                <textarea wire:model="deliveryReferences" maxlength="240" placeholder="Ej. Portón negro, frente al parque"></textarea>
                            </label>
                            @error('deliveryReferences')
                                <p class="kiosk-error"><i class="bx bx-error-circle"></i>{{ $message }}</p>
                            @enderror
                            <p class="kiosk-delivery-help"><i class="bx bx-phone-call"></i> Conserva tu teléfono
                                disponible por si necesitamos confirmar la ubicación.</p>
                        </section>
                    @endif
                    <label class="kiosk-field"><span>Nota general <small>Opcional</small></span>
                        <textarea wire:model="orderNotes" maxlength="500" placeholder="Algo que debamos saber sobre tu pedido"></textarea>
                    </label>
                    <div class="kiosk-payment-note"><i class="bx bx-wallet"></i>
                        <div>
                            <strong>{{ $fulfillment === 'delivery' ? 'Pago contra entrega' : 'Forma de pago' }}</strong><small>{{ $fulfillment === 'delivery' ? 'Paga cuando recibas tu pedido en el domicilio.' : $this->terminal->payment_instructions }}</small>
                        </div>
                    </div>
                </div>
                <aside class="kiosk-order-summary">
                    <div class="kiosk-section-title"><span><i class="bx bx-receipt"></i></span>
                        <div>
                            <h2>Resumen</h2>
                            <p>{{ match ($fulfillment) {'dine_in' => 'Comer aquí · Mesa '.($this->kioskTables->firstWhere('id', $selectedMesaId)?->number ?? 'por elegir'),'delivery' => 'Para domicilio',default => 'Para llevar'} }}
                            </p>
                        </div>
                    </div>
                    <div class="kiosk-summary-lines">
                        @foreach ($cart as $line)
                            <div class="kiosk-summary-line"><span><b>{{ $line['quantity'] }}×</b>
                                    {{ $line['product_name'] }}</span><strong>${{ number_format($line['subtotal'], 2) }}</strong>
                            </div>
                        @endforeach
                    </div>
                    <div class="kiosk-cart-total">
                        <span>Total</span><strong>${{ number_format($this->cartTotal, 2) }}</strong>
                    </div>
                    @error('order')
                        <p class="kiosk-error kiosk-error-block"><i class="bx bx-error-circle"></i>{{ $message }}
                        </p>
                    @enderror
                    @error('cart')
                        <p class="kiosk-error kiosk-error-block"><i class="bx bx-error-circle"></i>{{ $message }}
                        </p>
                    @enderror
                    <button class="kiosk-primary-button kiosk-confirm-button" type="button" wire:click="placeOrder"
                        wire:loading.attr="disabled" wire:target="placeOrder">
                        <span wire:loading.remove wire:target="placeOrder">Confirmar pedido <i
                                class="bx bx-check"></i></span>
                        <span wire:loading wire:target="placeOrder">Creando tu pedido…</span>
                    </button>
                    <small class="kiosk-secure-note"><i class="bx bx-lock-alt"></i> Precios y opciones verificados de
                        forma segura</small>
                </aside>
            </div>
        </section>
    @elseif($step === 6)
        <section class="kiosk-success" x-data x-init="setTimeout(() => $wire.startAgain(), {{ $this->terminal->auto_reset_seconds * 1000 }})">
            <div class="kiosk-success-icon"><i class="bx bx-check"></i></div>
            <span class="kiosk-eyebrow">Pedido recibido</span>
            <h1>¡Listo, {{ $customerName }}!</h1>
            <p>Tu número de pedido es</p>
            <strong class="kiosk-order-number">#{{ $completedOrderId }}</strong>
            <p>{{ $this->terminal->success_message }}</p>
            <div class="kiosk-qr-card">
                @if ($this->qrDataUri)
                    <img src="{{ $this->qrDataUri }}" alt="Código QR para seguir el pedido">
                @endif
                <div>
                    <h2>Escanea para seguir tu pedido</h2>
                    <p>Guarda este QR y consulta el estado desde tu teléfono.</p>
                </div>
            </div>
            <div class="kiosk-success-actions">
                <button class="kiosk-primary-button" type="button" wire:click="startAgain"><i
                        class="bx bx-user-plus"></i> Preparar para el siguiente cliente</button>
            </div>
            <p class="kiosk-timeout-hint"><i class="bx bx-info-circle"></i>
                {{ $fulfillment === 'delivery' ? 'Mantén tu teléfono disponible para la entrega.' : $this->terminal->payment_instructions }}
                Esta pantalla volverá al inicio en {{ $this->terminal->auto_reset_seconds }} segundos.</p>
        </section>
    @endif
</main>
