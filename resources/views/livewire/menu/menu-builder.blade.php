<div class="app-page menu-builder-page" x-data="{ toasts: [] }" x-on:notify.window="
    toasts.push({ id: Date.now(), type: $event.detail.type, message: $event.detail.message });
    setTimeout(() => toasts.shift(), 3500);
">

{{-- ══════════════════════════════════════════════════════════════ Page header --}}
<header class="app-page-header">
    <div class="app-page-heading">
        <span class="app-page-icon" aria-hidden="true"><i class="bx bx-restaurant"></i></span>
        <div>
            <div class="app-eyebrow">Configuración · Catálogo</div>
            <h1 class="app-page-title">Constructor de menú</h1>
            <p class="app-page-subtitle">Gestiona productos, categorías, complementos, ingredientes y áreas de impresión.</p>
        </div>
    </div>
    <div class="app-page-actions">
        <span class="app-count-pill"><i class="bx bx-customize" aria-hidden="true"></i>5 módulos</span>
    </div>
</header>

{{-- ══════════════════════════════════════════════════════════════════ Nav tabs --}}
<ul class="nav nav-tabs app-tabs" role="tablist" aria-label="Secciones del constructor de menú">
    <li class="nav-item">
        <button class="nav-link {{ $tab === 'products' ? 'active' : '' }}" wire:click="$set('tab','products')" type="button" role="tab" aria-selected="{{ $tab === 'products' ? 'true' : 'false' }}">
            <i class="bx bx-dish me-1"></i> Productos
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link {{ $tab === 'categories' ? 'active' : '' }}" wire:click="$set('tab','categories')" type="button" role="tab" aria-selected="{{ $tab === 'categories' ? 'true' : 'false' }}">
            <i class="bx bx-category me-1"></i> Categorías
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link {{ $tab === 'addons' ? 'active' : '' }}" wire:click="$set('tab','addons')" type="button" role="tab" aria-selected="{{ $tab === 'addons' ? 'true' : 'false' }}">
            <i class="bx bx-plus-circle me-1"></i> Complementos
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link {{ $tab === 'ingredients' ? 'active' : '' }}" wire:click="$set('tab','ingredients')" type="button" role="tab" aria-selected="{{ $tab === 'ingredients' ? 'true' : 'false' }}">
            <i class="bx bx-spa me-1"></i> Ingredientes
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link {{ $tab === 'areas' ? 'active' : '' }}" wire:click="$set('tab','areas')" type="button" role="tab" aria-selected="{{ $tab === 'areas' ? 'true' : 'false' }}">
            <i class="bx bx-printer me-1"></i> Áreas de Impresión
        </button>
    </li>
</ul>

{{-- ══════════════════════════════════════════════════════════════ TAB: Products --}}
@if($tab === 'products')
<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="input-group input-group-sm menu-filter-search" >
                <span class="input-group-text"><i class="bx bx-search"></i></span>
                <input wire:model.live.debounce.400ms="productSearch" type="text" class="form-control" placeholder="Buscar producto…">
            </div>
            <select wire:model.live="productCategoryFilter" class="form-select form-select-sm menu-filter-select" >
                <option value="">Todas las categorías</option>
                @foreach($this->allCategories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        @can('crear platos')
        <button class="btn btn-primary btn-sm" wire:click="openProductModal()">
            <i class="bx bx-plus me-1"></i> Nuevo producto
        </button>
        @endcan
    </div>

    <div class="card-body p-0">
        @if($this->products->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bx bx-dish menu-empty-icon" ></i>
                <p class="mt-2">No hay productos aún.</p>
            </div>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th  class="menu-image-column"></th>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th>Precio</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($this->products as $product)
                <tr>
                    <td>
                        @if($product->image)
                            <img src="{{ asset('storage/'.$product->image) }}"
                                 class="rounded menu-object-cover" width="40" height="40"
                                 onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
                            <div  class="menu-media-placeholder menu-media-40 menu-fallback-hidden" hidden>
                                <i class="bx bx-image menu-placeholder-icon menu-icon-12" ></i>
                            </div>
                        @else
                            <div  class="menu-media-placeholder menu-media-40">
                                <i class="bx bx-image menu-placeholder-icon menu-icon-12" ></i>
                            </div>
                        @endif
                    </td>
                    <td>
                        <div class="fw-semibold">{{ $product->name }}</div>
                        @if($product->is_customizable)
                            <span class="badge bg-label-info menu-text-xxs" >Personalizable</span>
                        @endif
                    </td>
                    <td>
                        @if($product->category)
                            <span class="badge rounded-pill menu-dynamic-badge" >
                                {{ $product->category->name }}
                            </span>
                        @else
                            <span class="text-muted small">Sin categoría</span>
                        @endif
                    </td>
                    <td><span class="fw-semibold">${{ number_format($product->price, 2) }}</span></td>
                    <td>
                        @if($product->is_active)
                            <span class="badge bg-label-success">Activo</span>
                        @else
                            <span class="badge bg-label-secondary">Inactivo</span>
                        @endif
                    </td>
                    <td class="text-end">
                        @can('editar platos')
                        <button class="btn btn-sm btn-icon btn-outline-success" title="Ingredientes" wire:click="openProductIngredientsModal({{ $product->id }})">
                            <i class="bx bx-spa"></i>
                        </button>
                        <button class="btn btn-sm btn-icon btn-outline-primary" title="Editar" wire:click="openProductModal({{ $product->id }})">
                            <i class="bx bx-edit-alt"></i>
                        </button>
                        @endcan
                        @can('eliminar platos')
                        <button class="btn btn-sm btn-icon btn-outline-danger" title="Eliminar" wire:click="confirmDeleteProduct({{ $product->id }})">
                            <i class="bx bx-trash"></i>
                        </button>
                        @endcan
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="menu-builder-pagination px-4 py-3">
            {{ $this->products->links('pagination::bootstrap-5') }}
        </div>
        @endif
    </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════════════════ TAB: Categories --}}
