@if($showCustomizeModal)
<div class="pos-modal-wrap show pos-modal-shell" wire:click.self="$set('showCustomizeModal',false)" role="dialog" aria-modal="true" aria-labelledby="customize-product-title">
    <div class="pos-modal wide pos-modal-modern" @click.stop>
        @if($this->customizingProduct)
            <header class="modal-header-pos pos-modal-modern__header">
                <div class="pos-modal-heading">
                    <span class="pos-modal-heading__icon" aria-hidden="true"><i class="bx bx-customize"></i></span>
                    <div>
                        <span class="pos-modal-eyebrow">Personalizar producto</span>
                        <h2 id="customize-product-title">{{ $this->customizingProduct->name }}</h2>
                        <p>Precio base <strong>${{ number_format($this->customizingProduct->price, 2) }}</strong></p>
                    </div>
                </div>
                <button type="button" wire:click="$set('showCustomizeModal',false)" class="pos-modal-close" aria-label="Cerrar personalización">
                    <i class="bx bx-x" aria-hidden="true"></i>
                </button>
            </header>

            <div class="modal-body-pos pos-modal-modern__body">
                <section class="pos-quantity-card" aria-label="Cantidad del producto">
                    <div>
                        <strong>Cantidad</strong>
                        <span>Unidades de este producto</span>
                    </div>
                    <div class="addon-qty-controls">
                        <button type="button" wire:click="$set('itemQuantity', max(1, itemQuantity - 1))" class="addon-qty-btn" aria-label="Disminuir cantidad" {{ $itemQuantity <= 1 ? 'disabled' : '' }}><i class="bx bx-minus"></i></button>
                        <span class="addon-qty-val" aria-live="polite">{{ $itemQuantity }}</span>
                        <button type="button" wire:click="$set('itemQuantity', itemQuantity + 1)" class="addon-qty-btn" aria-label="Aumentar cantidad"><i class="bx bx-plus"></i></button>
                    </div>
                </section>

                @foreach($this->customizingProduct->addonGroups as $group)
                    @php
                        $available = $group->addons->count();
                        $minimum = $group->is_required ? max(1, (int) $group->min_selections) : (int) $group->min_selections;
                        $maximum = max($minimum, min((int) $group->max_selections, $available));
                        $selectedCount = $group->addons->filter(fn($addon) => isset($selectedAddons[$addon->id]))->count();
                        $singleChoice = $maximum === 1;
                        $groupComplete = $available >= $minimum && $selectedCount >= $minimum && $selectedCount <= $maximum;
                    @endphp
                    <section class="pos-option-group {{ $groupComplete ? 'is-complete' : 'needs-attention' }}" aria-labelledby="addon-group-{{ $group->id }}">
                        <div class="pos-option-group__header">
                            <div>
                                <h3 id="addon-group-{{ $group->id }}">{{ $group->name }}</h3>
                                @if($group->description)<p>{{ $group->description }}</p>@endif
                            </div>
                            <div class="pos-option-group__meta">
                                @if($minimum > 0)
                                    <span class="pos-requirement pos-requirement--required">Obligatorio</span>
                                @else
                                    <span class="pos-requirement">Opcional</span>
                                @endif
                                <span class="pos-selection-count {{ $groupComplete ? 'is-complete' : '' }}">
                                    <i class="bx {{ $groupComplete ? 'bx-check' : 'bx-info-circle' }}" aria-hidden="true"></i>
                                    {{ $selectedCount }}/{{ $maximum }}
                                </span>
                            </div>
                        </div>

                        @if($available === 0)
                            <div class="pos-inline-alert pos-inline-alert--danger"><i class="bx bx-error-circle"></i>No hay opciones disponibles para completar este grupo.</div>
                        @else
                            <p class="pos-option-instruction">
                                @if($singleChoice)
                                    Selecciona una opción.
                                @elseif($minimum > 0)
                                    Selecciona entre {{ $minimum }} y {{ $maximum }} opciones.
                                @else
                                    Puedes seleccionar hasta {{ $maximum }} opciones.
                                @endif
                                @if($available === 1 && $minimum > 0)
                                    La única opción disponible se seleccionó automáticamente.
                                @endif
                            </p>
                            <div class="pos-option-list" role="{{ $singleChoice ? 'radiogroup' : 'group' }}">
                                @foreach($group->addons as $addon)
                                    @php $checked = isset($selectedAddons[$addon->id]); @endphp
                                    <button type="button" wire:click="toggleAddon({{ $addon->id }})"
                                        class="addon-option {{ $checked ? 'selected' : '' }}"
                                        role="{{ $singleChoice ? 'radio' : 'checkbox' }}" aria-checked="{{ $checked ? 'true' : 'false' }}">
                                        <span class="addon-opt-left">
                                            <span class="addon-check {{ $singleChoice ? 'radio' : '' }} {{ $checked ? 'selected' : '' }}">
                                                @if($checked)<i class="bx bx-check" aria-hidden="true"></i>@endif
                                            </span>
                                            <span class="addon-opt-name">{{ $addon->name }}</span>
                                        </span>
                                        @if($addon->extra_price > 0)
                                            <span class="addon-opt-price">+${{ number_format($addon->extra_price, 2) }}</span>
                                        @else
                                            <span class="addon-opt-free">Sin costo</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        @endif
                        @error('addons_'.$group->id)
                            <div class="pos-inline-alert pos-inline-alert--danger"><i class="bx bx-error-circle"></i>{{ $message }}</div>
                        @enderror
                    </section>
                @endforeach

                @if($this->customizingProduct->ingredients->count() > 0)
                    @php
                        $ingredientMin = (int) $this->customizingProduct->min_ingredients;
                        $ingredientMax = (int) ($this->customizingProduct->max_ingredients ?: $this->customizingProduct->ingredients->count());
                        $ingredientsComplete = $this->totalSelectedIngredients >= $ingredientMin && $this->totalSelectedIngredients <= $ingredientMax;
                    @endphp
                    <section class="pos-option-group {{ $ingredientsComplete ? 'is-complete' : 'needs-attention' }}" aria-labelledby="ingredients-title">
                        <div class="pos-option-group__header">
                            <div><h3 id="ingredients-title">Ingredientes</h3><p>Ajusta las cantidades según la preparación.</p></div>
                            <div class="pos-option-group__meta">
                                <span class="pos-requirement {{ $ingredientMin > 0 ? 'pos-requirement--required' : '' }}">{{ $ingredientMin > 0 ? 'Obligatorio' : 'Opcional' }}</span>
                                <span class="pos-selection-count {{ $ingredientsComplete ? 'is-complete' : '' }}">{{ $this->totalSelectedIngredients }}/{{ $ingredientMax }}</span>
                            </div>
                        </div>
                        <div class="ing-grid">
                            @foreach($this->customizingProduct->ingredients as $ing)
                                @php $qty = $selectedIngredients[$ing->id] ?? 0; @endphp
                                <article class="ing-card {{ $qty > 0 ? 'selected' : '' }}">
                                    <button type="button" class="ing-card-select" wire:click="incrementIngredient({{ $ing->id }})" aria-label="Agregar {{ $ing->name }}">
                                        <span class="ing-card-img-wrap">
                                            @if($ing->image)
                                                <img src="{{ asset('storage/'.$ing->image) }}" alt="{{ $ing->name }}">
                                            @else
                                                <span class="ing-card-img-placeholder"><i class="bx bx-bowl-hot"></i></span>
                                            @endif
                                        </span>
                                        <span class="ing-card-name">{{ $ing->name }}</span>
                                        @if($ing->extra_price > 0)<span class="ing-card-price">+${{ number_format($ing->extra_price, 2) }}/u</span>@endif
                                    </button>
                                    <div class="ing-card-controls">
                                        <button type="button" wire:click="decrementIngredient({{ $ing->id }})" class="ing-card-btn" aria-label="Quitar {{ $ing->name }}" {{ $qty === 0 ? 'disabled' : '' }}><i class="bx bx-minus"></i></button>
                                        <span class="ing-card-qty">{{ $qty }}</span>
                                        <button type="button" wire:click="incrementIngredient({{ $ing->id }})" class="ing-card-btn" aria-label="Agregar {{ $ing->name }}" {{ $this->totalSelectedIngredients >= $ingredientMax ? 'disabled' : '' }}><i class="bx bx-plus"></i></button>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                        @error('ingredients')<div class="pos-inline-alert pos-inline-alert--danger"><i class="bx bx-error-circle"></i>{{ $message }}</div>@enderror
                    </section>
                @endif

                <div class="pos-note-field">
                    <label for="pos-item-notes"><i class="bx bx-note" aria-hidden="true"></i>Nota para cocina</label>
                    <input id="pos-item-notes" type="text" wire:model="itemNotes" class="co-input" placeholder="Ej. sin sal, servir aparte…">
                </div>
            </div>

            @php
                $addonTotal = 0;
                foreach($this->customizingProduct->addonGroups as $g)
                    foreach($g->addons as $a)
                        if(isset($selectedAddons[$a->id])) $addonTotal += $a->extra_price;
                foreach($this->customizingProduct->ingredients as $ing)
                    $addonTotal += ($selectedIngredients[$ing->id] ?? 0) * $ing->extra_price;
                $unitP = ($this->customizingProduct->price ?? 0) + $addonTotal;
            @endphp
            <footer class="modal-footer-pos pos-modal-modern__footer">
                <div class="pos-modal-total"><span>Total</span><strong>${{ number_format($unitP * $itemQuantity, 2) }}</strong></div>
                <div class="pos-modal-actions">
                    <button type="button" wire:click="$set('showCustomizeModal',false)" class="pos-btn pos-btn-ghost">Cancelar</button>
                    <button type="button" wire:click="addToCart" wire:loading.attr="disabled" wire:target="addToCart"
                        class="pos-btn pos-btn-primary pos-btn-lg" {{ $this->customizationIsValid ? '' : 'disabled' }}>
                        <span wire:loading wire:target="addToCart" class="pos-btn-spinner"></span>
                        <i wire:loading.remove wire:target="addToCart" class="bx bx-cart-add"></i>
                        {{ $editingCartId ? 'Actualizar producto' : 'Agregar al carrito' }}
                    </button>
                </div>
            </footer>
        @endif
    </div>
</div>
@endif
