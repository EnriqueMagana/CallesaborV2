<section class="sidebar-manager" aria-labelledby="sidebar-manager-title">
    @if(session('menu_success'))
        <div class="biz-toast" role="status" aria-live="polite"><i class="bx bx-check-circle"></i>{{ session('menu_success') }}</div>
    @endif

    <header class="sidebar-manager__header">
        <div><span class="biz-eyebrow">Navegación persistente</span><h2 id="sidebar-manager-title">Elementos del menú lateral</h2><p>Organiza secciones, grupos y accesos respetando los permisos de cada rol.</p></div>
        @canany(['crear menu sidebar','gestionar bloqueos por caja'])
        <div class="sidebar-manager__create">
            @can('gestionar bloqueos por caja')
                <button type="button" class="biz-risk-button" wire:click="openRegisterPolicy" wire:loading.attr="disabled" wire:target="openRegisterPolicy"><i class="bx bx-lock-alt"></i>Acceso por caja</button>
            @endcan
            @can('crear menu sidebar')
                <button type="button" class="biz-ghost-button" wire:click="createItem('section')"><i class="bx bx-heading"></i>Nueva sección</button>
                <button type="button" class="biz-ghost-button" wire:click="createItem('group')"><i class="bx bx-folder-plus"></i>Nuevo grupo</button>
                <button type="button" class="biz-primary-button" wire:click="createItem('link')"><i class="bx bx-plus"></i>Nuevo elemento</button>
            @endcan
        </div>
        @endcanany
    </header>

    @can('gestionar bloqueos por caja')
        <section class="sidebar-risk-overview" aria-label="Política de acceso por caja">
            <span class="sidebar-risk-overview__icon"><i class="bx bx-shield-quarter" aria-hidden="true"></i></span>
            <div><strong>Control de módulos por caja</strong><p>Los accesos marcados se bloquean en el menú y también al escribir su URL cuando no existe una caja abierta.</p></div>
            <span class="sidebar-risk-overview__count"><b>{{ $this->protectedModuleCount }}</b>{{ $this->protectedModuleCount === 1 ? 'módulo protegido' : 'módulos protegidos' }}</span>
        </section>
    @endcan

    <section class="sidebar-structure-guide" aria-label="Niveles permitidos del menú">
        <div><span>1</span><i class="bx bx-heading"></i><p><strong>Sección principal</strong><small>Ordena las áreas grandes del sistema.</small></p></div>
        <i class="bx bx-chevron-right" aria-hidden="true"></i>
        <div><span>2</span><i class="bx bx-folder"></i><p><strong>Grupo padre</strong><small>Agrupa módulos relacionados.</small></p></div>
        <i class="bx bx-chevron-right" aria-hidden="true"></i>
        <div><span>3</span><i class="bx bx-link"></i><p><strong>Módulo único</strong><small>Cada ruta solo puede aparecer una vez.</small></p></div>
    </section>

    <div class="sidebar-manager__layout">
        <div class="sidebar-tree" wire:loading.class="is-loading" wire:target="saveItem,moveItem,handleConfirmation">
            <header><strong>Estructura actual</strong><span>{{ $this->menuTree->count() }} bloques principales</span></header>
            <div class="sidebar-tree__loading" wire:loading.flex wire:target="saveItem,moveItem,handleConfirmation" aria-label="Actualizando menú">
                @for($i=0;$i<5;$i++)<span></span>@endfor
            </div>
            <div wire:loading.remove wire:target="saveItem,moveItem,handleConfirmation">
                @forelse($this->menuTree as $item)
                    <x-business.sidebar-tree-node :item="$item" />
                @empty
                    <div class="sidebar-empty"><i class="bx bx-list-ul"></i><strong>Aún no hay elementos</strong><p>Crea una sección o un acceso para comenzar.</p></div>
                @endforelse
            </div>
        </div>

        @canany(['crear menu sidebar','editar menu sidebar'])
        <form wire:submit="saveItem" class="sidebar-editor">
            <header><div><span class="biz-eyebrow">{{ $editingId ? 'Editando elemento' : 'Nuevo elemento' }}</span><h3>{{ $editingId ? $label : 'Configura el acceso' }}</h3></div>@if($editingId)<button type="button" wire:click="resetEditor" aria-label="Cerrar edición"><i class="bx bx-x"></i></button>@endif</header>
            <div class="sidebar-type-selector" role="radiogroup" aria-label="Tipo de elemento">
                @foreach(['link' => ['bx-link','Enlace'], 'group' => ['bx-folder','Grupo'], 'section' => ['bx-heading','Sección']] as $value => $meta)
                    <label class="{{ $type === $value ? 'is-active' : '' }}"><input type="radio" wire:model.live="type" value="{{ $value }}"><i class="bx {{ $meta[0] }}"></i><span>{{ $meta[1] }}</span></label>
                @endforeach
            </div>
            <x-business.field label="Nombre visible" for="sidebar-label" :error="$errors->first('label')"><input id="sidebar-label" type="text" wire:model.blur="label" placeholder="Ej. Reportes de ventas" maxlength="80"></x-business.field>
            @if($type !== 'section')
                <x-business.field label="Agrupar dentro de" for="sidebar-parent" hint="Opcional. Los grupos solo pueden estar dentro de una sección." :error="$errors->first('parentId')">
                    <select id="sidebar-parent" wire:model="parentId"><option value="">{{ $type === 'group' ? 'Selecciona una sección' : 'Nivel principal' }}</option>@foreach($this->parentOptions as $parent)<option value="{{ $parent->id }}">{{ $parent->type === 'section' ? 'Sección' : 'Grupo' }} · {{ $parent->parent ? $parent->parent->label.' / ' : '' }}{{ $parent->label }}</option>@endforeach</select>
                </x-business.field>
                @if($editingId)<p class="sidebar-move-hint"><i class="bx bx-move"></i>Cambia este campo para mover el elemento completo, incluyendo sus hijos.</p>@endif
            @endif
            <x-business.field label="Icono Boxicons" for="sidebar-icon" hint="Déjalo vacío para eliminar el icono." :error="$errors->first('icon')">
                <div class="sidebar-icon-field" x-data="{ selectedIcon: $wire.entangle('icon') }"><span><i class="bx" x-bind:class="selectedIcon || 'bx-minus'"></i></span><input id="sidebar-icon" type="text" x-model="selectedIcon" list="sidebar-icon-list" placeholder="bx-receipt"><button type="button" x-on:click="selectedIcon = ''" aria-label="Quitar icono"><i class="bx bx-x"></i></button></div>
                <datalist id="sidebar-icon-list">@foreach($icons as $availableIcon)<option value="{{ $availableIcon }}">@endforeach</datalist>
            </x-business.field>
            @if($type === 'link')
                <x-business.field label="Ruta interna" for="sidebar-route" hint="Usa el nombre de una ruta Laravel existente." :error="$errors->first('routeName')"><input id="sidebar-route" type="text" wire:model.blur="routeName" list="sidebar-route-list" placeholder="app.reportes"><datalist id="sidebar-route-list">@foreach($this->appRoutes as $route)<option value="{{ $route }}">@endforeach</datalist></x-business.field>
                <x-business.field label="URL personalizada" for="sidebar-url" hint="Solo si el acceso no usa una ruta interna." :error="$errors->first('url')"><input id="sidebar-url" type="text" wire:model.blur="url" placeholder="https:// o /ruta"></x-business.field>
                <x-business.field label="Patrón de página activa" for="sidebar-pattern" hint="Admite comodines, por ejemplo app.mesas*." :error="$errors->first('activePattern')"><input id="sidebar-pattern" type="text" wire:model.blur="activePattern" placeholder="app.modulo*"></x-business.field>
            @endif
            <x-business.field label="Permiso requerido" for="sidebar-permission" hint="Sin permiso será visible para cualquier usuario autenticado." :error="$errors->first('permission')"><select id="sidebar-permission" wire:model="permission"><option value="">Sin restricción adicional</option>@foreach($this->permissions->groupBy('group') as $group => $permissions)<optgroup label="{{ ucfirst($group ?: 'general') }}">@foreach($permissions as $availablePermission)<option value="{{ $availablePermission->name }}">{{ $availablePermission->name }}</option>@endforeach</optgroup>@endforeach</select></x-business.field>
            @if($type === 'link')
                @can('gestionar bloqueos por caja')
                    <x-business.policy-toggle
                        model="requiresOpenRegister"
                        :checked="$requiresOpenRegister"
                        title="Requiere caja abierta"
                        :description="$this->canRequireOpenRegister ? 'Impide entrar a este módulo y sus subrutas cuando la caja está cerrada.' : 'Selecciona una ruta interna. Caja y Configuración siempre permanecen disponibles.'"
                        :disabled="! $this->canRequireOpenRegister"
                    />
                    @error('requiresOpenRegister')<p class="biz-form-error" role="alert">{{ $message }}</p>@enderror
                @endcan
            @endif
            <label class="sidebar-active-toggle"><input type="checkbox" wire:model="isActive"><span><strong>Elemento visible</strong><small>Al desactivarlo se conserva la configuración, pero desaparece del sidebar.</small></span></label>
            <button type="submit" class="biz-primary-button" wire:loading.attr="disabled" wire:target="saveItem"><span wire:loading.remove wire:target="saveItem"><i class="bx bx-save"></i>{{ $editingId ? 'Guardar cambios' : 'Crear elemento' }}</span><span wire:loading wire:target="saveItem">Guardando…</span></button>
        </form>
        @endcanany
    </div>

    @if($showRegisterPolicy)
        <div class="register-policy-modal" role="presentation" wire:key="register-policy-modal">
            <button type="button" class="register-policy-modal__backdrop" wire:click="closeRegisterPolicy" aria-label="Cerrar política de acceso"></button>
            <section class="register-policy-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="register-policy-title">
                <header>
                    <span class="register-policy-modal__icon"><i class="bx bx-shield-quarter" aria-hidden="true"></i></span>
                    <div><span class="biz-eyebrow">Seguridad operativa</span><h2 id="register-policy-title">Acceso por caja</h2><p>Selecciona únicamente los módulos que representan un riesgo cuando no hay un turno de caja activo.</p></div>
                    <button type="button" wire:click="closeRegisterPolicy" aria-label="Cerrar"><i class="bx bx-x"></i></button>
                </header>

                <div class="register-policy-modal__notice"><i class="bx bx-info-circle" aria-hidden="true"></i><span><strong>No todos los módulos necesitan caja.</strong> Dashboard, configuración, catálogos o reportes pueden permanecer disponibles. Solo los elementos activados quedarán bloqueados.</span></div>

                <form wire:submit="saveRegisterPolicies">
                    <div class="register-policy-list">
                        @forelse($registerPolicies as $index => $policy)
                            <x-business.policy-toggle
                                model="registerPolicies.{{ $index }}.requires"
                                :checked="$policy['requires']"
                                :title="$policy['label']"
                                :description="$policy['route']"
                                :icon="$policy['icon']"
                                compact
                            />
                        @empty
                            <div class="sidebar-empty"><i class="bx bx-link"></i><strong>No hay rutas configurables</strong><p>Agrega primero enlaces internos al menú lateral.</p></div>
                        @endforelse
                    </div>
                    <footer>
                        <p><i class="bx bx-bolt-circle" aria-hidden="true"></i>Los cambios se envían juntos al guardar, sin una petición por cada interruptor.</p>
                        <div><button type="button" class="biz-ghost-button" wire:click="closeRegisterPolicy">Cancelar</button><button type="submit" class="biz-primary-button" wire:loading.attr="disabled" wire:target="saveRegisterPolicies"><span wire:loading.remove wire:target="saveRegisterPolicies"><i class="bx bx-save"></i>Guardar política</span><span wire:loading wire:target="saveRegisterPolicies">Guardando…</span></button></div>
                    </footer>
                </form>
            </section>
        </div>
    @endif
</section>