@if($tab === 'categories')
<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="input-group input-group-sm menu-filter-search" >
            <span class="input-group-text"><i class="bx bx-search"></i></span>
            <input wire:model.live.debounce.400ms="categorySearch" type="text" class="form-control" placeholder="Buscar categoría…">
        </div>
        @can('gestionar categorias')
        <button class="btn btn-primary btn-sm" wire:click="openCategoryModal()">
            <i class="bx bx-plus me-1"></i> Nueva categoría
        </button>
        @endcan
    </div>
    <div class="card-body">
        @if($this->categories->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bx bx-category menu-empty-icon" ></i>
                <p class="mt-2">No hay categorías aún.</p>
            </div>
        @else
        <div class="row g-3">
        @foreach($this->categories as $cat)
        <div class="col-sm-6 col-xl-4">
            <div class="card border-0 h-100 menu-accent-card" >
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="flex-shrink-0 rounded d-flex align-items-center justify-content-center menu-color-icon" >
                        <i class="bx {{ $cat->icon }} menu-color-icon-glyph"></i>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold text-truncate">{{ $cat->name }}</div>
                        <div class="small text-muted">
                            {{ $cat->printArea?->name ?? 'Sin área' }}
                            @if(!$cat->is_active) <span class="badge bg-label-secondary ms-1">Inactiva</span> @endif
                        </div>
                    </div>
                    @can('gestionar categorias')
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-icon btn-outline-primary" wire:click="openCategoryModal({{ $cat->id }})">
                            <i class="bx bx-edit-alt"></i>
                        </button>
                        <button class="btn btn-sm btn-icon btn-outline-danger" wire:click="confirmDeleteCategory({{ $cat->id }})">
                            <i class="bx bx-trash"></i>
                        </button>
                    </div>
                    @endcan
                </div>
            </div>
        </div>
        @endforeach
        </div>
        @endif
    </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════════════════ TAB: Addons --}}
@if($tab === 'addons')
<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0">Grupos de complementos</h6>
        @can('gestionar complementos')
        <button class="btn btn-primary btn-sm" wire:click="openGroupModal()">
            <i class="bx bx-plus me-1"></i> Nuevo grupo
        </button>
        @endcan
    </div>
    <div class="card-body">
        @if($this->addonGroups->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bx bx-plus-circle menu-empty-icon" ></i>
                <p class="mt-2">No hay grupos de complementos aún.</p>
            </div>
        @else
        <div class="row g-3">
        @foreach($this->addonGroups as $group)
        <div class="col-sm-6 col-xl-4">
            <div class="card border h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between mb-2">
                        <div>
                            <div class="fw-semibold">{{ $group->name }}</div>
                            <div class="small text-muted">{{ $group->addons_count }} complemento(s)</div>
                        </div>
                        <div class="d-flex gap-1">
                            @if($group->is_required)
                                <span class="badge bg-label-danger">Obligatorio</span>
                            @else
                                <span class="badge bg-label-secondary">Opcional</span>
                            @endif
                        </div>
                    </div>
                    <div class="small text-muted mb-3">
                        Sel. {{ $group->min_selections }}–{{ $group->max_selections }}
                    </div>
                    @can('gestionar complementos')
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-secondary flex-grow-1" wire:click="openAddonsModal({{ $group->id }})">
                            <i class="bx bx-list-ul me-1"></i> Complementos
                        </button>
                        <button class="btn btn-sm btn-icon btn-outline-primary" wire:click="openGroupModal({{ $group->id }})">
                            <i class="bx bx-edit-alt"></i>
                        </button>
                        <button class="btn btn-sm btn-icon btn-outline-danger" wire:click="confirmDeleteGroup({{ $group->id }})">
                            <i class="bx bx-trash"></i>
                        </button>
                    </div>
                    @endcan
                </div>
            </div>
        </div>
        @endforeach
        </div>
        @endif
    </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════════════ TAB: Ingredients --}}
@if($tab === 'ingredients')
<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0">Catálogo de ingredientes</h6>
        @can('gestionar complementos')
        <button type="button" class="btn btn-primary btn-sm" wire:click="openIngredientModal()">
            <i class="bx bx-plus me-1" aria-hidden="true"></i> Nuevo ingrediente
        </button>
        @endcan
    </div>
    <div class="card-body">
        @if($this->ingredients->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bx bx-spa menu-empty-icon" aria-hidden="true"></i>
                <p class="mt-2">No hay ingredientes en el catálogo aún.</p>
            </div>
        @else
        <div class="row g-3">
        @foreach($this->ingredients as $ingredient)
        <div class="col-sm-6 col-xl-4">
            <div class="card border-0 h-100 menu-accent-card">
                <div class="card-body d-flex align-items-center gap-3">
                    @if($ingredient->image)
                        <img src="{{ asset('storage/'.$ingredient->image) }}"
                             alt="Imagen de {{ $ingredient->name }}"
                             class="rounded flex-shrink-0 menu-media menu-media-52"
                             width="52" height="52" loading="lazy"
                             onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
                        <div class="menu-media-placeholder menu-media-52" hidden>
                            <i class="bx bx-spa menu-placeholder-icon menu-icon-14" aria-hidden="true"></i>
                        </div>
                    @else
                        <div class="menu-media-placeholder menu-media-52">
                            <i class="bx bx-spa menu-placeholder-icon menu-icon-14" aria-hidden="true"></i>
                        </div>
                    @endif
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold text-truncate">{{ $ingredient->name }}</div>
                        <div class="small text-muted">
                            {{ $ingredient->products_count }} producto(s)
                            @if(!$ingredient->is_active)
                                <span class="badge bg-label-secondary ms-1">Inactivo</span>
                            @endif
                        </div>
                        @if($ingredient->extra_price > 0)
                            <div class="small text-success fw-semibold">+${{ number_format($ingredient->extra_price, 2) }}</div>
                        @else
                            <div class="small text-muted">Sin costo extra</div>
                        @endif
                    </div>
                    @can('gestionar complementos')
                    <div class="d-flex gap-1 flex-shrink-0">
                        <button type="button" class="btn btn-sm btn-icon btn-outline-primary" title="Editar {{ $ingredient->name }}" aria-label="Editar {{ $ingredient->name }}" wire:click="openIngredientModal({{ $ingredient->id }})">
                            <i class="bx bx-edit-alt" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-icon btn-outline-danger" title="Eliminar {{ $ingredient->name }}" aria-label="Eliminar {{ $ingredient->name }}" wire:click="confirmDeleteIngredient({{ $ingredient->id }})">
                            <i class="bx bx-trash" aria-hidden="true"></i>
                        </button>
                    </div>
                    @endcan
                </div>
            </div>
        </div>
        @endforeach
        </div>
        @endif
    </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════════════════ TAB: Areas --}}
