<?php

namespace App\Livewire\Mesas;

use App\Models\Area;
use App\Models\Mesa;
use App\Models\MesaAssignment;
use App\Models\MesaGroup;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Component;

class GestionMesas extends Component
{
    // ── UI State ──
    public string $tab = 'disponibles'; // disponibles | mis_mesas | kiosko | todas

    public string $search = '';

    public ?int $areaFilter = null;

    public string $statusFilter = '';

    // ── Assign modal ──
    public bool $showAssignModal = false;

    public ?int $assignMesaId = null;

    // ── Reassign modal ──
    public bool $showReassignModal = false;

    public ?int $reassignMesaId = null;

    public ?int $reassignUserId = null;

    public string $reassignReason = '';

    // ── Release modal ──
    public bool $showReleaseModal = false;

    public ?int $releaseMesaId = null;

    public string $releaseReason = '';

    // ── Status change modal ──
    public bool $showStatusModal = false;

    public ?int $statusMesaId = null;

    public string $newStatus = '';

    // ── Detail / history modal ──
    public bool $showDetailModal = false;

    public ?int $detailMesaId = null;

    // ── Group modal ──
    public bool $showGroupModal = false;

    public array $groupSelection = [];

    public string $groupName = '';

    public ?int $groupAreaId = null;

    // ── Ungroup confirm ──
    public bool $showUngroupModal = false;

    public ?int $ungroupMesaId = null;

    // ── Area CRUD modal ──
    public bool $showAreaModal = false;

    public ?int $editAreaId = null;

    public string $areaName = '';

    public string $areaColor = '#696cff';

    public string $areaIcon = 'bx-map-pin';

    public int $areaSort = 0;

    // ── Mesa CRUD modal ──
    public bool $showMesaModal = false;

    public ?int $editMesaId = null;

    public string $mesaNumber = '';

    public string $mesaName = '';

    public int $mesaCapacity = 4;

    public ?int $mesaAreaId = null;

    // ── Mesa delete confirm ──
    public bool $showDeleteMesaModal = false;

    public ?int $deleteMesaId = null;

    // ── Computed ──

    #[Computed]
    public function areas()
    {
        return Area::orderBy('sort_order')->orderBy('name')->get();
    }

    #[Computed]
    public function waiters()
    {
        return User::role(['mesero', 'cajero', 'gerente', 'admin', 'super-admin'])
            ->orderBy('name')
            ->get(['id', 'name', 'avatar']);
    }

