<div class="app-page admin-management-page roles-page notification-roles-page"
     x-data="{ toasts: [], roleSearch: '' }"
     x-on:notify.window="toasts.push($event.detail); setTimeout(() => toasts.shift(), 3500)">

    <div class="position-fixed top-0 end-0 p-3 notification-roles-toasts" aria-live="polite">
        <template x-for="(toast, index) in toasts" :key="index">
            <div class="toast show align-items-center border-0 mb-2"
                 :class="{'text-bg-success':toast.type==='success','text-bg-danger':toast.type==='error','text-bg-info':toast.type==='info'}">
                <div class="d-flex">
                    <div class="toast-body fw-medium" x-text="toast.message"></div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto"
                            aria-label="Cerrar notificación" @click="toasts.splice(index, 1)"></button>
                </div>
            </div>
        </template>
    </div>

    <header class="app-page-header roles-hero notification-roles-hero">
        <div class="app-page-heading">
            <span class="app-page-icon roles-hero__icon" aria-hidden="true"><i class="bx bx-bell"></i></span>
            <div>
                <div class="app-eyebrow">Administración · Comunicaciones</div>
                <h1 class="app-page-title">Notificaciones por rol</h1>
                <p class="app-page-subtitle">Decide qué avisos operativos recibe cada responsabilidad del equipo.</p>
            </div>
        </div>
        <div class="notification-roles-hero__actions">
            <div class="roles-hero__summary" aria-label="Resumen de configuración">
                <span><i class="bx bx-group" aria-hidden="true"></i><strong>{{ $this->roles->count() }}</strong> roles</span>
                <span><i class="bx bx-bell" aria-hidden="true"></i><strong>{{ count(\App\Support\NotificationEventCatalog::keys()) }}</strong> avisos</span>
                <span><i class="bx bx-slider-alt" aria-hidden="true"></i><strong>{{ $this->configuredRolesCount }}</strong> personalizados</span>
            </div>
            <a class="notification-roles-back" href="{{ route('app.roles-permisos') }}">
                <i class="bx bx-shield-quarter" aria-hidden="true"></i>
                <span>Ir a roles y permisos</span>
            </a>
        </div>
    </header>

    <section class="notification-roles-guide" aria-label="Cómo funciona la configuración">
        <span class="notification-roles-guide__icon" aria-hidden="true"><i class="bx bx-bulb"></i></span>
        <div>
            <strong>Configura por responsabilidad, no por persona</strong>
            <p>Elige un rol, marca los avisos compatibles con sus permisos y guarda una sola vez. Los cambios aplican a todos sus usuarios.</p>
        </div>
        <span class="notification-roles-guide__privacy"><i class="bx bx-lock-alt" aria-hidden="true"></i>Los avisos incompatibles permanecen bloqueados</span>
    </section>

    <div class="role-notifications-layout">
        <aside class="role-notifications-roles" aria-label="Roles disponibles">
            <header>
                <span><strong>Roles del sistema</strong><small>{{ $this->roles->count() }} disponibles</small></span>
            </header>
            <div class="role-notifications-search">
                <i class="bx bx-search" aria-hidden="true"></i>
                <label for="role-notification-search" class="visually-hidden">Buscar rol</label>
                <input id="role-notification-search" type="search" x-model="roleSearch"
                       placeholder="Buscar rol..." autocomplete="off">
                <button type="button" x-show="roleSearch" x-cloak @click="roleSearch = ''"
                        aria-label="Limpiar búsqueda de roles"><i class="bx bx-x" aria-hidden="true"></i></button>
            </div>
            <div class="role-notifications-role-list">
                @foreach($this->roles as $role)
                    <button type="button"
                            class="role-notifications-role {{ $notificationRoleId === $role->id ? 'is-active' : '' }}"
                            wire:click="selectNotificationRole({{ $role->id }})"
                            wire:key="notification-role-{{ $role->id }}"
                            data-role-name="{{ str($role->name)->lower() }}"
                            x-show="$el.dataset.roleName.includes(roleSearch.trim().toLowerCase())"
                            aria-pressed="{{ $notificationRoleId === $role->id ? 'true' : 'false' }}">
                        <span><i class="bx bx-user" aria-hidden="true"></i></span>
                        <span>
                            <strong>{{ str($role->name)->replace('-', ' ')->title() }}</strong>
                            <small>{{ $role->users_count }} usuario(s) · {{ $role->permissions_count }} permisos</small>
                        </span>
                        <i class="bx {{ $notificationRoleId === $role->id ? 'bx-check-circle' : 'bx-chevron-right' }}" aria-hidden="true"></i>
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
                            <span class="roles-toolbar__eyebrow">Configurando avisos para</span>
                            <h2>{{ str($this->notificationRole->name)->replace('-', ' ')->title() }}</h2>
                            <p>{{ $notificationRoleConfigured ? 'Configuración personalizada activa para este rol.' : 'Usa el comportamiento automático hasta que guardes una configuración.' }}</p>
                        </div>
                        <span class="role-notifications-status {{ $notificationRoleConfigured ? 'is-custom' : '' }}">
                            <i class="bx {{ $notificationRoleConfigured ? 'bx-slider-alt' : 'bx-history' }}" aria-hidden="true"></i>
                            {{ $notificationRoleConfigured ? 'Personalizado' : 'Automático' }}
                        </span>
                    </header>

                    <div class="role-notifications-unsaved" wire:dirty wire:target="roleNotificationEvents">
                        <i class="bx bx-edit-alt" aria-hidden="true"></i>
                        Tienes cambios sin guardar para este rol.
                    </div>

                    <div class="role-notifications-groups">
                        @foreach($this->notificationEventGroups as $groupKey => $group)
                            <fieldset class="role-notification-group" wire:key="notification-group-{{ $groupKey }}">
                                <legend>
                                    <i class="bx {{ $group['icon'] }}" aria-hidden="true"></i>
                                    <span><strong>{{ $group['label'] }}</strong><small>{{ $group['description'] }}</small></span>
                                </legend>
                                <div>
                                    @foreach($group['events'] as $eventKey => $event)
                                        @php($compatible = $this->roleSupportsEvent($this->notificationRole, $eventKey))
                                        <label class="role-notification-event {{ $compatible ? '' : 'is-disabled' }}">
                                            <input type="checkbox" wire:model="roleNotificationEvents"
                                                   value="{{ $eventKey }}" @disabled(!$compatible)>
                                            <span class="role-notification-event__icon"><i class="bx {{ $event['icon'] }}" aria-hidden="true"></i></span>
                                            <span class="role-notification-event__copy">
                                                <strong>{{ $event['label'] }}</strong>
                                                <small>{{ $event['description'] }}</small>
                                                @if(!$compatible)
                                                    <em><i class="bx bx-lock-alt" aria-hidden="true"></i> Requiere alguno: {{ implode(', ', $event['permissions']) }}</em>
                                                @endif
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach
                    </div>

                    @error('roleNotificationEvents')
                        <p class="role-notifications-error" role="alert">{{ $message }}</p>
                    @enderror

                    <footer class="role-notifications-actions">
                        <p><i class="bx bx-info-circle" aria-hidden="true"></i> Marcar opciones no genera peticiones; todo se envía junto al guardar.</p>
                        <div>
                            @if($notificationRoleConfigured)
                                <button type="button" class="btn btn-outline-secondary"
                                        wire:click="restoreAutomaticRoleNotifications"
                                        wire:loading.attr="disabled"
                                        wire:target="restoreAutomaticRoleNotifications,saveRoleNotifications">
                                    <i class="bx bx-reset" aria-hidden="true"></i> Restaurar automático
                                </button>
                            @endif
                            <button type="submit" class="roles-primary-action"
                                    wire:loading.attr="disabled" wire:target="saveRoleNotifications">
                                <span wire:loading.remove wire:target="saveRoleNotifications"><i class="bx bx-save" aria-hidden="true"></i>Guardar notificaciones</span>
                                <span wire:loading wire:target="saveRoleNotifications"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Guardando…</span>
                            </button>
                        </div>
                    </footer>
                </form>
            @else
                <div class="role-notifications-empty">
                    <span><i class="bx bx-bell" aria-hidden="true"></i></span>
                    <h2>No hay roles disponibles</h2>
                    <p>Crea un rol desde el módulo de roles y permisos para comenzar su configuración.</p>
                </div>
            @endif
        </section>
    </div>
</div>