@if($tab === 'areas')
<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0">Áreas de impresión</h6>
        @can('gestionar areas impresion')
        <button class="btn btn-primary btn-sm" wire:click="openAreaModal()">
            <i class="bx bx-plus me-1"></i> Nueva área
        </button>
        @endcan
    </div>
    <div class="card-body">
        @if($this->printAreas->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bx bx-printer menu-empty-icon" ></i>
                <p class="mt-2">No hay áreas de impresión aún.</p>
            </div>
        @else
        <div class="row g-3">
        @foreach($this->printAreas as $area)
        <div class="col-sm-6 col-xl-4">
            <div class="card border-0 h-100 menu-accent-card" >
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="flex-shrink-0 rounded d-flex align-items-center justify-content-center menu-color-icon" >
                        <i class="bx bx-printer menu-color-icon-glyph" ></i>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold text-truncate">{{ $area->name }}</div>
                        <div class="small text-muted">
                            {{ $area->categories_count }} categoría(s)
                            @if(!$area->is_active) <span class="badge bg-label-secondary ms-1">Inactiva</span> @endif
                        </div>
                    </div>
                    @can('gestionar areas impresion')
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-icon btn-outline-primary" wire:click="openAreaModal({{ $area->id }})">
                            <i class="bx bx-edit-alt"></i>
                        </button>
                        <button class="btn btn-sm btn-icon btn-outline-danger" wire:click="confirmDeleteArea({{ $area->id }})">
                            <i class="bx bx-trash"></i>
                        </button>
                    </div>
                    @endcan
                </div>
            </div>
        </div>
        @endforeach
        </div>
        @endif
    </div>
</div>
@endif

{{-- ══════════════════════════════════ MODAL: Product Form ══════════════════════ --}}
@if($showProductModal)
<div class="modal-backdrop fade show app-modal-backdrop" wire:click="$set('showProductModal',false)"></div>
<div class="modal fade show d-block app-modal-layer" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="product-form-title">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="product-form-title">
                    <i class="bx {{ $editProductId ? 'bx-edit-alt' : 'bx-plus-circle' }} me-2 text-primary"></i>
                    {{ $editProductId ? 'Editar producto' : 'Nuevo producto' }}
                </h5>
                <button type="button" class="btn-close" wire:click="$set('showProductModal',false)"></button>
            </div>
            <div class="modal-body">
                <div class="row g-4">
                    {{-- Left column --}}
                    <div class="col-md-7">
                        <div class="mb-3">
                            <label class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input wire:model="pName" type="text" class="form-control @error('pName') is-invalid @enderror" placeholder="Ej. Hamburguesa Clásica">
                            @error('pName') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea wire:model="pDescription" class="form-control" rows="3" placeholder="Descripción del producto…"></textarea>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label">Categoría</label>
                                <select wire:model="pCategoryId" class="form-select @error('pCategoryId') is-invalid @enderror">
                                    <option value="">Sin categoría</option>
                                    @foreach($this->allCategories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Precio <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input wire:model="pPrice" type="number" min="0" step="0.01" class="form-control @error('pPrice') is-invalid @enderror">
                                </div>
                                @error('pPrice') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="product-image">Imagen del producto</label>
                            <div class="menu-image-uploader">
                                <div class="menu-image-uploader__preview">
                                    <div class="menu-image-uploader__skeleton" wire:loading.flex wire:target="pImage" role="status"><span class="visually-hidden">Procesando imagen</span></div>
                                    <div wire:loading.remove wire:target="pImage" class="menu-image-uploader__content">
                                        @if($pImage)
                                            <img src="{{ $pImage->temporaryUrl() }}" alt="Vista previa de {{ $pName ?: 'nuevo producto' }}" width="320" height="240">
                                        @elseif($pCurrentImage)
                                            <img src="{{ asset('storage/'.$pCurrentImage) }}" alt="Imagen actual de {{ $pName }}" width="320" height="240">
                                        @else
                                            <span><i class="bx bx-image-add"></i><small>Sin imagen</small></span>
                                        @endif
                                    </div>
                                </div>
                                <div class="menu-image-uploader__controls">
                                    <label class="menu-image-uploader__button" for="product-image"><i class="bx bx-upload"></i><span>{{ $pImage || $pCurrentImage ? 'Cambiar imagen' : 'Seleccionar imagen' }}</span></label>
                                    <input id="product-image" wire:model.live="pImage" type="file" class="@error('pImage') is-invalid @enderror" accept="image/jpeg,image/png,image/gif,image/webp">
                                    <small>JPG, PNG, GIF o WebP · máximo 6 MB. Se optimizará automáticamente.</small>
                                    <span wire:loading.flex wire:target="pImage" class="menu-image-uploader__status"><i class="bx bx-loader-alt bx-spin"></i>Preparando vista previa…</span>
                                    @if($pImage)<span class="menu-upload-success"><i class="bx bx-check-circle"></i>Imagen lista para guardar.</span>@endif
                                </div>
                            </div>
                            @error('pImage') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input wire:model.live="pIsCustomizable" class="form-check-input" type="checkbox" id="pIsCustomizable">
                            <label class="form-check-label" for="pIsCustomizable">Producto personalizable (con complementos)</label>
                        </div>
                        @if($pIsCustomizable)
                        <div class="mb-3">
                            <label class="form-label">Máximo de complementos (opcional)</label>
                            <input wire:model="pMaxAddons" type="number" min="1" class="form-control @error('pMaxAddons') is-invalid @enderror" placeholder="Sin límite">
                            @error('pMaxAddons') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        @endif
                        <div class="form-check form-switch">
                            <input wire:model="pIsActive" class="form-check-input" type="checkbox" id="pIsActive">
                            <label class="form-check-label" for="pIsActive">Producto activo</label>
                        </div>
                    </div>
                    {{-- Right column: addon groups --}}
                    <div class="col-md-5">
                        <label class="form-label">Grupos de complementos</label>
                        <div class="border rounded p-3 menu-scroll-lg" >
                            @foreach($this->allAddonGroups as $group)
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox"
                                    wire:model="pAddonGroupIds"
                                    value="{{ $group->id }}"
                                    id="pag_{{ $group->id }}">
                                <label class="form-check-label" for="pag_{{ $group->id }}">
                                    <span class="fw-semibold">{{ $group->name }}</span>
                                    <span class="text-muted small ms-1">({{ $group->addons_count }})</span>
                                    @if($group->is_required)
                                        <span class="badge bg-label-danger ms-1 menu-text-micro" >Obligatorio</span>
                                    @endif
                                </label>
                            </div>
                            @endforeach
                            @if($this->allAddonGroups->isEmpty())
                                <p class="text-muted small mb-0">No hay grupos creados aún.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" wire:click="$set('showProductModal',false)">Cancelar</button>
                <button type="button" class="btn btn-primary" wire:click="saveProduct" wire:loading.attr="disabled" wire:target="saveProduct,pImage">
                    <span wire:loading.remove wire:target="saveProduct">
                        <i class="bx bx-check me-1"></i> {{ $editProductId ? 'Actualizar' : 'Crear producto' }}
                    </span>
                    <span wire:loading wire:target="saveProduct"  class="menu-loading">
                        <span class="spinner-border spinner-border-sm" role="status"></span> Guardando…
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ══════════════════════════════════ MODAL: Category Form ════════════════════ --}}
@if($showCategoryModal)
@php
$iconList = [
    // Comida y bebida
    'bx-food-menu','bx-food-tag','bx-restaurant','bx-dish','bx-coffee','bx-coffee-togo',
    'bx-cake','bx-beer','bx-wine','bx-cookie',
    // Generales útiles
    'bx-store','bx-store-alt','bx-cart','bx-cart-alt','bx-package','bx-box',
    'bx-tag','bx-tag-alt','bx-star','bx-heart','bx-like',
    'bx-category','bx-category-alt','bx-grid-alt','bx-list-ul',
    'bx-recycle','bx-spa','bx-water','bx-sun','bx-moon','bx-cut','bx-bell','bx-time',
];
@endphp
<div class="modal-backdrop fade show app-modal-backdrop" wire:click="$set('showCategoryModal',false)"></div>
<div class="modal fade show d-block app-modal-layer" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered menu-modal-540" >
        <div class="modal-content" x-data="{ customIcon: false }">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bx {{ $editCategoryId ? 'bx-edit-alt' : 'bx-plus-circle' }} me-2 text-primary"></i>
                    {{ $editCategoryId ? 'Editar categoría' : 'Nueva categoría' }}
                </h5>
                <button type="button" class="btn-close" wire:click="$set('showCategoryModal',false)"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nombre <span class="text-danger">*</span></label>
                    <input wire:model="cName" type="text" class="form-control @error('cName') is-invalid @enderror" placeholder="Ej. Entradas">
                    @error('cName') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">Descripción</label>
                    <input wire:model="cDescription" type="text" class="form-control" placeholder="Descripción opcional">
                </div>

                {{-- Icon picker --}}
                <div class="mb-3">
                    <label class="form-label">Ícono</label>
                    {{-- Preview + toggle --}}
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="rounded d-flex align-items-center justify-content-center menu-color-icon" >
                            <i class="bx {{ $cIcon }} menu-color-icon-glyph" ></i>
                        </div>
                        <span class="text-muted small">{{ $cIcon }}</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" x-on:click="customIcon = !customIcon">
                            <i class="bx bx-pencil me-1"></i>
                            <span x-text="customIcon ? 'Ocultar' : 'Código manual'"></span>
                        </button>
                    </div>
                    {{-- Manual input --}}
                    <div x-show="customIcon" x-cloak class="mb-2">
                        <input wire:model.live.debounce.350ms="cIcon" type="text" class="form-control form-control-sm" placeholder="bx-food-menu">
                        <div class="form-text">Clase de <a href="https://boxicons.com" target="_blank">Boxicons</a> sin el prefijo "bx ".</div>
                    </div>
                    {{-- Icon grid --}}
                    <div class="border rounded p-2 menu-scroll-sm" >
                        <div class="d-flex flex-wrap gap-1">
                            @foreach($iconList as $ic)
                            <button type="button" wire:click="$set('cIcon','{{ $ic }}')" title="{{ $ic }}" class="btn btn-sm p-0 d-flex align-items-center justify-content-center rounded {{ $cIcon === $ic ? 'btn-primary' : 'btn-outline-secondary' }} menu-icon-choice" >
                                <i class="bx {{ $ic }} menu-icon-12" ></i>
                            </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Color</label>
                    <div class="input-group">
                        <span class="input-group-text p-1">
                            <input wire:model.change="cColor" type="color" class="form-control form-control-color border-0 menu-color-input" title="Color">
                        </span>
                        <input wire:model.blur="cColor" type="text" class="form-control" placeholder="#696cff">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Área de impresión</label>
                    <select wire:model="cPrintAreaId" class="form-select">
                        <option value="">Sin área asignada</option>
                        @foreach($this->allPrintAreas as $area)
                            <option value="{{ $area->id }}">{{ $area->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-check form-switch">
                    <input wire:model="cIsActive" class="form-check-input" type="checkbox" id="cIsActive">
                    <label class="form-check-label" for="cIsActive">Categoría activa</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" wire:click="$set('showCategoryModal',false)">Cancelar</button>
                <button type="button" class="btn btn-primary" wire:click="saveCategory">
                    <span wire:loading.remove wire:target="saveCategory"><i class="bx bx-check me-1"></i> {{ $editCategoryId ? 'Actualizar' : 'Crear' }}</span>
                    <span wire:loading wire:target="saveCategory"  class="menu-loading"><span class="spinner-border spinner-border-sm"></span> Guardando…</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ══════════════════════════════════ MODAL: Addon Group Form ═════════════════ --}}
