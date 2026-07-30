<?php

namespace App\Livewire\Mesas;

use App\Models\Area;
use App\Models\CashRegister;
use App\Models\Mesa;
use App\Models\MesaAssignment;
use App\Models\MesaGroup;
use App\Models\User;
use App\Services\MesaServiceManager;
use Livewire\Attributes\Computed;
use Livewire\Component;

class GestionMesas extends Component
{
    public function mount(): void
    {
        $this->requirePermission('ver mesas');
    }

    // ── UI State ──
    public string $tab = 'disponibles'; // disponibles | mis_mesas | kiosko | todas

    public string $search = '';

    public ?int $areaFilter = null;

    public string $statusFilter = '';

    public function applySearch(): void
    {
        $this->search = trim($this->search);
        unset($this->mesas);
    }

    public function clearSearch(): void
    {
        $this->search = '';
        unset($this->mesas);
    }

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
        $register = CashRegister::where('is_open', true)->latest('id')->first();
        $service = $register
            ? app(MesaServiceManager::class)->resolveOrCreate($mesa, $register, auth()->id())
            : null;

        MesaAssignment::create([
            'mesa_id' => $mesa->id,
            'mesa_service_id' => $service?->id,
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
        $this->requirePermission('reasignar mesas');
        $this->reassignMesaId = $mesaId;
        $this->reassignUserId = null;
        $this->reassignReason = '';
        $this->showReassignModal = true;
        unset($this->reassignMesa);
    }

    public function confirmReassign(): void
    {
        $this->requirePermission('reasignar mesas');
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
        $register = CashRegister::where('is_open', true)->latest('id')->first();
        $service = $register
            ? app(MesaServiceManager::class)->findActiveForMesa($mesa, $register->id)
            : null;

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
            'mesa_service_id' => $service?->id,
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
        $this->requirePermission('liberar mesas');
        $this->releaseMesaId = $mesaId;
        $this->releaseReason = '';
        $this->showReleaseModal = true;
    }

    public function confirmRelease(): void
    {
        $this->requirePermission('liberar mesas');
        $mesa = Mesa::find($this->releaseMesaId);
        if (! $mesa) {
            return;
        }

        $register = CashRegister::where('is_open', true)->latest('id')->first();
        $service = $register
            ? app(MesaServiceManager::class)->findActiveForMesa($mesa, $register->id)
            : null;
        $memberIds = $service?->mesas()->pluck('mesas.id')->all() ?: [$mesa->id];

        MesaAssignment::whereIn('mesa_id', $memberIds)
            ->whereNull('released_at')
            ->update([
                'released_by' => auth()->id(),
                'released_at' => now(),
                'release_reason' => $this->releaseReason ?: 'Liberación manual',
            ]);

        if ($service) {
            app(MesaServiceManager::class)->releaseWithoutPayment(
                $service,
                auth()->id(),
                $this->releaseReason ?: 'Liberación manual'
            );
        }

        $this->releaseMesaAndUngroup($mesa, $memberIds);

        $this->showReleaseModal = false;
        $this->releaseMesaId = null;
        unset($this->mesas, $this->myActiveMesaCount, $this->availableCount);

        session()->flash('success', "Mesa {$mesa->number} liberada.");
    }

    // ── Close mesa (waiter requests bill) ──

    public function closeMesa(int $mesaId): void
    {
        $this->requirePermission('cerrar mesas');
        $mesa = Mesa::find($mesaId);
        if (! $mesa || $mesa->status !== 'ocupada') {
            return;
        }

        $register = CashRegister::where('is_open', true)->latest('id')->first();
        $service = $register
            ? app(MesaServiceManager::class)->markInAccount($mesa, $register->id)
            : null;
        $memberIds = $service?->mesas()->pluck('mesas.id')->all() ?: [$mesa->id];
        Mesa::whereIn('id', $memberIds)->update(['status' => 'en_cuenta']);
        unset($this->mesas);

        session()->flash('success', "Mesa {$mesa->number} cerrada. Divide la cuenta antes de enviarla a caja.");
        // Cerrar mesa no cobra ni libera: abre siempre el flujo de split para
        // que cada producto quede asignado a una subcuenta antes del POS.
        $this->redirect(route('app.mesas.split', $mesa->id));
    }

    // ── Shared: release + ungroup helper ──

    private function releaseMesaAndUngroup(Mesa $mesa, ?array $memberIds = null): void
    {
        $groupId = $mesa->mesa_group_id;
        $memberIds ??= $groupId
            ? Mesa::where('mesa_group_id', $groupId)->pluck('id')->all()
            : [$mesa->id];

        Mesa::whereIn('id', $memberIds)
            ->update(['status' => 'disponible', 'mesa_group_id' => null]);

        if ($groupId) {
            MesaGroup::destroy($groupId);
        }
    }

    // ── Go to ordering ──

    public function goToOrden(int $mesaId): void
    {
        $this->requirePermission('ordenar mesas');
        $this->redirect(route('app.mesas.ordenar', $mesaId));
    }

    // ── Status change ──

    public function openStatusChange(int $mesaId, string $status): void
    {
        $this->requirePermission('cambiar estado mesas', 'gestionar mesas');
        abort_unless(in_array($status, ['disponible', 'ocupada', 'reservada', 'en_cuenta', 'bloqueada'], true), 422);
        $this->statusMesaId = $mesaId;
        $this->newStatus = $status;
        $this->showStatusModal = true;
    }

    public function confirmStatusChange(): void
    {
        $this->requirePermission('cambiar estado mesas', 'gestionar mesas');
        abort_unless(in_array($this->newStatus, ['disponible', 'ocupada', 'reservada', 'en_cuenta', 'bloqueada'], true), 422);
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
        $this->requirePermission('ver mesas');
        $this->detailMesaId = $mesaId;
        $this->showDetailModal = true;
        unset($this->detailMesa);
    }

    // ── Group ──

    public function openGroupModal(?int $areaId = null): void
    {
        $this->requirePermission('gestionar grupos');
        $this->groupSelection = [];
        $this->groupName = '';
        $this->groupAreaId = $areaId;
        $this->showGroupModal = true;
    }

    public function toggleGroupSelection(int $mesaId): void
    {
        $this->requirePermission('gestionar grupos');
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
        $this->requirePermission('gestionar grupos');
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
        $this->requirePermission('gestionar grupos');
        $this->ungroupMesaId = $mesaId;
        $this->showUngroupModal = true;
    }

    public function confirmUngroup(): void
    {
        $this->requirePermission('gestionar grupos');
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
        $this->requirePermission(
            $areaId ? 'editar areas de mesas' : 'crear areas de mesas',
            'gestionar mesas'
        );
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
        $this->requirePermission(
            $this->editAreaId ? 'editar areas de mesas' : 'crear areas de mesas',
            'gestionar mesas'
        );
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
        $this->requirePermission('eliminar areas de mesas', 'gestionar mesas');
        Area::destroy($id);
        unset($this->areas, $this->mesas);
        session()->flash('success', 'Área eliminada.');
    }

    // ── Mesa CRUD ──

    public function openMesaModal(?int $mesaId = null): void
    {
        $this->requirePermission(
            $mesaId ? 'editar mesas' : 'crear mesas',
            'gestionar mesas'
        );
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
        $this->requirePermission(
            $this->editMesaId ? 'editar mesas' : 'crear mesas',
            'gestionar mesas'
        );
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
        $this->requirePermission('eliminar mesas', 'gestionar mesas');
        $this->deleteMesaId = $mesaId;
        $this->showDeleteMesaModal = true;
    }

    public function confirmDeleteMesa(): void
    {
        $this->requirePermission('eliminar mesas', 'gestionar mesas');
        Mesa::destroy($this->deleteMesaId);
        $this->showDeleteMesaModal = false;
        $this->deleteMesaId = null;
        unset($this->mesas);
        session()->flash('success', 'Mesa eliminada.');
    }

    // ── Split redirect ──

    public function goToSplit(int $mesaId): void
    {
        $this->requirePermission('dividir mesas');
        $this->redirect(route('app.mesas.split', $mesaId));
    }

    private function requirePermission(string $permission, ?string $fallback = null): void
    {
        $user = auth()->user();

        abort_unless(
            $user && ($user->can($permission) || ($fallback && $user->can($fallback))),
            403
        );
    }

    public function render()
    {
        return view('livewire.mesas.gestion-mesas')
            ->layout('layouts.app');
    }
}
