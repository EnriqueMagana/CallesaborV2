@php
    $t = $this->totals;
    $changed = $t['summary']['added'] + $t['summary']['removed'] + $t['summary']['updated'] > 0;
    $backUrl = $source === 'list' ? route('app.ordenes') : route('app.ordenes.show', $order);
@endphp

<main class="app-page order-editor-page" aria-labelledby="order-editor-title" x-data="{ ticket: false }"
    x-on:keydown.escape.window="ticket = false">
    <header class="order-editor-header">
        <a class="order-editor-back" href="{{ $backUrl }}" aria-label="Volver a la orden">
            <i class="bx bx-arrow-back" aria-hidden="true"></i><span>Volver</span>
        </a>
        <div>
            <span class="app-eyebrow">Modificar productos · {{ $order->type_label }}</span>
            <h1 id="order-editor-title">{{ $order->display_folio }} <span>· {{ $order->display_name }}</span></h1>
            <p>Arma el cambio aquí. La orden no se modifica hasta que se autorice la solicitud.</p>
        </div>
        <span class="order-editor-status"><i class="bx bx-time-five" aria-hidden="true"></i> {{ $order->status_label }}</span>
    </header>

    <div class="order-editor">
        {{-- ── Catálogo ─────────────────────────────────────────────── --}}
        <section class="order-editor-catalog" aria-labelledby="order-editor-catalog-title">
            <h2 id="order-editor-catalog-title" class="visually-hidden">Menú</h2>
            <div class="order-editor-toolbar">
                <label class="order-editor-search">
                    <i class="bx bx-search" aria-hidden="true"></i>
                    <span class="visually-hidden">Buscar en el menú</span>
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar en el menú" autocomplete="off">
                </label>
                @if ($this->categories->isNotEmpty())
                    <div class="order-editor-chips" role="group" aria-label="Categorías">
                        <button type="button" wire:click="setCategory('all')" class="{{ $category === 'all' ? 'is-active' : '' }}" aria-pressed="{{ $category === 'all' ? 'true' : 'false' }}">Todo</button>
                        @foreach ($this->categories as $item)
                            <button type="button" wire:click="setCategory('{{ $item->id }}')" wire:key="cat-{{ $item->id }}"
                                class="{{ $category === (string) $item->id ? 'is-active' : '' }}" aria-pressed="{{ $category === (string) $item->id ? 'true' : 'false' }}">{{ $item->name }}</button>
                        @endforeach
                        @if ($this->hasUncategorized)
                            <button type="button" wire:click="setCategory('none')" class="{{ $category === 'none' ? 'is-active' : '' }}" aria-pressed="{{ $category === 'none' ? 'true' : 'false' }}">Otros</button>
                        @endif
                    </div>
                @endif
            </div>

            <div class="order-editor-grid" wire:loading.class="is-refreshing" wire:target="search,setCategory">
                @forelse ($this->products as $product)
                    @php
                        $customizable = $product->is_customizable || $product->addon_groups_count > 0 || $product->ingredients_count > 0;
                        $inDraft = $this->addedByProduct[$product->id] ?? 0;
                    @endphp
                    <button type="button" class="order-editor-product {{ $inDraft ? 'is-in-draft' : '' }}" wire:key="product-{{ $product->id }}"
                        wire:click="addProduct({{ $product->id }})" wire:loading.attr="disabled" wire:target="addProduct({{ $product->id }})"
                        aria-label="{{ $customizable ? 'Personalizar y agregar' : 'Agregar' }} {{ $product->name }}, ${{ number_format($product->price, 2) }}{{ $inDraft ? ", {$inDraft} agregado(s)" : '' }}">
                        <span class="order-editor-product__media" aria-hidden="true">
                            @if ($product->image)
                                <img src="{{ Storage::url($product->image) }}" alt="" loading="lazy" decoding="async" width="120" height="90">
                            @else
                                <span>{{ mb_strtoupper(mb_substr($product->name, 0, 1)) }}</span>
                            @endif
                            @if ($inDraft)<b class="order-editor-product__count">{{ $inDraft }}</b>@endif
                        </span>
                        <span class="order-editor-product__name">{{ $product->name }}</span>
                        <span class="order-editor-product__meta">
                            <strong>${{ number_format($product->price, 2) }}</strong>
                            @if ($customizable)
                                <small><i class="bx bx-slider-alt" aria-hidden="true"></i> Personalizable</small>
                            @endif
                        </span>
                        <i class="bx bx-loader-alt bx-spin order-editor-product__loading" wire:loading wire:target="addProduct({{ $product->id }})" aria-hidden="true"></i>
                    </button>
                @empty
                    <div class="order-editor-empty">
                        <i class="bx bx-search-alt" aria-hidden="true"></i>
                        <h3>Sin productos</h3>
                        <p>{{ $search !== '' ? 'Ningún producto coincide con «'.$search.'».' : 'No hay productos activos en esta categoría.' }}</p>
                    </div>
                @endforelse
            </div>
        </section>

        {{-- ── Orden ────────────────────────────────────────────────── --}}
        <aside class="order-editor-ticket" :class="ticket ? 'is-open' : ''" aria-labelledby="order-editor-ticket-title">
            <header>
                <div>
                    <h2 id="order-editor-ticket-title">Orden {{ $order->display_folio }}</h2>
                    <p>
                        @if ($changed)
                            {{ $t['summary']['added'] }} agregado(s) · {{ $t['summary']['removed'] }} retirado(s) · {{ $t['summary']['updated'] }} con cambio de cantidad
                        @else
                            Sin cambios todavía
                        @endif
                    </p>
                </div>
                <button type="button" class="order-editor-ticket__close" x-on:click="ticket = false" aria-label="Cerrar resumen de la orden"><i class="bx bx-x"></i></button>
            </header>

            <ul class="order-editor-lines" aria-live="polite">
                @foreach ($lines as $index => $line)
                    @php
                        $isNew = $line['kind'] === 'new';
                        $removed = ! $isNew && (int) $line['quantity'] === 0;
                        $qtyChanged = ! $isNew && ! $removed && (int) $line['quantity'] !== (int) $line['original_quantity'];
                    @endphp
                    <li class="order-editor-line {{ $isNew ? 'is-new' : '' }} {{ $removed ? 'is-removed' : '' }} {{ $qtyChanged ? 'is-changed' : '' }}" wire:key="line-{{ $line['key'] }}">
                        <div class="order-editor-line__info">
                            <strong>{{ $line['name'] }}</strong>
                            @if (filled($line['modifiers'] ?? null))<small class="order-editor-line__mods">{{ $line['modifiers'] }}</small>@endif
                            <small>
                                @if ($isNew)<span class="order-editor-tag is-new">Nuevo</span>
                                @elseif ($removed)<span class="order-editor-tag is-removed">Se retira</span>
                                @elseif ($qtyChanged)<span class="order-editor-tag is-changed">Antes {{ $line['original_quantity'] }}</span>
                                @endif
                                ${{ number_format($line['unit_subtotal'], 2) }} c/u
                            </small>
                        </div>

                        @if ($removed)
                            <button type="button" class="order-editor-link" wire:click="restoreLine({{ $index }})"><i class="bx bx-undo" aria-hidden="true"></i> Deshacer</button>
                        @else
                            <div class="order-editor-qty" role="group" aria-label="Cantidad de {{ $line['name'] }}">
                                <button type="button" wire:click="adjustLine({{ $index }}, -1)" aria-label="Quitar una unidad de {{ $line['name'] }}"><i class="bx bx-minus"></i></button>
                                <b aria-live="polite">{{ $line['quantity'] }}</b>
                                <button type="button" wire:click="adjustLine({{ $index }}, 1)" @disabled($line['quantity'] >= 99) aria-label="Agregar una unidad de {{ $line['name'] }}"><i class="bx bx-plus"></i></button>
                            </div>
                        @endif

                        <div class="order-editor-line__end">
                            <strong>${{ number_format($line['unit_subtotal'] * $line['quantity'], 2) }}</strong>
                            <span>
                                @if ($isNew)
                                    <button type="button" class="order-editor-icon" wire:click="editLine({{ $index }})" aria-label="Editar {{ $line['name'] }}"><i class="bx bx-edit-alt"></i></button>
                                @endif
                                @unless ($removed)
                                    <button type="button" class="order-editor-icon is-danger" wire:click="removeLine({{ $index }})" aria-label="{{ $isNew ? 'Quitar' : 'Retirar de la orden' }} {{ $line['name'] }}"><i class="bx bx-trash"></i></button>
                                @endunless
                            </span>
                        </div>
                    </li>
                @endforeach
            </ul>

            <footer class="order-editor-summary">
                <dl>
                    <div><dt>Total actual</dt><dd>${{ number_format($t['current'], 2) }}</dd></div>
                    <div class="is-total"><dt>Nuevo total</dt><dd>${{ number_format($t['proposed'], 2) }}</dd></div>
                    @if (abs($t['delta']) > 0.009)
                        <div class="{{ $t['delta'] > 0 ? 'is-up' : 'is-down' }}"><dt>Diferencia</dt><dd>{{ $t['delta'] > 0 ? '+' : '−' }}${{ number_format(abs($t['delta']), 2) }}</dd></div>
                    @endif
                </dl>
                @if ($t['pending'] > 0.009)
                    <p class="order-editor-money is-up"><i class="bx bx-time-five" aria-hidden="true"></i> Ya se recibieron ${{ number_format($t['net_paid'], 2) }}. Quedarán ${{ number_format($t['pending'], 2) }} por cobrar {{ $t['collected_on_delivery'] ? 'contra entrega' : 'en caja' }}.</p>
                @elseif ($t['refund'] > 0.009)
                    <p class="order-editor-money is-down"><i class="bx bx-undo" aria-hidden="true"></i> Ya se recibieron ${{ number_format($t['net_paid'], 2) }}. Se devolverán ${{ number_format($t['refund'], 2) }} al autorizar.</p>
                @endif
                @error('lines')<p class="order-editor-error" role="alert"><i class="bx bx-error-circle" aria-hidden="true"></i> {{ $message }}</p>@enderror
                <div class="order-editor-actions">
                    <button type="button" class="orders-button orders-button--ghost" wire:click="discardChanges"
                        wire:confirm="¿Descartar los cambios y volver a la orden original?" @disabled(! $changed)>Descartar</button>
                    <button type="button" class="orders-button orders-button--primary" wire:click="continueToRequest"
                        wire:loading.attr="disabled" wire:target="continueToRequest" @disabled(! $changed)>
                        <span>Continuar</span><i class="bx bx-right-arrow-alt" aria-hidden="true"></i>
                    </button>
                </div>
            </footer>
        </aside>
    </div>

    {{-- Barra fija en celular: abre el resumen de la orden --}}
    <button type="button" class="order-editor-mobile-bar" x-on:click="ticket = true" x-show="! ticket" aria-label="Ver orden, nuevo total ${{ number_format($t['proposed'], 2) }}">
        <span><i class="bx bx-receipt" aria-hidden="true"></i> Ver orden
            @if ($changed)<b>{{ $t['summary']['added'] + $t['summary']['removed'] + $t['summary']['updated'] }}</b>@endif
        </span>
        <strong>${{ number_format($t['proposed'], 2) }}</strong>
    </button>
    <div class="order-editor-scrim" x-show="ticket" x-on:click="ticket = false" x-transition.opacity aria-hidden="true"></div>

    @if ($this->customizingProduct)
        <x-orders.customize-product-modal :product="$this->customizingProduct" :addons="$customAddons"
            :ingredients="$customIngredients" :quantity="$customQuantity" :preview="$this->customPreview"
            :editing="$editingLineIndex !== null" />
    @endif
</main>