@if($showGroupModal)
<div class="modal-backdrop fade show app-modal-backdrop" wire:click="$set('showGroupModal',false)"></div>
<div class="modal fade show d-block app-modal-layer" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered menu-modal-480" >
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bx {{ $editGroupId ? 'bx-edit-alt' : 'bx-plus-circle' }} me-2 text-primary"></i>
                    {{ $editGroupId ? 'Editar grupo' : 'Nuevo grupo de complementos' }}
                </h5>
                <button type="button" class="btn-close" wire:click="$set('showGroupModal',false)"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nombre <span class="text-danger">*</span></label>
                    <input wire:model="gName" type="text" class="form-control @error('gName') is-invalid @enderror" placeholder="Ej. Salsas, Tamaño, Extras…">
                    @error('gName') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">Descripción</label>
                    <input wire:model="gDescription" type="text" class="form-control" placeholder="Ej. Elige tu salsa favorita">
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label">Mín. selecciones</label>
                        <input wire:model="gMinSelections" type="number" min="0" max="20" class="form-control @error('gMinSelections') is-invalid @enderror">
                        <div class="form-text">Si es obligatorio, el mínimo efectivo será 1.</div>
                        @error('gMinSelections') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-6">
                        <label class="form-label">Máx. selecciones</label>
                        <input wire:model="gMaxSelections" type="number" min="1" max="20" class="form-control @error('gMaxSelections') is-invalid @enderror">
                        <div class="form-text">Usa 1 para selección única.</div>
                        @error('gMaxSelections') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="d-flex gap-4">
                    <div class="form-check form-switch">
                        <input wire:model="gIsRequired" class="form-check-input" type="checkbox" id="gIsRequired">
                        <label class="form-check-label" for="gIsRequired">Obligatorio</label>
                    </div>
                    <div class="form-check form-switch">
                        <input wire:model="gIsActive" class="form-check-input" type="checkbox" id="gIsActive">
                        <label class="form-check-label" for="gIsActive">Activo</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" wire:click="$set('showGroupModal',false)">Cancelar</button>
                <button type="button" class="btn btn-primary" wire:click="saveGroup">
                    <span wire:loading.remove wire:target="saveGroup"><i class="bx bx-check me-1"></i> {{ $editGroupId ? 'Actualizar' : 'Crear' }}</span>
                    <span wire:loading wire:target="saveGroup"  class="menu-loading"><span class="spinner-border spinner-border-sm"></span> Guardando…</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ══════════════════════════════════ MODAL: Addons list + form ═══════════════ --}}
