@if($showCustomizeModal)
<div class="pos-modal-wrap show" wire:click.self="$set('showCustomizeModal',false)">
    <div class="pos-modal wide" @click.stop>
        @if($this->customizingProduct)
        <div class="modal-header-pos">
            <div style="flex:1">
                <h4 style="margin-bottom:2px">{{ $this->customizingProduct->name }}</h4>
                <small style="color:var(--pos-muted);font-size:.72rem">Base: ${{ number_format($this->customizingProduct->price,2) }}</small>
            </div>
            <button wire:click="$set('showCustomizeModal',false)" class="pos-btn pos-btn-secondary" style="padding:4px 8px"><i class="bx bx-x"></i></button>
        </div>
        <div class="modal-body-pos">
            {{-- Cantidad --}}
            <div class="addon-qty-bar">
                <span>Cantidad</span>
                <div class="addon-qty-controls">
                    <button wire:click="$set('itemQuantity', max(1, itemQuantity - 1))" class="addon-qty-btn"><i class="bx bx-minus"></i></button>
                    <span class="addon-qty-val">{{ $itemQuantity }}</span>
                    <button wire:click="$set('itemQuantity', itemQuantity + 1)" class="addon-qty-btn"><i class="bx bx-plus"></i></button>
                </div>
            </div>

            {{-- Grupos de opciones --}}
            @foreach($this->customizingProduct->addonGroups as $group)
                <div style="margin-bottom:18px">
                    <div class="addon-group-title">
                        {{ $group->name }}
                        @if($group->is_required)
                            <span style="font-size:.6rem;padding:2px 7px;border-radius:20px;background:rgba(255,62,29,.1);border:1px solid rgba(255,62,29,.3);color:var(--pos-danger);font-weight:700">Obligatorio</span>
                        @else
                            <span style="font-size:.6rem;padding:2px 7px;border-radius:20px;background:var(--pos-bg);border:1px solid var(--pos-border-md);color:var(--pos-muted);font-weight:700">Opcional</span>
                        @endif
                        @if($group->max_selections > 1)
                            <span style="font-size:.6rem;color:var(--pos-muted)">Máx. {{ $group->max_selections }}</span>
                        @endif
                    </div>
                    @foreach($group->addons as $addon)
                        @php $checked = isset($selectedAddons[$addon->id]); @endphp
                        <div wire:click="toggleAddon({{ $addon->id }})" class="addon-option {{ $checked ? 'selected' : '' }}">
                            <div class="addon-opt-left">
                                <div class="addon-check {{ $group->selection_type === 'single' ? 'radio' : '' }} {{ $checked ? 'selected' : '' }}">
                                    @if($checked && $group->selection_type !== 'single')<i class="bx bx-check" style="color:#fff;font-size:.7rem"></i>@endif
                                </div>
                                <span class="addon-opt-name">{{ $addon->name }}</span>
                            </div>
                            @if($addon->extra_price > 0)
                                <span class="addon-opt-price">+${{ number_format($addon->extra_price,2) }}</span>
                            @else
                                <span class="addon-opt-free">Gratis</span>
                            @endif
                        </div>
                        @error('addons_'.$group->id)
                            <div style="font-size:.72rem;color:var(--pos-danger);margin-top:3px">{{ $message }}</div>
                        @enderror
                    @endforeach
                </div>
            @endforeach

            {{-- Ingredientes --}}
            @if($this->customizingProduct->ingredients->count() > 0)
                <div class="addon-group-title" style="margin-top:4px">
                    Ingredientes
                    @if($this->customizingProduct->max_ingredients)
                        <span style="font-size:.6rem;color:var(--pos-muted)">Máx. {{ $this->customizingProduct->max_ingredients }}</span>
                    @endif
                    <span style="font-size:.6rem;color:var(--pos-muted)">Seleccionados: {{ $this->totalSelectedIngredients }}</span>
                </div>
                <div class="ing-grid">
                    @foreach($this->customizingProduct->ingredients as $ing)
                        @php $qty = $selectedIngredients[$ing->id] ?? 0; @endphp
                        <div class="ing-card {{ $qty > 0 ? 'selected' : '' }}"
                             wire:click="incrementIngredient({{ $ing->id }})">
                            <div class="ing-card-img-wrap">
                                @if($ing->image)
                                    <img src="{{ asset('storage/'.$ing->image) }}" alt="{{ $ing->name }}">
                                @else
                                    <span class="ing-card-img-placeholder">
                                        <i class="bx bx-bowl-hot"></i>
                                    </span>
                                @endif
                            </div>
                            <div class="ing-card-name">{{ $ing->name }}</div>
                            @if($ing->extra_price > 0)
                                <div class="ing-card-price">+${{ number_format($ing->extra_price,2) }}/u</div>
                            @endif
                            <div class="ing-card-controls" wire:click.stop>
                                <button wire:click="decrementIngredient({{ $ing->id }})" class="ing-card-btn" {{ $qty === 0 ? 'disabled' : '' }}>
                                    <i class="bx bx-minus"></i>
                                </button>
                                <span class="ing-card-qty">{{ $qty }}</span>
                                <button wire:click="incrementIngredient({{ $ing->id }})" class="ing-card-btn">
                                    <i class="bx bx-plus"></i>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
                @error('ingredients')<div style="font-size:.72rem;color:var(--pos-danger);margin-bottom:6px">{{ $message }}</div>@enderror
            @endif

            {{-- Nota --}}
            <div style="margin-top:8px">
                <label class="co-label"><i class="bx bx-note me-1"></i>Nota para cocina</label>
                <input type="text" wire:model="itemNotes" class="co-input" placeholder="Instrucción especial…">
            </div>
        </div>
        <div class="modal-footer-pos">
            <button wire:click="$set('showCustomizeModal',false)" class="pos-btn pos-btn-ghost">Cancelar</button>
            <button wire:click="addToCart"
                    wire:loading.attr="disabled" wire:target="addToCart"
                    class="pos-btn pos-btn-primary pos-btn-lg">
                <span wire:loading wire:target="addToCart" class="pos-btn-spinner"></span>
                <i wire:loading.remove wire:target="addToCart" class="bx bx-cart-add"></i>
                {{ $editingCartId ? 'Actualizar' : 'Agregar al carrito' }}
                @php
                    $addonTotal = 0;
                    foreach($this->customizingProduct->addonGroups as $g)
                        foreach($g->addons as $a)
                            if(isset($selectedAddons[$a->id])) $addonTotal += $a->extra_price;
                    foreach($this->customizingProduct->ingredients as $ing)
                        $addonTotal += ($selectedIngredients[$ing->id] ?? 0) * $ing->extra_price;
                    $unitP = ($this->customizingProduct->price ?? 0) + $addonTotal;
                @endphp
                · ${{ number_format($unitP * $itemQuantity, 2) }}
            </button>
        </div>
        @endif
    </div>
</div>
@endif
