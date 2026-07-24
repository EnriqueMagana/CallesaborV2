<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryPurchase;
use App\Services\InventoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class InventoryManager extends Component
{
    use WithPagination;

    public string $activeTab = 'stock';

    public string $search = '';

    public string $unitFilter = '';

    public string $stockFilter = '';

    public bool $showItemModal = false;

    public ?int $editingItemId = null;

    public string $itemName = '';

    public string $itemSku = '';

    public string $itemCategory = '';

    public string $itemUnit = 'piece';

    public string $minimumStock = '0';

    public string $estimatedUnitCost = '';

    public string $openingStock = '0';

    public bool $itemIsActive = true;

    public bool $showAdjustmentModal = false;

    public ?int $adjustItemId = null;

    public string $adjustDirection = 'in';

    public string $adjustQuantity = '';

    public string $adjustReason = '';

    public bool $showPurchaseModal = false;

    public ?int $editingPurchaseId = null;

    public string $purchaseNotes = '';

    public array $purchaseLines = [];

    public ?int $lastCreatedPurchaseId = null;

    public string $purchaseSearch = '';

    public string $purchaseStatusFilter = '';

    public bool $showPurchaseDetailModal = false;

    public ?int $selectedPurchaseId = null;

    public bool $showDeletePurchaseConfirm = false;

    public bool $showReceptionModal = false;

    public string $receptionFolio = '';

    public ?int $receptionPurchaseId = null;

    public array $receptionQuantities = [];

    public array $receptionNotes = [];

    public string $receptionGeneralNotes = '';

    public function mount(): void
    {
        $this->authorizePermission('ver inventario');
    }

    #[Computed]
    public function stats(): array
    {
        $active = InventoryItem::query()->where('is_active', true);

        return [
            'items' => (clone $active)->count(),
            'low' => (clone $active)->whereColumn('current_stock', '<=', 'minimum_stock')->where('current_stock', '>', 0)->count(),
            'empty' => (clone $active)->where('current_stock', '<=', 0)->count(),
            'pending' => InventoryPurchase::query()->where('status', 'pending')->count(),
        ];
    }

    #[Computed]
    public function items()
    {
        return InventoryItem::query()
            ->when($this->search !== '', function (Builder $query) {
                $term = '%'.$this->search.'%';
                $query->where(fn (Builder $search) => $search
                    ->where('name', 'like', $term)
                    ->orWhere('sku', 'like', $term)
                    ->orWhere('category', 'like', $term));
            })
            ->when($this->unitFilter !== '', fn (Builder $query) => $query->where('unit', $this->unitFilter))
            ->when($this->stockFilter === 'low', fn (Builder $query) => $query
                ->whereColumn('current_stock', '<=', 'minimum_stock')
                ->where('current_stock', '>', 0))
            ->when($this->stockFilter === 'empty', fn (Builder $query) => $query->where('current_stock', '<=', 0))
            ->when($this->stockFilter === 'ok', fn (Builder $query) => $query->whereColumn('current_stock', '>', 'minimum_stock'))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(12, ['*'], 'inventoryPage');
    }

    #[Computed]
    public function purchases()
    {
        return InventoryPurchase::query()
            ->with(['requester:id,name', 'receiver:id,name'])
            ->withCount('items')
            ->when($this->purchaseSearch !== '', function (Builder $query) {
                $term = '%'.$this->purchaseSearch.'%';
                $query->where(function (Builder $search) use ($term) {
                    $search->where('folio', 'like', $term)
                        ->orWhere('notes', 'like', $term)
                        ->orWhereHas('items', fn (Builder $items) => $items->where('item_name', 'like', $term));
                });
            })
            ->when($this->purchaseStatusFilter !== '', fn (Builder $query) => $query->where('status', $this->purchaseStatusFilter))
            ->latest('issued_at')
            ->paginate(10, ['*'], 'purchasePage');
    }

    #[Computed]
    public function selectedPurchase(): ?InventoryPurchase
    {
        if (! $this->selectedPurchaseId) {
            return null;
        }

        return InventoryPurchase::query()
            ->with(['items.inventoryItem', 'requester:id,name', 'receiver:id,name'])
            ->find($this->selectedPurchaseId);
    }

    #[Computed]
    public function catalog()
    {
        return InventoryItem::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'unit', 'estimated_unit_cost']);
    }

    #[Computed]
    public function recentMovements()
    {
        return InventoryMovement::query()
            ->with(['item:id,name,unit', 'user:id,name'])
            ->latest()
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function receptionPurchase(): ?InventoryPurchase
    {
        if (! $this->receptionPurchaseId) {
            return null;
        }

        return InventoryPurchase::query()
            ->with(['items.inventoryItem', 'requester:id,name'])
            ->find($this->receptionPurchaseId);
    }

    public function switchTab(string $tab): void
    {
        abort_unless(in_array($tab, ['stock', 'purchases'], true), 404);
        $this->activeTab = $tab;
    }

    public function applyFilters(): void
    {
        $this->search = trim($this->search);
        unset($this->items);
        $this->resetPage('inventoryPage');
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'unitFilter', 'stockFilter');
        unset($this->items);
        $this->resetPage('inventoryPage');
    }

    public function applyPurchaseFilters(): void
    {
        $this->purchaseSearch = strtoupper(trim($this->purchaseSearch));
        unset($this->purchases);
        $this->resetPage('purchasePage');
    }

    public function clearPurchaseFilters(): void
    {
        $this->reset('purchaseSearch', 'purchaseStatusFilter');
        unset($this->purchases);
        $this->resetPage('purchasePage');
    }

    public function openItemModal(?int $itemId = null): void
    {
        $this->authorizePermission('gestionar insumos');
        $this->resetValidation();
        $this->resetItemForm();

        if ($itemId) {
            $item = InventoryItem::findOrFail($itemId);
            $this->editingItemId = $item->id;
            $this->itemName = $item->name;
            $this->itemSku = (string) $item->sku;
            $this->itemCategory = (string) $item->category;
            $this->itemUnit = $item->unit;
            $this->minimumStock = (string) $item->minimum_stock;
            $this->estimatedUnitCost = (string) ($item->estimated_unit_cost ?? '');
            $this->itemIsActive = $item->is_active;
        }

        $this->showItemModal = true;
    }

    public function closeItemModal(): void
    {
        $this->showItemModal = false;
        $this->resetValidation();
    }

    public function saveItem(InventoryService $inventory): void
    {
        $this->authorizePermission('gestionar insumos');
        $this->itemSku = strtoupper(trim($this->itemSku));

        $validated = $this->validate([
            'itemName' => ['required', 'string', 'max:160'],
            'itemSku' => ['nullable', 'string', 'max:80', Rule::unique('inventory_items', 'sku')->ignore($this->editingItemId)],
            'itemCategory' => ['nullable', 'string', 'max:100'],
            'itemUnit' => ['required', Rule::in(array_keys(InventoryItem::UNITS))],
            'minimumStock' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'estimatedUnitCost' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'openingStock' => [$this->editingItemId ? 'nullable' : 'required', 'numeric', 'min:0', 'max:999999999'],
            'itemIsActive' => ['boolean'],
        ]);

        $item = $this->editingItemId ? InventoryItem::findOrFail($this->editingItemId) : new InventoryItem;
        if ($item->exists && (float) $item->current_stock !== 0.0 && $item->unit !== $validated['itemUnit']) {
            $this->addError('itemUnit', 'Solo puedes cambiar la unidad cuando la existencia sea cero.');

            return;
        }

        if (! $item->exists && (float) $validated['openingStock'] > 0 && ! auth()->user()->can('ajustar inventario')) {
            abort(403);
        }

        $item->fill([
            'name' => trim($validated['itemName']),
            'sku' => $validated['itemSku'] ?: null,
            'category' => trim((string) $validated['itemCategory']) ?: null,
            'unit' => $validated['itemUnit'],
            'minimum_stock' => round((float) $validated['minimumStock'], 3),
            'estimated_unit_cost' => $validated['estimatedUnitCost'] === '' ? null : round((float) $validated['estimatedUnitCost'], 2),
            'is_active' => $validated['itemIsActive'],
        ])->save();

        if (! $this->editingItemId && (float) $validated['openingStock'] > 0) {
            $inventory->adjust(
                $item,
                'in',
                (float) $validated['openingStock'],
                'Existencia inicial',
                auth()->user(),
                'opening_balance',
            );
        }

        $this->showItemModal = false;
        unset($this->items, $this->stats, $this->catalog, $this->recentMovements);
        session()->flash('inventoryNotice', $this->editingItemId ? 'Insumo actualizado correctamente.' : 'Insumo creado correctamente.');
    }

    public function openAdjustmentModal(int $itemId, string $direction = 'in'): void
    {
        $this->authorizePermission('ajustar inventario');
        abort_unless(in_array($direction, ['in', 'out'], true), 422);
        $item = InventoryItem::findOrFail($itemId);

        $this->resetValidation();
        $this->adjustItemId = $item->id;
        $this->adjustDirection = $direction;
        $this->adjustQuantity = '';
        $this->adjustReason = '';
        $this->showAdjustmentModal = true;
    }

    public function closeAdjustmentModal(): void
    {
        $this->showAdjustmentModal = false;
        $this->resetValidation();
    }

    public function saveAdjustment(InventoryService $inventory): void
    {
        $this->authorizePermission('ajustar inventario');
        $validated = $this->validate([
            'adjustItemId' => ['required', 'exists:inventory_items,id'],
            'adjustDirection' => ['required', Rule::in(['in', 'out'])],
            'adjustQuantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'adjustReason' => ['required', 'string', 'min:4', 'max:255'],
        ]);

        $inventory->adjust(
            InventoryItem::findOrFail($validated['adjustItemId']),
            $validated['adjustDirection'],
            (float) $validated['adjustQuantity'],
            trim($validated['adjustReason']),
            auth()->user(),
            $validated['adjustDirection'] === 'in' ? 'manual_in' : 'manual_out',
        );

        $this->showAdjustmentModal = false;
        unset($this->items, $this->stats, $this->recentMovements);
        session()->flash('inventoryNotice', 'Movimiento aplicado y registrado en el historial.');
    }

    public function openPurchaseModal(): void
    {
        $this->authorizePermission('generar compras inventario');
        abort_if($this->catalog->isEmpty(), 422, 'Primero registra al menos un insumo activo.');
        $this->resetValidation();
        $this->editingPurchaseId = null;
        $this->purchaseNotes = '';
        $this->purchaseLines = [$this->emptyPurchaseLine()];
        $this->showPurchaseModal = true;
    }

    public function editPurchase(int $purchaseId): void
    {
        $this->authorizePermission('editar compras inventario');
        $purchase = InventoryPurchase::query()->with('items')->findOrFail($purchaseId);
        abort_unless($purchase->status === 'pending', 422, 'Solo se pueden editar compras pendientes.');

        $this->resetValidation();
        $this->editingPurchaseId = $purchase->id;
        $this->purchaseNotes = (string) $purchase->notes;
        $this->purchaseLines = $purchase->items->map(fn ($line) => [
            'inventory_item_id' => (string) $line->inventory_item_id,
            'quantity' => rtrim(rtrim(number_format((float) $line->requested_quantity, 3, '.', ''), '0'), '.'),
            'notes' => (string) $line->notes,
        ])->all();
        $this->showPurchaseDetailModal = false;
        $this->showDeletePurchaseConfirm = false;
        $this->showPurchaseModal = true;
    }

    public function closePurchaseModal(): void
    {
        $this->showPurchaseModal = false;
        $this->resetValidation();
    }

    public function addPurchaseLine(): void
    {
        $this->authorizePermission($this->editingPurchaseId ? 'editar compras inventario' : 'generar compras inventario');
        if (count($this->purchaseLines) < 30) {
            $this->purchaseLines[] = $this->emptyPurchaseLine();
        }
    }

    public function removePurchaseLine(int $index): void
    {
        $this->authorizePermission($this->editingPurchaseId ? 'editar compras inventario' : 'generar compras inventario');
        if (count($this->purchaseLines) > 1 && isset($this->purchaseLines[$index])) {
            unset($this->purchaseLines[$index]);
            $this->purchaseLines = array_values($this->purchaseLines);
        }
    }

    public function createPurchase(): void
    {
        $this->authorizePermission($this->editingPurchaseId ? 'editar compras inventario' : 'generar compras inventario');
        $this->purchaseLines = collect($this->purchaseLines)
            ->filter(fn (array $line) => filled($line['inventory_item_id'] ?? null)
                || filled($line['quantity'] ?? null)
                || filled(trim((string) ($line['notes'] ?? ''))))
            ->values()
            ->all();

        $validated = $this->validate([
            'purchaseNotes' => ['nullable', 'string', 'max:1000'],
            'purchaseLines' => ['required', 'array', 'min:1', 'max:30'],
            'purchaseLines.*.inventory_item_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('inventory_items', 'id')->where('is_active', true),
            ],
            'purchaseLines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'purchaseLines.*.notes' => ['nullable', 'string', 'max:255'],
        ], [
            'purchaseNotes.max' => 'Las indicaciones generales no pueden superar los 1,000 caracteres.',
            'purchaseLines.required' => 'Agrega al menos un insumo a la lista de compra.',
            'purchaseLines.min' => 'Agrega al menos un insumo a la lista de compra.',
            'purchaseLines.max' => 'Puedes incluir hasta 30 partidas en una sola compra.',
            'purchaseLines.*.inventory_item_id.required' => 'Selecciona el insumo de esta partida.',
            'purchaseLines.*.inventory_item_id.integer' => 'Selecciona un insumo válido.',
            'purchaseLines.*.inventory_item_id.distinct' => 'Este insumo ya está incluido en otra partida.',
            'purchaseLines.*.inventory_item_id.exists' => 'El insumo seleccionado ya no está disponible.',
            'purchaseLines.*.quantity.required' => 'Indica la cantidad que necesitas comprar.',
            'purchaseLines.*.quantity.numeric' => 'La cantidad debe ser un número válido.',
            'purchaseLines.*.quantity.gt' => 'La cantidad debe ser mayor a cero.',
            'purchaseLines.*.quantity.max' => 'La cantidad indicada es demasiado grande.',
            'purchaseLines.*.notes.max' => 'La indicación de la partida no puede superar los 255 caracteres.',
        ]);

        $purchase = DB::transaction(function () use ($validated) {
            if ($this->editingPurchaseId) {
                $purchase = InventoryPurchase::query()->lockForUpdate()->findOrFail($this->editingPurchaseId);
                abort_unless($purchase->status === 'pending', 422, 'Esta compra ya fue recibida y no puede modificarse.');
                $purchase->update(['notes' => trim((string) $validated['purchaseNotes']) ?: null]);
                $purchase->items()->delete();
            } else {
                $purchase = InventoryPurchase::create([
                    'status' => 'pending',
                    'notes' => trim((string) $validated['purchaseNotes']) ?: null,
                    'requested_by' => auth()->id(),
                    'issued_at' => now(),
                ]);
                $purchase->update([
                    'folio' => 'CMP-'.now()->format('ym').'-'.str_pad((string) $purchase->id, 6, '0', STR_PAD_LEFT),
                ]);
            }

            $catalog = InventoryItem::query()
                ->whereIn('id', collect($validated['purchaseLines'])->pluck('inventory_item_id'))
                ->get()
                ->keyBy('id');

            foreach ($validated['purchaseLines'] as $line) {
                $item = $catalog->get((int) $line['inventory_item_id']);
                $purchase->items()->create([
                    'inventory_item_id' => $item->id,
                    'item_name' => $item->name,
                    'unit' => $item->unit,
                    'requested_quantity' => round((float) $line['quantity'], 3),
                    'estimated_unit_cost' => $item->estimated_unit_cost,
                    'notes' => trim((string) ($line['notes'] ?? '')) ?: null,
                ]);
            }

            return $purchase;
        });

        $wasEditing = $this->editingPurchaseId !== null;
        $this->lastCreatedPurchaseId = $purchase->id;
        $this->editingPurchaseId = null;
        $this->showPurchaseModal = false;
        $this->selectedPurchaseId = $purchase->id;
        $this->showPurchaseDetailModal = true;
        $this->activeTab = 'purchases';
        unset($this->purchases, $this->selectedPurchase, $this->stats);
        session()->flash('inventoryNotice', $wasEditing
            ? "La lista {$purchase->folio} se actualizó correctamente."
            : "Lista {$purchase->folio} preparada. Revisa el contenido antes de imprimir.");
    }

    public function openPurchaseDetail(int $purchaseId): void
    {
        $this->authorizePermission('ver inventario');
        InventoryPurchase::findOrFail($purchaseId);
        $this->selectedPurchaseId = $purchaseId;
        $this->showDeletePurchaseConfirm = false;
        $this->showPurchaseDetailModal = true;
        unset($this->selectedPurchase);
    }

    public function closePurchaseDetail(): void
    {
        $this->showPurchaseDetailModal = false;
        $this->showDeletePurchaseConfirm = false;
        unset($this->selectedPurchase);
    }

    public function askDeletePurchase(): void
    {
        $this->authorizePermission('eliminar compras inventario');
        abort_unless($this->selectedPurchase?->status === 'pending', 422, 'Solo se pueden eliminar compras pendientes.');
        $this->showDeletePurchaseConfirm = true;
    }

    public function deletePurchase(): void
    {
        $this->authorizePermission('eliminar compras inventario');
        $folio = DB::transaction(function () {
            $purchase = InventoryPurchase::query()->lockForUpdate()->findOrFail($this->selectedPurchaseId);
            abort_unless($purchase->status === 'pending' && ! $purchase->movements()->exists(), 422, 'Esta compra ya tiene movimientos y debe conservarse.');
            $folio = $purchase->folio;
            $purchase->delete();

            return $folio;
        });

        if ($this->lastCreatedPurchaseId === $this->selectedPurchaseId) {
            $this->lastCreatedPurchaseId = null;
        }
        $this->selectedPurchaseId = null;
        $this->showPurchaseDetailModal = false;
        $this->showDeletePurchaseConfirm = false;
        unset($this->selectedPurchase, $this->purchases, $this->stats);
        session()->flash('inventoryNotice', "La lista {$folio} fue eliminada.");
    }

    public function openReceptionModal(?int $purchaseId = null): void
    {
        $this->authorizePermission('recepcionar compras inventario');
        $this->resetValidation();
        $this->receptionFolio = '';
        $this->receptionPurchaseId = null;
        $this->receptionQuantities = [];
        $this->receptionNotes = [];
        $this->receptionGeneralNotes = '';
        $this->showReceptionModal = true;

        if ($purchaseId) {
            $this->loadReceptionPurchase($purchaseId);
        }
    }

    public function closeReceptionModal(): void
    {
        $this->showReceptionModal = false;
        $this->resetValidation();
        unset($this->receptionPurchase);
    }

    public function lookupReception(): void
    {
        $this->authorizePermission('recepcionar compras inventario');
        $this->receptionFolio = strtoupper(trim($this->receptionFolio));
        $this->validate(['receptionFolio' => ['required', 'string', 'max:40']]);

        $purchase = InventoryPurchase::query()->where('folio', $this->receptionFolio)->first();
        if (! $purchase) {
            $this->addError('receptionFolio', 'No encontramos una compra con este folio.');

            return;
        }
        if ($purchase->status !== 'pending') {
            $this->addError('receptionFolio', 'Este folio ya fue recibido o ya no está pendiente.');

            return;
        }

        $this->loadReceptionPurchase($purchase->id);
    }

    public function confirmReception(InventoryService $inventory): void
    {
        $this->authorizePermission('recepcionar compras inventario');
        $purchase = InventoryPurchase::query()->with('items')->findOrFail($this->receptionPurchaseId);

        $this->validate([
            'receptionQuantities' => ['required', 'array'],
            'receptionQuantities.*' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'receptionNotes' => ['array'],
            'receptionNotes.*' => ['nullable', 'string', 'max:255'],
            'receptionGeneralNotes' => ['nullable', 'string', 'max:1000'],
        ]);

        $hasMissingAdjustmentNote = false;
        foreach ($purchase->items as $line) {
            $received = round((float) ($this->receptionQuantities[$line->id] ?? 0), 3);
            if (abs($received - (float) $line->requested_quantity) > 0.0005
                && trim((string) ($this->receptionNotes[$line->id] ?? '')) === '') {
                $this->addError("receptionNotes.{$line->id}", 'Explica por qué la cantidad recibida es diferente.');
                $hasMissingAdjustmentNote = true;
            }
        }
        if ($hasMissingAdjustmentNote) {
            return;
        }

        $inventory->receivePurchase(
            $purchase,
            $this->receptionQuantities,
            $this->receptionNotes,
            trim($this->receptionGeneralNotes) ?: null,
            auth()->user(),
        );

        $folio = $purchase->folio;
        $this->showReceptionModal = false;
        $this->activeTab = 'purchases';
        unset($this->receptionPurchase, $this->purchases, $this->items, $this->stats, $this->recentMovements);
        session()->flash('inventoryNotice', "Folio {$folio} recibido. Las existencias ya fueron actualizadas.");
    }

    public function render()
    {
        return view('livewire.inventory.inventory-manager', [
            'units' => InventoryItem::UNITS,
        ]);
    }

    private function resetItemForm(): void
    {
        $this->editingItemId = null;
        $this->itemName = '';
        $this->itemSku = '';
        $this->itemCategory = '';
        $this->itemUnit = 'piece';
        $this->minimumStock = '0';
        $this->estimatedUnitCost = '';
        $this->openingStock = '0';
        $this->itemIsActive = true;
    }

    private function emptyPurchaseLine(): array
    {
        return ['inventory_item_id' => '', 'quantity' => '', 'notes' => ''];
    }

    private function loadReceptionPurchase(int $purchaseId): void
    {
        $purchase = InventoryPurchase::query()->with('items')->findOrFail($purchaseId);
        abort_unless($purchase->status === 'pending', 422, 'Este folio ya no está pendiente.');

        $this->receptionPurchaseId = $purchase->id;
        $this->receptionFolio = (string) $purchase->folio;
        $this->receptionQuantities = $purchase->items
            ->mapWithKeys(fn ($line) => [$line->id => (string) $line->requested_quantity])
            ->all();
        $this->receptionNotes = $purchase->items->mapWithKeys(fn ($line) => [$line->id => ''])->all();
        unset($this->receptionPurchase);
    }

    private function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
