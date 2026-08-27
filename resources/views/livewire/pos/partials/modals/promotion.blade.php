@if($showPromotionModal && $this->customizingPromotion)
    @php
        $promotion = $this->customizingPromotion;
    @endphp
    <div class="pos-modal-backdrop" wire:click="closePromotionModal"></div>
    <div class="pos-modal-wrap is-open promotion-picker-wrap" role="dialog" aria-modal="true" aria-labelledby="promotion-picker-title">
        <section class="promotion-picker">
            <header class="promotion-picker__header">
                <div><span><i class="bx bx-purchase-tag-alt"></i></span><div><small>Precio especial</small><h2 id="promotion-picker-title">{{ $promotion->name }}</h2><p>{{ $promotion->description ?: 'Selecciona los productos incluidos.' }}</p></div></div>
                <strong>${{ number_format($promotion->price, 2) }}</strong>
                <button type="button" wire:click="closePromotionModal" aria-label="Cerrar"><i class="bx bx-x"></i></button>
            </header>
            <div class="promotion-picker__body">
                @foreach($promotion->groups as $group)
                    @php
                        $selectedCount = collect($promotionSelections[$group->id] ?? [])->sum();
                    @endphp
                    <fieldset class="promotion-picker__group">
                        <legend><span><strong>{{ $group->name }}</strong><small>Elige de {{ $group->min_selections }} a {{ $group->max_selections }}</small></span><b class="{{ $selectedCount >= $group->min_selections && $selectedCount <= $group->max_selections ? 'is-valid' : '' }}">{{ $selectedCount }}/{{ $group->max_selections }}</b></legend>
                        <div>
                            @foreach($group->products as $product)
                                @php
                                    $selectedQuantity = (int) ($promotionSelections[$group->id][$product->id] ?? 0);
                                @endphp
                                <article class="promotion-choice {{ $selectedQuantity > 0 ? 'is-selected' : '' }}">
                                    @if($product->image)<img src="{{ Storage::url($product->image) }}" alt="" width="68" height="68">@else<span><i class="bx bx-dish"></i></span>@endif
                                    <strong>{{ $product->name }}</strong>
                                    <div><button type="button" wire:click="changePromotionSelection({{ $group->id }},{{ $product->id }},-1)" @disabled($selectedQuantity===0) aria-label="Quitar {{ $product->name }}"><i class="bx bx-minus"></i></button><b>{{ $selectedQuantity }}</b><button type="button" wire:click="changePromotionSelection({{ $group->id }},{{ $product->id }},1)" @disabled($selectedCount >= $group->max_selections) aria-label="Agregar {{ $product->name }}"><i class="bx bx-plus"></i></button></div>
                                </article>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
                @error('promotion')<p class="promotion-picker__error"><i class="bx bx-error-circle"></i>{{ $message }}</p>@enderror
            </div>
            <footer class="promotion-picker__footer">
                <label><span>Cantidad de promociones</span><input type="number" wire:model="promotionQuantity" min="1" max="99"></label>
                <div><button type="button" class="pos-btn pos-btn-secondary" wire:click="closePromotionModal">Cancelar</button><button type="button" class="pos-btn pos-btn-primary" wire:click="addPromotionToCart"><i class="bx bx-cart-add"></i>Agregar por ${{ number_format($promotion->price, 2) }}</button></div>
            </footer>
        </section>
    </div>
@endif
