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
                <div class="promotion-picker__terms"><span><i class="bx bx-map-pin"></i>{{ $promotion->fulfillmentSummary() }}</span>@if($promotion->terms_and_conditions)<p><i class="bx bx-info-circle"></i>{{ $promotion->terms_and_conditions }}</p>@endif</div>
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

@if($activePromotionNotice)
    <div class="pos-modal-backdrop"></div>
    <div class="pos-modal-wrap is-open promotion-picker-wrap" role="dialog" aria-modal="true"
        aria-labelledby="active-promotion-title" aria-describedby="active-promotion-description">
        <section class="promotion-picker active-promotion-modal">
            <header class="promotion-picker__header active-promotion-modal__header">
                <div>
                    <span aria-hidden="true"><i class="bx bx-check"></i></span>
                    <div>
                        <small>Aplicar promociones</small>
                        <h2 id="active-promotion-title">Promoción activa</h2>
                        <p>{{ $activePromotionNotice['promotion_name'] }}</p>
                    </div>
                </div>
                <strong>{{ $activePromotionNotice['label'] }}</strong>
                <button type="button" wire:click="closeActivePromotionNotice" aria-label="Cerrar aviso de promoción">
                    <i class="bx bx-x" aria-hidden="true"></i>
                </button>
            </header>

            <div class="promotion-picker__body active-promotion-modal__body">
                <div class="active-promotion-modal__success" role="status" aria-live="polite">
                    <span aria-hidden="true"><i class="bx bx-purchase-tag-alt"></i></span>
                    <div>
                        <strong>El pedido ya cumple las condiciones</strong>
                        <p id="active-promotion-description">{{ $activePromotionNotice['explanation'] }}</p>
                    </div>
                </div>

                <div class="active-promotion-modal__summary" aria-label="Resumen de la promoción">
                    <div>
                        <span>Productos participantes</span>
                        <strong>{{ $activePromotionNotice['eligible_quantity'] }}</strong>
                    </div>
                    <div>
                        <span>Veces aplicada</span>
                        <strong>{{ $activePromotionNotice['application_count'] }}</strong>
                    </div>
                    <div class="is-saving">
                        <span>Ahorro en el pedido</span>
                        <strong>−${{ number_format($activePromotionNotice['total_savings'], 2) }}</strong>
                    </div>
                </div>

                <section class="active-promotion-modal__products" aria-labelledby="active-promotion-products-title">
                    <h3 id="active-promotion-products-title">Así se completa</h3>
                    <div>
                        @foreach($activePromotionNotice['products'] as $product)
                            <span><i class="bx bx-check-circle" aria-hidden="true"></i><strong>{{ $product['quantity'] }}×</strong> {{ $product['name'] }}</span>
                        @endforeach
                    </div>
                </section>
            </div>

            <footer class="promotion-picker__footer active-promotion-modal__footer">
                <span><i class="bx bx-shield-quarter" aria-hidden="true"></i> El cálculo se validó con los precios actuales.</span>
                <div>
                    <button type="button" class="pos-btn pos-btn-secondary" wire:click="closeActivePromotionNotice">Cerrar</button>
                    <button type="button" class="pos-btn pos-btn-primary" wire:click="confirmActivePromotion">
                        <i class="bx bx-check" aria-hidden="true"></i>Confirmar promoción
                    </button>
                </div>
            </footer>
        </section>
    </div>
@endif