@if($showAddonsModal && $this->activeGroup)
<div class="modal-backdrop fade show app-modal-backdrop" wire:click="$set('showAddonsModal',false)"></div>
<div class="modal fade show d-block app-modal-layer" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bx bx-list-ul me-2 text-primary"></i>
                    Complementos: <span class="text-primary">{{ $this->activeGroup->name }}</span>
                </h5>
                <button type="button" class="btn-close" wire:click="$set('showAddonsModal',false)"></button>
            </div>
            <div class="modal-body">
                {{-- Addon Form --}}
                @if($showAddonForm)
                <div class="card border-primary border-opacity-25 mb-3 menu-soft-panel" >
                    <div class="card-body">
                        <h6 class="mb-3 text-primary">
                            <i class="bx {{ $editAddonId ? 'bx-edit-alt' : 'bx-plus-circle' }} me-1"></i>
                            {{ $editAddonId ? 'Editar complemento' : 'Nuevo complemento' }}
                        </h6>
                        <div class="row g-3">
                            <div class="col-sm-7">
                                <label class="form-label">Nombre <span class="text-danger">*</span></label>
                                <input wire:model="aName" type="text" class="form-control @error('aName') is-invalid @enderror" placeholder="Ej. Salsa BBQ">
                                @error('aName') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-sm-5">
                                <label class="form-label">Precio extra</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input wire:model="aExtraPrice" type="number" min="0" step="0.01" class="form-control @error('aExtraPrice') is-invalid @enderror">
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Descripción</label>
                                <input wire:model="aDescription" type="text" class="form-control" placeholder="Descripción opcional">
                            </div>
                            {{-- Image upload + live preview --}}
                            <div class="col-12">
                                <label class="form-label">Imagen</label>
                                <div class="d-flex align-items-start gap-3 flex-wrap">
                                    {{-- Preview box --}}
                                    <div class="menu-upload-frame">
                                        <div class="menu-upload-frame__skeleton" wire:loading.flex wire:target="aImage" aria-label="Procesando imagen"></div>
                                        <span wire:loading.remove wire:target="aImage">
                                            @if($aImage)
                                                <img src="{{ $aImage->temporaryUrl() }}"
                                                     class="rounded shadow-sm menu-media menu-media-80">
                                            @elseif($aCurrentImage)
                                                <img src="{{ asset('storage/'.$aCurrentImage) }}" class="rounded shadow-sm menu-media menu-media-80">
                                            @else
                                                <span class="menu-media-placeholder menu-media-80"><i class="bx bx-image menu-placeholder-icon menu-icon-20"></i></span>
                                            @endif
                                        </span>
                                    </div>
                                    {{-- File input --}}
                                    <div class="flex-grow-1">
                                        <input wire:model.live="aImage" type="file"
                                               class="form-control @error('aImage') is-invalid @enderror"
                                               accept="image/jpeg,image/png,image/gif,image/webp">
                                        @error('aImage') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        <div class="form-text">JPG, PNG, GIF o WebP de hasta 6 MB.</div>
                                        @if($aImage)
                                            <div class="small text-muted mt-1">
                                                <i class="bx bx-check-circle text-success me-1"></i>
                                                Se guardará como <strong>.webp</strong>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input wire:model="aIsActive" class="form-check-input" type="checkbox" id="aIsActive">
                                    <label class="form-check-label" for="aIsActive">Complemento activo</label>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mt-3">
                            <button class="btn btn-sm btn-outline-secondary" wire:click="$set('showAddonForm',false)">
                                <i class="bx bx-x me-1"></i> Cancelar
                            </button>
                            <button type="button" class="btn btn-sm btn-primary" wire:click="saveAddon" wire:loading.attr="disabled" wire:target="saveAddon,aImage">
                                <span wire:loading.remove wire:target="saveAddon">
                                    <i class="bx bx-check me-1"></i> {{ $editAddonId ? 'Actualizar' : 'Agregar complemento' }}
                                </span>
                                <span wire:loading wire:target="saveAddon"  class="menu-loading">
                                    <span class="spinner-border spinner-border-sm"></span> Guardando…
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
                @else
                <button class="btn btn-sm btn-outline-primary mb-3" wire:click="openAddonForm()">
                    <i class="bx bx-plus me-1"></i> Agregar complemento
                </button>
                @endif

                {{-- Addons list --}}
                @if($this->activeGroup->addons->isEmpty())
                    <div class="text-center py-4 text-muted">
                        <i class="bx bx-plus-circle menu-icon-25" ></i>
                        <p class="mt-1 small">No hay complementos en este grupo.</p>
                    </div>
                @else
                <div class="row g-2">
                    @foreach($this->activeGroup->addons as $addon)
                    <div class="col-12">
                        <div class="d-flex align-items-center gap-3 p-2 rounded border">
                            {{-- Image thumbnail --}}
                            @if($addon->image)
                                <img src="{{ asset('storage/'.$addon->image) }}"
                                     class="rounded flex-shrink-0 shadow-sm menu-media menu-media-64"
                                     onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
                                <div  class="menu-media-placeholder menu-media-64 menu-fallback-hidden" hidden>
                                    <i class="bx bx-food-tag menu-placeholder-icon menu-icon-16" ></i>
                                </div>
                            @else
                                <div  class="menu-media-placeholder menu-media-64">
                                    <i class="bx bx-food-tag menu-placeholder-icon menu-icon-16" ></i>
                                </div>
                            @endif
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold">{{ $addon->name }}</div>
                                @if($addon->description)
                                    <div class="small text-muted text-truncate">{{ $addon->description }}</div>
                                @endif
                                @if($addon->extra_price > 0)
                                    <div class="small text-success fw-semibold">+${{ number_format($addon->extra_price, 2) }}</div>
                                @else
                                    <div class="small text-muted">Sin costo extra</div>
                                @endif
                            </div>
                            <div class="d-flex flex-column align-items-end gap-1">
                                @if(!$addon->is_active)
                                    <span class="badge bg-label-secondary menu-text-micro" >Inactivo</span>
                                @endif
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-icon btn-outline-primary"
                                            title="Editar"
                                            wire:click="openAddonForm({{ $addon->id }})">
                                        <i class="bx bx-edit-alt"></i>
                                    </button>
                                    <button class="btn btn-sm btn-icon btn-outline-danger"
                                            title="Eliminar"
                                            wire:click="confirmDeleteAddon({{ $addon->id }})">
                                        <i class="bx bx-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" wire:click="$set('showAddonsModal',false)">Cerrar</button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ══════════════════════════════════ MODAL: Ingredient Form ══════════════════ --}}
