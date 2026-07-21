<?php

namespace App\Livewire\Admin;

use App\Models\SidebarMenuItem;
use App\Services\CashRegisterModuleAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Spatie\Permission\Models\Permission;

class SidebarMenuManager extends Component
{
    public ?int $editingId = null;

    public ?int $parentId = null;

    public string $type = 'link';

    public string $label = '';

    public string $icon = 'bx-circle';

    public string $routeName = '';

    public string $url = '';

    public string $activePattern = '';

    public string $permission = '';

    public bool $isActive = true;

    public bool $requiresOpenRegister = false;

    public bool $showRegisterPolicy = false;

    public array $registerPolicies = [];

    public const ICONS = [
        'bx-home-circle', 'bx-grid-alt', 'bx-store', 'bx-store-alt', 'bx-receipt',
        'bx-table', 'bx-calendar-check', 'bx-calculator', 'bx-history', 'bx-group',
        'bx-user', 'bx-shield-quarter', 'bx-cog', 'bx-devices', 'bx-restaurant',
        'bx-dish', 'bx-list-ul', 'bx-package', 'bx-cycling', 'bx-wallet', 'bx-chart',
    ];

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('ver menu sidebar'), 403);
    }

    #[Computed]
    public function menuTree()
    {
        $roots = SidebarMenuItem::query()
            ->whereNull('parent_id')
            ->with(['children.children'])
            ->orderBy('sort_order')->orderBy('id')->get();

        return $this->decorateSiblings($roots);
    }

    #[Computed]
    public function parentOptions()
    {
        return SidebarMenuItem::query()
            ->whereIn('type', $this->type === 'group' ? ['section'] : ['section', 'group'])
            ->when($this->editingId, fn ($query) => $query->whereKeyNot($this->editingId))
            ->with('parent:id,label')
            ->orderByRaw("CASE type WHEN 'section' THEN 0 ELSE 1 END")
            ->orderBy('sort_order')->orderBy('label')->get();
    }

    #[Computed]
    public function permissions()
    {
        return Permission::query()->orderBy('group')->orderBy('name')->get();
    }

    #[Computed]
    public function appRoutes(): array
    {
        return collect(Route::getRoutes()->getRoutesByName())
            ->filter(fn ($route, $name) => $name === 'profile' || str_starts_with($name, 'app.'))
            ->keys()->sort()->values()->all();
    }

    #[Computed]
    public function canRequireOpenRegister(): bool
    {
        return $this->type === 'link'
            && $this->routeName !== ''
            && ! CashRegisterModuleAccess::isAlwaysAvailable($this->routeName);
    }

    #[Computed]
    public function protectedModuleCount(): int
    {
        return SidebarMenuItem::query()->where('requires_open_register', true)->count();
    }

    public function openRegisterPolicy(): void
    {
        $this->authorizeAction('gestionar bloqueos por caja');
        $this->registerPolicies = SidebarMenuItem::query()
            ->where('type', 'link')
            ->whereNotNull('route_name')
            ->whereNotIn('route_name', CashRegisterModuleAccess::ALWAYS_AVAILABLE_ROUTES)
            ->orderBy('label')
            ->get(['id', 'label', 'icon', 'route_name', 'requires_open_register'])
            ->map(fn (SidebarMenuItem $item) => [
                'id' => $item->id,
                'label' => $item->label,
                'icon' => $item->icon ?: 'bx-link',
                'route' => $item->route_name,
                'requires' => (bool) $item->requires_open_register,
            ])->values()->all();
        $this->showRegisterPolicy = true;
        $this->resetValidation();
    }

    public function closeRegisterPolicy(): void
    {
        $this->showRegisterPolicy = false;
        $this->registerPolicies = [];
        $this->resetValidation();
    }

    public function saveRegisterPolicies(): void
    {
        $this->authorizeAction('gestionar bloqueos por caja');
        $validated = $this->validate([
            'registerPolicies' => 'required|array',
            'registerPolicies.*.id' => 'required|integer|distinct',
            'registerPolicies.*.requires' => 'required|boolean',
        ]);

        $eligibleItems = SidebarMenuItem::query()
            ->where('type', 'link')
            ->whereNotNull('route_name')
            ->whereNotIn('route_name', CashRegisterModuleAccess::ALWAYS_AVAILABLE_ROUTES)
            ->get(['id', 'requires_open_register'])
            ->keyBy('id');
        $submitted = collect($validated['registerPolicies'])->keyBy('id');

        abort_unless(
            $submitted->keys()->diff($eligibleItems->keys())->isEmpty()
            && $eligibleItems->keys()->diff($submitted->keys())->isEmpty(),
            422
        );

        $changes = $eligibleItems->filter(function (SidebarMenuItem $item) use ($submitted): bool {
            return $item->requires_open_register !== (bool) data_get($submitted->get($item->id), 'requires', false);
        });

        if ($changes->isNotEmpty()) {
            $cases = $changes->map(function (SidebarMenuItem $item) use ($submitted): string {
                $requires = (bool) data_get($submitted->get($item->id), 'requires', false);

                return 'WHEN '.((int) $item->id).' THEN '.($requires ? '1' : '0');
            })->implode(' ');

            DB::table('sidebar_menu_items')
                ->whereIn('id', $changes->keys())
                ->update([
                    'requires_open_register' => DB::raw("CASE id {$cases} ELSE requires_open_register END"),
                    'updated_by' => auth()->id(),
                    'updated_at' => now(),
                ]);
        }

        $this->showRegisterPolicy = false;
        $this->registerPolicies = [];
        unset($this->menuTree, $this->protectedModuleCount);
        $this->dispatch('sidebar-menu-updated');
        session()->flash('menu_success', 'Política de acceso por caja actualizada.');
    }

    public function createItem(string $type = 'link', ?int $parentId = null): void
    {
        $this->authorizeAction('crear menu sidebar');
        $this->resetEditor();
        $this->type = in_array($type, ['section', 'group', 'link'], true) ? $type : 'link';
        $this->parentId = $parentId;
    }

    public function editItem(int $id): void
    {
        $this->authorizeAction('editar menu sidebar');
        $item = SidebarMenuItem::findOrFail($id);
        $this->editingId = $item->id;
        $this->parentId = $item->parent_id;
        $this->type = $item->type;
        $this->label = $item->label;
        $this->icon = $item->icon ?? '';
        $this->routeName = $item->route_name ?? '';
        $this->url = $item->url ?? '';
        $this->activePattern = $item->active_pattern ?? '';
        $this->permission = $item->permission ?? '';
        $this->isActive = $item->is_active;
        $this->requiresOpenRegister = $item->requires_open_register;
        $this->resetValidation();
    }

    public function saveItem(): void
    {
        $this->authorizeAction($this->editingId ? 'editar menu sidebar' : 'crear menu sidebar');
        if ($this->type !== 'link') {
            $this->requiresOpenRegister = false;
        }

        $validated = $this->validate([
            'type' => 'required|in:section,group,link',
            'label' => 'required|string|max:80',
            'icon' => 'nullable|string|max:80|regex:/^[a-z0-9-]+$/',
            'routeName' => ['nullable', 'string', 'max:120', Rule::unique('sidebar_menu_items', 'route_name')->ignore($this->editingId)],
            'url' => 'nullable|string|max:255',
            'activePattern' => 'nullable|string|max:160',
            'permission' => 'nullable|string|max:160|exists:permissions,name',
            'parentId' => 'nullable|integer|exists:sidebar_menu_items,id',
            'isActive' => 'boolean',
            'requiresOpenRegister' => 'boolean',
        ]);

        if ($this->type === 'link' && ! $this->routeName && ! $this->url) {
            throw ValidationException::withMessages(['routeName' => 'Selecciona una ruta interna o captura una URL.']);
        }
        if ($this->routeName && ! Route::has($this->routeName)) {
            throw ValidationException::withMessages(['routeName' => 'La ruta indicada no existe en la aplicación.']);
        }
        if ($this->url && SidebarMenuItem::query()
            ->where('url', trim($this->url))
            ->when($this->editingId, fn ($query) => $query->whereKeyNot($this->editingId))
            ->exists()) {
            throw ValidationException::withMessages(['url' => 'Esta URL ya pertenece a otro elemento del menú.']);
        }
        if ($this->requiresOpenRegister && ! $this->canRequireOpenRegister()) {
            throw ValidationException::withMessages([
                'requiresOpenRegister' => 'Selecciona una ruta interna protegible. Caja y Configuración siempre deben permanecer disponibles.',
            ]);
        }

        $parent = $this->parentId ? SidebarMenuItem::find($this->parentId) : null;
        if ($this->type === 'section' && $parent) {
            throw ValidationException::withMessages(['parentId' => 'Las secciones deben estar en el nivel principal.']);
        }
        if ($this->type === 'group' && $parent && $parent->type !== 'section') {
            throw ValidationException::withMessages(['parentId' => 'Un grupo solo puede estar dentro de una sección.']);
        }
        if ($this->editingId && $this->parentId && $this->isDescendant($this->editingId, $this->parentId)) {
            throw ValidationException::withMessages(['parentId' => 'No puedes mover un elemento dentro de uno de sus hijos.']);
        }

        $item = $this->editingId ? SidebarMenuItem::findOrFail($this->editingId) : new SidebarMenuItem;
        if (! $item->exists || $item->parent_id !== $this->parentId) {
            $item->sort_order = ((int) SidebarMenuItem::where('parent_id', $this->parentId)->max('sort_order')) + 10;
        }
        $canManageRegisterLocks = (bool) auth()->user()?->can('gestionar bloqueos por caja');
        $requiresOpenRegister = $canManageRegisterLocks
            ? ($this->type === 'link' && $this->requiresOpenRegister)
            : ($this->type === 'link' && (bool) ($item->requires_open_register ?? false));

        $item->fill([
            'parent_id' => $this->type === 'section' ? null : $this->parentId,
            'type' => $this->type,
            'label' => trim($validated['label']),
            'icon' => trim($this->icon) ?: null,
            'route_name' => $this->type === 'link' ? (trim($this->routeName) ?: null) : null,
            'url' => $this->type === 'link' ? (trim($this->url) ?: null) : null,
            'active_pattern' => $this->type === 'link' ? (trim($this->activePattern) ?: null) : null,
            'permission' => trim($this->permission) ?: null,
            'requires_open_register' => $requiresOpenRegister,
            'is_active' => $this->isActive,
            'updated_by' => auth()->id(),
        ])->save();

        $this->resetEditor();
        unset($this->menuTree, $this->parentOptions, $this->protectedModuleCount);
        $this->dispatch('sidebar-menu-updated');
        session()->flash('menu_success', 'Elemento del menú guardado.');
    }

    public function moveItem(int $id, int $direction): void
    {
        $this->authorizeAction('editar menu sidebar');
        abort_unless(in_array($direction, [-1, 1], true), 422);

        DB::transaction(function () use ($id, $direction): void {
            $item = SidebarMenuItem::query()->lockForUpdate()->findOrFail($id);
            $siblings = SidebarMenuItem::query()
                ->where('parent_id', $item->parent_id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $index = $siblings->search(fn (SidebarMenuItem $sibling): bool => $sibling->id === $item->id);
            $targetIndex = $index === false ? -1 : $index + $direction;

            if ($targetIndex < 0 || $targetIndex >= $siblings->count()) {
                return;
            }

            $orderedIds = $siblings->pluck('id')->all();
            $movingId = array_splice($orderedIds, $index, 1)[0];
            array_splice($orderedIds, $targetIndex, 0, [$movingId]);

            foreach ($orderedIds as $position => $siblingId) {
                SidebarMenuItem::query()->whereKey($siblingId)->update([
                    'sort_order' => ($position + 1) * 10,
                    'updated_by' => auth()->id(),
                ]);
            }
        });

        unset($this->menuTree);
        $this->dispatch('sidebar-menu-updated');
        session()->flash('menu_success', 'Posición del elemento actualizada.');
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizeAction('eliminar menu sidebar');
        $item = SidebarMenuItem::withCount('children')->findOrFail($id);
        $message = $item->children_count
            ? "También se eliminarán {$item->children_count} elementos agrupados dentro de {$item->label}."
            : 'El elemento dejará de aparecer en el sidebar.';
        $this->dispatch('open-confirm', type: 'danger', title: '¿Eliminar elemento del menú?', message: $message, action: 'delete-sidebar-item', params: ['id' => $id], confirmText: 'Eliminar');
    }

    #[On('modal-confirmed')]
    public function handleConfirmation(string $action, array $params = []): void
    {
        if ($action !== 'delete-sidebar-item') {
            return;
        }
        $this->authorizeAction('eliminar menu sidebar');
        SidebarMenuItem::findOrFail((int) ($params['id'] ?? 0))->delete();
        if ($this->editingId === (int) ($params['id'] ?? 0)) {
            $this->resetEditor();
        }
        unset($this->menuTree, $this->parentOptions, $this->protectedModuleCount);
        $this->dispatch('sidebar-menu-updated');
        session()->flash('menu_success', 'Elemento eliminado del menú.');
    }

    public function resetEditor(): void
    {
        $this->reset(['editingId', 'parentId', 'label', 'routeName', 'url', 'activePattern', 'permission']);
        $this->type = 'link';
        $this->icon = 'bx-circle';
        $this->isActive = true;
        $this->requiresOpenRegister = false;
        $this->resetValidation();
    }

    private function isDescendant(int $itemId, int $candidateId): bool
    {
        $candidate = SidebarMenuItem::find($candidateId);
        while ($candidate) {
            if ($candidate->id === $itemId) {
                return true;
            }
            $candidate = $candidate->parent;
        }

        return false;
    }

    private function decorateSiblings($siblings)
    {
        $lastIndex = $siblings->count() - 1;

        return $siblings->values()->map(function (SidebarMenuItem $item, int $index) use ($lastIndex): SidebarMenuItem {
            $item->setAttribute('is_first_sibling', $index === 0);
            $item->setAttribute('is_last_sibling', $index === $lastIndex);
            $item->setRelation('children', $this->decorateSiblings($item->children));

            return $item;
        });
    }

    private function authorizeAction(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }

    public function render()
    {
        return view('livewire.admin.sidebar-menu-manager', ['icons' => self::ICONS]);
    }
}
