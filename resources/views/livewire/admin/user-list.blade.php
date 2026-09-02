<div class="app-page users-page" x-data="{ toasts: [] }"
     x-on:notify.window="toasts.push($event.detail); setTimeout(() => toasts.shift(), 3800)">
    <div class="app-toast-stack" aria-live="polite" aria-atomic="true">
        <template x-for="(toast, index) in toasts" :key="index">
            <div class="users-toast" :class="'is-' + (toast.type || 'info')">
                <i class="bx" :class="toast.type === 'success' ? 'bx-check-circle' : (toast.type === 'error' ? 'bx-error-circle' : 'bx-info-circle')"></i>
                <span x-text="toast.message"></span>
                <button type="button" @click="toasts.splice(index, 1)" aria-label="Cerrar aviso"><i class="bx bx-x"></i></button>
            </div>
        </template>
    </div>

    <header class="app-page-header users-hero">
        <div class="app-page-heading">
            <span class="app-page-icon" aria-hidden="true"><i class="bx bx-group"></i></span>
            <div>
                <div class="app-eyebrow">Administración · Equipo</div>
                <h1 class="app-page-title">Usuarios</h1>
                <p class="app-page-subtitle">Administra el acceso, rol y estado de cada integrante del restaurante.</p>
            </div>
        </div>
        @can('crear usuarios')
            <div class="users-hero-actions">
                <button type="button" class="users-secondary-button" wire:click="openInvitationPanel">
                    <i class="bx bx-envelope" aria-hidden="true"></i><span>Invitar usuario</span>
                </button>
                <button type="button" class="users-primary-button" wire:click="openCreatePanel">
                    <i class="bx bx-user-plus" aria-hidden="true"></i><span>Nuevo usuario</span>
                </button>
            </div>
        @endcan
    </header>

    <section class="users-stats" aria-label="Resumen de usuarios">
        @foreach([
            ['key' => 'active', 'label' => 'Activos', 'hint' => 'Con acceso disponible', 'icon' => 'bx-user-check', 'tone' => 'success'],
            ['key' => 'banned', 'label' => 'Bloqueados', 'hint' => 'Acceso suspendido', 'icon' => 'bx-block', 'tone' => 'warning'],
            ['key' => 'trashed', 'label' => 'Papelera', 'hint' => 'Eliminación reversible', 'icon' => 'bx-trash', 'tone' => 'danger'],
            ['key' => 'total', 'label' => 'Registros', 'hint' => 'Histórico del equipo', 'icon' => 'bx-id-card', 'tone' => 'primary'],
        ] as $stat)
            <article class="users-stat-card is-{{ $stat['tone'] }}">
                <span class="users-stat-icon"><i class="bx {{ $stat['icon'] }}"></i></span>
                <div><small>{{ $stat['label'] }}</small><strong>{{ $counts[$stat['key']] }}</strong><span>{{ $stat['hint'] }}</span></div>
            </article>
        @endforeach
    </section>

    <section class="users-directory" aria-labelledby="users-directory-title">
        <header class="users-directory-header">
            <div><span class="app-eyebrow">Directorio</span><h2 id="users-directory-title">Equipo registrado</h2><p>Busca, filtra y administra cuentas sin perder su historial.</p></div>
            <span class="users-result-count"><strong>{{ $users->total() }}</strong> {{ $users->total() === 1 ? 'resultado' : 'resultados' }}</span>
        </header>

        <div class="users-filters">
            <label class="users-search" for="users-search">
                <i class="bx bx-search" aria-hidden="true"></i>
                <input id="users-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar nombre, correo o teléfono" autocomplete="off">
                @if($search)<button type="button" wire:click="$set('search', '')" aria-label="Limpiar búsqueda"><i class="bx bx-x"></i></button>@endif
            </label>
            <label class="users-select" for="users-role-filter">
                <span>Rol</span>
                <select id="users-role-filter" wire:model.live="filterRole">
                    <option value="">Todos los roles</option>
                    @foreach($this->roles as $role)<option value="{{ $role->name }}">{{ str($role->name)->replace('-', ' ')->title() }}</option>@endforeach
                </select>
            </label>
            <div class="users-status-tabs" role="group" aria-label="Filtrar por estado">
                @foreach([
                    'active' => ['Activos', 'bx-user-check', $counts['active']],
                    'banned' => ['Bloqueados', 'bx-block', $counts['banned']],
                    'trashed' => ['Papelera', 'bx-trash', $counts['trashed']],
                    'all' => ['Todos', 'bx-list-ul', $counts['total']],
                ] as $value => $tab)
                    <button type="button" wire:click="$set('filterStatus', '{{ $value }}')" class="{{ $filterStatus === $value ? 'is-active' : '' }}" aria-pressed="{{ $filterStatus === $value ? 'true' : 'false' }}">
                        <i class="bx {{ $tab[1] }}"></i><span>{{ $tab[0] }}</span><b>{{ $tab[2] }}</b>
                    </button>
                @endforeach
            </div>
            <button type="button" class="users-reset-button" wire:click="clearFilters" aria-label="Restablecer filtros" title="Restablecer filtros"><i class="bx bx-reset"></i></button>
        </div>

        <div class="users-table-wrap" wire:loading.class="is-loading" wire:target="search,filterRole,filterStatus,clearFilters">
            <div class="users-table-skeleton" wire:loading.flex wire:target="search,filterRole,filterStatus,clearFilters" aria-label="Cargando usuarios">
                @for($i = 0; $i < 5; $i++)<span></span>@endfor
            </div>
            <table class="users-table">
                <thead><tr><th>Usuario</th><th>Rol y contacto</th><th>Estado de acceso</th><th>Registro</th><th><span class="visually-hidden">Acciones</span></th></tr></thead>
                <tbody>
                    @forelse($users as $user)
                        @php
                            $status = $user->trashed() ? 'deleted' : ($user->isBanned() ? 'banned' : 'active');
                            $statusData = match($status) {
                                'deleted' => ['Eliminado', 'En papelera', 'bx-trash', 'danger'],
                                'banned' => ['Bloqueado', 'Sin acceso', 'bx-block', 'warning'],
                                default => ['Activo', 'Puede ingresar', 'bx-check-circle', 'success'],
                            };
                        @endphp
                        <tr wire:key="user-row-{{ $user->id }}" class="is-{{ $status }} {{ $panelUserId === $user->id ? 'is-selected' : '' }}">
                            <td data-label="Usuario">
                                <div class="users-identity">
                                    <span class="users-avatar">
                                        @if($user->avatar && !$user->trashed())<img src="{{ Storage::url($user->avatar) }}" alt="Avatar de {{ $user->name }}">@else{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}@endif
                                    </span>
                                    <div><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small>@if($user->id === auth()->id())<span class="users-self-chip">Tu cuenta</span>@endif</div>
                                </div>
                            </td>
                            <td data-label="Rol y contacto">
                                <div class="users-roles">@forelse($user->roles as $role)<span>{{ str($role->name)->replace('-', ' ')->title() }}</span>@empty<em>Sin rol</em>@endforelse</div>
                                <small class="users-phone"><i class="bx bx-phone"></i>{{ $user->phone ?: 'Sin teléfono' }}</small>
                            </td>
                            <td data-label="Estado">
                                <span class="users-status is-{{ $statusData[3] }}"><i class="bx {{ $statusData[2] }}"></i><span><strong>{{ $statusData[0] }}</strong><small>{{ $statusData[1] }}</small></span></span>
                            </td>
                            <td data-label="Registro"><time datetime="{{ $user->created_at->toDateString() }}">{{ $user->created_at->translatedFormat('d M Y') }}</time><small>{{ $user->created_at->diffForHumans() }}</small></td>
                            <td class="users-row-actions">
                                <button type="button" wire:click="openPanel({{ $user->id }})" aria-label="Ver detalles de {{ $user->name }}" title="Ver detalles"><i class="bx bx-show"></i></button>
                                @if($user->trashed())
                                    @can('eliminar usuarios')<button type="button" class="is-success" wire:click="confirmRestore({{ $user->id }})" aria-label="Restaurar a {{ $user->name }}" title="Restaurar"><i class="bx bx-revision"></i></button>@endcan
                                @else
                                    @can('bloquear usuarios')
                                        @if($user->isBanned())<button type="button" class="is-success" wire:click="confirmUnban({{ $user->id }})" aria-label="Desbloquear a {{ $user->name }}" title="Desbloquear"><i class="bx bx-lock-open-alt"></i></button>
                                        @else<button type="button" class="is-warning" wire:click="openBanPanel({{ $user->id }})" @disabled($user->id === auth()->id()) aria-label="Bloquear a {{ $user->name }}" title="Bloquear"><i class="bx bx-block"></i></button>@endif
                                    @endcan
                                    @can('eliminar usuarios')<button type="button" class="is-danger" wire:click="confirmSoftDelete({{ $user->id }})" @disabled($user->id === auth()->id()) aria-label="Eliminar a {{ $user->name }}" title="Mover a papelera"><i class="bx bx-trash"></i></button>@endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="users-empty"><span><i class="bx bx-user-x"></i></span><h3>No encontramos usuarios</h3><p>Modifica los filtros o limpia la búsqueda para consultar el directorio.</p><button type="button" wire:click="clearFilters"><i class="bx bx-reset"></i>Restablecer filtros</button></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($users->hasPages())<footer class="users-pagination">{{ $users->links() }}</footer>@endif
    </section>

    @if($panelUser)
        <div class="app-modal-backdrop" wire:click="closePanel"></div>
        <div class="app-modal-layer users-modal-layer" role="dialog" aria-modal="true" aria-labelledby="user-detail-title">
            <section class="users-modal users-detail-modal">
                <header class="users-modal-header">
                    <div class="users-modal-identity"><span class="users-avatar is-large">{{ mb_strtoupper(mb_substr($panelUser->name, 0, 1)) }}</span><div><span class="app-eyebrow">Expediente del usuario</span><h2 id="user-detail-title">{{ $panelUser->name }}</h2><p>{{ $panelUser->email }}</p></div></div>
                    <button type="button" class="users-modal-close" wire:click="closePanel" aria-label="Cerrar"><i class="bx bx-x"></i></button>
                </header>

                <div class="users-modal-body">
                    <div class="users-detail-status-row">
                        @if($panelUser->trashed())<span class="users-status is-danger"><i class="bx bx-trash"></i><span><strong>En papelera</strong><small>Eliminación reversible</small></span></span>
                        @elseif($panelUser->isBanned())<span class="users-status is-warning"><i class="bx bx-block"></i><span><strong>Cuenta bloqueada</strong><small>Inicio de sesión suspendido</small></span></span>
                        @else<span class="users-status is-success"><i class="bx bx-check-circle"></i><span><strong>Cuenta activa</strong><small>Acceso disponible</small></span></span>@endif
                        <span class="users-created-chip"><i class="bx bx-calendar"></i>Desde {{ $panelUser->created_at->translatedFormat('d M Y') }}</span>
                    </div>

                    @if($panelUser->isBanned())
                        <aside class="users-ban-audit"><i class="bx bx-shield-x"></i><div><strong>Motivo del bloqueo</strong><p>{{ $panelUser->ban_reason }}</p><small>Aplicado {{ $panelUser->banned_at?->diffForHumans() }} por {{ $panelUser->bannedBy?->name ?? 'Administrador' }}.</small></div></aside>
                    @endif

                    <div class="users-detail-grid">
                        <section class="users-form-section">
                            <header><span><i class="bx bx-id-card"></i></span><div><h3>Datos personales</h3><p>Información para identificar y contactar al integrante.</p></div></header>
                            <div class="users-form-grid">
                                <label><span>Nombre completo</span><input type="text" wire:model="editName" @disabled($panelUser->trashed() || !auth()->user()->can('editar usuarios'))>@error('editName')<small class="users-field-error">{{ $message }}</small>@enderror</label>
                                <label><span>Correo electrónico</span><input type="email" wire:model="editEmail" @disabled($panelUser->trashed() || !auth()->user()->can('editar usuarios'))>@error('editEmail')<small class="users-field-error">{{ $message }}</small>@enderror</label>
                                <label class="is-full"><span>Teléfono</span><input type="tel" wire:model="editPhone" placeholder="Ej. 5512345678" @disabled($panelUser->trashed() || !auth()->user()->can('editar usuarios'))>@error('editPhone')<small class="users-field-error">{{ $message }}</small>@enderror</label>
                            </div>
                        </section>

                        <section class="users-form-section">
                            <header><span><i class="bx bx-shield-quarter"></i></span><div><h3>Roles y permisos</h3><p>Define qué módulos y acciones puede utilizar.</p></div></header>
                            <div class="users-assigned-roles">@forelse($panelUser->roles as $role)<span><i class="bx bx-badge-check"></i>{{ str($role->name)->replace('-', ' ')->title() }}<b>{{ $role->permissions->count() }} permisos</b></span>@empty<em>Sin rol asignado</em>@endforelse</div>
                            @can('gestionar roles')@unless($panelUser->trashed())<button type="button" class="users-secondary-button" wire:click="openRolePanel"><i class="bx bx-edit-alt"></i>Administrar roles</button>@endunless@endcan
                        </section>
                    </div>
                </div>

                <footer class="users-modal-footer">
                    <div class="users-danger-actions">
                        @if($panelUser->trashed())
                            @can('eliminar usuarios')<button type="button" class="is-success" wire:click="confirmRestore({{ $panelUser->id }})"><i class="bx bx-revision"></i>Restaurar</button>@endcan
                        @else
                            @can('bloquear usuarios')
                                @if($panelUser->isBanned())<button type="button" class="is-success" wire:click="confirmUnban({{ $panelUser->id }})"><i class="bx bx-lock-open-alt"></i>Desbloquear</button>
                                @else<button type="button" class="is-warning" wire:click="openBanPanel({{ $panelUser->id }})" @disabled($panelUser->id === auth()->id())><i class="bx bx-block"></i>Bloquear</button>@endif
                            @endcan
                            @can('eliminar usuarios')<button type="button" class="is-danger" wire:click="confirmSoftDelete({{ $panelUser->id }})" @disabled($panelUser->id === auth()->id())><i class="bx bx-trash"></i>Eliminar</button>@endcan
                        @endif
                    </div>
                    <div><button type="button" class="users-secondary-button" wire:click="closePanel">Cerrar</button>@can('editar usuarios')@unless($panelUser->trashed())<button type="button" class="users-primary-button" wire:click="saveUserInfo" wire:loading.attr="disabled" wire:target="saveUserInfo"><span wire:loading.remove wire:target="saveUserInfo"><i class="bx bx-save"></i>Guardar cambios</span><span wire:loading wire:target="saveUserInfo"><i class="bx bx-loader-alt bx-spin"></i>Guardando</span></button>@endunless@endcan</div>
                </footer>
            </section>
        </div>
    @endif

    @if($showRolePanel && $panelUser)
        <div class="app-modal-backdrop users-stacked-backdrop"></div>
        <div class="app-modal-layer users-modal-layer is-stacked" role="dialog" aria-modal="true" aria-labelledby="roles-modal-title">
            <section class="users-modal users-roles-modal">
                <header class="users-modal-header"><div><span class="app-eyebrow">Control de acceso</span><h2 id="roles-modal-title">Roles de {{ $panelUser->name }}</h2><p>Debe conservar al menos un rol.</p></div><button type="button" class="users-modal-close" wire:click="closeRolePanel" aria-label="Cerrar"><i class="bx bx-x"></i></button></header>
                <div class="users-modal-body"><div class="users-role-options">
                    @foreach($this->roles as $role)
                        <label class="users-role-option {{ in_array($role->name, $selectedRoles) ? 'is-selected' : '' }}">
                            <input type="checkbox" wire:model.live="selectedRoles" value="{{ $role->name }}">
                            <span class="users-role-option-icon"><i class="bx {{ $role->icon ?: 'bx-shield-quarter' }}"></i></span>
                            <span><strong>{{ str($role->name)->replace('-', ' ')->title() }}</strong><small>{{ $role->permissions->count() }} permisos configurados</small></span>
                            <i class="bx bx-check-circle users-role-check"></i>
                        </label>
                    @endforeach
                </div>@error('selectedRoles')<p class="users-form-alert">{{ $message }}</p>@enderror</div>
                <footer class="users-modal-footer"><button type="button" class="users-secondary-button" wire:click="closeRolePanel"><i class="bx bx-arrow-back"></i>Volver</button><button type="button" class="users-primary-button" wire:click="saveRoles" wire:loading.attr="disabled" wire:target="saveRoles"><span wire:loading.remove wire:target="saveRoles"><i class="bx bx-save"></i>Guardar roles</span><span wire:loading wire:target="saveRoles"><i class="bx bx-loader-alt bx-spin"></i>Guardando</span></button></footer>
            </section>
        </div>
    @endif

    @if($showInvitationPanel)
        <div class="app-modal-backdrop" wire:click="closeInvitationPanel"></div>
        <div class="app-modal-layer users-modal-layer" role="dialog" aria-modal="true" aria-labelledby="invite-user-title">
            <section class="users-modal users-invitation-modal">
                <header class="users-modal-header">
                    <div class="users-modal-heading"><span><i class="bx bx-envelope" aria-hidden="true"></i></span><div><span class="app-eyebrow">Incorporación segura</span><h2 id="invite-user-title">Invitar usuario</h2><p>La persona completará sus propios datos mediante un enlace privado.</p></div></div>
                    <button type="button" class="users-modal-close" wire:click="closeInvitationPanel" aria-label="Cerrar"><i class="bx bx-x"></i></button>
                </header>
                <div class="users-modal-body">
                    <aside class="users-invitation-notice"><i class="bx bx-time-five" aria-hidden="true"></i><div><strong>Vigencia obligatoria de 1 hora</strong><p>Al reenviar una invitación para el mismo correo, el enlace anterior quedará invalidado. El rol no podrá modificarse durante el registro.</p></div></aside>
                    <div class="users-form-section">
                        <header><span><i class="bx bx-user-voice" aria-hidden="true"></i></span><div><h3>Datos de la invitación</h3><p>Selecciona primero la cuenta y su función dentro del equipo.</p></div></header>
                        <div class="users-form-grid">
                            <label class="is-full"><span>Correo electrónico <b>*</b></span><input type="email" wire:model="inviteEmail" placeholder="persona@dominio.com" autocomplete="email" autofocus><small class="users-field-help">Aquí recibirá el enlace para completar su registro.</small>@error('inviteEmail')<small class="users-field-error">{{ $message }}</small>@enderror</label>
                            <label class="is-full"><span>Rol que desempeñará <b>*</b></span><select wire:model="inviteRole"><option value="">Selecciona el rol asignado</option>@foreach($this->roles as $role)<option value="{{ $role->name }}">{{ str($role->name)->replace('-', ' ')->title() }} · {{ $role->permissions->count() }} permisos</option>@endforeach</select><small class="users-field-help">Este rol viajará protegido en la invitación y se asignará automáticamente.</small>@error('inviteRole')<small class="users-field-error">{{ $message }}</small>@enderror</label>
                        </div>
                    </div>
                </div>
                <footer class="users-modal-footer">
                    <button type="button" class="users-secondary-button" wire:click="closeInvitationPanel">Cancelar</button>
                    <button type="button" class="users-primary-button" wire:click="sendUserInvitation" wire:loading.attr="disabled" wire:target="sendUserInvitation">
                        <span wire:loading.remove wire:target="sendUserInvitation"><i class="bx bx-send" aria-hidden="true"></i>Enviar invitación</span>
                        <span wire:loading wire:target="sendUserInvitation"><i class="bx bx-loader-alt bx-spin" aria-hidden="true"></i>Enviando correo</span>
                    </button>
                </footer>
            </section>
        </div>
    @endif

    @if($showCreatePanel)
        <div class="app-modal-backdrop" wire:click="closeCreatePanel"></div>
        <div class="app-modal-layer users-modal-layer" role="dialog" aria-modal="true" aria-labelledby="create-user-title">
            <section class="users-modal users-create-modal">
                <header class="users-modal-header"><div class="users-modal-heading"><span><i class="bx bx-user-plus"></i></span><div><span class="app-eyebrow">Nueva cuenta</span><h2 id="create-user-title">Crear usuario</h2><p>El rol inicial es obligatorio para habilitar su área de trabajo.</p></div></div><button type="button" class="users-modal-close" wire:click="closeCreatePanel" aria-label="Cerrar"><i class="bx bx-x"></i></button></header>
                <div class="users-modal-body">
                    <div class="users-form-section"><header><span><i class="bx bx-id-card"></i></span><div><h3>Información personal</h3><p>Datos visibles dentro del equipo.</p></div></header><div class="users-form-grid">
                        <label><span>Nombre completo <b>*</b></span><input type="text" wire:model="createName" placeholder="Ej. Adriana López" autocomplete="name" autofocus>@error('createName')<small class="users-field-error">{{ $message }}</small>@enderror</label>
                        <label><span>Correo electrónico <b>*</b></span><input type="email" wire:model="createEmail" placeholder="adriana@restaurante.com" autocomplete="email">@error('createEmail')<small class="users-field-error">{{ $message }}</small>@enderror</label>
                        <label class="is-full"><span>Teléfono</span><input type="tel" wire:model="createPhone" placeholder="Ej. 5512345678" autocomplete="tel">@error('createPhone')<small class="users-field-error">{{ $message }}</small>@enderror</label>
                    </div></div>
                    <div class="users-form-section"><header><span><i class="bx bx-lock-alt"></i></span><div><h3>Acceso y rol</h3><p>Credenciales iniciales y nivel de acceso.</p></div></header><div class="users-form-grid">
                        <label><span>Contraseña <b>*</b></span><input type="password" wire:model="createPassword" placeholder="Mínimo 8 caracteres" autocomplete="new-password">@error('createPassword')<small class="users-field-error">{{ $message }}</small>@enderror</label>
                        <label><span>Confirmar contraseña <b>*</b></span><input type="password" wire:model="createPasswordCon" placeholder="Repite la contraseña" autocomplete="new-password">@error('createPasswordCon')<small class="users-field-error">{{ $message }}</small>@enderror</label>
                        <label class="is-full"><span>Rol inicial <b>*</b></span><select wire:model="createRole"><option value="">Selecciona el área de trabajo</option>@foreach($this->roles as $role)<option value="{{ $role->name }}">{{ str($role->name)->replace('-', ' ')->title() }} · {{ $role->permissions->count() }} permisos</option>@endforeach</select><small class="users-field-help">El usuario podrá iniciar con los permisos incluidos en este rol.</small>@error('createRole')<small class="users-field-error">{{ $message }}</small>@enderror</label>
                    </div></div>
                </div>
                <footer class="users-modal-footer"><button type="button" class="users-secondary-button" wire:click="closeCreatePanel">Cancelar</button><button type="button" class="users-primary-button" wire:click="createUser" wire:loading.attr="disabled" wire:target="createUser"><span wire:loading.remove wire:target="createUser"><i class="bx bx-user-plus"></i>Crear usuario</span><span wire:loading wire:target="createUser"><i class="bx bx-loader-alt bx-spin"></i>Creando cuenta</span></button></footer>
            </section>
        </div>
    @endif

    @if($showBanPanel && $banUserId)
        @php $banTarget = $this->banTarget; @endphp
        <div class="app-modal-backdrop users-stacked-backdrop" wire:click="closeBanPanel"></div>
        <div class="app-modal-layer users-modal-layer is-stacked" role="dialog" aria-modal="true" aria-labelledby="ban-user-title">
            <section class="users-modal users-ban-modal">
                <header class="users-modal-header is-warning"><div class="users-modal-heading"><span><i class="bx bx-block"></i></span><div><span class="app-eyebrow">Suspender acceso</span><h2 id="ban-user-title">Bloquear a {{ $banTarget?->name }}</h2><p>La cuenta podrá desbloquearse posteriormente.</p></div></div><button type="button" class="users-modal-close" wire:click="closeBanPanel" aria-label="Cerrar"><i class="bx bx-x"></i></button></header>
                <div class="users-modal-body"><aside class="users-ban-notice"><i class="bx bx-info-circle"></i><div><strong>Efecto inmediato</strong><p>Se cerrarán sus sesiones abiertas y no podrá volver a ingresar hasta que un administrador desbloquee la cuenta.</p></div></aside><label class="users-ban-reason"><span>Motivo administrativo <b>*</b></span><textarea wire:model.live="banReason" rows="4" maxlength="500" placeholder="Ej. Suspensión temporal solicitada por gerencia"></textarea><small>{{ mb_strlen($banReason) }}/500 caracteres</small>@error('banReason')<span class="users-field-error">{{ $message }}</span>@enderror</label></div>
                <footer class="users-modal-footer"><button type="button" class="users-secondary-button" wire:click="closeBanPanel">Cancelar</button><button type="button" class="users-warning-button" wire:click="banUser" wire:loading.attr="disabled" wire:target="banUser"><span wire:loading.remove wire:target="banUser"><i class="bx bx-block"></i>Bloquear acceso</span><span wire:loading wire:target="banUser"><i class="bx bx-loader-alt bx-spin"></i>Bloqueando</span></button></footer>
            </section>
        </div>
    @endif
</div>
