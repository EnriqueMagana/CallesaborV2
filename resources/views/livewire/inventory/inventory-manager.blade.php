<div
    class="inventory-page"
    x-data="{ booting: true }"
    x-init="requestAnimationFrame(() => requestAnimationFrame(() => booting = false))"
>
    <div class="inventory-initial-skeleton" x-show="booting" aria-label="Cargando inventario" role="status">
        <div class="inventory-skeleton-hero"><i></i><div><b></b><span></span></div><strong></strong></div>
        <div class="inventory-skeleton-stats">@for($i = 0; $i < 4; $i++)<article><i></i><div><b></b><span></span></div></article>@endfor</div>
        <div class="inventory-skeleton-table"><header><b></b><span></span></header>@for($i = 0; $i < 5; $i++)<article><i></i><div><b></b><span></span></div><strong></strong></article>@endfor</div>
    </div>

    <div x-show="!booting" x-cloak>
        <header class="inventory-hero">
            <div>
                <span class="inventory-eyebrow"><i class="bx bx-package"></i> Operación de sucursal</span>
                <h1>Inventario de insumos</h1>
                <p>Controla existencias, registra cada movimiento y prepara compras con recepción por folio.</p>
            </div>
            <div class="inventory-hero-actions">
                @can('recepcionar compras inventario')
                    <button type="button" class="btn btn-outline-primary inventory-action" wire:click="openReceptionModal" wire:loading.class="is-loading" wire:loading.attr="disabled" wire:target="openReceptionModal">
                        <i class="bx bx-barcode-reader"></i><span>Recibir compra</span><b class="inventory-button-loader"></b>
                    </button>
                @endcan
                @can('generar compras inventario')
                    <button type="button" class="btn btn-primary inventory-action" wire:click="openPurchaseModal" wire:loading.class="is-loading" wire:loading.attr="disabled" wire:target="openPurchaseModal">
                        <i class="bx bx-receipt"></i><span>Nueva lista</span><b class="inventory-button-loader"></b>
                    </button>
                @endcan
            </div>
        </header>

        @if(session('inventoryNotice'))
            <div class="inventory-notice" role="status">
                <i class="bx bx-check-circle"></i>
                <span>{{ session('inventoryNotice') }}</span>
            </div>
        @endif

        @if($lastCreatedPurchaseId)
            @php $createdPurchase = \App\Models\InventoryPurchase::find($lastCreatedPurchaseId); @endphp
            @if($createdPurchase)
                <section class="inventory-ticket-ready">
                    <span><i class="bx bx-printer"></i></span>
                    <div><strong>{{ $createdPurchase->folio }} está listo</strong><small>Revisa los insumos y la presentación del ticket antes de imprimir.</small></div>
                    <button type="button" class="btn btn-sm btn-primary inventory-action" wire:click="openPurchaseDetail({{ $createdPurchase->id }})" wire:loading.class="is-loading" wire:loading.attr="disabled" wire:target="openPurchaseDetail({{ $createdPurchase->id }})"><i class="bx bx-show"></i><span>Revisar ticket</span><b class="inventory-button-loader"></b></button>
                </section>
            @endif
        @endif

        <section class="inventory-stats" aria-label="Resumen del inventario">
            <article><span class="is-purple"><i class="bx bx-package"></i></span><div><small>Insumos activos</small><strong>{{ $this->stats['items'] }}</strong></div></article>
            <article><span class="is-amber"><i class="bx bx-down-arrow-circle"></i></span><div><small>Existencia baja</small><strong>{{ $this->stats['low'] }}</strong></div></article>
            <article><span class="is-red"><i class="bx bx-error-circle"></i></span><div><small>Sin existencia</small><strong>{{ $this->stats['empty'] }}</strong></div></article>
            <article><span class="is-blue"><i class="bx bx-shopping-bag"></i></span><div><small>Compras pendientes</small><strong>{{ $this->stats['pending'] }}</strong></div></article>
        </section>

        <nav class="inventory-tabs" aria-label="Secciones de inventario">
            <button type="button" class="{{ $activeTab === 'stock' ? 'is-active' : '' }}" wire:click="switchTab('stock')" wire:loading.attr="disabled" wire:target="switchTab">
                <i class="bx bx-grid-alt"></i><span>Existencias</span>
            </button>
            <button type="button" class="{{ $activeTab === 'purchases' ? 'is-active' : '' }}" wire:click="switchTab('purchases')" wire:loading.attr="disabled" wire:target="switchTab">
                <i class="bx bx-purchase-tag"></i><span>Compras y recepciones</span>
                @if($this->stats['pending'])<b>{{ $this->stats['pending'] }}</b>@endif
            </button>
        </nav>

        <div class="inventory-tab-loader" wire:loading.flex wire:target="switchTab" role="status"><i class="bx bx-loader-alt"></i><span>Cambiando sección…</span></div>

        @if($activeTab === 'stock')
            <section class="inventory-panel">
                <div class="inventory-panel-head">
                    <div><h2>Existencias actuales</h2><p>Las cantidades solo cambian mediante movimientos registrados.</p></div>
                    @can('gestionar insumos')
                        <button type="button" class="btn btn-primary inventory-action" wire:click="openItemModal" wire:loading.class="is-loading" wire:loading.attr="disabled" wire:target="openItemModal">
                            <i class="bx bx-plus"></i><span>Nuevo insumo</span><b class="inventory-button-loader"></b>
                        </button>
                    @endcan
                </div>

                <form class="inventory-filters" wire:submit="applyFilters">
                    <label class="inventory-search">
                        <span class="visually-hidden">Buscar insumo</span>
                        <i class="bx bx-search"></i>
                        <input type="search" wire:model="search" placeholder="Nombre, SKU o categoría" autocomplete="off">
                    </label>
                    <label>
                        <span class="visually-hidden">Filtrar por unidad</span>
                        <select class="form-select" wire:model="unitFilter">
                            <option value="">Todas las unidades</option>
                            @foreach($units as $key => $unit)<option value="{{ $key }}">{{ $unit['label'] }}</option>@endforeach
                        </select>
                    </label>
                    <label>
                        <span class="visually-hidden">Filtrar por existencia</span>
                        <select class="form-select" wire:model="stockFilter">
                            <option value="">Cualquier existencia</option>
                            <option value="ok">Existencia suficiente</option>
                            <option value="low">Existencia baja</option>
                            <option value="empty">Sin existencia</option>
                        </select>
                    </label>
                    <button type="submit" class="btn btn-primary inventory-action" wire:loading.class="is-loading" wire:loading.attr="disabled" wire:target="applyFilters">
                        <span>Aplicar</span><b class="inventory-button-loader"></b>
                    </button>
                    @if($search || $unitFilter || $stockFilter)
                        <button type="button" class="btn btn-outline-secondary" wire:click="clearFilters">Limpiar</button>
                    @endif
                </form>

                <div class="inventory-component-loader" wire:loading.flex wire:target="applyFilters,clearFilters,gotoPage,nextPage,previousPage" role="status">
                    <i class="bx bx-loader-alt"></i><span>Actualizando existencias…</span>
                </div>

                <div class="inventory-table-wrap">
                    <table class="inventory-table">
                        <thead><tr><th>Insumo</th><th>Unidad</th><th>Existencia</th><th>Mínimo</th><th>Estado</th><th><span class="visually-hidden">Acciones</span></th></tr></thead>
                        <tbody>
                            @forelse($this->items as $item)
                                @php
                                    $stock = rtrim(rtrim(number_format((float) $item->current_stock, 3, '.', ''), '0'), '.');
                                    $minimum = rtrim(rtrim(number_format((float) $item->minimum_stock, 3, '.', ''), '0'), '.');
                                    $status = (float) $item->current_stock <= 0 ? 'empty' : ($item->is_low_stock ? 'low' : 'ok');
                                @endphp
                                <tr wire:key="inventory-item-{{ $item->id }}" class="{{ $item->is_active ? '' : 'is-inactive' }}">
                                    <td data-label="Insumo">
                                        <div class="inventory-item-cell">
                                            <span><i class="bx bx-cube"></i></span>
                                            <div><strong>{{ $item->name }}</strong><small>{{ $item->sku ?: 'Sin SKU' }}{{ $item->category ? ' · '.$item->category : '' }}</small></div>
                                        </div>
                                    </td>
                                    <td data-label="Unidad"><span class="inventory-unit">{{ $item->unit_label }}</span></td>
                                    <td data-label="Existencia"><strong class="inventory-stock">{{ $stock }} <small>{{ $item->unit_short }}</small></strong></td>
                                    <td data-label="Mínimo">{{ $minimum }} {{ $item->unit_short }}</td>
                                    <td data-label="Estado">
                                        <span class="inventory-status is-{{ $status }}"><i class="bx {{ $status === 'ok' ? 'bx-check-circle' : 'bx-error-circle' }}"></i>{{ $status === 'ok' ? 'Suficiente' : ($status === 'low' ? 'Bajo' : 'Agotado') }}</span>
                                        @unless($item->is_active)<span class="inventory-status is-muted">Inactivo</span>@endunless
                                    </td>
                                    <td>
                                        <div class="inventory-row-actions">
                                            @can('ajustar inventario')
                                                <button type="button" class="is-in" wire:click="openAdjustmentModal({{ $item->id }}, 'in')" wire:loading.attr="disabled" wire:target="openAdjustmentModal({{ $item->id }}, 'in')" aria-label="Agregar existencia a {{ $item->name }}"><i class="bx bx-plus"></i></button>
                                                <button type="button" class="is-out" wire:click="openAdjustmentModal({{ $item->id }}, 'out')" wire:loading.attr="disabled" wire:target="openAdjustmentModal({{ $item->id }}, 'out')" aria-label="Descontar existencia de {{ $item->name }}"><i class="bx bx-minus"></i></button>
                                            @endcan
                                            @can('gestionar insumos')
                                                <button type="button" wire:click="openItemModal({{ $item->id }})" wire:loading.attr="disabled" wire:target="openItemModal({{ $item->id }})" aria-label="Editar {{ $item->name }}"><i class="bx bx-edit-alt"></i></button>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><div class="inventory-empty"><span><i class="bx bx-package"></i></span><h3>No encontramos insumos</h3><p>Ajusta los filtros o registra el primer insumo de la sucursal.</p></div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($this->items->hasPages())<footer class="inventory-pagination">{{ $this->items->links() }}</footer>@endif
            </section>

            <section class="inventory-panel inventory-movements-panel">
                <div class="inventory-panel-head"><div><h2>Movimientos recientes</h2><p>Trazabilidad de entradas y salidas manuales o por recepción.</p></div></div>
                <div class="inventory-movement-list">
                    @forelse($this->recentMovements as $movement)
                        <article>
                            <span class="{{ (float) $movement->quantity >= 0 ? 'is-in' : 'is-out' }}"><i class="bx {{ (float) $movement->quantity >= 0 ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt' }}"></i></span>
                            <div><strong>{{ $movement->item?->name }}</strong><small>{{ $movement->reason }} · {{ $movement->user?->name }}</small></div>
                            <b class="{{ (float) $movement->quantity >= 0 ? 'is-in' : 'is-out' }}">{{ (float) $movement->quantity >= 0 ? '+' : '' }}{{ rtrim(rtrim(number_format((float) $movement->quantity, 3, '.', ''), '0'), '.') }} {{ $movement->item?->unit_short }}</b>
                            <time>{{ $movement->created_at->diffForHumans() }}</time>
                        </article>
                    @empty
                        <div class="inventory-empty is-compact"><span><i class="bx bx-transfer"></i></span><p>Aún no hay movimientos registrados.</p></div>
                    @endforelse
                </div>
            </section>
        @else
            <section class="inventory-panel">
                <div class="inventory-panel-head">
                    <div><h2>Compras y recepciones</h2><p>Cada lista genera un folio que se utiliza al regresar con los insumos.</p></div>
                    <div class="inventory-panel-actions">
                        @can('recepcionar compras inventario')<button type="button" class="btn btn-outline-primary" wire:click="openReceptionModal"><i class="bx bx-barcode-reader"></i> Ingresar folio</button>@endcan
                        @can('generar compras inventario')<button type="button" class="btn btn-primary" wire:click="openPurchaseModal"><i class="bx bx-plus"></i> Nueva lista</button>@endcan
                    </div>
                </div>

                <form class="inventory-purchase-filters" wire:submit="applyPurchaseFilters">
                    <label class="inventory-search">
                        <span class="visually-hidden">Buscar compra por folio o insumo</span>
                        <i class="bx bx-search"></i>
                        <input type="search" wire:model="purchaseSearch" placeholder="Buscar folio CMP-…, insumo o nota" autocomplete="off">
                    </label>
                    <label>
                        <span class="visually-hidden">Estado de la compra</span>
                        <select class="form-select" wire:model="purchaseStatusFilter">
                            <option value="">Todos los estados</option>
                            <option value="pending">Pendientes</option>
                            <option value="received">Recibidas</option>
                        </select>
                    </label>
                    <button type="submit" class="btn btn-primary inventory-action" wire:loading.class="is-loading" wire:loading.attr="disabled" wire:target="applyPurchaseFilters">
                        <i class="bx bx-search"></i><span>Buscar</span><b class="inventory-button-loader"></b>
                    </button>
                    @if($purchaseSearch || $purchaseStatusFilter)
                        <button type="button" class="btn btn-outline-secondary" wire:click="clearPurchaseFilters">Limpiar</button>
                    @endif
                </form>

                <div class="inventory-component-loader" wire:loading.flex wire:target="applyPurchaseFilters,clearPurchaseFilters,gotoPage,nextPage,previousPage" role="status">
                    <i class="bx bx-loader-alt"></i><span>Buscando compras…</span>
                </div>

                <div class="inventory-purchase-list">
                    @forelse($this->purchases as $purchase)
                        <article wire:key="inventory-purchase-{{ $purchase->id }}">
                            <div class="inventory-purchase-icon {{ $purchase->status === 'received' ? 'is-received' : 'is-pending' }}"><i class="bx {{ $purchase->status === 'received' ? 'bx-check' : 'bx-shopping-bag' }}"></i></div>
                            <div class="inventory-purchase-main">
                                <div><strong>{{ $purchase->folio }}</strong><span class="inventory-status {{ $purchase->status === 'received' ? 'is-ok' : 'is-pending' }}">{{ $purchase->status === 'received' ? 'Recibido' : 'Pendiente' }}</span></div>
                                <small>{{ $purchase->items_count }} {{ $purchase->items_count === 1 ? 'partida' : 'partidas' }} · Preparó {{ $purchase->requester?->name }}</small>
                                <p>{{ $purchase->notes ?: 'Sin indicaciones adicionales.' }}</p>
                            </div>
                            <dl>
                                <div><dt>Emisión</dt><dd>{{ $purchase->issued_at?->format('d/m/Y H:i') }}</dd></div>
                                <div><dt>Recepción</dt><dd>{{ $purchase->received_at?->format('d/m/Y H:i') ?: 'Pendiente' }}</dd></div>
                            </dl>
                            <div class="inventory-purchase-actions">
                                <button type="button" class="btn btn-sm btn-outline-secondary inventory-action" wire:click="openPurchaseDetail({{ $purchase->id }})" wire:loading.class="is-loading" wire:loading.attr="disabled" wire:target="openPurchaseDetail({{ $purchase->id }})"><i class="bx bx-show"></i><span>Ver</span><b class="inventory-button-loader"></b></button>
                                @if($purchase->status === 'pending')
                                    @can('recepcionar compras inventario')
                                        <button type="button" class="btn btn-sm btn-primary inventory-action" wire:click="openReceptionModal({{ $purchase->id }})" wire:loading.class="is-loading" wire:loading.attr="disabled" wire:target="openReceptionModal({{ $purchase->id }})">
                                            <span>Recepcionar</span><b class="inventory-button-loader"></b>
                                        </button>
                                    @endcan
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="inventory-empty"><span><i class="bx bx-receipt"></i></span><h3>{{ $purchaseSearch || $purchaseStatusFilter ? 'No encontramos compras' : 'Aún no hay listas de compra' }}</h3><p>{{ $purchaseSearch || $purchaseStatusFilter ? 'Verifica el folio o ajusta los filtros.' : 'Prepara una lista para generar el primer folio de recepción.' }}</p></div>
                    @endforelse
                </div>
                @if($this->purchases->hasPages())<footer class="inventory-pagination">{{ $this->purchases->links() }}</footer>@endif
            </section>
        @endif
    </div>

    @if($showItemModal)
        <div class="inventory-modal-backdrop" wire:click.self="closeItemModal">
            <section class="inventory-modal" role="dialog" aria-modal="true" aria-labelledby="inventory-item-title">
                <header><div><span>{{ $editingItemId ? 'Editar catálogo' : 'Nuevo registro' }}</span><h2 id="inventory-item-title">{{ $editingItemId ? 'Configurar insumo' : 'Registrar insumo' }}</h2></div><button type="button" wire:click="closeItemModal" aria-label="Cerrar"><i class="bx bx-x"></i></button></header>
                <form wire:submit="saveItem" class="inventory-modal-body">
                    <section class="inventory-form-section">
                        <div class="inventory-section-title"><span><i class="bx bx-cube"></i></span><div><h3>Identificación</h3><p>Datos que verá el equipo en inventario y compras.</p></div></div>
                        <div class="inventory-form-grid">
                            <label class="is-wide"><span>Nombre del insumo</span><input class="form-control" type="text" wire:model="itemName" maxlength="160" placeholder="Ej. Aceite vegetal">@error('itemName')<small class="inventory-error">{{ $message }}</small>@enderror</label>
                            <label><span>SKU o clave</span><input class="form-control" type="text" wire:model="itemSku" maxlength="80" placeholder="Ej. ACE-001">@error('itemSku')<small class="inventory-error">{{ $message }}</small>@enderror</label>
                            <label><span>Categoría</span><input class="form-control" type="text" wire:model="itemCategory" maxlength="100" placeholder="Ej. Abarrotes">@error('itemCategory')<small class="inventory-error">{{ $message }}</small>@enderror</label>
                        </div>
                    </section>
                    <section class="inventory-form-section">
                        <div class="inventory-section-title"><span><i class="bx bx-ruler"></i></span><div><h3>Control de existencia</h3><p>Define cómo se cuenta y cuándo debe reponerse.</p></div></div>
                        <div class="inventory-form-grid is-three">
                            <label><span>Unidad</span><select class="form-select" wire:model="itemUnit">@foreach($units as $key => $unit)<option value="{{ $key }}">{{ $unit['label'] }}</option>@endforeach</select>@error('itemUnit')<small class="inventory-error">{{ $message }}</small>@enderror</label>
                            <label><span>Existencia mínima</span><input class="form-control" type="number" inputmode="decimal" step="0.001" min="0" wire:model="minimumStock">@error('minimumStock')<small class="inventory-error">{{ $message }}</small>@enderror</label>
                            <label><span>Costo estimado por unidad</span><input class="form-control" type="number" inputmode="decimal" step="0.01" min="0" wire:model="estimatedUnitCost" placeholder="Opcional">@error('estimatedUnitCost')<small class="inventory-error">{{ $message }}</small>@enderror</label>
                            @if(!$editingItemId && auth()->user()->can('ajustar inventario'))
                                <label><span>Existencia inicial</span><input class="form-control" type="number" inputmode="decimal" step="0.001" min="0" wire:model="openingStock">@error('openingStock')<small class="inventory-error">{{ $message }}</small>@enderror</label>
                            @endif
                        </div>
                        <label class="inventory-switch"><input type="checkbox" wire:model="itemIsActive"><span><i class="bx bx-power-off"></i></span><div><strong>Insumo activo</strong><small>Disponible para ajustes y nuevas listas de compra.</small></div></label>
                    </section>
                    <footer><button type="button" class="btn btn-outline-secondary" wire:click="closeItemModal">Cancelar</button><button type="submit" class="btn btn-primary inventory-action" wire:loading.class="is-loading" wire:loading.attr="disabled" wire:target="saveItem"><span>{{ $editingItemId ? 'Guardar cambios' : 'Crear insumo' }}</span><b class="inventory-button-loader"></b></button></footer>
                </form>
            </section>
        </div>
    @endif

    @if($showAdjustmentModal && $adjustItemId)
        @php $adjustItem = \App\Models\InventoryItem::find($adjustItemId); @endphp
        <div class="inventory-modal-backdrop" wire:click.self="closeAdjustmentModal">
            <section class="inventory-modal is-small" role="dialog" aria-modal="true" aria-labelledby="inventory-adjust-title" x-data="{ direction: $wire.entangle('adjustDirection') }">
                <header><div><span>Movimiento manual</span><h2 id="inventory-adjust-title" x-text="direction === 'in' ? 'Agregar existencia' : 'Descontar existencia'"></h2></div><button type="button" wire:click="closeAdjustmentModal" aria-label="Cerrar"><i class="bx bx-x"></i></button></header>
                <form wire:submit="saveAdjustment" class="inventory-modal-body">
                    <div class="inventory-adjust-summary">
                        <span :class="direction === 'in' ? 'is-in' : 'is-out'"><i class="bx" :class="direction === 'in' ? 'bx-plus' : 'bx-minus'"></i></span>
                        <div><strong>{{ $adjustItem?->name }}</strong><small>Existencia actual: {{ rtrim(rtrim(number_format((float) $adjustItem?->current_stock, 3, '.', ''), '0'), '.') }} {{ $adjustItem?->unit_short }}</small></div>
                    </div>
                    <div class="inventory-direction-toggle">
                        <label><input type="radio" x-model="direction" value="in"><span><i class="bx bx-plus-circle"></i> Entrada</span></label>
                        <label><input type="radio" x-model="direction" value="out"><span><i class="bx bx-minus-circle"></i> Salida</span></label>
                    </div>
                    <label class="inventory-field"><span>Cantidad</span><div class="inventory-input-suffix"><input class="form-control" type="number" inputmode="decimal" step="0.001" min="0.001" wire:model="adjustQuantity" autofocus><b>{{ $adjustItem?->unit_short }}</b></div>@error('adjustQuantity')<small class="inventory-error">{{ $message }}</small>@enderror</label>
                    <label class="inventory-field"><span>Motivo del movimiento</span><textarea class="form-control" rows="3" wire:model="adjustReason" maxlength="255" placeholder="Ej. Conteo físico, merma o entrada inicial"></textarea>@error('adjustReason')<small class="inventory-error">{{ $message }}</small>@enderror</label>
                    <footer><button type="button" class="btn btn-outline-secondary" wire:click="closeAdjustmentModal">Cancelar</button><button type="submit" class="btn inventory-action" :class="direction === 'in' ? 'btn-primary' : 'btn-danger'" wire:loading.class="is-loading" wire:loading.attr="disabled" wire:target="saveAdjustment"><span>Aplicar movimiento</span><b class="inventory-button-loader"></b></button></footer>
                </form>
            </section>
        </div>
    @endif

    @if($showPurchaseModal)
        <div class="inventory-modal-backdrop" wire:click.self="closePurchaseModal">
            <section class="inventory-modal is-large" role="dialog" aria-modal="true" aria-labelledby="inventory-purchase-title">
                <header><div><span>{{ $editingPurchaseId ? 'Editar solicitud' : 'Solicitud de compra' }}</span><h2 id="inventory-purchase-title">{{ $editingPurchaseId ? 'Actualizar lista de insumos' : 'Preparar lista de insumos' }}</h2></div><button type="button" wire:click="closePurchaseModal" aria-label="Cerrar"><i class="bx bx-x"></i></button></header>
                <form wire:submit="createPurchase" class="inventory-modal-body">
                    <div class="inventory-purchase-guide"><span><i class="bx bx-info-circle"></i></span><div><strong>{{ $editingPurchaseId ? 'El folio se conservará' : 'Se generará un folio único' }}</strong><p>{{ $editingPurchaseId ? 'Los cambios aparecerán inmediatamente en la vista previa y en la siguiente impresión.' : 'Primero podrás revisar el ticket; sólo se imprimirá cuando lo confirmes.' }}</p></div></div>
                    <section class="inventory-purchase-lines">
                        <div class="inventory-purchase-lines-head"><div><h3>Partidas por comprar</h3><p>Agrega hasta 30 insumos en un solo folio.</p></div><button type="button" class="btn btn-sm btn-outline-primary inventory-action" wire:click="addPurchaseLine" wire:loading.class="is-loading" wire:loading.attr="disabled" wire:target="addPurchaseLine"><i class="bx bx-plus"></i><span>Agregar partida</span><b class="inventory-button-loader"></b></button></div>
                        @foreach($purchaseLines as $index => $line)
                            <article wire:key="purchase-line-{{ $index }}">
                                <b>{{ $index + 1 }}</b>
                                <label><span>Insumo</span><select class="form-select" wire:model="purchaseLines.{{ $index }}.inventory_item_id"><option value="">Selecciona un insumo</option>@foreach($this->catalog as $catalogItem)<option value="{{ $catalogItem->id }}">{{ $catalogItem->name }} · {{ $catalogItem->unit_label }}</option>@endforeach</select>@error('purchaseLines.'.$index.'.inventory_item_id')<small class="inventory-error">{{ $message }}</small>@enderror</label>
                                <label><span>Cantidad</span><input class="form-control" type="number" inputmode="decimal" step="0.001" min="0.001" wire:model="purchaseLines.{{ $index }}.quantity" placeholder="0">@error('purchaseLines.'.$index.'.quantity')<small class="inventory-error">{{ $message }}</small>@enderror</label>
                                <label><span>Indicaciones</span><input class="form-control" type="text" maxlength="255" wire:model="purchaseLines.{{ $index }}.notes" placeholder="Marca, presentación…">@error('purchaseLines.'.$index.'.notes')<small class="inventory-error">{{ $message }}</small>@enderror</label>
                                <button type="button" wire:click="removePurchaseLine({{ $index }})" {{ count($purchaseLines) === 1 ? 'disabled' : '' }} aria-label="Eliminar partida"><i class="bx bx-trash"></i></button>
                            </article>
                        @endforeach
                    </section>
                    <label class="inventory-field"><span>Indicaciones generales</span><textarea class="form-control" rows="3" wire:model="purchaseNotes" maxlength="1000" placeholder="Proveedor sugerido, horario o presupuesto de referencia"></textarea>@error('purchaseNotes')<small class="inventory-error">{{ $message }}</small>@enderror</label>
                    <footer><button type="button" class="btn btn-outline-secondary" wire:click="closePurchaseModal">Cancelar</button><button type="submit" class="btn btn-primary inventory-action" wire:loading.class="is-loading" wire:loading.attr="disabled" wire:target="createPurchase"><i class="bx {{ $editingPurchaseId ? 'bx-save' : 'bx-show' }}"></i><span>{{ $editingPurchaseId ? 'Guardar y revisar' : 'Generar y revisar ticket' }}</span><b class="inventory-button-loader"></b></button></footer>
                </form>
            </section>
        </div>
    @endif

    @if($showPurchaseDetailModal && $this->selectedPurchase)
        @php
            $detailPurchase = $this->selectedPurchase;
            $canPrintPurchase = auth()->user()->can('generar compras inventario') || auth()->user()->can('recepcionar compras inventario');
        @endphp
        <div class="inventory-modal-backdrop" wire:click.self="closePurchaseDetail">
            <section class="inventory-modal is-ticket-preview" role="dialog" aria-modal="true" aria-labelledby="inventory-purchase-detail-title">
                <header>
                    <div><span>Compra {{ $detailPurchase->status === 'received' ? 'recibida' : 'pendiente' }}</span><h2 id="inventory-purchase-detail-title">{{ $detailPurchase->folio }}</h2></div>
                    <button type="button" wire:click="closePurchaseDetail" aria-label="Cerrar"><i class="bx bx-x"></i></button>
                </header>
                <div class="inventory-ticket-detail">
                    <section class="inventory-ticket-summary">
                        <div class="inventory-ticket-summary-head">
                            <span class="inventory-status {{ $detailPurchase->status === 'received' ? 'is-ok' : 'is-pending' }}"><i class="bx {{ $detailPurchase->status === 'received' ? 'bx-check-circle' : 'bx-time-five' }}"></i>{{ $detailPurchase->status === 'received' ? 'Recibido' : 'Pendiente' }}</span>
                            <small>{{ $detailPurchase->issued_at?->format('d/m/Y H:i') }}</small>
                        </div>
                        <div class="inventory-ticket-person"><span><i class="bx bx-user"></i></span><div><small>Preparó</small><strong>{{ $detailPurchase->requester?->name ?: 'Sin asignar' }}</strong></div></div>
                        <div class="inventory-detail-lines">
                            <div class="inventory-detail-lines-head"><strong>Insumos solicitados</strong><span>{{ $detailPurchase->items->count() }}</span></div>
                            @foreach($detailPurchase->items as $line)
                                @php
                                    $detailUnit = $units[$line->unit]['short'] ?? $line->unit;
                                    $detailQuantity = rtrim(rtrim(number_format((float) $line->requested_quantity, 3, '.', ''), '0'), '.');
                                @endphp
                                <article>
                                    <span>{{ $loop->iteration }}</span>
                                    <div><strong>{{ $line->item_name }}</strong><small>{{ $line->notes ?: 'Sin indicaciones' }}</small></div>
                                    <b>{{ $detailQuantity }} {{ $detailUnit }}</b>
                                </article>
                            @endforeach
                        </div>
                        @if($detailPurchase->notes)<div class="inventory-detail-note"><small>Indicaciones generales</small><p>{{ $detailPurchase->notes }}</p></div>@endif
                    </section>

                    @if($canPrintPurchase)
                        <section class="inventory-ticket-paper-preview" x-data="{ frameLoading: true }">
                            <div class="inventory-ticket-preview-label"><span><i class="bx bx-receipt"></i> Vista exacta del Ticket Maker</span><small>La impresión usará este diseño.</small></div>
                            <div class="inventory-ticket-frame">
                                <div class="inventory-ticket-frame-loader" x-show="frameLoading" role="status"><i class="bx bx-loader-alt"></i><span>Preparando ticket…</span></div>
                                <iframe src="{{ route('print.inventory-purchase', $detailPurchase) }}" title="Vista previa del ticket {{ $detailPurchase->folio }}" @load="frameLoading = false"></iframe>
                            </div>
                        </section>
                    @endif
                </div>
                @if($showDeletePurchaseConfirm)
                    <div class="inventory-delete-confirm" role="alert">
                        <span><i class="bx bx-error-circle"></i></span>
                        <div><strong>¿Eliminar esta lista de compra?</strong><p>El folio y sus partidas desaparecerán. Esta acción no se puede deshacer.</p></div>
                        <button type="button" class="btn btn-outline-secondary" wire:click="$set('showDeletePurchaseConfirm', false)">Conservar</button>
                        <button type="button" class="btn btn-danger inventory-action" wire:click="deletePurchase" wire:loading.class="is-loading" wire:loading.attr="disabled" wire:target="deletePurchase"><span>Eliminar</span><b class="inventory-button-loader"></b></button>
                    </div>
                @endif
                <footer class="inventory-ticket-actions">
                    @if($detailPurchase->status === 'pending')
                        @can('eliminar compras inventario')
                            <button type="button" class="btn btn-outline-danger" wire:click="askDeletePurchase" @disabled($showDeletePurchaseConfirm)><i class="bx bx-trash"></i> Eliminar</button>
                        @endcan
                        @can('editar compras inventario')
                            <button type="button" class="btn btn-outline-primary inventory-action" wire:click="editPurchase({{ $detailPurchase->id }})" wire:loading.class="is-loading" wire:loading.attr="disabled" wire:target="editPurchase({{ $detailPurchase->id }})"><i class="bx bx-edit-alt"></i><span>Editar lista</span><b class="inventory-button-loader"></b></button>
                        @endcan
                    @endif
                    <span class="inventory-ticket-actions-spacer"></span>
                    <button type="button" class="btn btn-outline-secondary" wire:click="closePurchaseDetail">Cerrar</button>
                    @if($canPrintPurchase)
                        <a class="btn btn-primary" href="{{ route('print.inventory-purchase', $detailPurchase) }}?autoprint=1" target="_blank" rel="noopener"><i class="bx bx-printer"></i> {{ $detailPurchase->status === 'received' ? 'Reimprimir' : 'Imprimir' }}</a>
                    @endif
                </footer>
            </section>
        </div>
    @endif

    @if($showReceptionModal)
        <div class="inventory-modal-backdrop" wire:click.self="closeReceptionModal">
            <section class="inventory-modal is-large" role="dialog" aria-modal="true" aria-labelledby="inventory-reception-title">
                <header><div><span>Entrada por compra</span><h2 id="inventory-reception-title">Recepcionar insumos</h2></div><button type="button" wire:click="closeReceptionModal" aria-label="Cerrar"><i class="bx bx-x"></i></button></header>
                <div class="inventory-modal-body">
                    @if(!$receptionPurchaseId)
                        <div class="inventory-folio-lookup">
                            <span><i class="bx bx-barcode-reader"></i></span>
                            <div><h3>Ingresa el folio del ticket</h3><p>Buscaremos la lista pendiente para corroborar cada cantidad.</p></div>
                            <form wire:submit="lookupReception">
                                <label><span>Folio de compra</span><input class="form-control" type="text" wire:model="receptionFolio" maxlength="40" placeholder="CMP-2607-000001" autofocus>@error('receptionFolio')<small class="inventory-error">{{ $message }}</small>@enderror</label>
                                <button type="submit" class="btn btn-primary inventory-action" wire:loading.class="is-loading" wire:loading.attr="disabled" wire:target="lookupReception"><span>Buscar folio</span><b class="inventory-button-loader"></b></button>
                            </form>
                        </div>
                    @elseif($this->receptionPurchase)
                        <form wire:submit="confirmReception">
                            <div class="inventory-reception-head">
                                <div><span>Folio</span><strong>{{ $this->receptionPurchase->folio }}</strong><small>Preparó {{ $this->receptionPurchase->requester?->name }} · {{ $this->receptionPurchase->issued_at?->format('d/m/Y H:i') }}</small></div>
                                <span class="inventory-status is-pending"><i class="bx bx-time-five"></i>Pendiente</span>
                            </div>
                            <div class="inventory-reception-help"><i class="bx bx-check-shield"></i><p>Confirma lo recibido. Si una cantidad cambió, ajústala y explica la diferencia; al guardar se sumará directamente a la existencia.</p></div>
                            <section class="inventory-reception-lines">
                                @foreach($this->receptionPurchase->items as $line)
                                    @php $unit = $units[$line->unit]['short'] ?? $line->unit; @endphp
                                    <article wire:key="reception-line-{{ $line->id }}">
                                        <div><strong>{{ $line->item_name }}</strong><small>Se solicitaron {{ rtrim(rtrim(number_format((float) $line->requested_quantity, 3, '.', ''), '0'), '.') }} {{ $unit }}{{ $line->notes ? ' · '.$line->notes : '' }}</small></div>
                                        <label><span>Cantidad recibida</span><div class="inventory-input-suffix"><input class="form-control" type="number" inputmode="decimal" min="0" step="0.001" wire:model="receptionQuantities.{{ $line->id }}"><b>{{ $unit }}</b></div>@error('receptionQuantities.'.$line->id)<small class="inventory-error">{{ $message }}</small>@enderror</label>
                                        <label><span>Nota de ajuste</span><input class="form-control" type="text" maxlength="255" wire:model="receptionNotes.{{ $line->id }}" placeholder="Solo si hubo diferencia">@error('receptionNotes.'.$line->id)<small class="inventory-error">{{ $message }}</small>@enderror</label>
                                    </article>
                                @endforeach
                            </section>
                            <label class="inventory-field"><span>Notas generales de recepción</span><textarea class="form-control" rows="3" maxlength="1000" wire:model="receptionGeneralNotes" placeholder="Observaciones del proveedor o estado de los insumos"></textarea>@error('receptionGeneralNotes')<small class="inventory-error">{{ $message }}</small>@enderror</label>
                            <footer><button type="button" class="btn btn-outline-secondary" wire:click="closeReceptionModal">Cancelar</button><button type="submit" class="btn btn-primary inventory-action" wire:loading.class="is-loading" wire:loading.attr="disabled" wire:target="confirmReception"><i class="bx bx-check-shield"></i><span>Confirmar y actualizar inventario</span><b class="inventory-button-loader"></b></button></footer>
                        </form>
                    @endif
                </div>
            </section>
        </div>
    @endif

</div>
