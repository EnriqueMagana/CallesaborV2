<?php

namespace App\Livewire\Mesas;

use App\Models\Area;
use App\Models\CashRegister;
use App\Models\Mesa;
use App\Models\MesaAssignment;
use App\Models\MesaGroup;
use App\Models\MesaHelpRequest;
use App\Models\MesaSplit;
use App\Models\Order;
use App\Models\User;
use App\Services\MesaServiceManager;
use App\Services\OperationalNotificationService;
use App\Services\ThermalTicketRenderer;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

class GestionMesas extends Component
{
    public function mount(): void
    {
        $this->requirePermission('ver mesas');

        $user = auth()->user();
        $isDedicatedWaiter = $user?->hasRole('mesero')
            && ! $user->hasAnyRole(['cajero', 'gerente', 'admin', 'super-admin']);

        if ($isDedicatedWaiter) {
            $this->tab = 'mis_mesas';

            return;
        }

        if (
            $user
            && ! $user->can('ver todas las mesas')
            && MesaAssignment::where('user_id', $user->id)->whereNull('released_at')->exists()
        ) {
            $this->tab = 'mis_mesas';
        }
    }

    // ── UI State ──
    public string $tab = 'disponibles'; // disponibles | mis_mesas | kiosko | todas

    public string $search = '';

    public ?int $areaFilter = null;

    public string $statusFilter = '';

    // ── Close account choice modal ──
    public bool $showCloseModal = false;

    public ?int $closeMesaId = null;

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

    // La vista previa vive en Livewire para no depender de transportar todo el
    // HTML del ticket mediante un evento del navegador.
    public bool $showMesaTicketPreview = false;

    public string $mesaTicketPreviewHtml = '';

    public string $mesaTicketPreviewTitle = 'Cuenta de mesa';

    // ── Collaborative service team ──
    public bool $showServiceTeamModal = false;

    public ?int $serviceTeamMesaId = null;

    public array $serviceTeamWaiterIds = [];

    public bool $serviceTeamApplyToGroup = true;