    #[Computed]
    public function mesas()
    {
        $user = auth()->user();

        $query = Mesa::with([
            'area',
            'group',
            'currentAssignment.waiter',
            'activeOrders',
            'splits' => fn ($q) => $q->whereIn('status', ['pendiente', 'parcial'])->latest('id'),
        ]);

        // Tab filter
        if ($this->tab === 'mis_mesas') {
            $myMesaIds = MesaAssignment::where('user_id', $user->id)
                ->whereNull('released_at')
                ->pluck('mesa_id');

            // Also include group-mates so the full group card renders
            $myGroupIds = Mesa::whereIn('id', $myMesaIds)
                ->whereNotNull('mesa_group_id')
                ->pluck('mesa_group_id');
            if ($myGroupIds->isNotEmpty()) {
                $groupMateIds = Mesa::whereIn('mesa_group_id', $myGroupIds)->pluck('id');
                $myMesaIds = $myMesaIds->merge($groupMateIds)->unique()->values();
            }

            $query->whereIn('id', $myMesaIds);
        } elseif ($this->tab === 'kiosko') {
            $query->whereIn('status', ['ocupada', 'en_cuenta'])
                ->whereHas('orders', fn ($orders) => $orders
                    ->where('source', 'kiosk')
                    ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada']));
        } elseif ($this->tab === 'disponibles') {
            $query->where('status', 'disponible')
                ->whereDoesntHave('currentAssignment');
        }
        // 'todas' → admin panel: default to active mesas (ocupada + en_cuenta)
        if ($this->tab === 'todas') {
            if ($this->statusFilter) {
                $query->where('status', $this->statusFilter);
            } else {
                $query->whereIn('status', ['ocupada', 'en_cuenta']);
            }
        }

        // Area filter
        if ($this->areaFilter) {
            $query->where('area_id', $this->areaFilter);
        }

        // Status filter (non-todas tabs)
        if ($this->statusFilter && $this->tab !== 'todas') {
            $query->where('status', $this->statusFilter);
        }

        // Search by number or name
        if (trim($this->search) !== '') {
            $s = trim($this->search);
            $query->where(function ($q) use ($s) {
                $q->where('number', 'like', "%{$s}%")
                    ->orWhere('name', 'like', "%{$s}%");
            });
        }

        return $query->orderBy('number')->get();
    }

    #[Computed]
    public function detailMesa(): ?Mesa
    {
        if (! $this->detailMesaId) {
            return null;
        }

        return Mesa::with([
            'area',
            'group.mesas',
            'assignments.waiter',
            'assignments.assignedBy',
            'assignments.releasedBy',
            'activeOrders.items',
            'activeOrders.seller',
            'splits' => fn ($q) => $q->whereIn('status', ['pendiente', 'parcial'])->latest('id'),
            'orders' => fn ($q) => $q->latest()->take(20),
        ])->find($this->detailMesaId);
    }

    #[Computed]
    public function assignMesa(): ?Mesa
    {
        return $this->assignMesaId ? Mesa::with('area')->find($this->assignMesaId) : null;
    }

    #[Computed]
    public function reassignMesa(): ?Mesa
    {
        return $this->reassignMesaId
            ? Mesa::with(['area', 'currentAssignment.waiter'])->find($this->reassignMesaId)
            : null;
    }

    #[Computed]
    public function myActiveMesaCount(): int
    {
        return MesaAssignment::where('user_id', auth()->id())
            ->whereNull('released_at')
            ->count();
    }

    #[Computed]
    public function availableCount(): int
    {
        return Mesa::where('status', 'disponible')->count();
    }

    #[Computed]
    public function kioskCount(): int
    {
        return Mesa::whereIn('status', ['ocupada', 'en_cuenta'])
            ->whereHas('orders', fn ($orders) => $orders
                ->where('source', 'kiosk')
                ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada']))
            ->count();
    }

    // ── Tab helpers ──

    public function setTab(string $tab): void
    {
        abort_unless(in_array($tab, ['disponibles', 'mis_mesas', 'kiosko', 'todas'], true), 404);
        if ($tab === 'kiosko') {
            abort_unless(auth()->user()?->can('ver mesas'), 403);
        }
        $this->tab = $tab;
        $this->resetFilters();
        unset($this->mesas);
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->areaFilter = null;
        $this->statusFilter = '';
        unset($this->mesas);
    }

    public function updatedSearch(): void
    {
        unset($this->mesas);
    }

    public function updatedAreaFilter(): void
    {
        unset($this->mesas);
    }

    public function updatedStatusFilter(): void
    {
        unset($this->mesas);
    }

    // ── Assign ──

    public function openAssign(int $mesaId): void
    {
        abort_unless(auth()->user()?->can('asignar mesas'), 403);
        $this->assignMesaId = $mesaId;
        $this->showAssignModal = true;
        unset($this->assignMesa);
    }

    public function confirmAssign(): void
    {
        abort_unless(auth()->user()?->can('asignar mesas'), 403);
        $mesa = Mesa::with('activeOrders')->find($this->assignMesaId);
        $hasKioskOrder = $mesa?->activeOrders->contains(fn ($order) => $order->source === 'kiosk');
        if (! $mesa || ! in_array($mesa->status, ['disponible', 'ocupada'], true) || ($mesa->status === 'ocupada' && ! $hasKioskOrder)) {
            $this->showAssignModal = false;

            return;
        }

        $now = now();

        MesaAssignment::create([
            'mesa_id' => $mesa->id,
            'user_id' => auth()->id(),
            'assigned_by' => auth()->id(),
            'assigned_at' => $now,
        ]);

        $mesa->update(['status' => 'ocupada']);

        // If this mesa belongs to a group, mark all group members as 'ocupada'
        if ($mesa->mesa_group_id) {
            Mesa::where('mesa_group_id', $mesa->mesa_group_id)
                ->where('id', '!=', $mesa->id)
                ->update(['status' => 'ocupada']);
        }

        $this->showAssignModal = false;
        $this->assignMesaId = null;
        unset($this->mesas, $this->myActiveMesaCount, $this->availableCount, $this->assignMesa);

        session()->flash('success', "Mesa {$mesa->number} asignada. ¡Buen turno!");
        $this->setTab('mis_mesas');
    }

    // ── Reassign ──

    public function openReassign(int $mesaId): void
    {
        $this->reassignMesaId = $mesaId;
        $this->reassignUserId = null;
        $this->reassignReason = '';
        $this->showReassignModal = true;
        unset($this->reassignMesa);
    }

    public function confirmReassign(): void
    {
        $this->validate([
            'reassignUserId' => 'required|exists:users,id',
            'reassignReason' => 'nullable|string|max:255',
        ], [
            'reassignUserId.required' => 'Selecciona un mesero.',
        ]);

        $mesa = Mesa::find($this->reassignMesaId);
        if (! $mesa) {
            return;
        }

        $now = now();

        // Close current assignment
        MesaAssignment::where('mesa_id', $mesa->id)
            ->whereNull('released_at')
            ->update([
                'released_by' => auth()->id(),
                'released_at' => $now,
                'release_reason' => $this->reassignReason ?: 'Reasignación',
            ]);

        // Create new assignment
        MesaAssignment::create([
            'mesa_id' => $mesa->id,
            'user_id' => $this->reassignUserId,
            'assigned_by' => auth()->id(),
            'assigned_at' => $now,
        ]);

        $this->showReassignModal = false;
        $this->reassignMesaId = null;
        $this->reassignUserId = null;
        $this->reassignReason = '';
        unset($this->mesas, $this->reassignMesa);

        session()->flash('success', 'Mesa reasignada correctamente.');
    }

    // ── Release ──

    public function openRelease(int $mesaId): void
    {
        $this->releaseMesaId = $mesaId;
        $this->releaseReason = '';
        $this->showReleaseModal = true;
    }

    public function confirmRelease(): void
    {
        $mesa = Mesa::find($this->releaseMesaId);
        if (! $mesa) {
            return;
        }

        MesaAssignment::where('mesa_id', $mesa->id)
            ->whereNull('released_at')
            ->update([
                'released_by' => auth()->id(),
                'released_at' => now(),
                'release_reason' => $this->releaseReason ?: 'Liberación manual',
            ]);

        $this->releaseMesaAndUngroup($mesa);

        $this->showReleaseModal = false;
        $this->releaseMesaId = null;
        unset($this->mesas, $this->myActiveMesaCount, $this->availableCount);

        session()->flash('success', "Mesa {$mesa->number} liberada.");
    }

    // ── Close mesa (waiter requests bill) ──

    public function closeMesa(int $mesaId): void
    {
        $mesa = Mesa::find($mesaId);
        if (! $mesa || $mesa->status !== 'ocupada') {
            return;
        }

        $mesa->update(['status' => 'en_cuenta']);
        unset($this->mesas);

        session()->flash('success', "Mesa {$mesa->number} cerrada. Divide la cuenta antes de enviarla a caja.");
        // Cerrar mesa no cobra ni libera: abre siempre el flujo de split para
        // que cada producto quede asignado a una subcuenta antes del POS.
        $this->redirect(route('app.mesas.split', $mesa->id));
    }

    // ── Shared: release + ungroup helper ──

    private function releaseMesaAndUngroup(Mesa $mesa): void
    {
        $groupId = $mesa->mesa_group_id;

        $mesa->update(['status' => 'disponible', 'mesa_group_id' => null]);

        if ($groupId) {
            $remaining = Mesa::where('mesa_group_id', $groupId)->count();
            if ($remaining <= 1) {
                Mesa::where('mesa_group_id', $groupId)->update(['mesa_group_id' => null]);
                MesaGroup::destroy($groupId);
            }
        }
    }

    // ── Go to ordering ──

    public function goToOrden(int $mesaId): void
    {
        $this->redirect(route('app.mesas.ordenar', $mesaId));
    }

    // ── Status change ──

    public function openStatusChange(int $mesaId, string $status): void
    {
        $this->statusMesaId = $mesaId;
        $this->newStatus = $status;
        $this->showStatusModal = true;
    }

    public function confirmStatusChange(): void
    {
        $mesa = Mesa::find($this->statusMesaId);
        if (! $mesa) {
            return;
        }

        $mesa->update(['status' => $this->newStatus]);

        // If blocking, also release any assignment
        if ($this->newStatus === 'bloqueada') {
            MesaAssignment::where('mesa_id', $mesa->id)
                ->whereNull('released_at')
                ->update([
                    'released_by' => auth()->id(),
                    'released_at' => now(),
                    'release_reason' => 'Mesa bloqueada',
                ]);
        }

        $this->showStatusModal = false;
        $this->statusMesaId = null;
        unset($this->mesas);

        session()->flash('success', "Estado de Mesa {$mesa->number} actualizado.");
    }

    // ── Detail ──

    public function openDetail(int $mesaId): void
    {
        $this->detailMesaId = $mesaId;
        $this->showDetailModal = true;
        unset($this->detailMesa);
    }

    // ── Group ──

    public function openGroupModal(?int $areaId = null): void
    {
        $this->groupSelection = [];
        $this->groupName = '';
        $this->groupAreaId = $areaId;
        $this->showGroupModal = true;
    }

    public function toggleGroupSelection(int $mesaId): void
    {
        if (in_array($mesaId, $this->groupSelection)) {
            $this->groupSelection = array_values(array_filter(
                $this->groupSelection, fn ($id) => $id !== $mesaId
            ));
        } else {
            $this->groupSelection[] = $mesaId;
        }
    }

    public function confirmGroup(): void
    {
        $this->validate([
            'groupName' => 'required|string|max:80',
            'groupSelection' => 'required|array|min:2',
        ], [
            'groupName.required' => 'El nombre del grupo es requerido.',
            'groupSelection.min' => 'Selecciona al menos 2 mesas para agrupar.',
        ]);

        $mesas = Mesa::whereIn('id', $this->groupSelection)->get();
        $areaId = $mesas->first()->area_id;

        $group = MesaGroup::create([
            'area_id' => $areaId,
            'name' => $this->groupName,
        ]);

        Mesa::whereIn('id', $this->groupSelection)->update([
            'mesa_group_id' => $group->id,
        ]);

        $this->showGroupModal = false;
        $this->groupSelection = [];
        $this->groupName = '';
        unset($this->mesas);

        session()->flash('success', "Grupo '{$group->name}' creado.");
    }

    public function openUngroup(int $mesaId): void
    {
        $this->ungroupMesaId = $mesaId;
        $this->showUngroupModal = true;
    }

    public function confirmUngroup(): void
    {
        $mesa = Mesa::find($this->ungroupMesaId);
        if (! $mesa || ! $mesa->mesa_group_id) {
            return;
        }

        $groupId = $mesa->mesa_group_id;
        $groupMesas = Mesa::where('mesa_group_id', $groupId)->get();

        // If only 1 remaining after ungroup (meaning just this one), delete group
        if ($groupMesas->count() <= 2) {
            Mesa::where('mesa_group_id', $groupId)->update(['mesa_group_id' => null]);
            MesaGroup::destroy($groupId);
        } else {
            $mesa->update(['mesa_group_id' => null]);
        }

        $this->showUngroupModal = false;
        $this->ungroupMesaId = null;
        unset($this->mesas);

        session()->flash('success', 'Mesa removida del grupo.');
    }

    // ── Area CRUD ──

    public function openAreaModal(?int $areaId = null): void
    {
        $this->editAreaId = $areaId;
        if ($areaId) {
            $area = Area::find($areaId);
            $this->areaName = $area->name;
            $this->areaColor = $area->color;
            $this->areaIcon = $area->icon;
            $this->areaSort = $area->sort_order;
        } else {
            $this->areaName = '';
            $this->areaColor = '#696cff';
            $this->areaIcon = 'bx-map-pin';
            $this->areaSort = 0;
        }
        $this->showAreaModal = true;
    }

    public function saveArea(): void
    {
        $this->validate([
            'areaName' => 'required|string|max:80',
            'areaColor' => 'required|string|max:20',
            'areaIcon' => 'required|string|max:50',
            'areaSort' => 'integer|min:0',
        ]);

        Area::updateOrCreate(
            ['id' => $this->editAreaId],
            [
                'name' => $this->areaName,
                'color' => $this->areaColor,
                'icon' => $this->areaIcon,
                'sort_order' => $this->areaSort,
            ]
        );

        $this->showAreaModal = false;
        unset($this->areas, $this->mesas);
        session()->flash('success', 'Área guardada.');
    }

    public function deleteArea(int $id): void
    {
        Area::destroy($id);
        unset($this->areas, $this->mesas);
        session()->flash('success', 'Área eliminada.');
    }

    // ── Mesa CRUD ──

    public function openMesaModal(?int $mesaId = null): void
    {
        $this->editMesaId = $mesaId;
        if ($mesaId) {
            $mesa = Mesa::find($mesaId);
            $this->mesaNumber = (string) $mesa->number;
            $this->mesaName = $mesa->name ?? '';
            $this->mesaCapacity = $mesa->capacity;
            $this->mesaAreaId = $mesa->area_id;
        } else {
            $this->mesaNumber = '';
            $this->mesaName = '';
            $this->mesaCapacity = 4;
            $this->mesaAreaId = $this->areaFilter;
        }
        $this->showMesaModal = true;
    }

    public function saveMesa(): void
    {
        $this->validate([
            'mesaNumber' => 'required|integer|min:1|max:999',
            'mesaCapacity' => 'required|integer|min:1|max:50',
            'mesaAreaId' => 'required|exists:areas,id',
        ], [
            'mesaNumber.required' => 'El número de mesa es requerido.',
            'mesaAreaId.required' => 'Selecciona un área.',
        ]);

        Mesa::updateOrCreate(
            ['id' => $this->editMesaId],
            [
                'number' => $this->mesaNumber,
                'name' => $this->mesaName ?: null,
                'capacity' => $this->mesaCapacity,
                'area_id' => $this->mesaAreaId,
            ]
        );

        $this->showMesaModal = false;
        unset($this->mesas);
        session()->flash('success', 'Mesa guardada.');
    }

    public function openDeleteMesa(int $mesaId): void
    {
        $this->deleteMesaId = $mesaId;
        $this->showDeleteMesaModal = true;
    }

    public function confirmDeleteMesa(): void
    {
        Mesa::destroy($this->deleteMesaId);
        $this->showDeleteMesaModal = false;
        $this->deleteMesaId = null;
        unset($this->mesas);
        session()->flash('success', 'Mesa eliminada.');
    }

    // ── Split redirect ──

    public function goToSplit(int $mesaId): void
    {
        $this->redirect(route('app.mesas.split', $mesaId));
    }

    public function render()
    {
        return view('livewire.mesas.gestion-mesas')
            ->layout('layouts.app');
    }
}