@if($showIngredientModal)
<div class="modal-backdrop fade show app-modal-backdrop" wire:click="$set('showIngredientModal',false)"></div>
<div class="modal fade show d-block app-modal-layer" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="ingredient-form-title">
    <div class="modal-dialog modal-dialog-centered menu-modal-500" >
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ingredient-form-title">
                    <i class="bx {{ $editIngredientId ? 'bx-edit-alt' : 'bx-plus-circle' }} me-2 text-primary"></i>
                    {{ $editIngredientId ? 'Editar ingrediente' : 'Nuevo ingrediente' }}
                </h5>
                <button type="button" class="btn-close" aria-label="Cerrar formulario de ingrediente" wire:click="$set('showIngredientModal',false)"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nombre <span class="text-danger">*</span></label>
                    <input wire:model="ingName" type="text" class="form-control @error('ingName') is-invalid @enderror" placeholder="Ej. Brócoli, Zanahoria…">
                    @error('ingName') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">Descripción</label>
                    <input wire:model="ingDescription" type="text" class="form-control" placeholder="Descripción opcional">
                </div>
                <div class="mb-3">
                    <label class="form-label">Costo adicional</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input wire:model="ingExtraPrice" type="number" min="0" step="0.01" class="form-control @error('ingExtraPrice') is-invalid @enderror" placeholder="0.00">
                    </div>
                    <div class="form-text">Usa 0 cuando esté incluido.</div>
                    @error('ingExtraPrice') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="menu-ingredient-upload">
                    <div class="menu-ingredient-upload__preview" wire:loading.class="is-loading" wire:target="ingImage">
                        <span class="menu-ingredient-upload__label">Vista previa</span>
                        <div class="menu-ingredient-upload__media">
                            @if($ingImage)
                                <img src="{{ $ingImage->temporaryUrl() }}" alt="Nueva imagen para {{ $ingName ?: 'el ingrediente' }}" width="320" height="200">
                            @elseif($ingCurrentImage && !$ingRemoveImage)
                                <img src="{{ asset('storage/'.$ingCurrentImage) }}" alt="Imagen actual de {{ $ingName }}" width="320" height="200" onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
                                <div class="menu-ingredient-upload__fallback" hidden><i class="bx bx-image-alt" aria-hidden="true"></i><span>No se pudo cargar la imagen actual</span></div>
                            @else
                                <div class="menu-ingredient-upload__fallback"><i class="bx bx-image-add" aria-hidden="true"></i><span>Selecciona una imagen</span></div>
                            @endif
                            <div class="menu-ingredient-upload__loading" wire:loading.flex wire:target="ingImage" aria-label="Procesando imagen">
                                <span class="spinner-border text-primary" aria-hidden="true"></span><strong>Preparando vista previa…</strong>
                            </div>
                        </div>
                    </div>
                    <div class="menu-ingredient-upload__controls">
                        <label class="form-label" for="ingImage">Imagen del ingrediente</label>
                        <input id="ingImage" wire:model.live="ingImage" type="file" class="form-control @error('ingImage') is-invalid @enderror" accept="image/jpeg,image/png,image/gif,image/webp">
                        @error('ingImage') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <p class="form-text">JPG, PNG, GIF o WebP de hasta 6 MB. Se optimiza automáticamente a WebP.</p>
                        @if($ingImage)
                            <div class="menu-upload-success" role="status"><i class="bx bx-check-circle" aria-hidden="true"></i><span>Nueva imagen lista para guardar.</span></div>
                        @elseif($ingCurrentImage)
                            <div class="form-check menu-remove-image">
                                <input wire:model.live="ingRemoveImage" class="form-check-input" type="checkbox" id="ingRemoveImage">
                                <label class="form-check-label" for="ingRemoveImage">Quitar la imagen actual al guardar</label>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="form-check form-switch">
                    <input wire:model="ingIsActive" class="form-check-input" type="checkbox" id="ingIsActive">
                    <label class="form-check-label" for="ingIsActive">Ingrediente activo</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" wire:click="$set('showIngredientModal',false)">Cancelar</button>
                <button type="button" class="btn btn-primary" wire:click="saveIngredient" wire:loading.attr="disabled" wire:target="saveIngredient,ingImage">
                    <span wire:loading.remove wire:target="saveIngredient"><i class="bx bx-check me-1"></i> {{ $editIngredientId ? 'Actualizar' : 'Crear' }}</span>
                    <span wire:loading wire:target="saveIngredient"  class="menu-loading"><span class="spinner-border spinner-border-sm"></span> Guardando…</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ════════════════════════════ MODAL: Product Ingredients Assignment ═════════ --}}
