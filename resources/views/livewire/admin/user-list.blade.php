<div x-data="{ toasts: [] }"
     x-on:notify.window="toasts.push($event.detail); setTimeout(() => toasts.shift(), 3500)">
    {{-- Toasts --}}
    <div class="position-fixed top-0 end-0 p-3" style="z-index:9999;pointer-events:none">
        <template x-for="(t, i) in toasts" :key="i">
            <div class="toast show align-items-center border-0 mb-2" style="pointer-events:all"
                 :class="{'text-bg-success':t.type==='success','text-bg-danger':t.type==='error','text-bg-info':t.type==='info'}">
                <div class="d-flex">
                    <div class="toast-body fw-medium" x-text="t.message"></div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" @click="toasts.splice(i,1)"></button>
                </div>
            </div>
        </template>
    </div>

    {{-- Cabecera --}}
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h4 class="fw-bold mb-1"><span class="text-muted fw-light">Admin /</span> Usuarios</h4>
            <small class="text-muted">Crea, edita, asigna roles y gestiona usuarios del restaurante</small>
        </div>
        <button class="btn btn-primary" wire:click="openCreatePanel">
            <i class="bx bx-user-plus me-1"></i> Nuevo usuario
        </button>
    </div>

    {{-- EstadÃ­sticas --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-2">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="avatar mx-auto mb-2">
                        <span class="avatar-initial rounded-circle bg-label-primary"><i class="bx bx-group"></i></span>
                    </div>
                    <h4 class="fw-bold mb-0">{{ $counts['active'] }}</h4>
                    <small class="text-muted">Activos</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="avatar mx-auto mb-2">
                        <span class="avatar-initial rounded-circle bg-label-danger"><i class="bx bx-trash"></i></span>
                    </div>
                    <h4 class="fw-bold mb-0">{{ $counts['trashed'] }}</h4>
                    <small class="text-muted">Papelera</small>
                </div>
            </div>
        </div>
        @foreach($this->roles->take(4) as $role)
        @php
            $rc = ['super-admin'=>'danger','admin'=>'primary','gerente'=>'info','cajero'=>'success','mesero'=>'warning','cocinero'=>'secondary'];
            $ri = ['super-admin'=>'bx-crown','admin'=>'bx-shield','gerente'=>'bx-briefcase','cajero'=>'bx-money','mesero'=>'bx-dish','cocinero'=>'bx-restaurant'];
            $c  = $rc[$role->name] ?? 'secondary';
            $ic = $ri[$role->name] ?? 'bx-user';
        @endphp
        <div class="col-6 col-md-2">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="avatar mx-auto mb-2">
                        <span class="avatar-initial rounded-circle bg-label-{{ $c }}"><i class="bx {{ $ic }}"></i></span>
                    </div>
                    <h4 class="fw-bold mb-0">{{ \App\Models\User::role($role->name)->count() }}</h4>
                    <small class="text-muted">{{ ucfirst($role->name) }}</small>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Filtros --}}
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0">
                            <i class="bx bx-search text-muted"></i>
                        </span>
                        <input wire:model.live.debounce.300ms="search" type="text"
                               class="form-control border-start-0 ps-0"
                               placeholder="Buscar por nombre o correo" />
                        @if($search)
                        <button wire:click="$set('search','')" class="btn btn-outline-secondary">
                            <i class="bx bx-x"></i>
                        </button>
                        @endif
                    </div>
                </div>
                <div class="col-md-3">
                    <select wire:model.live="filterRole" class="form-select">
                        <option value="">Todos los roles</option>
                        @foreach($this->roles as $role)
                            <option value="{{ $role->name }}">{{ ucfirst($role->name) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <div class="btn-group w-100">
                        <button wire:click="$set('filterStatus','active')"
                                class="btn btn-sm {{ $filterStatus==='active' ? 'btn-primary' : 'btn-outline-primary' }}">
                            Activos
                        </button>
                        <button wire:click="$set('filterStatus','trashed')"
                                class="btn btn-sm {{ $filterStatus==='trashed' ? 'btn-danger' : 'btn-outline-danger' }}">
                            Papelera
                        </button>
                        <button wire:click="$set('filterStatus','all')"
                                class="btn btn-sm {{ $filterStatus==='all' ? 'btn-secondary' : 'btn-outline-secondary' }}">
                            Todos
                        </button>
                    </div>
                </div>
                <div class="col-md-1">
                    <button wire:click="$set('filterRole',''); $set('search',''); $set('filterStatus','active')"
                            class="btn btn-outline-secondary w-100" title="Limpiar">
                        <i class="bx bx-reset"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabla --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div wire:loading.class="opacity-50" wire:target="search,filterRole,filterStatus"
                 class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width:38%">Usuario</th>
                            <th>Rol(es)</th>
                            <th>Email</th>
                            <th>Registrado</th>
                            <th class="text-center pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($users as $user)
                    <tr class="{{ $panelUserId===$user->id ? 'table-active' : '' }}"
                        wire:key="user-{{ $user->id }}"
                        style="cursor:pointer"
                        wire:click="openPanel({{ $user->id }})">
                        <td class="ps-4">
                            <div class="d-flex align-items-center gap-3">
                                @if($user->avatar && !$user->trashed())
                                    <img src="{{ Storage::url($user->avatar) }}" class="rounded-circle"
                                         style="width:38px;height:38px;object-fit:cover;" />
                                @else
                                    <div class="rounded-circle bg-label-{{ $user->trashed() ? 'danger' : 'primary' }}
                                                d-flex align-items-center justify-content-center fw-bold"
                                         style="width:38px;height:38px;font-size:1rem;">
                                        {{ strtoupper(substr($user->name, 0, 1)) }}
                                    </div>
                                @endif
                                <div>
                                    <div class="fw-semibold d-flex align-items-center gap-1 flex-wrap">
                                        {{ $user->name }}
                                        @if($user->id===auth()->id())
                                            <span class="badge bg-label-warning">Tu</span>
                                        @endif
                                        @if($user->trashed())
                                            <span class="badge bg-label-danger">Eliminado</span>
                                        @endif
                                    </div>
                                    <small class="text-muted">{{ $user->email }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            @forelse($user->roles as $role)
                                @php
                                    $map = ['super-admin'=>'danger','admin'=>'primary','gerente'=>'info','cajero'=>'success','mesero'=>'warning','cocinero'=>'secondary'];
                                    $c = $map[$role->name] ?? 'secondary';
                                @endphp
                                <span class="badge bg-label-{{ $c }} me-1">{{ ucfirst($role->name) }}</span>
                            @empty
                                <span class="text-muted small fst-italic">Sin rol</span>
                            @endforelse
                        </td>
                        <td>
                            @if($user->email_verified_at)
                                <span class="badge bg-label-success">
                                    <i class="bx bx-check me-1"></i>Verificado
                                </span>
                            @else
                                <span class="badge bg-label-warning">Pendiente</span>
                            @endif
                        </td>
                        <td>
                            <small class="text-muted">{{ $user->created_at->format('d M Y') }}</small>
                        </td>
                        <td class="text-center pe-4" wire:click.stop>
                            @if($user->trashed())
                                <div class="d-flex gap-1 justify-content-center">
                                    <button class="btn btn-icon btn-sm btn-outline-success"
                                            wire:click="restore({{ $user->id }})" title="Restaurar">
                                        <i class="bx bx-refresh"></i>
                                    </button>
                                    <button class="btn btn-icon btn-sm btn-danger"
                                            wire:click="confirmForceDelete({{ $user->id }})"
                                            title="Eliminar permanente">
                                        <i class="bx bx-skull"></i>
                                    </button>
                                </div>
                            @else
                                <div class="d-flex gap-1 justify-content-center">
                                    <button class="btn btn-icon btn-sm btn-outline-primary"
                                            wire:click="openPanel({{ $user->id }})" title="Editar">
                                        <i class="bx bx-edit"></i>
                                    </button>
                                    <button class="btn btn-icon btn-sm btn-outline-danger"
                                            wire:click="confirmSoftDelete({{ $user->id }})"
                                            @if($user->id===auth()->id()) disabled @endif
                                            title="Mover a papelera">
                                        <i class="bx bx-trash"></i>
                                    </button>
                                </div>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="bx bx-user-x d-block mb-2" style="font-size:3rem;opacity:.3"></i>
                            No se encontraron usuarios
                        </td>
                    </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($users->hasPages())
            <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top">
                <small class="text-muted">
                    {{ $users->firstItem() }}-{{ $users->lastItem() }} de {{ $users->total() }}
                </small>
                {{ $users->links('vendor.pagination.bootstrap-5') }}
            </div>
            @endif
        </div>
    </div>


    {{-- =====================================================
         MODAL 1 â€” Editar usuario
    ====================================================== --}}
    @if($panelUserId && $panelUser)
    {{-- Backdrop --}}
    <div class="modal-backdrop fade show" style="z-index:1110;" wire:click="{{ $showRolePanel ? '' : 'closePanel' }}"></div>

    <div class="modal fade show d-block" tabindex="-1" role="dialog"
         style="z-index:1115;">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content border-0 shadow-lg">

                {{-- Header --}}
                <div class="modal-header border-bottom pb-3">
                    <div class="d-flex align-items-center gap-3">
                        @if($panelUser->avatar)
                            <img src="{{ Storage::url($panelUser->avatar) }}" class="rounded-circle"
                                 style="width:50px;height:50px;object-fit:cover;" />
                        @else
                            <div class="rounded-circle bg-label-primary d-flex align-items-center
                                        justify-content-center fw-bold"
                                 style="width:50px;height:50px;font-size:1.4rem;">
                                {{ strtoupper(substr($panelUser->name, 0, 1)) }}
                            </div>
                        @endif
                        <div>
                            <h5 class="modal-title fw-bold mb-0">{{ $panelUser->name }}</h5>
                            <small class="text-muted">{{ $panelUser->email }}</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" wire:click="closePanel"></button>
                </div>

                {{-- Body --}}
                <div class="modal-body py-4">
                    <div class="row g-4">

                        {{-- Left: form fields --}}
                        <div class="col-lg-7">
                            <p class="text-uppercase small fw-semibold text-muted mb-3">
                                <i class="bx bx-edit-alt me-1"></i>Informacion del usuario
                            </p>
                            <div class="mb-3">
                                <label class="form-label small fw-medium">Nombre</label>
                                <input wire:model="editName" type="text"
                                       class="form-control @error('editName') is-invalid @enderror" />
                                @error('editName')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-medium">Correo</label>
                                <input wire:model="editEmail" type="email"
                                       class="form-control @error('editEmail') is-invalid @enderror" />
                                @error('editEmail')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-medium">Telefono</label>
                                <input wire:model="editPhone" type="text"
                                       class="form-control @error('editPhone') is-invalid @enderror"
                                       placeholder="+1 (555) 000-0000" />
                                @error('editPhone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        {{-- Right: roles + permissions --}}
                        <div class="col-lg-5">
                            {{-- Roles section --}}
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <p class="text-uppercase small fw-semibold text-muted mb-0">
                                        <i class="bx bx-shield me-1"></i>Roles asignados
                                    </p>
                                    <button class="btn btn-sm btn-outline-primary" wire:click="openRolePanel">
                                        <i class="bx bx-edit me-1"></i>Cambiar roles
                                    </button>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    @forelse($panelUser->roles as $role)
                                        @php
                                            $map = ['super-admin'=>'danger','admin'=>'primary','gerente'=>'info','cajero'=>'success','mesero'=>'warning','cocinero'=>'secondary'];
                                            $c = $map[$role->name] ?? 'secondary';
                                        @endphp
                                        <span class="badge bg-{{ $c }} px-3 py-2" style="font-size:.82rem;">
                                            <i class="bx bx-shield me-1"></i>{{ ucfirst($role->name) }}
                                        </span>
                                    @empty
                                        <em class="text-muted small">Sin roles asignados</em>
                                    @endforelse
                                </div>
                            </div>

                            {{-- Effective permissions accordion --}}
                            <div>
                                <p class="text-uppercase small fw-semibold text-muted mb-2">
                                    <i class="bx bx-key me-1"></i>Permisos efectivos
                                    <span class="badge bg-label-primary ms-1">
                                        {{ $panelUser->getAllPermissions()->count() }}
                                    </span>
                                </p>
                                @php $grouped = $panelUser->getAllPermissions()->groupBy('group'); @endphp
                                @forelse($grouped as $grp => $perms)
                                <div class="accordion accordion-flush mb-2">
                                    <div class="accordion-item border rounded">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed py-2 px-3 small"
                                                    type="button"
                                                    data-bs-toggle="collapse"
                                                    data-bs-target="#ep{{ Str::slug($grp . $panelUser->id) }}">
                                                <i class="bx bx-folder-open me-2 text-primary"></i>
                                                <span class="text-capitalize fw-semibold">{{ $grp }}</span>
                                                <span class="badge bg-label-primary ms-auto me-2">
                                                    {{ $perms->count() }}
                                                </span>
                                            </button>
                                        </h2>
                                        <div id="ep{{ Str::slug($grp . $panelUser->id) }}"
                                             class="accordion-collapse collapse">
                                            <div class="accordion-body py-2 px-3">
                                                <div class="d-flex flex-wrap gap-1">
                                                    @foreach($perms as $p)
                                                        <span class="badge bg-label-success"
                                                              style="font-size:.75rem;">{{ $p->name }}</span>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @empty
                                    <em class="text-muted small">Sin permisos</em>
                                @endforelse
                            </div>
                        </div>

                    </div>
                </div>

                {{-- Footer --}}
                <div class="modal-footer border-top d-flex justify-content-between">
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-secondary" wire:click="closePanel">
                            <i class="bx bx-x me-1"></i>Cerrar
                        </button>
                        @if($panelUser->trashed())
                            <button class="btn btn-success" wire:click="restore({{ $panelUser->id }})">
                                <i class="bx bx-refresh me-1"></i>Restaurar
                            </button>
                            <button class="btn btn-danger" wire:click="confirmForceDelete({{ $panelUser->id }})">
                                <i class="bx bx-skull me-1"></i>Eliminar permanente
                            </button>
                        @else
                            <button class="btn btn-outline-danger"
                                    wire:click="confirmSoftDelete({{ $panelUser->id }})"
                                    @if($panelUser->id===auth()->id()) disabled @endif>
                                <i class="bx bx-trash me-1"></i>Mover a papelera
                            </button>
                        @endif
                    </div>
                    <button class="btn btn-primary" wire:click="saveUserInfo">
                        <span wire:loading.remove wire:target="saveUserInfo">
                            <i class="bx bx-save me-1"></i>Guardar cambios
                        </span>
                        <span wire:loading wire:target="saveUserInfo"
                              style="gap:.4rem;">
                            <span class="spinner-border spinner-border-sm"
                                  style="width:.85rem;height:.85rem;border-width:2px;"></span>
                            Guardando...
                        </span>
                    </button>
                </div>

            </div>
        </div>
    </div>
    @endif


    {{-- =====================================================
         MODAL 2 â€” Asignar Roles (stacks on top of modal 1)
    ====================================================== --}}
    @if($panelUser && $showRolePanel)
    {{-- Backdrop for role modal --}}
    <div class="modal-backdrop fade show" style="z-index:1120;"></div>

    <div class="modal fade show d-block" tabindex="-1" role="dialog"
         style="z-index:1125;">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content border-0 shadow-lg">

                {{-- Header --}}
                <div class="modal-header border-bottom" style="background:rgba(105,108,255,.06)">
                    <div>
                        <h5 class="modal-title fw-bold mb-0">
                            <i class="bx bx-shield-quarter me-2 text-primary"></i>Asignar Roles
                        </h5>
                        <small class="text-muted">{{ $panelUser->name }}</small>
                    </div>
                    <button type="button" class="btn-close" wire:click="closeRolePanel"></button>
                </div>

                {{-- Body --}}
                <div class="modal-body py-4">
                    <p class="text-muted small mb-4">
                        Selecciona los roles para este usuario. Cada rol otorga permisos predefinidos.
                    </p>

                    <div class="d-flex flex-column gap-3">
                        @foreach($this->roles as $role)
                        @php
                            $rc = ['super-admin'=>'danger','admin'=>'primary','gerente'=>'info',
                                   'cajero'=>'success','mesero'=>'warning','cocinero'=>'secondary'];
                            $ri = ['super-admin'=>'bx-crown','admin'=>'bx-shield','gerente'=>'bx-briefcase',
                                   'cajero'=>'bx-money','mesero'=>'bx-dish','cocinero'=>'bx-restaurant'];
                            $c  = $rc[$role->name] ?? 'secondary';
                            $ic = $ri[$role->name] ?? 'bx-user';
                            $isChecked = in_array($role->name, $selectedRoles);
                        @endphp
                        <label class="d-flex align-items-start gap-3 p-3 rounded-2 border"
                               style="cursor:pointer;{{ $isChecked ? 'border-color:#696cff;background:rgba(105,108,255,.04);' : '' }}">
                            <input type="checkbox"
                                   wire:model.live="selectedRoles"
                                   value="{{ $role->name }}"
                                   class="form-check-input mt-1 flex-shrink-0" />
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <i class="bx {{ $ic }} text-{{ $c }} fs-5"></i>
                                    <span class="fw-semibold">{{ ucfirst($role->name) }}</span>
                                    <span class="badge bg-label-{{ $c }} ms-auto">
                                        {{ $role->permissions->count() }} permisos
                                    </span>
                                </div>
                                @if($role->permissions->count())
                                <div class="d-flex flex-wrap gap-1 mt-1">
                                    @foreach($role->permissions->take(4) as $p)
                                        <span class="badge bg-label-secondary"
                                              style="font-size:.7rem;">{{ $p->name }}</span>
                                    @endforeach
                                    @if($role->permissions->count() > 4)
                                        <span class="badge bg-label-secondary" style="font-size:.7rem;">
                                            +{{ $role->permissions->count() - 4 }} mas
                                        </span>
                                    @endif
                                </div>
                                @endif
                            </div>
                        </label>
                        @endforeach
                    </div>
                </div>

                {{-- Footer --}}
                <div class="modal-footer border-top d-flex justify-content-between">
                    <button class="btn btn-outline-secondary" wire:click="closeRolePanel">
                        <i class="bx bx-arrow-back me-1"></i>Volver
                    </button>
                    <button class="btn btn-primary" wire:click="saveRoles">
                        <span wire:loading.remove wire:target="saveRoles">
                            <i class="bx bx-save me-1"></i>Guardar roles
                        </span>
                        <span wire:loading wire:target="saveRoles"
                              style="gap:.4rem;">
                            <span class="spinner-border spinner-border-sm"
                                  style="width:.85rem;height:.85rem;border-width:2px;"></span>
                            Guardando...
                        </span>
                    </button>
                </div>

            </div>
        </div>
    </div>
    @endif


    {{-- =====================================================
         MODAL 3 â€” Nuevo usuario
    ====================================================== --}}
    @if($showCreatePanel)
    {{-- Backdrop --}}
    <div class="modal-backdrop fade show" style="z-index:1110;" wire:click="closeCreatePanel"></div>

    <div class="modal fade show d-block" tabindex="-1" role="dialog"
         style="z-index:1115;">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content border-0 shadow-lg">

                {{-- Header --}}
                <div class="modal-header border-bottom">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-label-primary d-flex align-items-center
                                    justify-content-center" style="width:46px;height:46px;">
                            <i class="bx bx-user-plus fs-5 text-primary"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0">Nuevo usuario</h5>
                            <small class="text-muted">Completa los datos para crear la cuenta</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" wire:click="closeCreatePanel"></button>
                </div>

                {{-- Body --}}
                <div class="modal-body py-4">
                    <div class="row g-4">

                        {{-- Left column: name, email, phone --}}
                        <div class="col-lg-6">
                            <div class="mb-3">
                                <label class="form-label small fw-medium">
                                    Nombre completo <span class="text-danger">*</span>
                                </label>
                                <input wire:model="createName" type="text"
                                       class="form-control @error('createName') is-invalid @enderror"
                                       placeholder="Ej: Juan PÃ©rez" autofocus />
                                @error('createName')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-medium">
                                    Correo electrÃ³nico <span class="text-danger">*</span>
                                </label>
                                <input wire:model="createEmail" type="email"
                                       class="form-control @error('createEmail') is-invalid @enderror"
                                       placeholder="correo@ejemplo.com" />
                                @error('createEmail')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-medium">TelÃ©fono</label>
                                <input wire:model="createPhone" type="text"
                                       class="form-control @error('createPhone') is-invalid @enderror"
                                       placeholder="+1 (555) 000-0000" />
                                @error('createPhone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        {{-- Right column: password, confirm, role --}}
                        <div class="col-lg-6">
                            <div class="mb-3">
                                <label class="form-label small fw-medium">
                                    ContraseÃ±a <span class="text-danger">*</span>
                                </label>
                                <input wire:model="createPassword" type="password"
                                       class="form-control @error('createPassword') is-invalid @enderror"
                                       placeholder="MÃ­nimo 8 caracteres" />
                                @error('createPassword')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-medium">
                                    Confirmar contraseÃ±a <span class="text-danger">*</span>
                                </label>
                                <input wire:model="createPasswordCon" type="password"
                                       class="form-control @error('createPasswordCon') is-invalid @enderror"
                                       placeholder="Repite la contraseÃ±a" />
                                @error('createPasswordCon')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-medium">Rol inicial</label>
                                <select wire:model="createRole"
                                        class="form-select @error('createRole') is-invalid @enderror">
                                    <option value="">Sin rol asignado</option>
                                    @foreach($this->roles as $role)
                                        @php
                                            $rc = ['super-admin'=>'danger','admin'=>'primary','gerente'=>'info',
                                                   'cajero'=>'success','mesero'=>'warning','cocinero'=>'secondary'];
                                            $c  = $rc[$role->name] ?? 'secondary';
                                        @endphp
                                        <option value="{{ $role->name }}">{{ ucfirst($role->name) }}</option>
                                    @endforeach
                                </select>
                                @error('createRole')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                    </div>
                </div>

                {{-- Footer --}}
                <div class="modal-footer border-top d-flex justify-content-between">
                    <button class="btn btn-outline-secondary" wire:click="closeCreatePanel">
                        <i class="bx bx-x me-1"></i>Cancelar
                    </button>
                    <button class="btn btn-primary" wire:click="createUser">
                        <span wire:loading.remove wire:target="createUser">
                            <i class="bx bx-user-plus me-1"></i>Crear usuario
                        </span>
                        <span wire:loading wire:target="createUser"
                              style="gap:.4rem;">
                            <span class="spinner-border spinner-border-sm"
                                  style="width:.85rem;height:.85rem;border-width:2px;"></span>
                            Creando...
                        </span>
                    </button>
                </div>

            </div>
        </div>
    </div>
    @endif

</div>

