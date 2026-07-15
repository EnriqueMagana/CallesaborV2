<?php

namespace App\Livewire\Menu;

use App\Models\Addon;
use App\Models\AddonGroup;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\PrintArea;
use App\Models\Product;
use App\Traits\ConvertsToWebp;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class MenuBuilder extends Component
{
    use WithFileUploads, ConvertsToWebp;

    // ─── Active tab ────────────────────────────────────────────────────────────
    public string $tab = 'products';

    // ─── Products ──────────────────────────────────────────────────────────────
    public string $productSearch = '';
    public ?int   $productCategoryFilter = null;

    public bool   $showProductModal = false;
    public ?int   $editProductId    = null;
    public string $pName            = '';
    public string $pDescription     = '';
    public ?int   $pCategoryId      = null;
    public string $pPrice           = '0.00';
    public bool   $pIsCustomizable  = false;
    public string $pMaxAddons       = '';
    public bool   $pIsActive        = true;
    public $pImage                  = null;
    public ?string $pCurrentImage   = null;
    public array  $pAddonGroupIds   = [];

    // ─── Categories ────────────────────────────────────────────────────────────
    public string $categorySearch = '';

    public bool   $showCategoryModal = false;
    public ?int   $editCategoryId    = null;
    public string $cName             = '';
    public string $cDescription      = '';
    public string $cIcon             = 'bx-food-menu';
    public string $cColor            = '#696cff';
    public ?int   $cPrintAreaId      = null;
    public bool   $cIsActive         = true;

    // ─── Addon Groups ──────────────────────────────────────────────────────────
    public bool   $showGroupModal  = false;
    public ?int   $editGroupId     = null;
    public string $gName           = '';
    public string $gDescription    = '';
    public bool   $gIsRequired     = false;
    public string $gMinSelections  = '0';
    public string $gMaxSelections  = '1';
    public bool   $gIsActive       = true;

    public bool   $showAddonsModal = false;
    public ?int   $activeGroupId   = null;

    public bool   $showAddonForm   = false;
    public ?int   $editAddonId     = null;
    public string $aName           = '';
    public string $aDescription    = '';
    public string $aExtraPrice     = '0.00';
    public bool   $aIsActive       = true;
    public $aImage                  = null;
    public ?string $aCurrentImage  = null;

    // ─── Ingredients ───────────────────────────────────────────────────────────
    public bool   $showIngredientModal = false;
    public ?int   $editIngredientId    = null;
    public string $ingName             = '';
    public string $ingDescription      = '';
    public string $ingExtraPrice       = '0.00';
    public bool   $ingIsActive         = true;
    public $ingImage                   = null;
    public ?string $ingCurrentImage    = null;

    // ─── Product ↔ Ingredients assignment ─────────────────────────────────────
    public bool   $showProductIngredientsModal = false;
    public ?int   $piProductId                 = null;
    public array  $piIngredientIds             = [];
    public string $piMinIngredients            = '0';
    public string $piMaxIngredients            = '6';

    // ─── Print Areas ───────────────────────────────────────────────────────────
    public bool   $showAreaModal = false;
    public ?int   $editAreaId    = null;
    public string $areaName      = '';
    public string $areaDesc      = '';
    public string $areaColor     = '#696cff';
    public bool   $areaIsActive  = true;

    // ──────────────────────────────────────────────────────────────────────────

    #[Computed]
    public function products()
    {
        return Product::with('category')
            ->when($this->productSearch, fn($q) => $q->where('name', 'like', "%{$this->productSearch}%"))
            ->when($this->productCategoryFilter, fn($q) => $q->where('category_id', $this->productCategoryFilter))
            ->orderBy('sort_order')->orderBy('name')
            ->paginate(12);
    }

    #[Computed]
    public function categories()
    {
        return Category::with('printArea')
            ->when($this->categorySearch, fn($q) => $q->where('name', 'like', "%{$this->categorySearch}%"))
            ->orderBy('sort_order')->orderBy('name')
            ->get();
    }

    #[Computed]
    public function addonGroups()
    {
        return AddonGroup::withCount('addons')->orderBy('sort_order')->orderBy('name')->get();
    }

    #[Computed]
    public function ingredients()
    {
        return Ingredient::withCount('products')->orderBy('sort_order')->orderBy('name')->get();
    }

    #[Computed]
    public function printAreas()
    {
        return PrintArea::withCount('categories')->orderBy('sort_order')->orderBy('name')->get();
    }

    #[Computed]
    public function allCategories()
    {
        return Category::orderBy('name')->get();
    }

    #[Computed]
    public function allAddonGroups()
    {
        return AddonGroup::orderBy('name')->get();
    }

    #[Computed]
    public function allIngredients()
    {
        return Ingredient::where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function allPrintAreas()
    {
        return PrintArea::orderBy('name')->get();
    }

    #[Computed]
    public function activeGroup(): ?AddonGroup
    {
        if (!$this->activeGroupId) {
            return null;
        }
        return AddonGroup::with(['addons' => fn($q) => $q->orderBy('sort_order')])->find($this->activeGroupId);
    }

    #[Computed]
    public function piProduct(): ?Product
    {
        return $this->piProductId ? Product::find($this->piProductId) : null;
    }

    // ─── Product CRUD ──────────────────────────────────────────────────────────

    public function openProductModal(?int $id = null): void
    {
        $this->resetProductForm();
        if ($id) {
            $product = Product::with('addonGroups')->findOrFail($id);
            $this->editProductId    = $product->id;
            $this->pName            = $product->name;
            $this->pDescription     = $product->description ?? '';
            $this->pCategoryId      = $product->category_id;
            $this->pPrice           = $product->price;
            $this->pIsCustomizable  = $product->is_customizable;
            $this->pMaxAddons       = $product->max_addons ?? '';
            $this->pIsActive        = $product->is_active;
            $this->pCurrentImage    = $product->image;
            $this->pAddonGroupIds   = $product->addonGroups->pluck('id')->map(fn($v) => (string) $v)->toArray();
        }
        $this->showProductModal = true;
    }

    public function saveProduct(): void
    {
        $this->validate([
            'pName'        => 'required|string|max:120',
            'pPrice'       => 'required|numeric|min:0',
            'pCategoryId'  => 'nullable|exists:categories,id',
            'pImage'       => 'nullable|image|max:2048',
            'pMaxAddons'   => 'nullable|integer|min:1',
        ]);

        $data = [
            'name'            => $this->pName,
            'description'     => $this->pDescription ?: null,
            'category_id'     => $this->pCategoryId,
            'price'           => $this->pPrice,
            'is_customizable' => $this->pIsCustomizable,
            'max_addons'      => $this->pIsCustomizable && $this->pMaxAddons !== '' ? (int) $this->pMaxAddons : null,
            'is_active'       => $this->pIsActive,
        ];

        if ($this->pImage) {
            $data['image'] = $this->storeAsWebp($this->pImage, 'products', 82, 1200);
        }

        if ($this->editProductId) {
            $product = Product::findOrFail($this->editProductId);
            $product->update($data);
        } else {
            $product = Product::create($data);
        }

        $sync = [];
        foreach ($this->pAddonGroupIds as $i => $gid) {
            $sync[(int) $gid] = ['sort_order' => $i];
        }
        $product->addonGroups()->sync($sync);

        $wasEdit = (bool) $this->editProductId;
        $this->showProductModal = false;
        $this->resetProductForm();
        unset($this->products);
        $this->dispatch('notify', type: 'success', message: $wasEdit ? 'Producto actualizado.' : 'Producto creado.');
    }

    public function confirmDeleteProduct(int $id): void
    {
        $this->dispatch('open-confirm',
            type: 'danger',
            title: 'Eliminar producto',
            message: '¿Seguro que deseas eliminar este producto? Se moverá a la papelera.',
            action: 'deleteProduct',
            params: ['id' => $id],
        );
    }

    #[On('modal-confirmed')]
    public function handleModalConfirmed(string $action, array $params = []): void
    {
        match ($action) {
            'deleteProduct'      => $this->deleteProduct($params['id']),
            'restoreProduct'     => $this->restoreProduct($params['id']),
            'forceProduct'       => $this->forceDeleteProduct($params['id']),
            'deleteCategory'     => $this->deleteCategory($params['id']),
            'deleteGroup'        => $this->deleteGroup($params['id']),
            'deleteAddon'        => $this->deleteAddon($params['id']),
            'deleteArea'         => $this->deleteArea($params['id']),
            'deleteIngredient'   => $this->deleteIngredient($params['id']),
            default              => null,
        };
    }

    private function deleteProduct(int $id): void
    {
        Product::findOrFail($id)->delete();
        unset($this->products);
        $this->dispatch('notify', type: 'warning', message: 'Producto eliminado (papelera).');
    }

    private function restoreProduct(int $id): void
    {
        Product::withTrashed()->findOrFail($id)->restore();
        unset($this->products);
        $this->dispatch('notify', type: 'success', message: 'Producto restaurado.');
    }

    private function forceDeleteProduct(int $id): void
    {
        $p = Product::withTrashed()->findOrFail($id);
        $p->addonGroups()->detach();
        $p->ingredients()->detach();
        $p->forceDelete();
        unset($this->products);
        $this->dispatch('notify', type: 'danger', message: 'Producto eliminado permanentemente.');
    }

    private function resetProductForm(): void
    {
        $this->editProductId   = null;
        $this->pName           = '';
        $this->pDescription    = '';
        $this->pCategoryId     = null;
        $this->pPrice          = '0.00';
        $this->pIsCustomizable = false;
        $this->pMaxAddons      = '';
        $this->pIsActive       = true;
        $this->pImage          = null;
        $this->pCurrentImage   = null;
        $this->pAddonGroupIds  = [];
    }

    // ─── Category CRUD ─────────────────────────────────────────────────────────

    public function openCategoryModal(?int $id = null): void
    {
        $this->resetCategoryForm();
        if ($id) {
            $cat = Category::findOrFail($id);
            $this->editCategoryId = $cat->id;
            $this->cName          = $cat->name;
            $this->cDescription   = $cat->description ?? '';
            $this->cIcon          = $cat->icon;
            $this->cColor         = $cat->color;
            $this->cPrintAreaId   = $cat->print_area_id;
            $this->cIsActive      = $cat->is_active;
        }
        $this->showCategoryModal = true;
    }

    public function saveCategory(): void
    {
        $this->validate([
            'cName'        => 'required|string|max:80',
            'cIcon'        => 'required|string|max:60',
            'cColor'       => 'required|string|max:20',
            'cPrintAreaId' => 'nullable|exists:print_areas,id',
        ]);

        $data = [
            'name'          => $this->cName,
            'description'   => $this->cDescription ?: null,
            'icon'          => $this->cIcon,
            'color'         => $this->cColor,
            'print_area_id' => $this->cPrintAreaId,
            'is_active'     => $this->cIsActive,
        ];

        if ($this->editCategoryId) {
            Category::findOrFail($this->editCategoryId)->update($data);
        } else {
            Category::create($data);
        }

        $wasEdit = (bool) $this->editCategoryId;
        $this->showCategoryModal = false;
        $this->resetCategoryForm();
        unset($this->categories, $this->allCategories);
        $this->dispatch('notify', type: 'success', message: $wasEdit ? 'Categoría actualizada.' : 'Categoría creada.');
    }

    public function confirmDeleteCategory(int $id): void
    {
        $this->dispatch('open-confirm',
            type: 'danger',
            title: 'Eliminar categoría',
            message: '¿Eliminar esta categoría? Los productos asociados quedarán sin categoría.',
            action: 'deleteCategory',
            params: ['id' => $id],
        );
    }

    private function deleteCategory(int $id): void
    {
        Category::findOrFail($id)->delete();
        unset($this->categories, $this->allCategories);
        $this->dispatch('notify', type: 'warning', message: 'Categoría eliminada.');
    }

    private function resetCategoryForm(): void
    {
        $this->editCategoryId = null;
        $this->cName          = '';
        $this->cDescription   = '';
        $this->cIcon          = 'bx-food-menu';
        $this->cColor         = '#696cff';
        $this->cPrintAreaId   = null;
        $this->cIsActive      = true;
    }

    // ─── Addon Groups CRUD ─────────────────────────────────────────────────────

    public function openGroupModal(?int $id = null): void
    {
        $this->resetGroupForm();
        if ($id) {
            $group = AddonGroup::findOrFail($id);
            $this->editGroupId    = $group->id;
            $this->gName          = $group->name;
            $this->gDescription   = $group->description ?? '';
            $this->gIsRequired    = $group->is_required;
            $this->gMinSelections = (string) $group->min_selections;
            $this->gMaxSelections = (string) $group->max_selections;
            $this->gIsActive      = $group->is_active;
        }
        $this->showGroupModal = true;
    }

    public function saveGroup(): void
    {
        $this->validate([
            'gName'          => 'required|string|max:80',
            'gMinSelections' => 'required|integer|min:0|max:20',
            'gMaxSelections' => 'required|integer|min:1|max:20',
        ]);

        $data = [
            'name'           => $this->gName,
            'description'    => $this->gDescription ?: null,
            'is_required'    => $this->gIsRequired,
            'min_selections' => (int) $this->gMinSelections,
            'max_selections' => (int) $this->gMaxSelections,
            'is_active'      => $this->gIsActive,
        ];

        if ($this->editGroupId) {
            AddonGroup::findOrFail($this->editGroupId)->update($data);
        } else {
            AddonGroup::create($data);
        }

        $wasEdit = (bool) $this->editGroupId;
        $this->showGroupModal = false;
        $this->resetGroupForm();
        unset($this->addonGroups, $this->allAddonGroups);
        $this->dispatch('notify', type: 'success', message: $wasEdit ? 'Grupo actualizado.' : 'Grupo creado.');
    }

    public function confirmDeleteGroup(int $id): void
    {
        $this->dispatch('open-confirm',
            type: 'danger',
            title: 'Eliminar grupo de complementos',
            message: '¿Eliminar este grupo? También se eliminarán todos sus complementos.',
            action: 'deleteGroup',
            params: ['id' => $id],
        );
    }

    private function deleteGroup(int $id): void
    {
        AddonGroup::findOrFail($id)->delete();
        unset($this->addonGroups, $this->allAddonGroups);
        $this->dispatch('notify', type: 'warning', message: 'Grupo eliminado.');
    }

    private function resetGroupForm(): void
    {
        $this->editGroupId    = null;
        $this->gName          = '';
        $this->gDescription   = '';
        $this->gIsRequired    = false;
        $this->gMinSelections = '0';
        $this->gMaxSelections = '1';
        $this->gIsActive      = true;
    }

    // ─── Addons CRUD ───────────────────────────────────────────────────────────

    public function openAddonsModal(int $groupId): void
    {
        $this->activeGroupId   = $groupId;
        $this->showAddonsModal = true;
        $this->showAddonForm   = false;
        $this->resetAddonForm();
        unset($this->activeGroup);
    }

    public function openAddonForm(?int $id = null): void
    {
        $this->resetAddonForm();
        if ($id) {
            $addon = Addon::findOrFail($id);
            $this->editAddonId    = $addon->id;
            $this->aName          = $addon->name;
            $this->aDescription   = $addon->description ?? '';
            $this->aExtraPrice    = $addon->extra_price;
            $this->aIsActive      = $addon->is_active;
            $this->aCurrentImage  = $addon->image;
        }
        $this->showAddonForm = true;
    }

    public function saveAddon(): void
    {
        $this->validate([
            'aName'       => 'required|string|max:80',
            'aExtraPrice' => 'required|numeric|min:0',
            'aImage'      => 'nullable|image|max:1024',
        ]);

        $data = [
            'addon_group_id' => $this->activeGroupId,
            'name'           => $this->aName,
            'description'    => $this->aDescription ?: null,
            'extra_price'    => $this->aExtraPrice,
            'is_active'      => $this->aIsActive,
        ];

        if ($this->aImage) {
            $data['image'] = $this->storeAsWebp($this->aImage, 'addons', 82, 800);
        }

        if ($this->editAddonId) {
            Addon::findOrFail($this->editAddonId)->update($data);
        } else {
            Addon::create($data);
        }

        $wasEdit = (bool) $this->editAddonId;
        $this->showAddonForm = false;
        $this->resetAddonForm();
        unset($this->activeGroup, $this->addonGroups);
        $this->dispatch('notify', type: 'success', message: $wasEdit ? 'Complemento actualizado.' : 'Complemento creado.');
    }

    public function confirmDeleteAddon(int $id): void
    {
        $this->dispatch('open-confirm',
            type: 'danger',
            title: 'Eliminar complemento',
            message: '¿Eliminar este complemento?',
            action: 'deleteAddon',
            params: ['id' => $id],
        );
    }

    private function deleteAddon(int $id): void
    {
        Addon::findOrFail($id)->delete();
        unset($this->activeGroup, $this->addonGroups);
        $this->dispatch('notify', type: 'warning', message: 'Complemento eliminado.');
    }

    private function resetAddonForm(): void
    {
        $this->editAddonId   = null;
        $this->aName         = '';
        $this->aDescription  = '';
        $this->aExtraPrice   = '0.00';
        $this->aIsActive     = true;
        $this->aImage        = null;
        $this->aCurrentImage = null;
    }

    // ─── Ingredients CRUD ──────────────────────────────────────────────────────

    public function openIngredientModal(?int $id = null): void
    {
        $this->resetIngredientForm();
        if ($id) {
            $ingredient = Ingredient::findOrFail($id);
            $this->editIngredientId = $ingredient->id;
            $this->ingName          = $ingredient->name;
            $this->ingDescription   = $ingredient->description ?? '';
            $this->ingExtraPrice    = $ingredient->extra_price;
            $this->ingIsActive      = $ingredient->is_active;
            $this->ingCurrentImage  = $ingredient->image;
        }
        $this->showIngredientModal = true;
    }

    public function saveIngredient(): void
    {
        $this->validate([
            'ingName'       => 'required|string|max:100',
            'ingExtraPrice' => 'required|numeric|min:0',
            'ingImage'      => 'nullable|image|max:1024',
        ]);

        $data = [
            'name'        => $this->ingName,
            'description' => $this->ingDescription ?: null,
            'extra_price' => $this->ingExtraPrice,
            'is_active'   => $this->ingIsActive,
        ];

        if ($this->ingImage) {
            $data['image'] = $this->storeAsWebp($this->ingImage, 'ingredients', 82, 600);
        }

        if ($this->editIngredientId) {
            Ingredient::findOrFail($this->editIngredientId)->update($data);
        } else {
            Ingredient::create($data);
        }

        $wasEdit = (bool) $this->editIngredientId;
        $this->showIngredientModal = false;
        $this->resetIngredientForm();
        unset($this->ingredients, $this->allIngredients);
        $this->dispatch('notify', type: 'success', message: $wasEdit ? 'Ingrediente actualizado.' : 'Ingrediente creado.');
    }

    public function confirmDeleteIngredient(int $id): void
    {
        $this->dispatch('open-confirm',
            type: 'danger',
            title: 'Eliminar ingrediente',
            message: '¿Eliminar este ingrediente del catálogo? Se desvinculará de todos los productos.',
            action: 'deleteIngredient',
            params: ['id' => $id],
        );
    }

    private function deleteIngredient(int $id): void
    {
        Ingredient::findOrFail($id)->delete();
        unset($this->ingredients, $this->allIngredients);
        $this->dispatch('notify', type: 'warning', message: 'Ingrediente eliminado.');
    }

    private function resetIngredientForm(): void
    {
        $this->editIngredientId = null;
        $this->ingName          = '';
        $this->ingDescription   = '';
        $this->ingExtraPrice    = '0.00';
        $this->ingIsActive      = true;
        $this->ingImage         = null;
        $this->ingCurrentImage  = null;
    }

    // ─── Product ↔ Ingredients assignment ─────────────────────────────────────

    public function openProductIngredientsModal(int $productId): void
    {
        $this->piProductId = $productId;
        $product = Product::with('ingredients')->findOrFail($productId);
        $this->piIngredientIds  = $product->ingredients->pluck('id')->map(fn($v) => (string) $v)->toArray();
        $this->piMinIngredients = (string) ($product->min_ingredients ?? 0);
        $this->piMaxIngredients = (string) ($product->max_ingredients ?? 6);
        $this->showProductIngredientsModal = true;
        unset($this->piProduct, $this->allIngredients);
    }

    public function saveProductIngredients(): void
    {
        $this->validate([
            'piMinIngredients' => 'required|integer|min:0|max:50',
            'piMaxIngredients' => 'required|integer|min:1|max:50',
        ]);

        $product = Product::findOrFail($this->piProductId);
        $product->update([
            'min_ingredients' => (int) $this->piMinIngredients,
            'max_ingredients' => (int) $this->piMaxIngredients,
        ]);

        $sync = [];
        foreach ($this->piIngredientIds as $i => $iid) {
            $sync[(int) $iid] = ['sort_order' => $i];
        }
        $product->ingredients()->sync($sync);

        $this->showProductIngredientsModal = false;
        $this->piProductId = null;
        unset($this->products, $this->piProduct);
        $this->dispatch('notify', type: 'success', message: 'Ingredientes del producto actualizados.');
    }

    // ─── Print Areas CRUD ──────────────────────────────────────────────────────

    public function openAreaModal(?int $id = null): void
    {
        $this->resetAreaForm();
        if ($id) {
            $area = PrintArea::findOrFail($id);
            $this->editAreaId   = $area->id;
            $this->areaName     = $area->name;
            $this->areaDesc     = $area->description ?? '';
            $this->areaColor    = $area->color;
            $this->areaIsActive = $area->is_active;
        }
        $this->showAreaModal = true;
    }

    public function saveArea(): void
    {
        $this->validate([
            'areaName'  => 'required|string|max:80',
            'areaColor' => 'required|string|max:20',
        ]);

        $data = [
            'name'        => $this->areaName,
            'description' => $this->areaDesc ?: null,
            'color'       => $this->areaColor,
            'is_active'   => $this->areaIsActive,
        ];

        if ($this->editAreaId) {
            PrintArea::findOrFail($this->editAreaId)->update($data);
        } else {
            PrintArea::create($data);
        }

        $wasEdit = (bool) $this->editAreaId;
        $this->showAreaModal = false;
        $this->resetAreaForm();
        unset($this->printAreas, $this->allPrintAreas);
        $this->dispatch('notify', type: 'success', message: $wasEdit ? 'Área actualizada.' : 'Área creada.');
    }

    public function confirmDeleteArea(int $id): void
    {
        $this->dispatch('open-confirm',
            type: 'danger',
            title: 'Eliminar área de impresión',
            message: '¿Eliminar esta área? Las categorías asignadas quedarán sin área.',
            action: 'deleteArea',
            params: ['id' => $id],
        );
    }

    private function deleteArea(int $id): void
    {
        PrintArea::findOrFail($id)->delete();
        unset($this->printAreas, $this->allPrintAreas);
        $this->dispatch('notify', type: 'warning', message: 'Área eliminada.');
    }

    private function resetAreaForm(): void
    {
        $this->editAreaId   = null;
        $this->areaName     = '';
        $this->areaDesc     = '';
        $this->areaColor    = '#696cff';
        $this->areaIsActive = true;
    }

    // ──────────────────────────────────────────────────────────────────────────

    public function render()
    {
        return view('livewire.menu.menu-builder')
            ->layout('layouts.app');
    }
}
