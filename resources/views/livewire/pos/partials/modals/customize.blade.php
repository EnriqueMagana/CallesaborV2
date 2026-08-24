@if($showCustomizeModal && $this->customizingProduct)
    @php
        $product = $this->customizingProduct;
        $ingredientMinimum = (int) ($product->min_ingredients ?? 0);
        $ingredientMaximum = (int) ($product->max_ingredients ?? 0);
        $productAddonMaximum = (int) ($product->max_addons ?? 0);
        $groupRules = $product->addonGroups->map(function ($group) {
            $minimum = $group->is_required
                ? max(1, (int) $group->min_selections)
                : (int) $group->min_selections;
            $configuredMaximum = (int) $group->max_selections;
            $maximum = $configuredMaximum > 0
                ? max($minimum, min($configuredMaximum, $group->addons->count()))
                : $group->addons->count();

            return [
                'id' => (int) $group->id,
                'ids' => $group->addons->pluck('id')->map(fn ($id) => (int) $id)->values(),
                'min' => $minimum,
                'max' => $maximum,
            ];
        })->values();
        $addonPrices = $product->addonGroups->flatMap->addons
            ->mapWithKeys(fn ($addon) => [(int) $addon->id => (float) $addon->extra_price]);
        $ingredientPrices = $product->ingredients
            ->mapWithKeys(fn ($ingredient) => [(int) $ingredient->id => (float) $ingredient->extra_price]);
    @endphp
    <div class="pos-modal-wrap show pos-modal-shell"
         wire:click.self="closeCustomizeModal"
         x-data="{
            addons: @js(collect($selectedAddons)->filter()->keys()->map(fn ($id) => (int) $id)->values()),
            ingredients: @js((object) $selectedIngredients),
            qty: @js($itemQuantity),
            notes: @js($itemNotes),
            basePrice: {{ (float) $product->price }},
            addonPrices: @js((object) $addonPrices),
            ingredientPrices: @js((object) $ingredientPrices),
            ingredientNames: @js($product->ingredients->pluck('name')->values()),
            groups: @js($groupRules),
            maxAddons: {{ $productAddonMaximum }},
            ingredientMin: {{ $ingredientMinimum }},
            ingredientMax: {{ $ingredientMaximum }},
            ingredientQuery: '',
            clientError: '',
            submitting: false,
            normalize(value) {
                return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
            },
            ingredientMatches(name) {
                return this.normalize(name).includes(this.normalize(this.ingredientQuery.trim()));
            },
            ingredientMatchCount() {
                return this.ingredientNames.filter(name => this.ingredientMatches(name)).length;
            },
            hasAddon(id) { return this.addons.includes(Number(id)); },
            groupCount(ids) { return ids.filter(id => this.hasAddon(id)).length; },
            totalIngredients() {
                return Object.values(this.ingredients).reduce((sum, value) => sum + Number(value || 0), 0);
            },
            total() {
                const addonExtra = this.addons.reduce((sum, id) => sum + Number(this.addonPrices[id] || 0), 0);
                const ingredientExtra = Object.entries(this.ingredients)
                    .reduce((sum, [id, amount]) => sum + Number(this.ingredientPrices[id] || 0) * Number(amount || 0), 0);
                return (this.basePrice + addonExtra + ingredientExtra) * this.qty;
            },
            isValid() {
                const groupsValid = this.groups.every(group => {
                    const count = this.groupCount(group.ids);
                    return count >= group.min && count <= group.max;
                });
                const ingredients = this.totalIngredients();
                return groupsValid
                    && (this.maxAddons === 0 || this.addons.length <= this.maxAddons)
                    && ingredients >= this.ingredientMin
                    && (this.ingredientMax === 0 || ingredients <= this.ingredientMax)
                    && this.qty >= 1 && this.qty <= 99
                    && this.notes.length <= 500;
            },
            toggleAddon(id, groupIds, minimum, maximum) {
                id = Number(id);
                this.clientError = '';
                if (this.hasAddon(id)) {
                    if (this.groupCount(groupIds) <= minimum) {
                        this.clientError = `Debes conservar al menos ${minimum} opción(es) en este grupo.`;
                        return;
                    }
                    this.addons = this.addons.filter(value => value !== id);
                    return;
                }
                if (maximum === 1) {
                    const replacing = this.groupCount(groupIds) > 0;
                    if (!replacing && this.maxAddons > 0 && this.addons.length >= this.maxAddons) {
                        this.clientError = `Este producto permite máximo ${this.maxAddons} complemento(s).`;
                        return;
                    }
                    this.addons = this.addons.filter(value => !groupIds.includes(value));
                    this.addons.push(id);
                    return;
                }
                if (this.groupCount(groupIds) >= maximum) {
                    this.clientError = `Este grupo permite máximo ${maximum} opción(es).`;
                    return;
                }
                if (this.maxAddons > 0 && this.addons.length >= this.maxAddons) {
                    this.clientError = `Este producto permite máximo ${this.maxAddons} complemento(s).`;
                    return;
                }
                this.addons.push(id);
            },
            changeIngredient(id, delta) {
                id = Number(id);
                this.clientError = '';
                const current = Number(this.ingredients[id] || 0);
                if (delta > 0 && this.ingredientMax > 0 && this.totalIngredients() >= this.ingredientMax) {
                    this.clientError = `Este producto permite máximo ${this.ingredientMax} ingrediente(s).`;
                    return;
                }
                const next = Math.max(0, current + delta);
                if (next === 0) delete this.ingredients[id];
                else this.ingredients[id] = next;
            },
            submit(wire) {
                if (!this.isValid() || this.submitting) return;
                this.submitting = true;
                wire.confirmCustomize(this.addons, this.ingredients, this.qty, this.notes)
                    .finally(() => this.submitting = false);
            }
         }"
         x-on:keydown.escape.window="$wire.closeCustomizeModal()"
         role="dialog" aria-modal="true" aria-labelledby="customize-product-title">
        <div class="pos-modal wide pos-modal-modern pos-customize-modal" @click.stop>
            <header class="modal-header-pos pos-modal-modern__header">
                <div class="pos-modal-heading">
                    @if($product->image)
                        <img class="pos-customize-product-image" src="{{ Storage::url($product->image) }}"
                             alt="" width="64" height="64" decoding="async">
                    @else
                        <span class="pos-customize-product-image is-empty" aria-hidden="true"><i class="bx bx-food-menu"></i></span>
                    @endif
                    <div>
                        <span class="pos-modal-eyebrow">Personalizar producto</span>
                        <h2 id="customize-product-title">{{ $product->name }}</h2>
                        <p>${{ number_format($product->price, 2) }} base
                            @if($productAddonMaximum > 0) · Máximo {{ $productAddonMaximum }} complementos @endif
                        </p>
                    </div>
                </div>
                <button type="button" wire:click="closeCustomizeModal" class="pos-modal-close"
                        aria-label="Cerrar personalización">
                    <i class="bx bx-x" aria-hidden="true"></i>
                </button>
            </header>

            <div class="modal-body-pos pos-modal-modern__body">
                <div class="pos-inline-alert pos-inline-alert--warning" x-show="clientError" x-cloak role="alert">
                    <i class="bx bx-info-circle" aria-hidden="true"></i>
                    <span x-text="clientError"></span>
                </div>

                @foreach($product->addonGroups as $group)
                    @php
                        $groupIds = $group->addons->pluck('id')->map(fn ($id) => (int) $id)->values();
                        $minimum = $group->is_required ? max(1, (int) $group->min_selections) : (int) $group->min_selections;
                        $configuredMaximum = (int) $group->max_selections;
                        $maximum = $configuredMaximum > 0
                            ? max($minimum, min($configuredMaximum, $group->addons->count()))
                            : $group->addons->count();
                    @endphp
                    <section class="pos-option-group" aria-labelledby="addon-group-{{ $group->id }}">
                        <div class="pos-option-group__header">
                            <div>
                                <h3 id="addon-group-{{ $group->id }}">{{ $group->name }}</h3>
                                @if($group->description)<p>{{ $group->description }}</p>@endif
                            </div>
                            <div class="pos-option-group__meta">
                                <span class="pos-requirement {{ $minimum > 0 ? 'pos-requirement--required' : '' }}">
                                    {{ $minimum > 0 ? 'Obligatorio' : 'Opcional' }}
                                </span>
                                <span class="pos-selection-count"
                                      :class="{ 'is-complete': groupCount(@js($groupIds)) >= {{ $minimum }} && groupCount(@js($groupIds)) <= {{ $maximum }} }">
                                    <span x-text="groupCount(@js($groupIds))"></span>/{{ $maximum }}
                                </span>
                            </div>
                        </div>
                        <p class="pos-option-instruction">
                            {{ $maximum === 1 ? 'Elige una opción.' : "Elige entre {$minimum} y {$maximum} opciones." }}
                        </p>
                        <div class="pos-option-list" role="{{ $maximum === 1 ? 'radiogroup' : 'group' }}">
                            @foreach($group->addons as $addon)
                                <button type="button"
                                    x-on:click="toggleAddon({{ $addon->id }}, @js($groupIds), {{ $minimum }}, {{ $maximum }})"
                                    :class="{ 'selected': hasAddon({{ $addon->id }}) }"
                                    :aria-checked="hasAddon({{ $addon->id }})"
                                    class="addon-option pos-addon-option-with-image"
                                    role="{{ $maximum === 1 ? 'radio' : 'checkbox' }}">
                                    @if($addon->image)
                                        <img class="pos-option-image" src="{{ Storage::url($addon->image) }}"
                                             alt="" width="48" height="48" loading="lazy" decoding="async">
                                    @else
                                        <span class="pos-option-image is-empty" aria-hidden="true"><i class="bx bx-plus-circle"></i></span>
                                    @endif
                                    <span class="addon-check {{ $maximum === 1 ? 'radio' : '' }}"
                                          :class="{ 'selected': hasAddon({{ $addon->id }}) }">
                                        <i class="bx bx-check" x-show="hasAddon({{ $addon->id }})"></i>
                                    </span>
                                    <span class="addon-opt-left">
                                        <span class="addon-opt-name">{{ $addon->name }}</span>
                                        @if($addon->description)<small>{{ $addon->description }}</small>@endif
                                    </span>
                                    <span class="{{ $addon->extra_price > 0 ? 'addon-opt-price' : 'addon-opt-free' }}">
                                        {{ $addon->extra_price > 0 ? '+$'.number_format($addon->extra_price, 2) : 'Sin costo' }}
                                    </span>
                                </button>
                            @endforeach
                        </div>
                        @error('addons_'.$group->id)
                            <div class="pos-inline-alert pos-inline-alert--danger"><i class="bx bx-error-circle"></i>{{ $message }}</div>
                        @enderror
                    </section>
                @endforeach
                @error('addons_general')
                    <div class="pos-inline-alert pos-inline-alert--danger"><i class="bx bx-error-circle"></i>{{ $message }}</div>
                @enderror

                @if($product->ingredients->isNotEmpty())
                    <section class="pos-option-group pos-ingredient-group" aria-labelledby="ingredients-title">
                        <div class="pos-option-group__header">
                            <div>
                                <h3 id="ingredients-title">Ingredientes extra</h3>
                                <p>
                                    {{ $ingredientMinimum > 0 ? 'Agrega al menos '.$ingredientMinimum.'.' : 'Agrega solo los que quieras.' }}
                                    {{ $ingredientMaximum > 0 ? 'Puedes elegir hasta '.$ingredientMaximum.' en total.' : 'No hay límite de cantidad.' }}
                                </p>
                            </div>
                            <div class="pos-option-group__meta">
                                <span class="pos-requirement {{ $ingredientMinimum > 0 ? 'pos-requirement--required' : '' }}">
                                    {{ $ingredientMinimum > 0 ? 'Obligatorio' : 'Opcional' }}
                                </span>
                            </div>
                        </div>
                        <div class="pos-ingredient-counter"
                             :class="{ 'has-selection': totalIngredients() > 0 }"
                             aria-live="polite">
                            <div>
                                <strong x-text="ingredientMax > 0 ? `${totalIngredients()} de ${ingredientMax}` : totalIngredients()"></strong>
                                <span x-text="totalIngredients() === 1 ? 'ingrediente agregado' : 'ingredientes agregados'"></span>
                            </div>
                            @if($ingredientMaximum > 0 && $ingredientMaximum <= 12)
                                <div class="pos-ingredient-progress" aria-hidden="true">
                                    @for($ingredientStep = 1; $ingredientStep <= $ingredientMaximum; $ingredientStep++)
                                        <span :class="{ 'is-filled': totalIngredients() >= {{ $ingredientStep }} }"></span>
                                    @endfor
                                </div>
                            @endif
                        </div>
                        <div class="pos-ingredient-tools">
                            <label class="pos-ingredient-search">
                                <i class="bx bx-search" aria-hidden="true"></i>
                                <span class="visually-hidden">Buscar ingrediente</span>
                                <input type="search" x-model.debounce.120ms="ingredientQuery"
                                    placeholder="Buscar ingrediente…" autocomplete="off">
                                <button type="button" x-show="ingredientQuery" x-cloak
                                    @click="ingredientQuery = ''" aria-label="Limpiar búsqueda de ingredientes">
                                    <i class="bx bx-x"></i>
                                </button>
                            </label>
                            <small><i class="bx bx-bolt-circle"></i>Los cambios se guardan juntos al agregar el producto.</small>
                        </div>
                        <div class="pos-ingredient-list">
                            @foreach($product->ingredients as $ingredient)
                                <article class="pos-ingredient-card"
                                    x-show="ingredientMatches(@js($ingredient->name))"
                                    :class="{ 'is-selected': Number(ingredients[{{ $ingredient->id }}] || 0) > 0 }">
                                    <div class="pos-ingredient-card__copy">
                                        <span class="pos-ingredient-card__media">
                                            @if($ingredient->image)
                                                <img src="{{ Storage::url($ingredient->image) }}"
                                                     alt="Imagen de {{ $ingredient->name }}" width="64" height="64"
                                                     loading="lazy" decoding="async">
                                            @else
                                                <i class="bx bx-list-ul" aria-hidden="true"></i>
                                            @endif
                                        </span>
                                        <span class="pos-ingredient-card__details">
                                            <strong>{{ $ingredient->name }}</strong>
                                            @if($ingredient->description)<small>{{ $ingredient->description }}</small>@endif
                                            <small>{{ $ingredient->extra_price > 0 ? '+$'.number_format($ingredient->extra_price, 2).' cada uno' : 'Sin costo extra' }}</small>
                                            <b x-text="Number(ingredients[{{ $ingredient->id }}] || 0) > 0
                                                ? `Seleccionado · ${Number(ingredients[{{ $ingredient->id }}] || 0)}`
                                                : 'No seleccionado'"></b>
                                        </span>
                                    </div>
                                    <div class="pos-ingredient-card__quantity" aria-label="Cantidad de {{ $ingredient->name }}">
                                        <button type="button" @click="changeIngredient({{ $ingredient->id }}, -1)"
                                                :disabled="Number(ingredients[{{ $ingredient->id }}] || 0) === 0"
                                                aria-label="Quitar una unidad de {{ $ingredient->name }}">
                                            <i class="bx bx-minus"></i><span>Quitar</span>
                                        </button>
                                        <div>
                                            <small>Cantidad</small>
                                            <strong x-text="Number(ingredients[{{ $ingredient->id }}] || 0)"></strong>
                                        </div>
                                        <button type="button" @click="changeIngredient({{ $ingredient->id }}, 1)"
                                                :disabled="ingredientMax > 0 && totalIngredients() >= ingredientMax"
                                                aria-label="Agregar una unidad de {{ $ingredient->name }}">
                                            <i class="bx bx-plus"></i><span>Agregar</span>
                                        </button>
                                    </div>
                                </article>
                            @endforeach
                            <div class="pos-ingredient-empty" x-show="ingredientQuery && ingredientMatchCount() === 0" x-cloak>
                                <i class="bx bx-search-alt"></i>
                                <strong>Sin coincidencias</strong>
                                <span>Prueba con otro nombre de ingrediente.</span>
                            </div>
                        </div>
                        @error('ingredients')
                            <div class="pos-inline-alert pos-inline-alert--danger"><i class="bx bx-error-circle"></i>{{ $message }}</div>
                        @enderror
                    </section>
                @endif

                <div class="pos-customize-bottom-grid">
                    <section class="pos-quantity-card" aria-label="Cantidad del producto">
                        <div><strong>Cantidad</strong><span>Máximo 99 unidades</span></div>
                        <div class="addon-qty-controls">
                            <button type="button" @click="qty = Math.max(1, qty - 1)" :disabled="qty <= 1"
                                    class="addon-qty-btn" aria-label="Disminuir cantidad"><i class="bx bx-minus"></i></button>
                            <span class="addon-qty-val" x-text="qty" aria-live="polite"></span>
                            <button type="button" @click="qty = Math.min(99, qty + 1)" :disabled="qty >= 99"
                                    class="addon-qty-btn" aria-label="Aumentar cantidad"><i class="bx bx-plus"></i></button>
                        </div>
                    </section>
                    <div class="pos-note-field">
                        <label for="pos-item-notes"><i class="bx bx-note" aria-hidden="true"></i>Nota para cocina</label>
                        <textarea id="pos-item-notes" x-model="notes" maxlength="500" rows="2" class="co-input"
                                  placeholder="Ej. sin sal, servir aparte..."></textarea>
                        <small><span x-text="notes.length"></span>/500</small>
                    </div>
                </div>
                @error('itemQuantity')<div class="pos-inline-alert pos-inline-alert--danger">{{ $message }}</div>@enderror
                @error('itemNotes')<div class="pos-inline-alert pos-inline-alert--danger">{{ $message }}</div>@enderror
            </div>

            <footer class="modal-footer-pos pos-modal-modern__footer">
                <div class="pos-modal-total">
                    <span>Total del producto</span>
                    <strong x-text="'$' + total().toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></strong>
                </div>
                <div class="pos-modal-actions">
                    <button type="button" wire:click="closeCustomizeModal" class="pos-btn pos-btn-ghost">Cancelar</button>
                    <button type="button" @click="submit($wire)" :disabled="!isValid() || submitting"
                            class="pos-btn pos-btn-primary pos-btn-lg">
                        <span class="pos-btn-spinner" x-show="submitting" x-cloak></span>
                        <i class="bx bx-cart" x-show="!submitting"></i>
                        <span x-text="submitting ? 'Guardando...' : @js($editingCartId ? 'Actualizar producto' : 'Agregar al carrito')"></span>
                    </button>
                </div>
            </footer>
        </div>
    </div>
@endif