@if($showProductIngredientsModal && $this->piProduct)
<div class="modal-backdrop fade show app-modal-backdrop" wire:click="$set('showProductIngredientsModal',false)"></div>
<div class="modal fade show d-block app-modal-layer" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="product-ingredients-title">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="product-ingredients-title">
                    <i class="bx bx-spa me-2 text-success"></i>
                    Ingredientes: <span class="text-success">{{ $this->piProduct->name }}</span>
                </h5>
                <button type="button" class="btn-close" aria-label="Cerrar configuración de ingredientes" wire:click="$set('showProductIngredientsModal',false)"></button>
            </div>
            <div class="modal-body">
                {{-- Limits --}}
                <div class="row g-3 mb-4 pb-3 menu-section-divider">
                    <div class="col-6">
                        <label class="form-label">Mín. ingredientes requeridos</label>
                        <input wire:model="piMinIngredients" type="number" min="0" max="50"
                               class="form-control @error('piMinIngredients') is-invalid @enderror">
                        @error('piMinIngredients') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-6">
                        <label class="form-label">Máx. ingredientes permitidos <span class="text-danger">*</span></label>
                        <input wire:model="piMaxIngredients" type="number" min="1" max="50"
                               class="form-control @error('piMaxIngredients') is-invalid @enderror">
                        @error('piMaxIngredients') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">El cliente puede repetir el mismo ingrediente; el total no puede superar este límite.</div>
                    </div>
                </div>

                {{-- Ingredient selection --}}
                <label class="form-label fw-semibold mb-2">Ingredientes disponibles para este producto</label>
                @if($this->allIngredients->isEmpty())
                    <div class="text-center py-4 text-muted">
                        <i class="bx bx-spa menu-icon-20" ></i>
                        <p class="small mt-1">No hay ingredientes en el catálogo. Créalos en la pestaña <strong>Ingredientes</strong>.</p>
                    </div>
                @else
                <div class="row g-2 menu-scroll-lg">
                    @foreach($this->allIngredients as $ing)
                    <div class="col-sm-6">
                        <label class="d-flex align-items-center gap-3 p-2 rounded border cursor-pointer menu-clickable {{ in_array((string)$ing->id, $piIngredientIds) ? 'border-success bg-success bg-opacity-10' : '' }}" for="pi_{{ $ing->id }}">
                            <input class="form-check-input flex-shrink-0 mt-0"
                                   type="checkbox"
                                   wire:model="piIngredientIds"
                                   value="{{ $ing->id }}"
                                   id="pi_{{ $ing->id }}">
                            @if($ing->image)
                                <img src="{{ asset('storage/'.$ing->image) }}"
                                     class="rounded flex-shrink-0 menu-media menu-media-40"
                                     alt="Imagen de {{ $ing->name }}" width="40" height="40" loading="lazy"
                                     onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
                                <div class="menu-media-placeholder menu-media-40" hidden><i class="bx bx-spa menu-placeholder-icon menu-icon-11" aria-hidden="true"></i></div>
                            @else
                                <div class="menu-media-placeholder menu-media-40"><i class="bx bx-spa menu-placeholder-icon menu-icon-11" aria-hidden="true"></i></div>
                            @endif
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold small text-truncate">{{ $ing->name }}</div>
                                @if($ing->extra_price > 0)
                                    <div class="text-success app-text-xs">+${{ number_format($ing->extra_price, 2) }}</div>
                                @else
                                    <div class="text-muted app-text-xs">Sin costo extra</div>
                                @endif
                            </div>
                        </label>
                    </div>
                    @endforeach
                </div>
                <div class="mt-2 text-muted small">
                    <i class="bx bx-info-circle me-1"></i>
                    {{ count($piIngredientIds) }} ingrediente(s) seleccionado(s).
                </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" wire:click="$set('showProductIngredientsModal',false)">Cancelar</button>
                <button type="button" class="btn btn-success" wire:click="saveProductIngredients">
                    <span wire:loading.remove wire:target="saveProductIngredients"><i class="bx bx-check me-1"></i> Guardar ingredientes</span>
                    <span wire:loading wire:target="saveProductIngredients"  class="menu-loading"><span class="spinner-border spinner-border-sm"></span> Guardando…</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ══════════════════════════════════ MODAL: Print Area Form ══════════════════ --}}
