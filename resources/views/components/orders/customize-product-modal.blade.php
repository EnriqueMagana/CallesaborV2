{{--
    Modal para personalizar un producto antes de agregarlo a una solicitud de
    cambio: extras por grupo (con mínimos y máximos), ingredientes con cantidad,
    cantidad y nota para cocina. El precio en vivo lo calcula el servidor.
--}}
@props(['product', 'addons' => [], 'ingredients' => [], 'quantity' => 1, 'preview' => [], 'editing' => false])

@php
    $customization = app(\App\Services\ProductCustomizationService::class);
    $totalIngredients = array_sum($ingredients);
@endphp

<div class="app-modal-backdrop order-customize-backdrop" wire:click="closeCustomization" aria-hidden="true"></div>
<div class="app-modal-layer order-customize-layer" role="dialog" aria-modal="true" aria-labelledby="order-customize-title"
    wire:key="order-customize-{{ $product->id }}" wire:click.self="closeCustomization" wire:keydown.escape.window="closeCustomization">
    <section class="order-customize">
        <header class="order-customize__header">
            <span class="order-customize__icon" aria-hidden="true"><i class="bx bx-customize"></i></span>
            <div>
                <span class="app-eyebrow">{{ $editing ? 'Editar artículo nuevo' : 'Agregar a la orden' }}</span>
                <h2 id="order-customize-title">{{ $product->name }}</h2>
                <p>Precio base ${{ number_format((float) $product->price, 2) }}@if ($product->description) · {{ $product->description }}@endif</p>
            </div>
            <button type="button" class="order-customize__close" wire:click="closeCustomization" aria-label="Cerrar sin agregar"><i class="bx bx-x"></i></button>
        </header>

        <div class="order-customize__body">
            @if ($product->addonGroups->isEmpty() && $product->ingredients->isEmpty())
                <p class="order-customize__notice" role="note">
                    <i class="bx bx-info-circle" aria-hidden="true"></i>
                    <span><strong>Este producto no tiene complementos ni ingredientes configurados en el menú.</strong>
                        Si el cliente eligió opciones (por ejemplo, sabores), escríbelas en la nota para cocina.</span>
                </p>
            @endif

            @error('customAddons')<p class="order-customize__error" role="alert"><i class="bx bx-error-circle"></i> {{ $message }}</p>@enderror

            @foreach ($product->addonGroups as $group)
                @php
                    $minimum = $customization->groupMinimum($group);
                    $maximum = $customization->groupMaximum($group);
                    $chosen = $group->addons->filter(fn ($addon) => ! empty($addons[$addon->id]))->count();
                    $single = $maximum === 1;
                @endphp
                <fieldset class="order-customize__group" wire:key="customize-group-{{ $group->id }}">
                    <legend>
                        <span>{{ $group->name }}</span>
                        <small class="{{ $chosen < $minimum ? 'is-pending' : 'is-done' }}">
                            @if ($minimum > 0)
                                {{ $single ? 'Obligatorio · elige 1' : "Obligatorio · {$minimum} a {$maximum}" }}
                            @else
                                {{ $single ? 'Opcional · máximo 1' : "Opcional · hasta {$maximum}" }}
                            @endif
                            · {{ $chosen }}/{{ $maximum }}
                        </small>
                    </legend>
                    @if ($group->description)<p class="order-customize__hint">{{ $group->description }}</p>@endif
                    <div class="order-customize__options" role="group" aria-label="{{ $group->name }}">
                        @foreach ($group->addons as $addon)
                            @php($isOn = ! empty($addons[$addon->id]))
                            <button type="button" wire:click="toggleCustomAddon({{ $addon->id }})"
                                class="order-customize__option {{ $isOn ? 'is-selected' : '' }}"
                                role="{{ $single ? 'radio' : 'checkbox' }}" aria-checked="{{ $isOn ? 'true' : 'false' }}"
                                @disabled(! $isOn && ! $single && $chosen >= $maximum)>
                                <i class="bx {{ $isOn ? ($single ? 'bxs-circle' : 'bxs-check-square') : ($single ? 'bx-circle' : 'bx-square') }}" aria-hidden="true"></i>
                                <span>{{ $addon->name }}</span>
                                <b>{{ (float) $addon->extra_price > 0 ? '+$'.number_format((float) $addon->extra_price, 2) : 'Incluido' }}</b>
                            </button>
                        @endforeach
                    </div>
                    @error('customGroup'.$group->id)<p class="order-customize__error" role="alert"><i class="bx bx-error-circle"></i> {{ $message }}</p>@enderror
                </fieldset>
            @endforeach

            @if ($product->ingredients->isNotEmpty())
                <fieldset class="order-customize__group">
                    <legend>
                        <span>Ingredientes</span>
                        <small class="{{ (int) $product->min_ingredients > $totalIngredients ? 'is-pending' : 'is-done' }}">
                            @if ((int) $product->min_ingredients > 0)Mínimo {{ $product->min_ingredients }} · @endif
                            @if ((int) $product->max_ingredients > 0)Máximo {{ $product->max_ingredients }} · @endif
                            {{ $totalIngredients }} elegido(s)
                        </small>
                    </legend>
                    <ul class="order-customize__ingredients">
                        @foreach ($product->ingredients as $ingredient)
                            @php($qty = (int) ($ingredients[$ingredient->id] ?? 0))
                            <li class="{{ $qty > 0 ? 'is-selected' : '' }}" wire:key="customize-ingredient-{{ $ingredient->id }}">
                                <span>{{ $ingredient->name }}<small>{{ (float) $ingredient->extra_price > 0 ? '+$'.number_format((float) $ingredient->extra_price, 2).' c/u' : 'Sin costo' }}</small></span>
                                <div class="orders-quantity-control" aria-label="Cantidad de {{ $ingredient->name }}">
                                    <button type="button" wire:click="adjustCustomIngredient({{ $ingredient->id }}, -1)" @disabled($qty === 0) aria-label="Quitar {{ $ingredient->name }}"><i class="bx bx-minus"></i></button>
                                    <b aria-live="polite">{{ $qty }}</b>
                                    <button type="button" wire:click="adjustCustomIngredient({{ $ingredient->id }}, 1)"
                                        @disabled((int) $product->max_ingredients > 0 && $totalIngredients >= (int) $product->max_ingredients)
                                        aria-label="Agregar {{ $ingredient->name }}"><i class="bx bx-plus"></i></button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    @error('customIngredients')<p class="order-customize__error" role="alert"><i class="bx bx-error-circle"></i> {{ $message }}</p>@enderror
                </fieldset>
            @endif

            <div class="order-customize__row">
                <fieldset class="order-customize__group">
                    <legend><span>Cantidad</span></legend>
                    <div class="orders-quantity-control" aria-label="Cantidad del producto">
                        <button type="button" wire:click="adjustCustomQuantity(-1)" @disabled($quantity <= 1) aria-label="Una unidad menos"><i class="bx bx-minus"></i></button>
                        <b aria-live="polite">{{ $quantity }}</b>
                        <button type="button" wire:click="adjustCustomQuantity(1)" @disabled($quantity >= 99) aria-label="Una unidad más"><i class="bx bx-plus"></i></button>
                    </div>
                    @error('customQuantity')<p class="order-customize__error" role="alert">{{ $message }}</p>@enderror
                </fieldset>
                <div class="order-customize__notes">
                    <label for="order-customize-notes">Nota para cocina <small>Opcional</small></label>
                    <textarea id="order-customize-notes" rows="2" maxlength="500" wire:model.blur="customNotes" placeholder="Ej. sin cebolla, salsa aparte"></textarea>
                    @error('customNotes')<p class="order-customize__error" role="alert">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <footer class="order-customize__footer">
            <div class="order-customize__total" aria-live="polite">
                <small>{{ $quantity }} × ${{ number_format($preview['unit_total'] ?? 0, 2) }}</small>
                <strong>${{ number_format($preview['subtotal'] ?? 0, 2) }}</strong>
                @if (! empty($preview['error']))<span class="order-customize__pending"><i class="bx bx-info-circle" aria-hidden="true"></i> {{ $preview['error'] }}</span>@endif
            </div>
            <div class="order-customize__actions">
                <button type="button" class="orders-button orders-button--ghost" wire:click="closeCustomization">Cancelar</button>
                <button type="button" class="orders-button orders-button--primary" wire:click="confirmCustomization"
                    wire:loading.attr="disabled" wire:target="confirmCustomization" @disabled(! empty($preview['error']))>
                    <i class="bx {{ $editing ? 'bx-check' : 'bx-plus' }}" aria-hidden="true"></i>
                    <span>{{ $editing ? 'Guardar cambios' : 'Agregar a la orden' }}</span>
                </button>
            </div>
        </footer>
    </section>
</div>
