<div class="app-page admin-management-page roles-page" x-data="{ toasts: [] }"
     x-on:notify.window="toasts.push($event.detail); setTimeout(() => toasts.shift(), 3500)">

    {{-- Toasts --}}
    <div class="position-fixed top-0 end-0 p-3" data-ui="xui-1lgunq">
        <template x-for="(t, i) in toasts" :key="i">
            <div class="toast show align-items-center border-0 mb-2" data-ui="xui-1nfksch"
                 :class="{'text-bg-success':t.type==='success','text-bg-danger':t.type==='error','text-bg-info':t.type==='info'}">
                <div class="d-flex">
                    <div class="toast-body fw-medium" x-text="t.message"></div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto"
                            aria-label="Cerrar notificación" @click="toasts.splice(i,1)"></button>
                </div>
            </div>
        </template>
    </div>

    {{-- Cabecera --}}
    <header class="app-page-header roles-hero">
        <div class="app-page-heading">
            <span class="app-page-icon roles-hero__icon" aria-hidden="true"><i class="bx bx-shield-quarter"></i></span>
            <div>
                <div class="app-eyebrow">Administración · Acceso</div>
                <h1 class="app-page-title">Roles y permisos</h1>
                <p class="app-page-subtitle">Define responsabilidades y controla el acceso a cada módulo del sistema.</p>
            </div>
        </div>
        <div class="roles-hero__summary" aria-label="Resumen de control de acceso">
            @can('gestionar roles')
            <span><i class="bx bx-shield" aria-hidden="true"></i><strong>{{ $this->roles->count() }}</strong> roles</span>
            @endcan
            @can('gestionar permisos')
            <span><i class="bx bx-key" aria-hidden="true"></i><strong>{{ $this->permissionsByGroup->flatten()->count() }}</strong> permisos</span>
            @endcan
        </div>
    </header>

    {{-- Tabs --}}
    <ul class="nav nav-pills app-tabs roles-tabs" role="tablist" aria-label="Gestión de acceso">
        @can('gestionar roles')
        <li class="nav-item">
            <button type="button" role="tab" aria-selected="{{ $activeTab === 'roles' ? 'true' : 'false' }}"
                    wire:click="$set('activeTab','roles')"
                    class="nav-link {{ $activeTab==='roles' ? 'active' : '' }}">
                <span class="roles-tab__icon"><i class="bx bx-shield" aria-hidden="true"></i></span>
                <span>Roles<small>Responsabilidades del equipo</small></span>
                <span class="roles-tab__count">
                    {{ $this->roles->count() }}
                </span>
            </button>
        </li>
        @endcan
        @can('gestionar roles')
        <li class="nav-item">
            <button type="button" role="tab" aria-selected="{{ $activeTab === 'notifications' ? 'true' : 'false' }}"
                    wire:click="$set('activeTab','notifications')"
                    class="nav-link {{ $activeTab==='notifications' ? 'active' : '' }}">
                <span class="roles-tab__icon"><i class="bx bx-bell" aria-hidden="true"></i></span>
                <span>Notificaciones<small>Avisos por responsabilidad</small></span>
                <span class="roles-tab__count">{{ count(\App\Support\NotificationEventCatalog::keys()) }}</span>
            </button>
        </li>
        @endcan
        @can('gestionar permisos')
        <li class="nav-item">
            <button type="button" role="tab" aria-selected="{{ $activeTab === 'permissions' ? 'true' : 'false' }}"
                    wire:click="$set('activeTab','permissions')"
                    class="nav-link {{ $activeTab==='permissions' ? 'active' : '' }}">
                <span class="roles-tab__icon"><i class="bx bx-key" aria-hidden="true"></i></span>
                <span>Permisos<small>Acciones disponibles</small></span>
                <span class="roles-tab__count">
                    {{ $this->permissionsByGroup->flatten()->count() }}
                </span>
            </button>
        </li>
        @endcan
    </ul>


    {{-- ================================================================
         TAB: ROLES
    ================================================================= --}}
    @can('gestionar roles')
    @if($activeTab === 'roles')
    <div class="roles-toolbar">
        <div>
            <span class="roles-toolbar__eyebrow">Directorio de roles</span>
            <h2>{{ $this->roles->count() }} roles registrados</h2>
            <p>Selecciona un rol para revisar usuarios, permisos y acciones disponibles.</p>
        </div>
        <button type="button" class="roles-primary-action" wire:click="openCreateRole">
            <i class="bx bx-plus" aria-hidden="true"></i><span>Nuevo rol</span>
        </button>
    </div>

    <div class="roles-grid">
        @foreach($this->roles as $role)
        @php
            $rc = ['super-admin'=>'danger','admin'=>'primary','gerente'=>'info','cajero'=>'success','mesero'=>'warning','cocinero'=>'secondary'];
            $ri = ['super-admin'=>'bx-crown','admin'=>'bx-shield','gerente'=>'bx-briefcase','cajero'=>'bx-money','mesero'=>'bx-dish','cocinero'=>'bx-restaurant'];
            $c  = $rc[$role->name] ?? 'secondary';
            $ic = $ri[$role->name] ?? 'bx-user';
        @endphp
        <button type="button" class="card roles-role-card"
                data-ui="xui-y4ry0a"
                wire:click="selectRole({{ $role->id }})"
                wire:key="role-card-{{ $role->id }}"
                aria-label="Abrir detalles del rol {{ $role->name }}">
                <div class="card-body">
                    <div class="roles-role-card__top">
                        <span class="roles-role-card__icon bg-label-{{ $c }}">
                            <i class="bx {{ $ic }}" aria-hidden="true"></i>
                        </span>
                        <span class="roles-role-card__users">
                            <i class="bx bx-user" aria-hidden="true"></i>{{ $role->users_count }}
                        </span>
                    </div>
                    <h3>{{ ucfirst($role->name) }}</h3>
                    <p>{{ $role->permissions_count }} permisos asignados</p>
                    <div class="roles-role-card__footer">
                        @if($role->name === 'super-admin')
                            <span class="roles-risk-badge"><i class="bx bx-crown" aria-hidden="true"></i>Acceso total</span>
                        @else
                            <span><i class="bx bx-check-shield" aria-hidden="true"></i>Acceso configurado</span>
                        @endif
                        <i class="bx bx-right-arrow-alt" aria-hidden="true"></i>
                    </div>
                </div>
        </button>
        @endforeach

        {{-- Card nueva rol --}}
        <button type="button" class="roles-create-card"
                data-ui="xui-152wzt9"
                wire:click="openCreateRole"
                aria-label="Crear un nuevo rol">
            <span><i class="bx bx-plus" aria-hidden="true"></i></span>
            <strong>Crear nuevo rol</strong>
            <small>Define responsabilidades y permisos</small>
        </button>
    </div>
    @endif
    @endcan


    {{-- ================================================================
         TAB: NOTIFICACIONES POR ROL
    ================================================================= --}}
    @can('gestionar roles')
    @if($activeTab === 'notifications')
    <div class="roles-toolbar">
        <div>
            <span class="roles-toolbar__eyebrow">Matriz de avisos operativos</span>
            <h2>Notificaciones por rol</h2>
            <p>Selecciona un rol y define qué eventos recibirá. Sólo se habilitan avisos compatibles con sus permisos.</p>
        </div>
    </div>

    <div class="role-notifications-layout">
        <aside class="role-notifications-roles" aria-label="Roles disponibles">
            <header><strong>Roles del sistema</strong><small>{{ $this->roles->count() }} disponibles</small></header>
            <div>
                @foreach($this->roles as $role)
                    <button type="button"
                            class="role-notifications-role {{ $notificationRoleId === $role->id ? 'is-active' : '' }}"
                            wire:click="selectNotificationRole({{ $role->id }})"
                            wire:key="notification-role-{{ $role->id }}"
                            aria-pressed="{{ $notificationRoleId === $role->id ? 'true' : 'false' }}">
                        <span><i class="bx bx-user" aria-hidden="true"></i></span>
                        <span><strong>{{ str($role->name)->replace('-', ' ')->title() }}</strong><small>{{ $role->users_count }} usuario(s) · {{ $role->permissions_count }} permisos</small></span>
                        <i class="bx bx-chevron-right" aria-hidden="true"></i>
                    </button>
                @endforeach
            </div>
        </aside>

        <section class="role-notifications-editor" aria-live="polite">
            @if($this->notificationRole)
                <form wire:submit="saveRoleNotifications">
                    <header class="role-notifications-editor__header">
                        <span class="roles-role-card__icon bg-label-primary"><i class="bx bx-bell" aria-hidden="true"></i></span>
                        <div>
                            <span class="roles-toolbar__eyebrow">Configurando</span>
                            <h3>{{ str($this->notificationRole->name)->replace('-', ' ')->title() }}</h3>
                            <p>{{ $notificationRoleConfigured ? 'Configuración personalizada activa.' : 'Sin configurar: conserva el comportamiento automático actual hasta que guardes.' }}</p>
                        </div>
                        <span class="role-notifications-status {{ $notificationRoleConfigured ? 'is-custom' : '' }}">
                            <i class="bx {{ $notificationRoleConfigured ? 'bx-slider-alt' : 'bx-history' }}" aria-hidden="true"></i>
                            {{ $notificationRoleConfigured ? 'Personalizado' : 'Automático' }}
                        </span>
                    </header>

                    <div class="role-notifications-groups">
                        @foreach($this->notificationEventGroups as $group)
                            <fieldset class="role-notification-group">
                                <legend><i class="bx {{ $group['icon'] }}" aria-hidden="true"></i><span><strong>{{ $group['label'] }}</strong><small>{{ $group['description'] }}</small></span></legend>
                                <div>
                                    @foreach($group['events'] as $eventKey => $event)
                                        @php
                                            $compatible = $this->roleSupportsEvent($this->notificationRole, $eventKey);
                                        @endphp
                                        <label class="role-notification-event {{ $compatible ? '' : 'is-disabled' }}">
                                            <input type="checkbox" wire:model="roleNotificationEvents" value="{{ $eventKey }}" @disabled(!$compatible)>
                                            <span class="role-notification-event__icon"><i class="bx {{ $event['icon'] }}" aria-hidden="true"></i></span>
                                            <span class="role-notification-event__copy">
                                                <strong>{{ $event['label'] }}</strong>
                                                <small>{{ $event['description'] }}</small>
                                                @if(!$compatible)
                                                    <em><i class="bx bx-lock-alt" aria-hidden="true"></i>Requiere alguno: {{ implode(', ', $event['permissions']) }}</em>
                                                @endif
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach
                    </div>

                    @error('roleNotificationEvents')<p class="role-notifications-error" role="alert">{{ $message }}</p>@enderror
                    <footer class="role-notifications-actions">
                        <p><i class="bx bx-info-circle" aria-hidden="true"></i>Los cambios se envían juntos al guardar; marcar opciones no genera peticiones.</p>
                        <div>
                            @if($notificationRoleConfigured)
                                <button type="button" class="btn btn-outline-secondary" wire:click="restoreAutomaticRoleNotifications" wire:loading.attr="disabled">Restaurar automático</button>
                            @endif
                            <button type="submit" class="roles-primary-action" wire:loading.attr="disabled" wire:target="saveRoleNotifications">
                                <span wire:loading.remove wire:target="saveRoleNotifications"><i class="bx bx-save" aria-hidden="true"></i>Guardar notificaciones</span>
                                <span wire:loading wire:target="saveRoleNotifications">Guardando…</span>
                            </button>
                        </div>
                    </footer>
                </form>
            @else
                <div class="role-notifications-empty">
                    <span><i class="bx bx-bell" aria-hidden="true"></i></span>
                    <h3>Selecciona un rol</h3>
                    <p>Verás todos los tipos de notificación y cuáles puede recibir según sus permisos efectivos.</p>
                </div>
            @endif
        </section>
    </div>
    @endif
    @endcan


    {{-- ================================================================
         TAB: PERMISOS
    ================================================================= --}}
    @can('gestionar permisos')
    @if($activeTab === 'permissions')
    <div class="roles-toolbar">
        <div>
            <span class="roles-toolbar__eyebrow">Catálogo de permisos</span>
            <h2>Permisos por módulo</h2>
            <p>Consulta qué acciones existen y en qué módulo se utilizan.</p>
        </div>
        <button type="button" class="roles-primary-action" wire:click="openCreatePerm">
            <i class="bx bx-plus" aria-hidden="true"></i><span>Nuevo permiso</span>
        </button>
    </div>

    <div class="roles-permission-grid">
        @foreach($this->permissionsByGroup as $grp => $perms)
        @php
            $groupMeta = $groupDefinitions[$grp] ?? ['label' => str($grp)->replace('_', ' ')->title(), 'icon' => 'bx-key', 'tone' => 'secondary', 'description' => 'Permisos pendientes de clasificación.'];
            $gc_val = $groupMeta['tone'];
            $gi_val = $groupMeta['icon'];
        @endphp
            <section class="card roles-permission-group">
                <header class="roles-permission-group__header">
                    <span class="roles-permission-group__icon bg-label-{{ $gc_val }}">
                        <i class="bx {{ $gi_val }}" aria-hidden="true"></i>
                    </span>
                    <div>
                        <h3>{{ $groupMeta['label'] }}</h3>
                        <small>{{ $perms->count() }} {{ $perms->count() === 1 ? 'permiso' : 'permisos' }}</small>
                    </div>
                </header>
                <p class="roles-permission-group__description">{{ $groupMeta['description'] }}</p>
                <div class="roles-permission-group__body">
                        @foreach($perms as $perm)
                        <button type="button" class="roles-permission-row"
                                data-ui="xui-1wc3lz9"
                                wire:click="selectPerm({{ $perm->id }})"
                                aria-label="Abrir permiso {{ $perm->name }}">
                            <span class="roles-permission-row__content">
                                <i class="bx bx-key" aria-hidden="true"></i>
                                <span>
                                    <strong>{{ $perm->name }}</strong>
                                    <small>{{ $perm->description ?: 'Este permiso todavía no tiene un alcance documentado.' }}</small>
                                </span>
                            </span>
                            <i class="bx bx-chevron-right" aria-hidden="true"></i>
                        </button>
                        @endforeach
                </div>
            </section>
        @endforeach
    </div>
    @endif
    @endcan


    {{-- ================================================================
         MODAL: Detalle de rol
    ================================================================= --}}
    @if($this->rolePanel && $this->selectedRole && !$this->showRoleForm)
    @php
        $rc2 = ['super-admin'=>'danger','admin'=>'primary','gerente'=>'info','cajero'=>'success','mesero'=>'warning','cocinero'=>'secondary'];
        $ri2 = ['super-admin'=>'bx-crown','admin'=>'bx-shield','gerente'=>'bx-briefcase','cajero'=>'bx-money','mesero'=>'bx-dish','cocinero'=>'bx-restaurant'];
        $mc  = $rc2[$selectedRole->name] ?? 'secondary';
        $mic = $ri2[$selectedRole->name] ?? 'bx-user';
    @endphp
    <div class="modal-backdrop fade show roles-modal-backdrop" data-ui="xui-1mk4i26" wire:click="closeRolePanel"></div>
    <div class="modal fade show d-block roles-modal-layer" tabindex="-1" data-ui="xui-n1v1df"
         role="dialog" aria-modal="true" aria-labelledby="role-detail-title"
         x-on:keydown.escape.window="$wire.closeRolePanel()">
        <div class="modal-dialog modal-lg roles-modal-dialog" role="document">
            <button type="button" class="roles-modal-close" wire:click="closeRolePanel"
                    aria-label="Cerrar detalles del rol" title="Cerrar">
                <i class="bx bx-x" aria-hidden="true"></i>
            </button>
            <div class="modal-content roles-modal-content">

                <div class="modal-header roles-modal-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar">
                            <span class="avatar-initial rounded-circle bg-label-{{ $mc }}">
                                <i class="bx {{ $mic }}"></i>
                            </span>
                        </div>
                        <div>
                            <h2 id="role-detail-title" class="modal-title">{{ ucfirst($selectedRole->name) }}</h2>
                            <small class="text-muted">
                                {{ $selectedRole->users_count }} usuario(s) · {{ $selectedRole->permissions->count() }} permiso(s)
                            </small>
                        </div>
                    </div>
                </div>

                <div class="modal-body roles-modal-body">
                    @if($selectedRole->name === 'super-admin')
                    <div class="alert alert-danger d-flex align-items-center gap-2 py-2 mb-4">
                        <i class="bx bx-crown fs-5"></i>
                        <small>Este rol tiene acceso total al sistema y no usa permisos individuales.</small>
                    </div>
                    @endif

                    <p class="text-uppercase small fw-semibold text-muted mb-3">
                        <i class="bx bx-key me-1"></i>Permisos asignados
                    </p>
                    @php $permGroups = $selectedRole->permissions->groupBy('group'); @endphp
                    @forelse($permGroups as $grp => $perms)
                    @php
                        $groupMeta = $groupDefinitions[$grp] ?? [
                            'label' => str($grp)->replace('_', ' ')->title(),
                        ];
                    @endphp
                    <div class="mb-3">
                        <small class="text-uppercase fw-semibold text-muted d-block mb-2">
                            <i class="bx bx-folder me-1 text-primary"></i>{{ $groupMeta['label'] }}
                        </small>
                        <div class="d-flex flex-wrap gap-1 ps-3">
                            @foreach($perms as $p)
                                <span class="badge bg-label-success" data-ui="xui-1q0o5lz">{{ $p->name }}</span>
                            @endforeach
                        </div>
                    </div>
                    @empty
                        <p class="text-muted fst-italic small">Sin permisos asignados</p>
                    @endforelse
                </div>

                <div class="modal-footer roles-modal-footer">
                    <button type="button" class="btn btn-outline-secondary" wire:click="closeRolePanel">
                        Cerrar
                    </button>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-danger"
                                wire:click="confirmDeleteRole({{ $selectedRole->id }})"
                                @if(in_array($selectedRole->name, ['owner','super-admin','admin'])) disabled title="No se puede eliminar" @endif>
                            <i class="bx bx-trash me-1"></i>Eliminar
                        </button>
                        <button type="button" class="btn btn-primary" wire:click="openEditRole">
                            <i class="bx bx-edit me-1"></i>Editar rol
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
    @endif


    {{-- ================================================================
         MODAL: Crear / Editar rol
    ================================================================= --}}
    @if($this->showRoleForm)
    <div class="modal-backdrop fade show roles-modal-backdrop" data-ui="xui-1mk4i26"
         wire:click="closeRoleForm"></div>
    <div class="modal fade show d-block roles-modal-layer" tabindex="-1" data-ui="xui-n1v1df"
         role="dialog" aria-modal="true" aria-labelledby="role-form-title"
         x-on:keydown.escape.window="$wire.closeRoleForm()">
        <div class="modal-dialog modal-xl roles-modal-dialog" role="document">
            <button type="button" class="roles-modal-close" wire:click="closeRoleForm"
                    aria-label="Cerrar formulario de rol" title="Cerrar">
                <i class="bx bx-x" aria-hidden="true"></i>
            </button>
            <div class="modal-content roles-modal-content roles-modal-content--wide">

                <div class="modal-header roles-modal-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-label-primary d-flex align-items-center justify-content-center"
                             data-ui="xui-w39nd4">
                            <i class="bx {{ $rolePanel ? 'bx-edit' : 'bx-plus' }} fs-5 text-primary"></i>
                        </div>
                        <div>
                            <h2 id="role-form-title" class="modal-title">
                                {{ $rolePanel ? 'Editar rol: ' . $roleName : 'Nuevo rol' }}
                            </h2>
                            <small class="text-muted">
                                {{ $rolePanel ? 'Modifica nombre y permisos' : 'Configura nombre y permisos iniciales' }}
                            </small>
                        </div>
                    </div>
                </div>

                <div class="modal-body roles-modal-body">
                    <div class="row g-4">
                        {{-- Nombre del rol --}}
                        <div class="col-12">
                            <label for="role-name" class="form-label fw-medium">
                                Nombre del rol <span class="text-danger">*</span>
                            </label>
                            <input id="role-name" wire:model="roleName" type="text"
                                   class="form-control @error('roleName') is-invalid @enderror"
                                   placeholder="ej: supervisor" />
                            @error('roleName')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Permisos por grupo --}}
                        <div class="col-12">
                            <label class="form-label fw-medium d-block mb-3">Permisos del rol</label>
                            <div class="row g-3">
                                @foreach($this->permissionsByGroup as $grp => $perms)
                                @php
                                    $groupMeta = $groupDefinitions[$grp] ?? ['label' => str($grp)->replace('_', ' ')->title(), 'icon' => 'bx-folder', 'tone' => 'secondary', 'description' => 'Permisos pendientes de clasificación.'];
                                    $gc3v = $groupMeta['tone'];
                                    $gi3v = $groupMeta['icon'];
                                @endphp
                                <div class="col-md-6 col-xl-4">
                                    <div class="card border shadow-none h-100">
                                        <div class="card-header py-2 d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bx {{ $gi3v }} text-{{ $gc3v }}"></i>
                                                <span class="small fw-semibold">{{ $groupMeta['label'] }}</span>
                                            </div>
                                            <span class="badge bg-label-{{ $gc3v }}">
                                                {{ collect($perms)->filter(fn($p) => in_array($p->name, $rolePermissions))->count() }}/{{ count($perms) }}
                                            </span>
                                        </div>
                                        <p class="roles-module-description">{{ $groupMeta['description'] }}</p>
                                        <div class="card-body py-2">
                                            <div class="d-flex flex-column gap-1">
                                                @foreach($perms as $perm)
                                                <label class="roles-permission-choice"
                                                       data-ui="xui-1wc3lz9">
                                                    <input type="checkbox"
                                                           wire:model.live="rolePermissions"
                                                           value="{{ $perm->name }}"
                                                           class="form-check-input" />
                                                    <span>
                                                        <strong>{{ $perm->name }}</strong>
                                                        <small>{{ $perm->description ?: 'Este permiso todavía no tiene un alcance documentado.' }}</small>
                                                    </span>
                                                </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer roles-modal-footer">
                    <button type="button" class="btn btn-outline-secondary" wire:click="closeRoleForm">
                        Cancelar
                    </button>
                    <button type="button" class="btn btn-primary" wire:click="saveRole"
                            wire:loading.attr="disabled" wire:target="saveRole">
                        <span wire:loading.remove wire:target="saveRole">
                            <i class="bx bx-save me-1"></i>{{ $rolePanel ? 'Guardar cambios' : 'Crear rol' }}
                        </span>
                        <span wire:loading wire:target="saveRole" data-ui="xui-3uu978">
                            <span class="spinner-border spinner-border-sm"
                                  data-ui="xui-n9k71g"></span>
                            {{ $rolePanel ? 'Guardando...' : 'Creando...' }}
                        </span>
                    </button>
                </div>

            </div>
        </div>
    </div>
    @endif


    {{-- ================================================================
         MODAL: Detalle de permiso
    ================================================================= --}}
    @if($this->permPanel && $this->selectedPerm && !$this->showPermForm)
    <div class="modal-backdrop fade show roles-modal-backdrop" data-ui="xui-1mk4i26" wire:click="closePermPanel"></div>
    <div class="modal fade show d-block roles-modal-layer" tabindex="-1" data-ui="xui-n1v1df"
         role="dialog" aria-modal="true" aria-labelledby="permission-detail-title"
         x-on:keydown.escape.window="$wire.closePermPanel()">
        <div class="modal-dialog roles-modal-dialog roles-modal-dialog--compact" role="document">
            <button type="button" class="roles-modal-close" wire:click="closePermPanel"
                    aria-label="Cerrar detalles del permiso" title="Cerrar">
                <i class="bx bx-x" aria-hidden="true"></i>
            </button>
            <div class="modal-content roles-modal-content">

                <div class="modal-header roles-modal-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-label-warning d-flex align-items-center justify-content-center"
                             data-ui="xui-w39nd4">
                            <i class="bx bx-key fs-5 text-warning"></i>
                        </div>
                        <div>
                            <h2 id="permission-detail-title" class="modal-title">{{ $selectedPerm->name }}</h2>
                            <span class="badge bg-label-primary text-capitalize">{{ $selectedPerm->group }}</span>
                        </div>
                    </div>
                </div>

                <div class="modal-body roles-modal-body">
                    <div class="roles-permission-scope">
                        <span><i class="bx bx-info-circle" aria-hidden="true"></i> Alcance exacto</span>
                        <p>{{ $selectedPerm->description ?: 'Este permiso todavía no tiene un alcance documentado.' }}</p>
                    </div>
                    <p class="text-uppercase small fw-semibold text-muted mb-3">
                        <i class="bx bx-shield me-1"></i>Usado en roles
                    </p>
                    @php $usedInRoles = $selectedPerm->roles; @endphp
                    @forelse($usedInRoles as $r)
                        @php
                            $rc3 = ['super-admin'=>'danger','admin'=>'primary','gerente'=>'info','cajero'=>'success','mesero'=>'warning','cocinero'=>'secondary'];
                            $c3 = $rc3[$r->name] ?? 'secondary';
                        @endphp
                        <span class="badge bg-label-{{ $c3 }} me-1 mb-1 px-3 py-2">
                            {{ ucfirst($r->name) }}
                        </span>
                    @empty
                        <em class="text-muted small d-block">Sin uso en roles</em>
                    @endforelse
                </div>

                <div class="modal-footer roles-modal-footer">
                    <button type="button" class="btn btn-outline-secondary" wire:click="closePermPanel">
                        Cerrar
                    </button>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-danger"
                                wire:click="confirmDeletePerm({{ $selectedPerm->id }})">
                            <i class="bx bx-trash me-1"></i>Eliminar
                        </button>
                        <button type="button" class="btn btn-primary"
                                wire:click="openEditPerm">
                            <i class="bx bx-edit me-1"></i>Editar
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
    @endif


    {{-- ================================================================
         MODAL: Crear / Editar permiso
    ================================================================= --}}
    @if($this->showPermForm)
    <div class="modal-backdrop fade show roles-modal-backdrop" data-ui="xui-1mk4i26"
         wire:click="closePermForm"></div>
    <div class="modal fade show d-block roles-modal-layer" tabindex="-1" data-ui="xui-n1v1df"
         role="dialog" aria-modal="true" aria-labelledby="permission-form-title"
         x-on:keydown.escape.window="$wire.closePermForm()">
        <div class="modal-dialog roles-modal-dialog roles-modal-dialog--compact" role="document">
            <button type="button" class="roles-modal-close" wire:click="closePermForm"
                    aria-label="Cerrar formulario de permiso" title="Cerrar">
                <i class="bx bx-x" aria-hidden="true"></i>
            </button>
            <div class="modal-content roles-modal-content">

                <div class="modal-header roles-modal-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-label-warning d-flex align-items-center justify-content-center"
                             data-ui="xui-w39nd4">
                            <i class="bx {{ $permPanel ? 'bx-edit' : 'bx-plus' }} fs-5 text-warning"></i>
                        </div>
                        <div>
                            <h2 id="permission-form-title" class="modal-title">
                                {{ $permPanel ? 'Editar permiso' : 'Nuevo permiso' }}
                            </h2>
                            <small class="text-muted">
                                {{ $permPanel ? 'Modifica nombre y módulo' : 'Define nombre y módulo' }}
                            </small>
                        </div>
                    </div>
                </div>

                <div class="modal-body roles-modal-body">
                    <div class="mb-3">
                        <label for="permission-name" class="form-label fw-medium">
                            Nombre del permiso <span class="text-danger">*</span>
                        </label>
                        <input id="permission-name" wire:model="permName" type="text"
                               class="form-control @error('permName') is-invalid @enderror"
                               placeholder="ej: exportar reportes" />
                        @error('permName')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="permission-description" class="form-label fw-medium">
                            Qué permite hacer <span class="text-danger">*</span>
                        </label>
                        <textarea id="permission-description" wire:model="permDescription" rows="4"
                                  class="form-control @error('permDescription') is-invalid @enderror"
                                  placeholder="Ej. Permite registrar ingresos de efectivo en una caja abierta; no autoriza cerrar la caja."></textarea>
                        <div class="form-text">Describe la acción, dónde se realiza y cualquier límite importante.</div>
                        @error('permDescription')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-1">
                        <label for="permission-group" class="form-label fw-medium">
                            Módulo <span class="text-danger">*</span>
                        </label>
                        <select id="permission-group" wire:model="permGroup"
                                class="form-select @error('permGroup') is-invalid @enderror">
                            <option value="">Selecciona un módulo</option>
                            @foreach($groups as $g)
                                <option value="{{ $g }}">{{ $groupDefinitions[$g]['label'] ?? str($g)->replace('_', ' ')->title() }}</option>
                            @endforeach
                        </select>
                        @error('permGroup')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="modal-footer roles-modal-footer">
                    <button type="button" class="btn btn-outline-secondary" wire:click="closePermForm">
                        Cancelar
                    </button>
                    <button type="button" class="btn btn-warning" wire:click="savePerm"
                            wire:loading.attr="disabled" wire:target="savePerm">
                        <span wire:loading.remove wire:target="savePerm">
                            <i class="bx bx-save me-1"></i>{{ $permPanel ? 'Guardar cambios' : 'Crear permiso' }}
                        </span>
                        <span wire:loading wire:target="savePerm" data-ui="xui-3uu978">
                            <span class="spinner-border spinner-border-sm"
                                  data-ui="xui-n9k71g"></span>
                            {{ $permPanel ? 'Guardando...' : 'Creando...' }}
                        </span>
                    </button>
                </div>

            </div>
        </div>
    </div>
    @endif

</div>