@if($this->automaticPromotionPicker)
    @php
        $automaticPromotion = $this->automaticPromotionPicker;
        $eligibleProducts = $automaticPromotion->groups->flatMap->products->unique('id')->values();
        if ($eligibleProducts->isEmpty() && $automaticPromotion->primaryProduct?->is_active) {
            $eligibleProducts = collect([$automaticPromotion->primaryProduct]);
        }
        $automaticRule = $automaticPromotion->normalizedPricingRule();
        $automaticCycle = $automaticRule['buy_quantity'] + $automaticRule['reward_quantity'];
        $automaticSelectedTotal = collect($automaticPromotionSelections)->sum();
    @endphp
    <div class="pos-modal-backdrop" wire:click="closeAutomaticPromotionPicker"></div>
    <div class="pos-modal-wrap is-open promotion-picker-wrap" role="dialog" aria-modal="true" aria-labelledby="automatic-promotion-picker-title">
        <section class="promotion-picker">
            <header class="promotion-picker__header">
                <div><span><i class="bx bx-group"></i></span><div><small>Grupo promocional</small><h2 id="automatic-promotion-picker-title">{{ $automaticPromotion->name }}</h2><p>Elige cualquier artículo elegible. El beneficio se calculará al completar {{ $automaticCycle }} productos.</p></div></div>
                <strong>{{ $automaticPromotion->pricingRuleShortLabel() }}</strong>
                <button type="button" wire:click="closeAutomaticPromotionPicker" aria-label="Cerrar"><i class="bx bx-x"></i></button>
            </header>
            <div class="promotion-picker__body">
                <div class="promotion-picker__terms"><span><i class="bx bx-info-circle"></i>En cada grupo de {{ $automaticCycle }}, {{ $automaticRule['reward_quantity'] }} producto(s) de menor precio reciben el beneficio.</span></div>
                <fieldset class="promotion-picker__group">
                    <legend><span><strong>Productos elegibles</strong><small>Selecciona {{ $automaticCycle }} en total; puedes combinar o repetir el mismo artículo.</small></span><b class="{{ $automaticSelectedTotal === $automaticCycle ? 'is-valid' : '' }}">{{ $automaticSelectedTotal }}/{{ $automaticCycle }}</b></legend>
                    <div>
                        @foreach($eligibleProducts as $product)
                            @php $automaticSelectedQuantity = (int) ($automaticPromotionSelections[$product->id] ?? 0); @endphp
                            <article class="promotion-choice {{ $automaticSelectedQuantity > 0 ? 'is-selected' : '' }}" wire:key="automatic-promotion-{{ $automaticPromotion->id }}-product-{{ $product->id }}">
                                @if($product->image)<img src="{{ Storage::url($product->image) }}" alt="" width="54" height="54">@else<span><i class="bx bx-dish"></i></span>@endif
                                <strong>{{ $product->name }} · ${{ number_format($product->price, 2) }}</strong>
                                <div><button type="button" wire:click="removeEligiblePromotionProduct({{ $automaticPromotion->id }}, {{ $product->id }})" @disabled($automaticSelectedQuantity === 0) aria-label="Quitar {{ $product->name }}"><i class="bx bx-minus"></i></button><b>{{ $automaticSelectedQuantity }}</b><button type="button" wire:click="addEligiblePromotionProduct({{ $automaticPromotion->id }}, {{ $product->id }})" @disabled($automaticSelectedTotal >= $automaticCycle || $automaticSelectedQuantity >= 99) aria-label="Agregar {{ $product->name }}"><i class="bx bx-plus"></i></button></div>
                            </article>
                        @endforeach
                    </div>
                </fieldset>
                @error('automaticPromotionSelection')<p class="promotion-picker__error"><i class="bx bx-error-circle"></i>{{ $message }}</p>@enderror
            </div>
            <footer class="promotion-picker__footer"><span>El grupo se agregará completo y la promoción se calculará en el carrito.</span><div><button type="button" class="pos-btn pos-btn-secondary" wire:click="closeAutomaticPromotionPicker">Cancelar</button><button type="button" class="pos-btn pos-btn-primary" wire:click="confirmAutomaticPromotionSelection({{ $automaticPromotion->id }})" @disabled($automaticSelectedTotal !== $automaticCycle)><i class="bx bx-check"></i>Agregar {{ $automaticCycle }} productos</button></div></footer>
        </section>
    </div>
@endif