@if($showAreaModal)
<div class="modal-backdrop fade show app-modal-backdrop" wire:click="$set('showAreaModal',false)"></div>
<div class="modal fade show d-block app-modal-layer" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered menu-modal-440" >
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bx {{ $editAreaId ? 'bx-edit-alt' : 'bx-plus-circle' }} me-2 text-primary"></i>
                    {{ $editAreaId ? 'Editar área' : 'Nueva área de impresión' }}
                </h5>
                <button type="button" class="btn-close" wire:click="$set('showAreaModal',false)"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nombre <span class="text-danger">*</span></label>
                    <input wire:model="areaName" type="text" class="form-control @error('areaName') is-invalid @enderror" placeholder="Ej. Cocina, Barra, Parrilla…">
                    @error('areaName') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">Descripción</label>
                    <input wire:model="areaDesc" type="text" class="form-control" placeholder="Descripción opcional">
                </div>
                <div class="mb-3">
                    <label class="form-label">Color identificador</label>
                    <div class="input-group">
                        <span class="input-group-text p-1">
                            <input wire:model.change="areaColor" type="color" class="form-control form-control-color border-0 menu-color-input" >
                        </span>
                        <input wire:model.blur="areaColor" type="text" class="form-control" placeholder="#696cff">
                    </div>
                </div>
                <div class="form-check form-switch">
                    <input wire:model="areaIsActive" class="form-check-input" type="checkbox" id="areaIsActive">
                    <label class="form-check-label" for="areaIsActive">Área activa</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" wire:click="$set('showAreaModal',false)">Cancelar</button>
                <button type="button" class="btn btn-primary" wire:click="saveArea">
                    <span wire:loading.remove wire:target="saveArea"><i class="bx bx-check me-1"></i> {{ $editAreaId ? 'Actualizar' : 'Crear' }}</span>
                    <span wire:loading wire:target="saveArea"  class="menu-loading"><span class="spinner-border spinner-border-sm"></span> Guardando…</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════════════════════ Toasts --}}
<div  class="app-toast-stack">
    <template x-for="toast in toasts" :key="toast.id">
        <div x-show="true"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="toast show align-items-center border-0 text-white"
             :class="{
                 'bg-success': toast.type === 'success',
                 'bg-danger':  toast.type === 'danger',
                 'bg-warning': toast.type === 'warning',
                 'bg-info':    toast.type === 'info'
             }"
             role="alert">
            <div class="d-flex">
                <div class="toast-body" x-text="toast.message"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" @click="toasts = toasts.filter(t => t.id !== toast.id)"></button>
            </div>
        </div>
    </template>
</div>

</div>
