<div class="app-page admin-management-page roles-page" x-data="{ toasts: [] }"
     x-on:notify.window="toasts.push($event.detail); setTimeout(() => toasts.shift(), 3500)">

    {{-- Toasts --}}
    <div class="position-fixed top-0 end-0 p-3" data-ui="xui-1lgunq">
        <template x-for="(t, i) in toasts" :key="i">
            <div class="toast show align-items-center border-0 mb-2" data-ui="xui-1nfksch"
                 :class="{'text-bg-success':t.type==='success','text-bg-danger':t.type==='error','text-bg-info':t.type==='info'}">
                <div class="d-flex">
                    <div class="toast-body fw-medium" x-text="t.message"></div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" @click="toasts.splice(i,1)"></button>
                </div>
            </div>
        </template>
    </div>

    {{-- Cabecera --}}
    <header class="app-page-header">
        <div class="app-page-heading">
            <span class="app-page-icon" aria-hidden="true"><i class="bx bx-shield-quarter"></i></span>
            <div>
                <div class="app-eyebrow">Administración · Acceso</div>
                <h1 class="app-page-title">Roles y permisos</h1>
                <p class="app-page-subtitle">Define responsabilidades y controla el acceso a cada módulo del sistema.</p>
            </div>
        </div>
        <span class="app-count-pill"><i class="bx bx-lock-alt" aria-hidden="true"></i>Control de acceso</span>
    </header>

    {{-- Tabs --}}
    <ul class="nav nav-pills app-tabs mb-4 gap-2" role="tablist" aria-label="Gestión de acceso">
        <li class="nav-item">
            <button wire:click="$set('activeTab','roles')"
                    class="nav-link {{ $activeTab==='roles' ? 'active' : '' }}">
                <i class="bx bx-shield me-1"></i>Roles
                <span class="badge {{ $activeTab==='roles' ? 'bg-white text-primary' : 'bg-label-primary' }} ms-1">
                    {{ $this->roles->count() }}
                </span>
            </button>
        </li>
        <li class="nav-item">
            <button wire:click="$set('activeTab','permissions')"
                    class="nav-link {{ $activeTab==='permissions' ? 'active' : '' }}">
                <i class="bx bx-key me-1"></i>Permisos
                <span class="badge {{ $activeTab==='permissions' ? 'bg-white text-primary' : 'bg-label-primary' }} ms-1">
                    {{ $this->permissionsByGroup->flatten()->count() }}
                </span>
            </button>
        </li>
    </ul>


    {{-- ================================================================
         TAB: ROLES
    ================================================================= --}}
    @if($activeTab === 'roles')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-semibold mb-0 text-muted">
            {{ $this->roles->count() }} roles registrados
        </h6>
        <button class="btn btn-primary btn-sm" wire:click="openCreateRole">
            <i class="bx bx-plus me-1"></i>Nuevo rol
        </button>
    </div>

    <div class="row g-3">
        @foreach($this->roles as $role)
        @php
            $rc = ['super-admin'=>'danger','admin'=>'primary','gerente'=>'info','cajero'=>'success','mesero'=>'warning','cocinero'=>'secondary'];
            $ri = ['super-admin'=>'bx-crown','admin'=>'bx-shield','gerente'=>'bx-briefcase','cajero'=>'bx-money','mesero'=>'bx-dish','cocinero'=>'bx-restaurant'];
            $c  = $rc[$role->name] ?? 'secondary';
            $ic = $ri[$role->name] ?? 'bx-user';
        @endphp
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100"
                 data-ui="xui-y4ry0a"
                 wire:click="selectRole({{ $role->id }})"
                 wire:key="role-card-{{ $role->id }}">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between mb-3">
                        <div class="avatar">
                            <span class="avatar-initial rounded-circle bg-label-{{ $c }}">
                                <i class="bx {{ $ic }}"></i>
                            </span>
                        </div>
                        <span class="badge bg-label-{{ $c }}">
                            {{ $role->users_count }} usuario(s)
                        </span>
                    </div>
                    <h6 class="mb-1 fw-bold">{{ ucfirst($role->name) }}</h6>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="badge bg-label-primary">
                            {{ $role->permissions_count }} permisos
                        </span>
                        @if($role->name === 'super-admin')
                            <span class="badge bg-label-danger">Bypass total</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endforeach

        {{-- Card nueva rol --}}
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 h-100 d-flex align-items-center justify-content-center"
                 data-ui="xui-152wzt9"
                 wire:click="openCreateRole">
                <div class="text-center text-muted py-3">
                    <i class="bx bx-plus-circle fs-3 mb-1 d-block"></i>
                    <small>Crear nuevo rol</small>
                </div>
            </div>
        </div>
    </div>
    @endif


    {{-- ================================================================
         TAB: PERMISOS
    ================================================================= --}}
    @if($activeTab === 'permissions')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-semibold mb-0 text-muted">
            Permisos organizados por mÃ³dulo
        </h6>
        <button class="btn btn-primary btn-sm" wire:click="openCreatePerm">
            <i class="bx bx-plus me-1"></i>Nuevo permiso
        </button>
    </div>

    <div class="row g-4">
        @foreach($this->permissionsByGroup as $grp => $perms)
        @php
            $gc = ['usuarios'=>'primary','menu'=>'success','ordenes'=>'info','mesas'=>'warning','caja'=>'danger','reportes'=>'secondary','configuracion'=>'dark'];
            $gi = ['usuarios'=>'bx-group','menu'=>'bx-food-menu','ordenes'=>'bx-receipt','mesas'=>'bx-table','caja'=>'bx-dollar-circle','reportes'=>'bx-bar-chart','configuracion'=>'bx-cog'];
            $gc_val = $gc[$grp] ?? 'secondary';
            $gi_val = $gi[$grp] ?? 'bx-key';
        @endphp
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header border-0 pb-0 d-flex align-items-center gap-2">
                    <span class="avatar avatar-sm">
                        <span class="avatar-initial rounded-circle bg-label-{{ $gc_val }}">
                            <i class="bx {{ $gi_val }}"></i>
                        </span>
                    </span>
                    <div>
                        <h6 class="mb-0 fw-bold text-capitalize">{{ $grp }}</h6>
                        <small class="text-muted">{{ $perms->count() }} permiso(s)</small>
                    </div>
                </div>
                <div class="card-body pt-3">
                    <div class="d-flex flex-column gap-2">
                        @foreach($perms as $perm)
                        <div class="d-flex align-items-center justify-content-between
                                    px-2 py-1 rounded bg-light"
                             data-ui="xui-1wc3lz9"
                             wire:click="selectPerm({{ $perm->id }})">
                            <span class="small fw-medium">{{ $perm->name }}</span>
                            <i class="bx bx-chevron-right text-muted"></i>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif


    {{-- ================================================================
         MODAL: Detalle de rol
    ================================================================= --}}
    @if($rolePanel && $selectedRole && !$showRoleForm)
    @php
        $rc2 = ['super-admin'=>'danger','admin'=>'primary','gerente'=>'info','cajero'=>'success','mesero'=>'warning','cocinero'=>'secondary'];
        $ri2 = ['super-admin'=>'bx-crown','admin'=>'bx-shield','gerente'=>'bx-briefcase','cajero'=>'bx-money','mesero'=>'bx-dish','cocinero'=>'bx-restaurant'];
        $mc  = $rc2[$selectedRole->name] ?? 'secondary';
        $mic = $ri2[$selectedRole->name] ?? 'bx-user';
    @endphp
    <div class="modal-backdrop fade show" data-ui="xui-1mk4i26" wire:click="closeRolePanel"></div>
    <div class="modal fade show d-block" tabindex="-1" data-ui="xui-n1v1df" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content border-0 shadow-lg">

                <div class="modal-header border-bottom">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar">
                            <span class="avatar-initial rounded-circle bg-label-{{ $mc }}">
                                <i class="bx {{ $mic }}"></i>
                            </span>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0">{{ ucfirst($selectedRole->name) }}</h5>
                            <small class="text-muted">
                                {{ $selectedRole->users_count }} usuario(s) Â· {{ $selectedRole->permissions->count() }} permiso(s)
                            </small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" wire:click="closeRolePanel"></button>
                </div>

                <div class="modal-body">
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
                    <div class="mb-3">
                        <small class="text-uppercase fw-semibold text-muted d-block mb-2">
                            <i class="bx bx-folder me-1 text-primary"></i>{{ $grp }}
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

                <div class="modal-footer border-top d-flex justify-content-between">
                    <button class="btn btn-outline-secondary" wire:click="closeRolePanel">
                        Cerrar
                    </button>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-danger"
                                wire:click="confirmDeleteRole({{ $selectedRole->id }})"
                                @if(in_array($selectedRole->name, ['owner','super-admin','admin'])) disabled title="No se puede eliminar" @endif>
                            <i class="bx bx-trash me-1"></i>Eliminar
                        </button>
                        <button class="btn btn-primary" wire:click="openEditRole">
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
    @if($showRoleForm)
    <div class="modal-backdrop fade show" data-ui="xui-1mk4i26"
         wire:click="{{ $rolePanel ? '$set(\'showRoleForm\', false)' : 'closeRolePanel' }}"></div>
    <div class="modal fade show d-block" tabindex="-1" data-ui="xui-n1v1df" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
            <div class="modal-content border-0 shadow-lg">

                <div class="modal-header border-bottom">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-label-primary d-flex align-items-center justify-content-center"
                             data-ui="xui-w39nd4">
                            <i class="bx {{ $rolePanel ? 'bx-edit' : 'bx-plus' }} fs-5 text-primary"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0">
                                {{ $rolePanel ? 'Editar rol: ' . $roleName : 'Nuevo rol' }}
                            </h5>
                            <small class="text-muted">
                                {{ $rolePanel ? 'Modifica nombre y permisos' : 'Configura nombre y permisos iniciales' }}
                            </small>
                        </div>
                    </div>
                    <button type="button" class="btn-close"
                            wire:click="{{ $rolePanel ? '$set(\'showRoleForm\', false)' : 'closeRolePanel' }}"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-4">
                        {{-- Nombre del rol --}}
                        <div class="col-12">
                            <label class="form-label fw-medium">
                                Nombre del rol <span class="text-danger">*</span>
                            </label>
                            <input wire:model="roleName" type="text"
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
                                    $gc3 = ['usuarios'=>'primary','menu'=>'success','ordenes'=>'info','mesas'=>'warning','caja'=>'danger','reportes'=>'secondary','configuracion'=>'dark'];
                                    $gi3 = ['usuarios'=>'bx-group','menu'=>'bx-food-menu','ordenes'=>'bx-receipt','mesas'=>'bx-table','caja'=>'bx-dollar-circle','reportes'=>'bx-bar-chart','configuracion'=>'bx-cog'];
                                    $gc3v = $gc3[$grp] ?? 'secondary';
                                    $gi3v = $gi3[$grp] ?? 'bx-folder';
                                    $allSelected = collect($perms)->every(fn($p) => in_array($p->name, $rolePermissions));
                                @endphp
                                <div class="col-md-6 col-xl-4">
                                    <div class="card border shadow-none h-100">
                                        <div class="card-header py-2 d-flex align-items-center justify-content-between">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bx {{ $gi3v }} text-{{ $gc3v }}"></i>
                                                <span class="small fw-semibold text-capitalize">{{ $grp }}</span>
                                            </div>
                                            <span class="badge bg-label-{{ $gc3v }}">
                                                {{ collect($perms)->filter(fn($p) => in_array($p->name, $rolePermissions))->count() }}/{{ count($perms) }}
                                            </span>
                                        </div>
                                        <div class="card-body py-2">
                                            <div class="d-flex flex-column gap-1">
                                                @foreach($perms as $perm)
                                                <label class="d-flex align-items-center gap-2 small py-1"
                                                       data-ui="xui-1wc3lz9">
                                                    <input type="checkbox"
                                                           wire:model.live="rolePermissions"
                                                           value="{{ $perm->name }}"
                                                           class="form-check-input" />
                                                    {{ $perm->name }}
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

                <div class="modal-footer border-top d-flex justify-content-between">
                    <button class="btn btn-outline-secondary"
                            wire:click="{{ $rolePanel ? '$set(\'showRoleForm\', false)' : 'closeRolePanel' }}">
                        Cancelar
                    </button>
                    <button class="btn btn-primary" wire:click="saveRole">
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
    @if($permPanel && $selectedPerm && !$showPermForm)
    <div class="modal-backdrop fade show" data-ui="xui-1mk4i26" wire:click="closePermPanel"></div>
    <div class="modal fade show d-block" tabindex="-1" data-ui="xui-n1v1df" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content border-0 shadow-lg">

                <div class="modal-header border-bottom">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-label-warning d-flex align-items-center justify-content-center"
                             data-ui="xui-w39nd4">
                            <i class="bx bx-key fs-5 text-warning"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0">{{ $selectedPerm->name }}</h5>
                            <span class="badge bg-label-primary text-capitalize">{{ $selectedPerm->group }}</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close" wire:click="closePermPanel"></button>
                </div>

                <div class="modal-body">
                    <p class="text-uppercase small fw-semibold text-muted mb-3">
                        <i class="bx bx-shield me-1"></i>Usado en roles
                    </p>
                    @php $usedInRoles = \Spatie\Permission\Models\Role::whereHas('permissions', fn($q) => $q->where('name', $selectedPerm->name))->get(); @endphp
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

                <div class="modal-footer border-top d-flex justify-content-between">
                    <button class="btn btn-outline-secondary" wire:click="closePermPanel">
                        Cerrar
                    </button>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-danger"
                                wire:click="confirmDeletePerm({{ $selectedPerm->id }})">
                            <i class="bx bx-trash me-1"></i>Eliminar
                        </button>
                        <button class="btn btn-primary"
                                wire:click="$set('showPermForm', true)">
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
    @if($showPermForm)
    <div class="modal-backdrop fade show" data-ui="xui-1mk4i26"
         wire:click="{{ $permPanel ? '$set(\'showPermForm\', false)' : 'closePermPanel' }}"></div>
    <div class="modal fade show d-block" tabindex="-1" data-ui="xui-n1v1df" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content border-0 shadow-lg">

                <div class="modal-header border-bottom">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-label-warning d-flex align-items-center justify-content-center"
                             data-ui="xui-w39nd4">
                            <i class="bx {{ $permPanel ? 'bx-edit' : 'bx-plus' }} fs-5 text-warning"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0">
                                {{ $permPanel ? 'Editar permiso' : 'Nuevo permiso' }}
                            </h5>
                            <small class="text-muted">
                                {{ $permPanel ? 'Modifica nombre y mÃ³dulo' : 'Define nombre y mÃ³dulo' }}
                            </small>
                        </div>
                    </div>
                    <button type="button" class="btn-close"
                            wire:click="{{ $permPanel ? '$set(\'showPermForm\', false)' : 'closePermPanel' }}"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium">
                            Nombre del permiso <span class="text-danger">*</span>
                        </label>
                        <input wire:model="permName" type="text"
                               class="form-control @error('permName') is-invalid @enderror"
                               placeholder="ej: exportar reportes" />
                        @error('permName')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-1">
                        <label class="form-label fw-medium">
                            MÃ³dulo <span class="text-danger">*</span>
                        </label>
                        <select wire:model="permGroup"
                                class="form-select @error('permGroup') is-invalid @enderror">
                            <option value="">Selecciona un mÃ³dulo</option>
                            @foreach($groups as $g)
                                <option value="{{ $g }}">{{ ucfirst($g) }}</option>
                            @endforeach
                        </select>
                        @error('permGroup')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="modal-footer border-top d-flex justify-content-between">
                    <button class="btn btn-outline-secondary"
                            wire:click="{{ $permPanel ? '$set(\'showPermForm\', false)' : 'closePermPanel' }}">
                        Cancelar
                    </button>
                    <button class="btn btn-warning" wire:click="savePerm">
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