    public string $serviceTeamMessage = '';

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
        return User::query()
            ->whereNull('banned_at')
            ->where(function ($query): void {
                $query->whereHas('roles', fn ($roles) => $roles->whereIn('name', ['mesero', 'cajero', 'gerente', 'admin', 'super-admin']))
                    ->orWhereHas('permissions', fn ($permissions) => $permissions->where('name', 'ordenar mesas'))
                    ->orWhereHas('roles.permissions', fn ($permissions) => $permissions->where('name', 'ordenar mesas'));
            })
            ->orderBy('name')
            ->get(['id', 'name', 'avatar']);
    }

    #[Computed]
    public function pendingHelpRequests()
    {
        return MesaHelpRequest::query()
            ->pending()
            ->where('requested_user_id', auth()->id())
            ->with(['mesa.area', 'group', 'requester'])
            ->latest()
            ->get();
    }

    #[Computed]
    public function mesas()
    {
        $user = auth()->user();

        if ($this->tab === 'todas') {
            $this->authorizeViewAllTables();
        }

        $query = Mesa::with([
            'area',
            'group',
            'currentAssignment.waiter',
            'activeAssignments.waiter',
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

        $canViewFullAssignmentHistory = (bool) auth()->user()?->can('ver historial completo de asignaciones mesas');
        $openRegisterId = $canViewFullAssignmentHistory
            ? null
            : CashRegister::query()->where('is_open', true)->latest('id')->value('id');

        $mesa = Mesa::with([
            'area',
            'group.mesas',
            'group.mesas.activeAssignments.waiter',
            'activeAssignments.waiter',
            'assignments' => function ($query) use ($canViewFullAssignmentHistory, $openRegisterId): void {
                $query->with(['waiter', 'assignedBy', 'releasedBy'])
                    ->latest('assigned_at');

                if ($canViewFullAssignmentHistory) {
                    return;
                }

                if (! $openRegisterId) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $query->whereHas(
                    'mesaService',
                    fn ($service) => $service->where('cash_register_id', $openRegisterId),
                );
            },
            'activeOrders.items',
            'activeOrders.seller',
            'splits' => fn ($q) => $q->whereIn('status', ['pendiente', 'parcial'])->latest('id'),
            'orders' => fn ($q) => $q->latest()->take(20),
        ])->find($this->detailMesaId);

        if ($mesa) {
            $this->authorizeMesaVisibility($mesa);
        }

        return $mesa;
    }

    #[Computed]
    public function printableMesaAccount(): ?array
    {
        if (! $this->detailMesaId || ! auth()->user()?->can('reimprimir tickets')) {
            return null;
        }

        $mesa = Mesa::find($this->detailMesaId);
        if ($mesa) {
            $this->authorizeMesaVisibility($mesa);
        }
        $context = $mesa ? $this->printableAccountContext($mesa) : null;
        if (! $context) {
            return null;
        }

        $split = $context['split'];

        return [
            'service_label' => $context['service']->service_label ?: $context['mesa']->display_name,
            'total' => (float) $context['orders']->sum('total'),
            'is_split' => $split !== null,
            'split_id' => $split?->id,
            'accounts' => collect($split?->split_data ?? [])->map(fn (array $account, int $index) => [
                'index' => $index,
                'label' => $account['label'] ?? 'Cuenta '.($index + 1),
                'total' => (float) ($account['total'] ?? 0),
                'item_count' => count($account['items'] ?? []),
                'paid' => (bool) ($account['paid'] ?? false),
            ])->values()->all(),
        ];
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
    public function closingMesa(): ?Mesa
    {
        return $this->closeMesaId ? Mesa::with('group')->find($this->closeMesaId) : null;
    }

    #[Computed]
    public function serviceTeamMesa(): ?Mesa
    {
        return $this->serviceTeamMesaId
            ? Mesa::with(['area', 'group.mesas.activeAssignments.waiter', 'activeAssignments.waiter'])->find($this->serviceTeamMesaId)
            : null;
    }

    #[Computed]
    public function serviceTeamAssignments()
    {
        $mesa = $this->serviceTeamMesa;
        if (! $mesa) {
            return collect();
        }

        $assignments = $mesa->mesa_group_id
            ? $mesa->group->mesas->flatMap->activeAssignments
            : $mesa->activeAssignments;

        return $assignments
            ->sortBy(fn (MesaAssignment $assignment): int => $assignment->assignment_type === 'primary' ? 0 : 1)
            ->unique('user_id')
            ->values();
    }

    #[Computed]
    public function canDirectlyManageServiceTeam(): bool
    {
        return $this->serviceTeamMesa
            ? $this->canDirectlyManageTableSupport($this->serviceTeamMesa)
            : false;
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
        if ($tab === 'todas') {
            $this->authorizeViewAllTables();
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
            'assignment_type' => 'primary',
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
            'assignment_type' => 'primary',
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

    // ── Collaborative service team ──

    public function openServiceTeam(int $mesaId): void
    {
        $this->requirePermission('ver mesas');
        $mesa = Mesa::with(['group.mesas', 'activeAssignments'])->findOrFail($mesaId);
        $this->authorizeMesaVisibility($mesa);
        abort_unless($this->canRequestTableSupport($mesa), 403);

        $this->showDetailModal = false;
        $this->detailMesaId = null;
        $this->serviceTeamMesaId = $mesa->id;
        $this->serviceTeamWaiterIds = [];
        $this->serviceTeamApplyToGroup = $mesa->mesa_group_id !== null;
        $this->serviceTeamMessage = '';
        $this->showServiceTeamModal = true;
        unset($this->detailMesa, $this->serviceTeamMesa);
    }

    public function closeServiceTeam(): void
    {
        $this->showServiceTeamModal = false;
        $this->serviceTeamMesaId = null;
        $this->serviceTeamWaiterIds = [];
        $this->serviceTeamMessage = '';
        unset($this->serviceTeamMesa);
    }

    public function addSupportWaiters(): void
    {
        $mesa = Mesa::with('group.mesas')->findOrFail($this->serviceTeamMesaId);
        abort_unless($this->canDirectlyManageTableSupport($mesa), 403);
        $waiterIds = $this->validatedServiceTeamWaiterIds();
        $memberIds = $this->serviceTeamMesaIds($mesa, $this->serviceTeamApplyToGroup);

        $result = DB::transaction(function () use ($mesa, $memberIds, $waiterIds): array {
            Mesa::query()->whereKey($memberIds)->orderBy('id')->lockForUpdate()->get();
            $register = CashRegister::where('is_open', true)->latest('id')->first();
            $service = $register
                ? app(MesaServiceManager::class)->findActiveForMesa($mesa, $register->id)
                : null;
            $added = 0;
            $addedWaiterIds = [];

            foreach ($memberIds as $memberId) {
                foreach ($waiterIds as $waiterId) {
                    $alreadyAssigned = MesaAssignment::query()
                        ->where('mesa_id', $memberId)
                        ->where('user_id', $waiterId)
                        ->whereNull('released_at')
                        ->exists();

                    if ($alreadyAssigned) {
                        continue;
                    }

                    MesaAssignment::create([
                        'mesa_id' => $memberId,
                        'mesa_service_id' => $service?->id,
                        'user_id' => $waiterId,
                        'assignment_type' => 'support',
                        'assigned_by' => auth()->id(),
                        'assigned_at' => now(),
                    ]);
                    $added++;
                    $addedWaiterIds[] = $waiterId;
                }
            }

            return ['assignments' => $added, 'waiter_ids' => array_values(array_unique($addedWaiterIds))];
        });

        $addedWaiters = User::query()->whereKey($result['waiter_ids'])->get();
        foreach ($addedWaiters as $waiter) {
            app(OperationalNotificationService::class)->mesaSupportAssigned(
                $mesa,
                $waiter,
                auth()->user(),
                $this->serviceTeamApplyToGroup && $mesa->mesa_group_id !== null,
            );
        }

        $this->serviceTeamWaiterIds = [];
        $this->refreshTableTeamState();
        $this->dispatch('notify', type: $result['assignments'] ? 'success' : 'info', message: $result['assignments']
            ? 'Los meseros seleccionados ya forman parte del equipo de servicio.'
            : 'Los meseros seleccionados ya estaban asignados.');
    }

    public function requestTableSupport(): void
    {
        $mesa = Mesa::with('group.mesas')->findOrFail($this->serviceTeamMesaId);
        abort_unless($this->canRequestTableSupport($mesa), 403);
        $waiterIds = $this->validatedServiceTeamWaiterIds();
        $scope = $this->serviceTeamApplyToGroup && $mesa->mesa_group_id ? 'group' : 'table';
        $memberIds = $this->serviceTeamMesaIds($mesa, $scope === 'group');
        $requests = [];

        DB::transaction(function () use ($mesa, $waiterIds, $scope, $memberIds, &$requests): void {
            foreach ($waiterIds as $waiterId) {
                $alreadyAssigned = MesaAssignment::query()
                    ->whereIn('mesa_id', $memberIds)
                    ->where('user_id', $waiterId)
                    ->whereNull('released_at')
                    ->exists();
                if ($alreadyAssigned) {
                    continue;
                }

                $request = MesaHelpRequest::query()->firstOrNew([
                    'mesa_id' => $mesa->id,
                    'requested_user_id' => $waiterId,
                    'status' => MesaHelpRequest::STATUS_PENDING,
                ]);
                $request->fill([
                    'mesa_group_id' => $scope === 'group' ? $mesa->mesa_group_id : null,
                    'requested_by' => auth()->id(),
                    'scope' => $scope,
                    'message' => trim($this->serviceTeamMessage) ?: null,
                ])->save();
                $requests[] = $request;
            }
        });

        foreach ($requests as $request) {
            app(OperationalNotificationService::class)->mesaHelpRequested($request);
        }

        $this->serviceTeamWaiterIds = [];
        $this->serviceTeamMessage = '';
        $this->dispatch('notify', type: $requests ? 'success' : 'info', message: $requests
            ? 'Solicitud de apoyo enviada. La asignación se activará cuando sea aceptada.'
            : 'Los meseros seleccionados ya participan en este servicio.');
    }

    public function respondToHelpRequest(int $requestId, bool $accept): void
    {
        $request = DB::transaction(function () use ($requestId, $accept): MesaHelpRequest {
            $request = MesaHelpRequest::query()->lockForUpdate()->findOrFail($requestId);
            abort_unless($request->requested_user_id === auth()->id(), 403);
            abort_unless($request->status === MesaHelpRequest::STATUS_PENDING, 409);

            $mesa = Mesa::with('group.mesas')->find($request->mesa_id);
            if ($accept && $mesa && in_array($mesa->status, ['ocupada', 'en_cuenta'], true)) {
                $memberIds = $this->serviceTeamMesaIds($mesa, $request->scope === 'group');
                Mesa::query()->whereKey($memberIds)->orderBy('id')->lockForUpdate()->get();
                $register = CashRegister::where('is_open', true)->latest('id')->first();
                $service = $register
                    ? app(MesaServiceManager::class)->findActiveForMesa($mesa, $register->id)
                    : null;

                foreach ($memberIds as $memberId) {
                    if (MesaAssignment::query()->where('mesa_id', $memberId)->where('user_id', auth()->id())->whereNull('released_at')->exists()) {
                        continue;
                    }
                    MesaAssignment::create([
                        'mesa_id' => $memberId,
                        'mesa_service_id' => $service?->id,
                        'user_id' => auth()->id(),
                        'assignment_type' => 'support',
                        'assigned_by' => $request->requested_by,
                        'assigned_at' => now(),
                    ]);
                }
            } elseif ($accept) {
                $accept = false;
            }

            $request->update([
                'status' => $accept ? MesaHelpRequest::STATUS_ACCEPTED : MesaHelpRequest::STATUS_DECLINED,
                'responded_at' => now(),
            ]);

            return $request->fresh(['mesa', 'requester', 'requestedUser']);
        });

        app(OperationalNotificationService::class)->mesaHelpResponded($request);
        unset($this->pendingHelpRequests, $this->mesas, $this->myActiveMesaCount);
        $this->dispatch('notify', type: $request->status === MesaHelpRequest::STATUS_ACCEPTED ? 'success' : 'info', message: $request->status === MesaHelpRequest::STATUS_ACCEPTED
                ? 'Apoyo aceptado. La mesa ya aparece en Mis mesas.'
                : 'Solicitud de apoyo rechazada.');
    }

    public function removeSupportWaiter(int $assignmentId): void
    {
        $assignment = MesaAssignment::with('mesa')->findOrFail($assignmentId);
        abort_unless($assignment->assignment_type === 'support' && $assignment->released_at === null, 422);
        abort_unless($this->canDirectlyManageTableSupport($assignment->mesa), 403);
        $memberIds = $this->serviceTeamMesaIds($assignment->mesa, true);

        MesaAssignment::query()
            ->whereIn('mesa_id', $memberIds)
            ->where('user_id', $assignment->user_id)
            ->where('assignment_type', 'support')
            ->whereNull('released_at')
            ->update([
                'released_by' => auth()->id(),
                'released_at' => now(),
                'release_reason' => 'Fin de apoyo en servicio',
            ]);

        $this->refreshTableTeamState();
        $this->dispatch('notify', type: 'success', message: 'El apoyo fue retirado del equipo de servicio.');
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

        $hasActiveOrders = $service
            ? $service->orders()->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada'])->exists()
            : Order::whereIn('mesa_id', $memberIds)
                ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada'])
                ->exists();
        $hasActiveSplit = $service
            ? $service->splits()->whereIn('status', ['pendiente', 'parcial'])->exists()
            : MesaSplit::whereIn('mesa_id', $memberIds)->whereIn('status', ['pendiente', 'parcial'])->exists();

        if ($hasActiveOrders || $hasActiveSplit) {
            $this->showReleaseModal = false;
            $this->dispatch('notify', type: 'warning', message: 'No puedes liberar una mesa con pedidos o subcuentas pendientes. Cierra y cobra la cuenta primero.');

            return;
        }

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

    public function openCloseMesa(int $mesaId): void
    {
        $this->requirePermission('cerrar mesas');
        $mesa = Mesa::with('currentAssignment')->find($mesaId);
        if (! $mesa || $mesa->status !== 'ocupada') {
            $this->dispatch('notify', type: 'warning', message: 'La mesa ya no está disponible para cerrar.');

            return;
        }

        $this->authorizeWaiterMesa($mesa);
        // The close choice replaces table detail; never stack two modal layers.
        $this->showDetailModal = false;
        $this->detailMesaId = null;
        $this->closeMesaId = $mesa->id;
        $this->showCloseModal = true;
        unset($this->detailMesa, $this->closingMesa);
    }

    public function closeCloseModal(): void
    {
        $this->showCloseModal = false;
        $this->closeMesaId = null;
        unset($this->closingMesa);
    }

    public function confirmCloseMesa(string $mode): void
    {
        abort_unless(in_array($mode, ['full', 'split'], true), 422);
        abort_unless($this->closeMesaId, 422);

        if ($mode === 'split') {
            $this->requirePermission('dividir mesas');
        }

        $mesaId = $this->closeMesaId;
        $this->closeCloseModal();
        $this->closeMesa($mesaId, $mode === 'split');
    }

    public function closeMesa(int $mesaId, bool $divide = false): void
    {
        $this->requirePermission('cerrar mesas');
        $mesa = Mesa::with('currentAssignment')->find($mesaId);
        if (! $mesa || $mesa->status !== 'ocupada') {
            return;
        }

        $this->authorizeWaiterMesa($mesa);

        $register = CashRegister::where('is_open', true)->latest('id')->first();
        if (! $register) {
            $this->dispatch('notify', type: 'warning', message: 'No hay una caja abierta para cerrar la mesa.');

            return;
        }

        $manager = app(MesaServiceManager::class);
        $service = $manager->resolveOrCreate($mesa, $register, auth()->id());
        $activeSplit = $service->splits()->whereIn('status', ['pendiente', 'parcial'])->exists();
        if ($activeSplit) {
            $this->dispatch('notify', type: 'warning', message: 'Esta mesa ya tiene una cuenta dividida pendiente.');

            return;
        }

        $service = $manager->markInAccount($mesa, $register->id);
        $memberIds = $service?->mesas()->pluck('mesas.id')->all() ?: [$mesa->id];
        Mesa::whereIn('id', $memberIds)->update(['status' => 'en_cuenta']);
        unset($this->mesas);

        session()->flash('success', $divide
            ? "Mesa {$mesa->number} cerrada. Divide la cuenta y envíala a caja."
            : "Mesa {$mesa->number} cerrada y enviada a caja para cobro conjunto.");

        if ($divide) {
            $this->redirect(route('app.mesas.split', $mesa->id));
        }
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

        $hasOperationalState = $mesa->currentAssignment()->exists()
            || $mesa->activeOrders()->exists()
            || $mesa->splits()->whereIn('status', ['pendiente', 'parcial'])->exists();
        if ($hasOperationalState) {
            $this->showStatusModal = false;
            $this->statusMesaId = null;
            $this->dispatch('notify', type: 'warning', message: 'No puedes cambiar manualmente el estado de una mesa con servicio activo. Usa cerrar, reabrir o cobrar.');

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
        $mesa = Mesa::findOrFail($mesaId);
        $this->authorizeMesaVisibility($mesa);
        $this->detailMesaId = $mesa->id;
        $this->showDetailModal = true;
        unset($this->detailMesa);
    }

    public function printActiveMesaAccount(
        int $mesaId,
        ?int $splitId = null,
        ?int $accountIndex = null,
    ): void {
        $this->requirePermission('reimprimir tickets');

        $mesa = Mesa::with('currentAssignment.waiter')->find($mesaId);
        if ($mesa) {
            $this->authorizeMesaVisibility($mesa);
        }
        $context = $mesa ? $this->printableAccountContext($mesa) : null;
        if (! $context) {
            $this->dispatch('notify', type: 'warning', message: 'Solo puedes imprimir una cuenta vigente de una mesa en estado En cuenta.');

            return;
        }

        $split = $context['split'];
        $label = $context['service']->service_label ?: $context['mesa']->display_name;
        $items = [];
        $total = (float) $context['orders']->sum('total');
        $payments = [];
        $cashierName = auth()->user()->name;
        $trackingOrder = $context['orders']->first();

        if ($split) {
            if ($splitId !== $split->id || $accountIndex === null) {
                $this->dispatch('notify', type: 'warning', message: 'Selecciona una subcuenta pendiente para imprimir.');

                return;
            }

            $account = ($split->split_data ?? [])[$accountIndex] ?? null;
            if (! $account) {
                $this->dispatch('notify', type: 'warning', message: 'Esta subcuenta dejó de estar disponible.');

                return;
            }

            $label = (string) ($account['label'] ?? 'Cuenta '.($accountIndex + 1));
            $items = collect($account['items'] ?? [])->map(fn (array $item) => [
                'qty' => (float) ($item['qty'] ?? 1),
                'name' => (string) ($item['name'] ?? 'Producto'),
                'subtotal' => (float) ($item['subtotal'] ?? 0),
            ])->values()->all();
            $total = (float) ($account['total'] ?? 0);
            $payments = (array) ($account['payments'] ?? []);
            $cashierName = User::find($account['paid_by'] ?? null)?->name ?? $cashierName;
            $trackingOrder = $context['orders']->firstWhere('id', (int) ($account['tracking_order_id'] ?? 0))
                ?? $trackingOrder;
        } else {
            if ($splitId !== null || $accountIndex !== null) {
                $this->dispatch('notify', type: 'warning', message: 'La cuenta vigente ya no coincide con la selección. Actualiza el detalle e intenta de nuevo.');

                return;
            }

            $items = $context['orders']->flatMap(fn (Order $order) => $order->items
                ->reject(fn ($item) => (bool) $item->is_cancelled)
                ->map(fn ($item) => [
                    'qty' => (float) $item->quantity,
                    'name' => $item->product_name,
                    'subtotal' => (float) $item->subtotal,
                ]))->values()->all();
        }

        if ($items === []) {
            $this->dispatch('notify', type: 'warning', message: 'La cuenta no tiene productos vigentes para imprimir.');

            return;
        }

        $assignment = $context['service']->assignments()
            ->with('waiter')
            ->whereNull('released_at')
            ->latest('assigned_at')
            ->first() ?? $mesa->currentAssignment;

        $html = app(ThermalTicketRenderer::class)->renderMesaAccount(
            mesa: $context['mesa'],
            accountLabel: $label,
            items: $items,
            total: $total,
            payments: $payments,
            assignment: $assignment,
            cashierName: $cashierName,
            autoPrint: false,
            trackingUrl: $trackingOrder instanceof Order
                ? route('kiosk.track', $trackingOrder->ensurePublicToken())
                : null,
        );

        $this->mesaTicketPreviewHtml = $html;
        $this->mesaTicketPreviewTitle = $label;
        $this->showMesaTicketPreview = true;

    }

    public function closeMesaTicketPreview(): void
    {
        $this->showMesaTicketPreview = false;
        $this->mesaTicketPreviewHtml = '';
        $this->mesaTicketPreviewTitle = 'Cuenta de mesa';
    }

    private function printableAccountContext(Mesa $mesa): ?array
    {
        if ($mesa->status !== 'en_cuenta') {
            return null;
        }

        $register = CashRegister::where('is_open', true)->latest('id')->first();
        if (! $register) {
            return null;
        }

        $service = app(MesaServiceManager::class)->findActiveForMesa($mesa, $register->id);
        if (! $service || $service->status !== 'en_cuenta') {
            return null;
        }

        $orders = $service->orders()
            ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada'])
            ->with('items')
            ->get();
        if ($orders->isEmpty()) {
            return null;
        }

        $split = $service->splits()
            ->whereIn('status', ['pendiente', 'parcial'])
            ->latest('id')
            ->first();

        return [
            'mesa' => $service->primaryMesa()->with('area')->first() ?? $mesa->loadMissing('area'),
            'service' => $service,
            'orders' => $orders,
            'split' => $split,
        ];
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
        $mesa = Mesa::find($mesaId);
        if (! $mesa || ! $mesa->mesa_group_id || ! $this->groupCanBeModified($mesa->mesa_group_id)) {
            $this->dispatch('notify', type: 'warning', message: 'No puedes desagrupar mesas mientras el grupo tenga un servicio activo.');

            return;
        }

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
        if (! $this->groupCanBeModified($groupId)) {
            $this->showUngroupModal = false;
            $this->ungroupMesaId = null;
            $this->dispatch('notify', type: 'warning', message: 'No puedes desagrupar mesas mientras el grupo tenga un servicio activo.');

            return;
        }

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
        $mesa = Mesa::find($mesaId);
        if (! $mesa || $mesa->status !== 'en_cuenta') {
            $this->dispatch('notify', type: 'warning', message: 'Primero cierra la mesa y elige dividir la cuenta.');

            return;
        }

        $this->redirect(route('app.mesas.split', $mesaId));
    }

    private function authorizeWaiterMesa(Mesa $mesa): void
    {
        $user = auth()->user();
        if ($user?->hasRole('mesero') && ! $user->hasAnyRole(['cajero', 'gerente', 'admin', 'super-admin'])) {
            abort_unless($this->userParticipatesInTableService($mesa, $user->id), 403);
        }
    }

    private function canRequestTableSupport(Mesa $mesa): bool
    {
        $user = auth()->user();

        return (bool) ($user && (
            $user->can('reasignar mesas')
            || $this->userParticipatesInTableService($mesa, $user->id)
        ));
    }

    private function canDirectlyManageTableSupport(Mesa $mesa): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->can('reasignar mesas')) {
            return true;
        }

        $memberIds = $this->serviceTeamMesaIds($mesa, true);

        return MesaAssignment::query()
            ->whereIn('mesa_id', $memberIds)
            ->where('user_id', $user->id)
            ->where('assignment_type', 'primary')
            ->whereNull('released_at')
            ->exists();
    }

    private function userParticipatesInTableService(Mesa $mesa, int $userId): bool
    {
        return $mesa->hasActiveAssignmentFor($userId);
    }

    private function serviceTeamMesaIds(Mesa $mesa, bool $includeGroup): array
    {
        if ($includeGroup && $mesa->mesa_group_id) {
            return Mesa::query()->where('mesa_group_id', $mesa->mesa_group_id)->pluck('id')->all();
        }

        return [$mesa->id];
    }

    private function validatedServiceTeamWaiterIds(): array
    {
        $this->validate([
            'serviceTeamWaiterIds' => ['required', 'array', 'min:1'],
            'serviceTeamWaiterIds.*' => ['integer', 'distinct'],
            'serviceTeamMessage' => ['nullable', 'string', 'max:255'],
        ], [
            'serviceTeamWaiterIds.required' => 'Selecciona al menos un mesero.',
            'serviceTeamWaiterIds.min' => 'Selecciona al menos un mesero.',
        ]);

        $allowedIds = $this->waiters->pluck('id');
        $waiterIds = collect($this->serviceTeamWaiterIds)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->filter(fn (int $id): bool => $id !== auth()->id() && $allowedIds->contains($id))
            ->values();

        abort_unless($waiterIds->count() === count(array_unique($this->serviceTeamWaiterIds)), 422);

        return $waiterIds->all();
    }

    private function refreshTableTeamState(): void
    {
        unset($this->mesas, $this->detailMesa, $this->serviceTeamMesa, $this->myActiveMesaCount);
    }

    private function groupCanBeModified(int $groupId): bool
    {
        $memberIds = Mesa::where('mesa_group_id', $groupId)->pluck('id');

        return ! Mesa::whereIn('id', $memberIds)->where('status', '!=', 'disponible')->exists()
            && ! MesaAssignment::whereIn('mesa_id', $memberIds)->whereNull('released_at')->exists()
            && ! Order::whereIn('mesa_id', $memberIds)
                ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada'])
                ->exists()
            && ! MesaSplit::whereIn('mesa_id', $memberIds)->whereIn('status', ['pendiente', 'parcial'])->exists();
    }

    private function requirePermission(string $permission, ?string $fallback = null): void
    {
        $user = auth()->user();

        abort_unless(
            $user && ($user->can($permission) || ($fallback && $user->can($fallback))),
            403
        );
    }

    private function authorizeViewAllTables(): void
    {
        $user = auth()->user();

        abort_unless(
            $user && $user->can('ver todas las mesas'),
            403
        );
    }

    private function authorizeMesaVisibility(Mesa $mesa): void
    {
        $user = auth()->user();

        abort_unless($user && $user->can('ver mesas'), 403);

        if ($user->can('ver todas las mesas')) {
            return;
        }

        $visibleMesaIds = MesaAssignment::query()
            ->where('user_id', $user->id)
            ->whereNull('released_at')
            ->pluck('mesa_id');

        if ($visibleMesaIds->contains($mesa->id)) {
            return;
        }

        if ($mesa->mesa_group_id) {
            $hasAssignedGroupMember = Mesa::query()
                ->where('mesa_group_id', $mesa->mesa_group_id)
                ->whereIn('id', $visibleMesaIds)
                ->exists();

            if ($hasAssignedGroupMember) {
                return;
            }
        }

        $isKioskMesa = $mesa->orders()
            ->where('source', 'kiosk')
            ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada'])
            ->exists();

        abort_unless($isKioskMesa, 403);
    }

    public function render()
    {
        return view('livewire.mesas.gestion-mesas')
            ->layout('layouts.app');
    }
}
