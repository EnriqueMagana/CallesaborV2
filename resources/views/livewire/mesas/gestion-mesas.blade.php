<div class="app-page mesas-page" x-data="{ booting: true }" x-init="requestAnimationFrame(() => requestAnimationFrame(() => booting = false))">
    <div class="mesas-initial-skeleton" x-show="booting" role="status" aria-label="Cargando gestión de mesas">
        <header><span></span>
            <div><b></b><i></i></div><strong></strong>
        </header>
        <nav>
            @for ($i = 0; $i < 4; $i++)
                <span></span>
            @endfor
            <i>
            </i>
        </nav>
        <section class="mesas-skeleton-filters">
            @for ($i = 0; $i < 5; $i++)
                <span></span>
            @endfor
        </section>
        <section class="mesas-skeleton-grid">
            @for ($i = 0; $i < 6; $i++)
                <article>
                    <header><span></span><i></i></header><b></b><i></i>
                    <footer><span></span><strong></strong></footer>
                </article>
            @endfor
        </section>
    </div>

    <div x-show="!booting" x-cloak>
        @php
            $mesaUser = auth()->user();
            $canLegacyManageMesas = $mesaUser?->can('gestionar mesas');
            $canCreateAreas = $canLegacyManageMesas || $mesaUser?->can('crear areas de mesas');
            $canEditAreas = $canLegacyManageMesas || $mesaUser?->can('editar areas de mesas');
            $canDeleteAreas = $canLegacyManageMesas || $mesaUser?->can('eliminar areas de mesas');
            $canCreateMesas = $canLegacyManageMesas || $mesaUser?->can('crear mesas');
            $canEditMesas = $canLegacyManageMesas || $mesaUser?->can('editar mesas');
            $canDeleteMesas = $canLegacyManageMesas || $mesaUser?->can('eliminar mesas');
            $canChangeMesaStatus = $canLegacyManageMesas || $mesaUser?->can('cambiar estado mesas');
            $canManageGroups = $canLegacyManageMesas || $mesaUser?->can('gestionar grupos');
            $canViewAllMesas = $mesaUser?->can('ver todas las mesas');
        @endphp

        {{-- Flash --}}
        @if (session('success'))
            <div class="mesas-toast" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3500)"
                x-transition:leave="mesas-toast-leave">
                <i class="bx bx-check-circle"></i> {{ session('success') }}
            </div>
        @endif

        {{-- ══════════════════ PAGE HEADER ══════════════════ --}}
        <div class="mesas-page-header">
            <div class="mesas-page-title">
                <i class="bx bx-table mesas-page-icon"></i>
                <div>
                    <h4>Gestión de Mesas</h4>
                    <p>
                        <span class="mh-stat"><i class="bx bx-check-circle text-success"></i>
                            {{ $this->availableCount }} disponibles</span>
                        <span class="mh-stat"><i class="bx bx-user-check text-primary"></i>
                            {{ $this->myActiveMesaCount }} mis mesas</span>
                    </p>
                </div>
            </div>
            <div class="mesas-page-actions">
                @if ($canCreateAreas || $canEditAreas || $canDeleteAreas)
                    <button class="btn btn-outline-secondary btn-sm mesas-action-loader" wire:click="openAreaModal()"
                        wire:loading.class="is-loading" wire:loading.attr="disabled" wire:target="openAreaModal">
                        <span><i class="bx bx-map-pin me-1"></i> Áreas</span><b></b>
                    </button>
                @endif
                @if ($canCreateMesas)
                    <button class="btn btn-primary btn-sm mesas-action-loader" wire:click="openMesaModal()"
                        wire:loading.class="is-loading" wire:loading.attr="disabled" wire:target="openMesaModal">
                        <span><i class="bx bx-plus me-1"></i> Nueva Mesa</span><b></b>
                    </button>
                @endif
            </div>
        </div>

        @if ($this->pendingHelpRequests->isNotEmpty())
            <section class="mesa-help-inbox" aria-labelledby="mesa-help-inbox-title">
                <div class="mesa-help-inbox__heading">
                    <span><i class="bx bx-user-plus" aria-hidden="true"></i></span>
                    <div>
                        <h5 id="mesa-help-inbox-title">Solicitudes de apoyo</h5>
                        <p>Confirma sólo las mesas que puedes atender en este momento.</p>
                    </div>
                    <strong>{{ $this->pendingHelpRequests->count() }}</strong>
                </div>
                <div class="mesa-help-inbox__list">
                    @foreach ($this->pendingHelpRequests as $helpRequest)
                        <article class="mesa-help-request" wire:key="mesa-help-request-{{ $helpRequest->id }}">
                            <div class="mesa-help-request__avatar">
                                @if ($helpRequest->requester?->avatar)
                                    <img src="{{ Storage::url($helpRequest->requester->avatar) }}" alt="Foto de {{ $helpRequest->requester->name }}" width="44" height="44">
                                @else
                                    <span>{{ strtoupper(substr($helpRequest->requester?->name ?? 'M', 0, 1)) }}</span>
                                @endif
                            </div>
                            <div class="mesa-help-request__copy">
                                <strong>{{ $helpRequest->requester?->name ?? 'Un compañero' }} solicita apoyo</strong>
                                <span>{{ $helpRequest->scope === 'group' ? ($helpRequest->group?->name ?? 'Grupo de mesas') : $helpRequest->mesa?->display_name }}</span>
                                @if ($helpRequest->message)<small>{{ $helpRequest->message }}</small>@endif
                            </div>
                            <div class="mesa-help-request__actions">
                                <button type="button" class="btn btn-outline-secondary" wire:click="respondToHelpRequest({{ $helpRequest->id }}, false)" wire:loading.attr="disabled" wire:target="respondToHelpRequest({{ $helpRequest->id }}, false)">No puedo</button>
                                <button type="button" class="btn btn-primary" wire:click="respondToHelpRequest({{ $helpRequest->id }}, true)" wire:loading.attr="disabled" wire:target="respondToHelpRequest({{ $helpRequest->id }}, true)"><i class="bx bx-check me-1"></i>Aceptar</button>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- ══════════════════ TABS ══════════════════ --}}
        <div class="mesas-tabs-bar">
            <div class="mesas-tabs">
                <button class="mesas-tab {{ $tab === 'disponibles' ? 'active' : '' }}"
                    wire:click="setTab('disponibles')" wire:loading.attr="disabled" wire:target="setTab">
                    <i class="bx bx-check-circle"></i>
                    <span>Disponibles</span>
                    <span class="mesas-tab-badge badge-green">{{ $this->availableCount }}</span>
                </button>
                <button class="mesas-tab {{ $tab === 'mis_mesas' ? 'active' : '' }}" wire:click="setTab('mis_mesas')"
                    wire:loading.attr="disabled" wire:target="setTab">
                    <i class="bx bx-user-check"></i>
                    <span>Mis Mesas</span>
                    <span class="mesas-tab-badge badge-blue">{{ $this->myActiveMesaCount }}</span>
                </button>
                <button class="mesas-tab mesas-tab--kiosk {{ $tab === 'kiosko' ? 'active' : '' }}"
                    wire:click="setTab('kiosko')" wire:loading.attr="disabled" wire:target="setTab">
                    <i class="bx bx-desktop"></i>
                    <span>Kiosko</span>
                    <span class="mesas-tab-badge badge-purple">{{ $this->kioskCount }}</span>
                </button>
                @if ($canViewAllMesas)
                    <button class="mesas-tab {{ $tab === 'todas' ? 'active' : '' }}" wire:click="setTab('todas')"
                        wire:loading.attr="disabled" wire:target="setTab">
                        <i class="bx bx-grid-alt"></i>
                        <span>Todas las Mesas</span>
                    </button>
                @endif
            </div>

            {{-- Search bar --}}
            <form class="mesas-search-wrap" wire:submit="applySearch">
                <i class="bx bx-search mesas-search-icon"></i>
                <input type="search" class="mesas-search-input" wire:model="search"
                    aria-label="Buscar mesa por número o nombre" placeholder="Buscar por número o nombre…"
                    autocomplete="off">
                @if ($search)
                    <button type="button" class="mesas-search-clear" wire:click="clearSearch"
                        wire:loading.attr="disabled" wire:target="clearSearch" aria-label="Limpiar búsqueda">
                        <i class="bx bx-x"></i>
                    </button>
                @endif
                <button type="submit" class="mesas-search-submit mesas-action-loader" wire:loading.class="is-loading"
                    wire:loading.attr="disabled" wire:target="applySearch">
                    <span>Buscar</span><b></b>
                </button>
            </form>
        </div>

        {{-- ══════════════════ FILTER BAR ══════════════════ --}}
        <div class="mesas-filter-bar">
            {{-- Area pills --}}
            <div class="mesas-filter-group">
                <span class="mesas-filter-label">Área:</span>
                <button class="mesas-filter-pill {{ $areaFilter === null ? 'active' : '' }}"
                    wire:click="$set('areaFilter', null)" wire:loading.attr="disabled"
                    wire:target="areaFilter">Todas</button>
                @foreach ($this->areas as $area)
                    <button class="mesas-filter-pill {{ $areaFilter === $area->id ? 'active' : '' }}"
                        wire:click="$set('areaFilter', {{ $area->id }})" wire:loading.attr="disabled"
                        wire:target="areaFilter">
                        <i class="bx {{ $area->icon }}"></i> {{ $area->name }}
                    </button>
                @endforeach
            </div>

            {{-- Status pills — solo en tab todas (admin) --}}
            @if ($tab === 'todas')
                <div class="mesas-filter-group">
                    <span class="mesas-filter-label">Ver:</span>
                    {{-- "Todas activas" = sin filtro específico → muestra ocupada + en_cuenta --}}
                    <button
                        class="mesas-filter-pill mesas-filter-pill--primary {{ $statusFilter === '' ? 'active' : '' }}"
                        wire:click="$set('statusFilter', '')" wire:loading.attr="disabled" wire:target="statusFilter">
                        <i class="bx bx-grid-alt me-1"></i> Todas activas
                    </button>
                    <button
                        class="mesas-filter-pill mesas-filter-pill--info {{ $statusFilter === 'ocupada' ? 'active' : '' }}"
                        wire:click="$set('statusFilter', '{{ $statusFilter === 'ocupada' ? '' : 'ocupada' }}')"
                        wire:loading.attr="disabled" wire:target="statusFilter">
                        <i class="bx bx-user-check me-1"></i> Ocupadas
                    </button>
                    <button
                        class="mesas-filter-pill mesas-filter-pill--warning {{ $statusFilter === 'en_cuenta' ? 'active' : '' }}"
                        wire:click="$set('statusFilter', '{{ $statusFilter === 'en_cuenta' ? '' : 'en_cuenta' }}')"
                        wire:loading.attr="disabled" wire:target="statusFilter">
                        <i class="bx bx-receipt me-1"></i> En cuenta
                    </button>
                </div>
            @endif

            {{-- Group action --}}
            @if ($canManageGroups)
                <div class="ms-auto">
                    <button class="btn btn-outline-secondary btn-sm"
                        wire:click="openGroupModal({{ $areaFilter }})">
                        <i class="bx bx-merge me-1"></i> Agrupar mesas
                    </button>
                </div>
            @endif
        </div>

        {{-- ══════════════════ MESA GRID ══════════════════ --}}
        <div wire:loading.class="mesas-grid-loading"
            wire:target="setTab,applySearch,clearSearch,areaFilter,statusFilter" class="mesas-grid-wrap">

            <div wire:loading.flex wire:target="setTab,applySearch,clearSearch,areaFilter,statusFilter"
                class="mesas-loading-overlay">
                <div class="mesas-loading-spinner">
                    <i class="bx bx-loader-alt"></i><span>Actualizando mesas…</span>
                </div>
            </div>

            @php $mesas = $this->mesas; @endphp

            @if ($mesas->isEmpty())
                {{-- Empty state --}}
                <div class="mesas-empty">
                    @if ($tab === 'disponibles')
                        <i class="bx bx-check-circle mesas-empty-icon" data-ui="xui-1cjvn4l"></i>
                        <h5>No hay mesas disponibles</h5>
                        <p>Todas las mesas están ocupadas o bloqueadas en este momento.</p>
                    @elseif($tab === 'mis_mesas')
                        <i class="bx bx-user mesas-empty-icon" data-ui="xui-eqavzl"></i>
                        <h5>No tienes mesas asignadas</h5>
                        <p>Ve a <strong>Disponibles</strong> para asignarte una mesa.</p>
                    @elseif($tab === 'kiosko')
                        <i class="bx bx-desktop mesas-empty-icon"></i>
                        <h5>No hay mesas con pedidos de kiosko</h5>
                        <p>Cuando un cliente seleccione una mesa en el kiosko, aparecerá aquí para que puedas tomarla.
                        </p>
                    @elseif($tab === 'todas')
                        <i class="bx bx-table mesas-empty-icon" data-ui="xui-ea0on8"></i>
                        <h5>Sin mesas activas</h5>
                        <p>No hay mesas ocupadas ni en cuenta en este momento.</p>
                    @else
                        <i class="bx bx-search-alt mesas-empty-icon" data-ui="xui-ea0on8"></i>
                        <h5>Sin resultados</h5>
                        <p>Prueba cambiando los filtros de búsqueda.</p>
                    @endif
                </div>
            @else
                @php
                    // disponibles + todas: group by area. mis_mesas: flat.
                    $groupByArea = in_array($tab, ['disponibles', 'kiosko', 'todas']);
                    $mesasByArea = $groupByArea ? $mesas->groupBy('area_id') : collect(['_flat' => $mesas]);
                @endphp

                @foreach ($mesasByArea as $areaId => $areaMesas)
                    @if ($groupByArea)
                        @php $areaModel = $areaMesas->first()->area; @endphp
                        <div class="mesas-area-block">
                            <div class="mesas-area-section">
                                <div
                                    class="mesas-area-header {{ $tab === 'disponibles' ? 'mesas-area-header--compact' : '' }}">
                                    <div class="mesas-area-header-left">
                                        <div class="mesas-area-icon">
                                            <i class="bx {{ $areaModel->icon ?? 'bx-map-pin' }}"></i>
                                        </div>
                                        <div>
                                            <span class="mesas-area-name">{{ $areaModel->name }}</span>
                                            <span class="mesas-area-meta">
                                                {{ $areaMesas->count() }} mesa(s)
                                                @if ($tab === 'todas')
                                                    ·
                                                    ${{ number_format($areaMesas->flatMap->activeOrders->sum('total'), 2) }}
                                                    en curso
                                                @endif
                                            </span>
                                        </div>
                                    </div>
                                    @if ($tab === 'todas')
                                        <div class="mesas-area-stats">
                                            @foreach (['ocupada', 'en_cuenta', 'reservada'] as $st)
                                                @php $cnt = $areaMesas->where('status', $st)->count(); @endphp
                                                @if ($cnt)
                                                    <span
                                                        class="mesas-area-stat-pill mesas-area-stat-pill--{{ $st }}">
                                                        {{ $cnt }}
                                                        {{ match ($st) {'ocupada' => 'ocupada(s)','en_cuenta' => 'en cuenta','reservada' => 'reservada(s)',default => $st} }}
                                                    </span>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                    @endif

                    <div class="mesas-grid">
                        @php
                            $renderedGroups = [];
                        @endphp
                        @foreach ($areaMesas as $mesa)
                            @if ($mesa->mesa_group_id && !in_array($mesa->mesa_group_id, $renderedGroups))
                                @php
                                    $renderedGroups[] = $mesa->mesa_group_id;
                                    $groupMesas = $mesas->where('mesa_group_id', $mesa->mesa_group_id);
                                    $firstMesa = $groupMesas->first();
                                    $activeSplit = $firstMesa->splits->first();
                                @endphp
                                {{-- GROUP CARD --}}
                                <div class="mesa-card mesa-card-group status-{{ $firstMesa->status }} {{ $tab === 'todas' ? 'mesa-card--directory' : '' }}"
                                    x-data="{ open: false }" :class="{ 'menu-open': open }">

                                    <div class="mesa-card-topbar">
                                        <div class="mesa-group-badge">
                                            <i class="bx bx-merge"></i>
                                            {{ $mesa->group->name ?? 'Grupo' }}
                                        </div>
                                        <div class="mesa-status-badge">
                                            <i class="bx {{ $firstMesa->status_icon }}"></i>
                                            {{ $firstMesa->status_label }}
                                        </div>
                                        @if ($firstMesa->activeOrders->contains(fn($order) => $order->source === 'kiosk'))
                                            <span class="mesa-kiosk-badge"><i class="bx bx-desktop"></i> Pedido
                                                kiosko</span>
                                        @endif
                                    </div>

                                    <div class="mesa-card-body">
                                        <div class="mesa-numbers-group">
                                            @foreach ($groupMesas as $gm)
                                                <span class="mesa-num-chip">{{ $gm->number }}</span>
                                            @endforeach
                                        </div>
                                        <div class="mesa-meta">
                                            <span><i class="bx bx-map-pin"></i>
                                                {{ $firstMesa->area->name ?? '–' }}</span>
                                            <span><i class="bx bx-group"></i>
                                                {{ $groupMesas->sum('capacity') }} personas
                                            </span>
                                        </div>
                                        @if (in_array($firstMesa->status, ['ocupada', 'en_cuenta']))
                                            @php $activeOrders = $groupMesas->flatMap->activeOrders; @endphp
                                            <div class="mesa-orders-info">
                                                <i class="bx bx-receipt"></i>
                                                {{ $activeOrders->count() }} órden(es) ·
                                                ${{ number_format($activeOrders->sum('total'), 2) }}
                                            </div>
                                        @endif
                                        @if ($tab === 'todas')
                                            @php $directoryWaiter = $firstMesa->currentAssignment?->waiter; @endphp
                                            <div class="mesa-staff-overview {{ $directoryWaiter ? '' : 'is-unassigned' }}">
                                                <div class="mesa-staff-overview__avatar">
                                                    @if ($directoryWaiter?->avatar)
                                                        <img src="{{ Storage::url($directoryWaiter->avatar) }}" alt="Foto de {{ $directoryWaiter->name }}" width="52" height="52" loading="lazy" decoding="async">
                                                    @elseif($directoryWaiter)
                                                        <span>{{ strtoupper(substr($directoryWaiter->name, 0, 1)) }}</span>
                                                    @else
                                                        <i class="bx bx-user-x" aria-hidden="true"></i>
                                                    @endif
                                                </div>
                                                <div class="mesa-staff-overview__copy">
                                                    <small>{{ $directoryWaiter ? 'Responsable de la mesa' : 'Atención pendiente' }}</small>
                                                    <strong>{{ $directoryWaiter?->name ?? 'Sin empleado asignado' }}</strong>
                                                    @php $groupSupportCount = $firstMesa->activeAssignments->where('assignment_type', 'support')->unique('user_id')->count(); @endphp
                                                    <span><i class="bx {{ $directoryWaiter ? 'bx-radio-circle-marked' : 'bx-error-circle' }}" aria-hidden="true"></i>{{ $directoryWaiter ? ($groupSupportCount ? "En servicio · {$groupSupportCount} apoyo(s)" : 'En servicio') : 'Requiere asignación' }}</span>
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="mesa-card-footer">
                                        @if ($firstMesa->currentAssignment)
                                            @if ($tab === 'todas')
                                                <div class="mesa-assignment-state"><i class="bx bx-check-shield" aria-hidden="true"></i>Responsable asignado</div>
                                            @elseif ($tab === 'mis_mesas' && $firstMesa->status === 'ocupada' && $mesaUser?->can('ordenar mesas'))
                                                <button class="btn-asignarme" data-ui="xui-18yv2pi"
                                                    wire:click="goToOrden({{ $firstMesa->id }})">
                                                    <i class="bx bx-receipt"></i> Ordenar
                                                </button>
                                            @else
                                                <div class="mesa-waiter">
                                                    @php $waiter = $firstMesa->currentAssignment->waiter; @endphp
                                                    <div class="mesa-waiter-avatar">
                                                        @if ($waiter?->avatar)
                                                            <img src="{{ Storage::url($waiter->avatar) }}"
                                                                alt="{{ $waiter->name }}">
                                                        @else
                                                            <span>{{ strtoupper(substr($waiter?->name ?? 'M', 0, 1)) }}</span>
                                                        @endif
                                                    </div>
                                                    <span class="mesa-waiter-name">{{ $waiter?->name }}</span>
                                                </div>
                                            @endif
                                        @elseif($firstMesa->status === 'disponible' && $mesaUser?->can('asignar mesas'))
                                            <button class="btn-asignarme"
                                                wire:click="openAssign({{ $firstMesa->id }})">
                                                <i class="bx bx-user-plus"></i> Asignarme
                                            </button>
                                        @elseif(
                                            $firstMesa->status === 'ocupada' &&
                                                $mesaUser?->can('asignar mesas') &&
                                                $groupMesas->flatMap->activeOrders->contains(fn($order) => $order->source === 'kiosk'))
                                            <button class="btn-asignarme btn-asignarme--kiosk"
                                                wire:click="openAssign({{ $firstMesa->id }})">
                                                <i class="bx bx-desktop"></i> Tomar mesa de kiosco
                                            </button>
                                        @else
                                            <div class="mesa-waiter mesa-waiter--none">
                                                <i class="bx bx-user-x"></i> Sin asignar
                                            </div>
                                        @endif

                                        <div class="mesa-card-actions" @click.outside="open = false">
                                            <button type="button" class="mesa-action-trigger"
                                                @click.stop="open = !open" :aria-expanded="open.toString()"
                                                aria-haspopup="true"
                                                aria-label="Más acciones para el grupo {{ $mesa->group->name ?? 'de mesas' }}"
                                                title="Más acciones">
                                                <i class="bx bx-dots-vertical-rounded"></i>
                                            </button>
                                            <div class="mesa-action-menu" x-cloak x-show="open" x-transition>
                                                <button wire:click="openDetail({{ $firstMesa->id }})"
                                                    @click="open=false">
                                                    <i class="bx bx-info-circle"></i> Detalle
                                                </button>
                                                @if (
                                                    in_array($firstMesa->status, ['ocupada', 'en_cuenta']) &&
                                                        ($mesaUser?->can('reasignar mesas') ||
                                                            $groupMesas->flatMap->activeAssignments->contains('user_id', $mesaUser?->id)))
                                                    <button class="mesa-action-help" wire:click="openServiceTeam({{ $firstMesa->id }})"
                                                        wire:loading.attr="disabled" wire:target="openServiceTeam"
                                                        @click="open=false">
                                                        <i class="bx bx-user-plus"></i>
                                                        <span><strong>Solicitar ayuda</strong>
                                                    </button>
                                                @endif
                                                @if ($firstMesa->status === 'ocupada')
                                                    @can('ordenar mesas')
                                                        <button wire:click="goToOrden({{ $firstMesa->id }})"
                                                            @click="open=false">
                                                            <i class="bx bx-receipt"></i> Ordenar
                                                        </button>
                                                    @endcan
                                                    @can('cerrar mesas')
                                                        <button type="button"
                                                            wire:click="openCloseMesa({{ $firstMesa->id }})"
                                                            wire:loading.attr="disabled" wire:target="openCloseMesa"
                                                            @click="open=false" aria-label="Cerrar mesa">
                                                            <i class="bx bx-lock-alt"></i> Cerrar mesa
                                                        </button>
                                                    @endcan
                                                @endif
                                                @if (in_array($firstMesa->status, ['ocupada', 'en_cuenta']))
                                                    <a href="{{ route('app.mesas.ordenes', $firstMesa->id) }}"
                                                        wire:navigate @click="open=false" class="mesa-action-link">
                                                        <i class="bx bx-list-ul"></i> Ver órdenes
                                                    </a>
                                                @endif
                                                @can('dividir mesas')
                                                    @if ($activeSplit)
                                                        <button wire:click="goToSplit({{ $firstMesa->id }})"
                                                            @click="open=false">
                                                            <i class="bx bx-check-circle"></i> Ver cuenta dividida
                                                        </button>
                                                    @elseif($firstMesa->status === 'en_cuenta')
                                                        <button wire:click="goToSplit({{ $firstMesa->id }})"
                                                            @click="open=false">
                                                            <i class="bx bx-git-branch"></i> Dividir cuenta
                                                        </button>
                                                    @endif
                                                @endcan
                                                @can('reasignar mesas')
                                                    @if ($firstMesa->currentAssignment && $firstMesa->status !== 'en_cuenta')
                                                        <button wire:click="openReassign({{ $firstMesa->id }})"
                                                            @click="open=false">
                                                            <i class="bx bx-transfer"></i> Reasignar
                                                        </button>
                                                    @endif
                                                @endcan
                                                @can('liberar mesas')
                                                    @if ($firstMesa->currentAssignment && $groupMesas->flatMap->activeOrders->isEmpty() && !$activeSplit)
                                                        <button wire:click="openRelease({{ $firstMesa->id }})"
                                                            @click="open=false" class="danger">
                                                            <i class="bx bx-user-minus"></i> Liberar
                                                        </button>
                                                    @endif
                                                @endcan
                                                @if (
                                                    $canManageGroups &&
                                                        $groupMesas->every(fn($member) => $member->status === 'disponible' &&
                                                                !$member->currentAssignment &&
                                                                $member->activeOrders->isEmpty()))
                                                    <div class="mesa-action-divider"></div>
                                                    <button wire:click="openUngroup({{ $firstMesa->id }})"
                                                        @click="open=false">
                                                        <i class="bx bx-unlink"></i> Desagrupar
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @elseif(!$mesa->mesa_group_id)
                                {{-- SINGLE CARD --}}
                                @php $activeSplit = $mesa->splits->first(); @endphp
                                <div class="mesa-card status-{{ $mesa->status }} {{ $tab === 'todas' ? 'mesa-card--directory' : '' }}" x-data="{ open: false }"
                                    :class="{ 'menu-open': open }">

                                    <div class="mesa-card-topbar">
                                        <div class="mesa-area-tag">
                                            <i class="bx {{ $mesa->area->icon ?? 'bx-map-pin' }}"></i>
                                            {{ $mesa->area->name ?? '–' }}
                                        </div>
                                        <div class="mesa-status-badge">
                                            <i class="bx {{ $mesa->status_icon }}"></i>
                                            {{ $mesa->status_label }}
                                        </div>
                                        @if ($mesa->activeOrders->contains(fn($order) => $order->source === 'kiosk'))
                                            <span class="mesa-kiosk-badge"><i class="bx bx-desktop"></i> Pedido
                                                kiosko</span>
                                        @endif
                                    </div>

                                    <div class="mesa-card-body">
                                        <div class="mesa-number-wrap">
                                            <span class="mesa-label-text">MESA</span>
                                            <span class="mesa-number">{{ $mesa->number }}</span>
                                            @if ($mesa->name)
                                                <span class="mesa-name-sub">{{ $mesa->name }}</span>
                                            @endif
                                        </div>
                                        <div class="mesa-meta">
                                            <span><i class="bx bx-group"></i> {{ $mesa->capacity }} personas</span>
                                            @if (in_array($mesa->status, ['ocupada', 'en_cuenta']))
                                                @php $ao = $mesa->activeOrders @endphp
                                                <span><i class="bx bx-receipt"></i> {{ $ao->count() }}
                                                    órden(es)</span>
                                            @endif
                                        </div>
                                        @if (in_array($mesa->status, ['ocupada', 'en_cuenta']) && $mesa->activeOrders->count())
                                            <div class="mesa-orders-info">
                                                <i class="bx bx-money"></i>
                                                ${{ number_format($mesa->activeOrders->sum('total'), 2) }}
                                            </div>
                                        @endif
                                        @if ($tab === 'todas')
                                            @php $directoryWaiter = $mesa->currentAssignment?->waiter; @endphp
                                            <div class="mesa-staff-overview {{ $directoryWaiter ? '' : 'is-unassigned' }}">
                                                <div class="mesa-staff-overview__avatar">
                                                    @if ($directoryWaiter?->avatar)
                                                        <img src="{{ Storage::url($directoryWaiter->avatar) }}" alt="Foto de {{ $directoryWaiter->name }}" width="52" height="52" loading="lazy" decoding="async">
                                                    @elseif($directoryWaiter)
                                                        <span>{{ strtoupper(substr($directoryWaiter->name, 0, 1)) }}</span>
                                                    @else
                                                        <i class="bx bx-user-x" aria-hidden="true"></i>
                                                    @endif
                                                </div>
                                                <div class="mesa-staff-overview__copy">
                                                    <small>{{ $directoryWaiter ? 'Responsable de la mesa' : 'Atención pendiente' }}</small>
                                                    <strong>{{ $directoryWaiter?->name ?? 'Sin empleado asignado' }}</strong>
                                                    @php $supportCount = $mesa->activeAssignments->where('assignment_type', 'support')->count(); @endphp
                                                    <span><i class="bx {{ $directoryWaiter ? 'bx-radio-circle-marked' : 'bx-error-circle' }}" aria-hidden="true"></i>{{ $directoryWaiter ? ($supportCount ? "En servicio · {$supportCount} apoyo(s)" : 'En servicio') : 'Requiere asignación' }}</span>
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="mesa-card-footer">
                                        @if ($mesa->currentAssignment)
                                            @if ($tab === 'todas')
                                                <div class="mesa-assignment-state"><i class="bx bx-check-shield" aria-hidden="true"></i>Responsable asignado</div>
                                            @elseif ($tab === 'mis_mesas' && $mesa->status === 'ocupada' && $mesaUser?->can('ordenar mesas'))
                                                <button class="btn-asignarme" data-ui="xui-18yv2pi"
                                                    wire:click="goToOrden({{ $mesa->id }})">
                                                    <i class="bx bx-receipt"></i> Ordenar
                                                </button>
                                            @else
                                                <div class="mesa-waiter">
                                                    @php $waiter = $mesa->currentAssignment->waiter; @endphp
                                                    <div class="mesa-waiter-avatar">
                                                        @if ($waiter?->avatar)
                                                            <img src="{{ Storage::url($waiter->avatar) }}"
                                                                alt="{{ $waiter->name }}">
                                                        @else
                                                            <span>{{ strtoupper(substr($waiter?->name ?? 'M', 0, 1)) }}</span>
                                                        @endif
                                                    </div>
                                                    <span class="mesa-waiter-name">{{ $waiter?->name }}</span>
                                                </div>
                                            @endif
                                        @elseif($mesa->status === 'disponible' && $mesaUser?->can('asignar mesas'))
                                            <button class="btn-asignarme"
                                                wire:click="openAssign({{ $mesa->id }})">
                                                <i class="bx bx-user-plus"></i> Asignarme
                                            </button>
                                        @elseif(
                                            $mesa->status === 'ocupada' &&
                                                $mesaUser?->can('asignar mesas') &&
                                                $mesa->activeOrders->contains(fn($order) => $order->source === 'kiosk'))
                                            <button class="btn-asignarme btn-asignarme--kiosk"
                                                wire:click="openAssign({{ $mesa->id }})">
                                                <i class="bx bx-desktop"></i> Tomar mesa de kiosco
                                            </button>
                                        @elseif($mesa->status === 'bloqueada')
                                            <div class="mesa-waiter mesa-waiter--blocked">
                                                <i class="bx bx-lock"></i> Bloqueada
                                            </div>
                                        @else
                                            <div class="mesa-waiter mesa-waiter--none">
                                                <i class="bx bx-user-x"></i> Sin asignar
                                            </div>
                                        @endif

                                        <div class="mesa-card-actions" @click.outside="open = false">
                                            <button type="button" class="mesa-action-trigger"
                                                @click.stop="open = !open" :aria-expanded="open.toString()"
                                                aria-haspopup="true"
                                                aria-label="Más acciones para la mesa {{ $mesa->number }}"
                                                title="Más acciones">
                                                <i class="bx bx-dots-vertical-rounded"></i>
                                            </button>
                                            <div class="mesa-action-menu" x-cloak x-show="open" x-transition>
                                                <button wire:click="openDetail({{ $mesa->id }})"
                                                    @click="open=false">
                                                    <i class="bx bx-info-circle"></i> Ver detalle
                                                </button>
                                                @if (
                                                    in_array($mesa->status, ['ocupada', 'en_cuenta']) &&
                                                        ($mesaUser?->can('reasignar mesas') ||
                                                            $mesa->activeAssignments->contains('user_id', $mesaUser?->id)))
                                                    <button class="mesa-action-help" wire:click="openServiceTeam({{ $mesa->id }})"
                                                        wire:loading.attr="disabled" wire:target="openServiceTeam"
                                                        @click="open=false">
                                                        <i class="bx bx-user-plus"></i>
                                                        <span><strong>Solicitar ayuda</strong><small>Agregar apoyo a la mesa</small></span>
                                                    </button>
                                                @endif
                                                @if ($mesa->status === 'ocupada')
                                                    @can('ordenar mesas')
                                                        <button wire:click="goToOrden({{ $mesa->id }})"
                                                            @click="open=false">
                                                            <i class="bx bx-receipt"></i> Ordenar
                                                        </button>
                                                    @endcan
                                                    @can('cerrar mesas')
                                                        <button type="button"
                                                            wire:click="openCloseMesa({{ $mesa->id }})"
                                                            wire:loading.attr="disabled" wire:target="openCloseMesa"
                                                            @click="open=false" aria-label="Cerrar mesa">
                                                            <i class="bx bx-lock-alt"></i> Cerrar mesa
                                                        </button>
                                                    @endcan
                                                @endif
                                                @if (in_array($mesa->status, ['ocupada', 'en_cuenta']))
                                                    <a href="{{ route('app.mesas.ordenes', $mesa->id) }}"
                                                        wire:navigate @click="open=false" class="mesa-action-link">
                                                        <i class="bx bx-list-ul"></i> Ver órdenes
                                                    </a>
                                                @endif
                                                @can('dividir mesas')
                                                    @if ($activeSplit)
                                                        <button wire:click="goToSplit({{ $mesa->id }})"
                                                            @click="open=false">
                                                            <i class="bx bx-check-circle"></i> Ver cuenta dividida
                                                        </button>
                                                    @elseif($mesa->status === 'en_cuenta')
                                                        <button wire:click="goToSplit({{ $mesa->id }})"
                                                            @click="open=false">
                                                            <i class="bx bx-git-branch"></i> Dividir cuenta
                                                        </button>
                                                    @endif
                                                @endcan
                                                @can('reasignar mesas')
                                                    @if ($mesa->currentAssignment && $mesa->status !== 'en_cuenta')
                                                        <button wire:click="openReassign({{ $mesa->id }})"
                                                            @click="open=false">
                                                            <i class="bx bx-transfer"></i> Reasignar a otro
                                                        </button>
                                                    @endif
                                                @endcan
                                                @can('liberar mesas')
                                                    @if ($mesa->currentAssignment && $mesa->activeOrders->isEmpty() && !$activeSplit)
                                                        <button wire:click="openRelease({{ $mesa->id }})"
                                                            @click="open=false" class="danger">
                                                            <i class="bx bx-user-minus"></i> Liberar mesa
                                                        </button>
                                                    @endif
                                                @endcan
                                                @if ($canChangeMesaStatus || $canEditMesas || $canDeleteMesas)
                                                    <div class="mesa-action-divider"></div>
                                                    @if ($canChangeMesaStatus && !$mesa->currentAssignment && $mesa->activeOrders->isEmpty() && !$activeSplit)
                                                        @foreach (['disponible', 'ocupada', 'reservada', 'en_cuenta', 'bloqueada'] as $st)
                                                            @if ($st !== $mesa->status)
                                                                <button
                                                                    wire:click="openStatusChange({{ $mesa->id }}, '{{ $st }}')"
                                                                    @click="open=false">
                                                                    <i class="bx bx-refresh"></i>
                                                                    Marcar
                                                                    {{ match ($st) {'disponible' => 'disponible','ocupada' => 'ocupada','reservada' => 'reservada','en_cuenta' => 'en cuenta','bloqueada' => 'bloqueada'} }}
                                                                </button>
                                                            @endif
                                                        @endforeach
                                                    @endif
                                                    @if ($canEditMesas || $canDeleteMesas)
                                                        <div class="mesa-action-divider"></div>
                                                    @endif
                                                    @if ($canEditMesas)
                                                        <button wire:click="openMesaModal({{ $mesa->id }})"
                                                            @click="open=false">
                                                            <i class="bx bx-edit"></i> Editar
                                                        </button>
                                                    @endif
                                                    @if ($canDeleteMesas)
                                                        <button wire:click="openDeleteMesa({{ $mesa->id }})"
                                                            @click="open=false" class="danger">
                                                            <i class="bx bx-trash"></i> Eliminar
                                                        </button>
                                                    @endif
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>{{-- /mesas-grid --}}
                    @if ($groupByArea)
        </div>{{-- /mesas-area-block --}}
        @endif
        @endforeach{{-- /mesasByArea --}}
        @endif
    </div>

    {{-- ══ MOBILE BOTTOM NAV ══ --}}
    <div class="mesas-bottom-nav">
        <div class="mesas-bottom-nav-inner">
            <button class="mbn-item {{ $tab === 'disponibles' ? 'active' : '' }}" wire:click="setTab('disponibles')"
                wire:loading.attr="disabled" wire:target="setTab">
                <i class="bx bx-check-circle"></i>
                Libres
            </button>
            <button class="mbn-item {{ $tab === 'mis_mesas' ? 'active' : '' }}" wire:click="setTab('mis_mesas')"
                wire:loading.attr="disabled" wire:target="setTab">
                @if ($this->myActiveMesaCount > 0)
                    <span class="mbn-badge">{{ $this->myActiveMesaCount }}</span>
                @endif
                <i class="bx bx-user-check"></i>
                Mis Mesas
            </button>
            <button class="mbn-item {{ $tab === 'kiosko' ? 'active' : '' }}" wire:click="setTab('kiosko')"
                wire:loading.attr="disabled" wire:target="setTab">
                @if ($this->kioskCount > 0)
                    <span class="mbn-badge">{{ $this->kioskCount }}</span>
                @endif
                <i class="bx bx-desktop"></i>
                Kiosko
            </button>
            @if ($canViewAllMesas)
                <button class="mbn-item {{ $tab === 'todas' ? 'active' : '' }}" wire:click="setTab('todas')"
                    wire:loading.attr="disabled" wire:target="setTab">
                    <i class="bx bx-grid-alt"></i>
                    Todas
                </button>
            @endif
        </div>
    </div>

    {{-- ══════════════════════════════════════════════
         MODALS
    ══════════════════════════════════════════════ --}}

    {{-- ── Assign confirm modal ── --}}
    @if ($showAssignModal && $this->assignMesa)
        <div class="mesas-modal-backdrop" wire:click.self="$set('showAssignModal', false)">
            <div class="mesas-modal mesas-modal--sm">
                <div class="mesas-modal-icon" data-ui="xui-71m8uj">
                    <i class="bx bx-user-plus"></i>
                </div>
                <h5>¿Asignarte la Mesa {{ $this->assignMesa->number }}?</h5>
                <p class="text-muted">
                    Área: <strong>{{ $this->assignMesa->area->name ?? '–' }}</strong> ·
                    Capacidad: <strong>{{ $this->assignMesa->capacity }} personas</strong>
                </p>
                <p class="text-muted small">
                    A partir de este momento serás el responsable de esta mesa y aparecerá en <em>Mis Mesas</em>.
                </p>
                <div class="mesas-modal-actions">
                    <button class="btn btn-outline-secondary"
                        wire:click="$set('showAssignModal', false)">Cancelar</button>
                    <button class="btn btn-primary" wire:click="confirmAssign" wire:loading.attr="disabled">
                        <span wire:loading wire:target="confirmAssign"
                            class="spinner-border spinner-border-sm me-1"></span>
                        Confirmar
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ── Reassign modal ── --}}
    @if ($showReassignModal && $this->reassignMesa)
        <div class="mesas-modal-backdrop" wire:click.self="$set('showReassignModal', false)">
            <div class="mesas-modal">
                <div class="mesas-modal-header">
                    <h5><i class="bx bx-transfer me-2"></i>Reasignar Mesa {{ $this->reassignMesa->number }}</h5>
                    <button class="mesas-modal-close" wire:click="$set('showReassignModal', false)">
                        <i class="bx bx-x"></i>
                    </button>
                </div>

                @if ($this->reassignMesa->currentAssignment)
                    <div class="mesas-current-waiter-info">
                        <span class="text-muted small">Actualmente atendida por:</span>
                        <div class="mesa-waiter mt-1">
                            @php $cw = $this->reassignMesa->currentAssignment->waiter; @endphp
                            <div class="mesa-waiter-avatar">
                                @if ($cw?->avatar)
                                    <img src="{{ Storage::url($cw->avatar) }}" alt="{{ $cw->name }}">
                                @else
                                    <span>{{ strtoupper(substr($cw?->name ?? 'M', 0, 1)) }}</span>
                                @endif
                            </div>
                            <strong>{{ $cw?->name }}</strong>
                        </div>
                    </div>
                @endif

                <div class="mesas-modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Asignar a <span class="text-danger">*</span></label>
                        <select class="form-select" wire:model="reassignUserId">
                            <option value="">— Selecciona un mesero —</option>
                            @foreach ($this->waiters as $w)
                                @if ($w->id !== $this->reassignMesa->currentAssignment?->user_id)
                                    <option value="{{ $w->id }}">{{ $w->name }}</option>
                                @endif
                            @endforeach
                        </select>
                        @error('reassignUserId')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Razón de reasignación</label>
                        <input type="text" class="form-control" wire:model="reassignReason"
                            placeholder="Ej: Cambio de turno, incidente…">
                    </div>
                    <div class="alert alert-info alert-sm small p-2 mb-0">
                        <i class="bx bx-info-circle me-1"></i>
                        Se registrará en el historial de asignaciones de esta mesa.
                    </div>
                </div>
                <div class="mesas-modal-actions">
                    <button class="btn btn-outline-secondary"
                        wire:click="$set('showReassignModal', false)">Cancelar</button>
                    <button class="btn btn-warning" wire:click="confirmReassign" wire:loading.attr="disabled">
                        <span wire:loading wire:target="confirmReassign"
                            class="spinner-border spinner-border-sm me-1"></span>
                        Reasignar
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ── Collaborative service team modal ── --}}
    @if ($showServiceTeamModal && $this->serviceTeamMesa)
        @php
            $teamMesa = $this->serviceTeamMesa;
            $activeTeam = $this->serviceTeamAssignments;
            $activeTeamUserIds = $activeTeam->pluck('user_id');
            $availableSupportWaiters = $this->waiters->reject(fn ($waiter) => $waiter->id === $mesaUser?->id || $activeTeamUserIds->contains($waiter->id));
        @endphp
        <div class="mesas-modal-backdrop mesas-modal-backdrop--team" wire:click.self="closeServiceTeam" x-data x-init="$nextTick(() => $refs.teamClose.focus())" @keydown.escape.window="$wire.closeServiceTeam()">
            <section class="mesas-modal mesas-modal--service-team" role="dialog" aria-modal="true" aria-labelledby="service-team-title" aria-describedby="service-team-description">
                <div class="mesas-modal-header">
                    <div>
                        <span class="mesas-modal-eyebrow">Atención colaborativa</span>
                        <h5 id="service-team-title">Equipo de {{ $teamMesa->display_name }}</h5>
                    </div>
                    <button type="button" class="mesas-modal-close" wire:click="closeServiceTeam" x-ref="teamClose" aria-label="Cerrar gestión del equipo"><i class="bx bx-x"></i></button>
                </div>
                <div class="mesas-modal-body">
                    <p id="service-team-description" class="text-muted">Agrega apoyo inmediatamente o envía una solicitud que el mesero deberá aceptar.</p>

                    <div class="mesa-team-current" aria-label="Integrantes actuales">
                        @foreach ($activeTeam as $memberAssignment)
                            <div class="mesa-team-current__member" title="{{ $memberAssignment->assignment_type_label }}: {{ $memberAssignment->waiter?->name }}">
                                @if ($memberAssignment->waiter?->avatar)
                                    <img src="{{ Storage::url($memberAssignment->waiter->avatar) }}" alt="Foto de {{ $memberAssignment->waiter->name }}" width="42" height="42">
                                @else
                                    <span>{{ strtoupper(substr($memberAssignment->waiter?->name ?? 'M', 0, 1)) }}</span>
                                @endif
                                <small>{{ $memberAssignment->waiter?->name }}</small>
                            </div>
                        @endforeach
                    </div>

                    @if ($teamMesa->mesa_group_id)
                        <label class="mesa-team-scope">
                            <input type="checkbox" wire:model="serviceTeamApplyToGroup">
                            <span><i class="bx bx-merge"></i></span>
                            <span><strong>Aplicar a todo el grupo</strong><small>El mesero podrá atender cualquiera de las mesas agrupadas.</small></span>
                        </label>
                    @endif

                    <fieldset class="mesa-team-picker">
                        <legend>Selecciona meseros disponibles</legend>
                        @forelse ($availableSupportWaiters as $waiter)
                            <label class="mesa-team-option">
                                <input type="checkbox" wire:model="serviceTeamWaiterIds" value="{{ $waiter->id }}">
                                <span class="mesa-team-option__avatar">
                                    @if ($waiter->avatar)
                                        <img src="{{ Storage::url($waiter->avatar) }}" alt="" width="44" height="44" loading="lazy">
                                    @else
                                        <span>{{ strtoupper(substr($waiter->name, 0, 1)) }}</span>
                                    @endif
                                </span>
                                <span><strong>{{ $waiter->name }}</strong><small>Disponible para seleccionar</small></span>
                                <i class="bx bx-check-circle" aria-hidden="true"></i>
                            </label>
                        @empty
                            <div class="mesa-team-picker__empty"><i class="bx bx-check-shield"></i><span>Todo el personal disponible ya participa en este servicio.</span></div>
                        @endforelse
                    </fieldset>
                    @error('serviceTeamWaiterIds')<div class="text-danger small mt-2" role="alert">{{ $message }}</div>@enderror

                    <label class="mesa-team-message">
                        <span>Mensaje para la solicitud <small>(opcional)</small></span>
                        <input type="text" class="form-control" wire:model="serviceTeamMessage" maxlength="255" placeholder="Ej. Necesito apoyo para tomar bebidas">
                    </label>
                </div>
                <div class="mesas-modal-actions mesas-modal-actions--team">
                    <button type="button" class="btn btn-outline-secondary" wire:click="closeServiceTeam">Cancelar</button>
                    <button type="button" class="btn btn-outline-primary" wire:click="requestTableSupport" wire:loading.attr="disabled" wire:target="requestTableSupport" @disabled($availableSupportWaiters->isEmpty())><i class="bx bx-bell me-1"></i>Solicitar ayuda</button>
                    @if ($this->canDirectlyManageServiceTeam)
                        <button type="button" class="btn btn-primary" wire:click="addSupportWaiters" wire:loading.attr="disabled" wire:target="addSupportWaiters" @disabled($availableSupportWaiters->isEmpty())><i class="bx bx-user-plus me-1"></i>Agregar al equipo</button>
                    @endif
                </div>
            </section>
        </div>
    @endif

    {{-- ── Close account choice modal ── --}}
    @if ($showCloseModal && $this->closingMesa)
        <div class="mesas-modal-backdrop" wire:click.self="closeCloseModal" x-data x-init="$nextTick(() => $refs.closeCancel.focus())"
            @keydown.escape.window="$wire.closeCloseModal()">
            <section class="mesas-modal mesas-modal--close-choice" role="dialog" aria-modal="true"
                aria-labelledby="close-mesa-title" aria-describedby="close-mesa-description">
                <div class="mesas-modal-header">
                    <div>
                        <span class="mesas-modal-eyebrow">Enviar cuenta a caja</span>
                        <h5 id="close-mesa-title">Cerrar {{ $this->closingMesa->display_name }}</h5>
                    </div>
                    <button type="button" class="mesas-modal-close" wire:click="closeCloseModal"
                        aria-label="Cancelar cierre de mesa">
                        <i class="bx bx-x"></i>
                    </button>
                </div>
                <div class="mesas-modal-body">
                    <p id="close-mesa-description" class="text-muted mb-3">
                        Elige cómo se cobrará esta cuenta. Después del cierre no se podrán agregar pedidos hasta reabrir
                        la mesa.
                    </p>
                    <div class="mesas-close-options">
                        <button type="button" class="mesas-close-option" wire:click="confirmCloseMesa('full')"
                            wire:loading.attr="disabled" wire:target="confirmCloseMesa">
                            <span class="mesas-close-option__icon" aria-hidden="true"><i
                                    class="bx bx-receipt"></i></span>
                            <span><strong>Cuenta completa</strong><small>Cobrar todo junto en el POS.</small></span>
                            <i class="bx bx-chevron-right" aria-hidden="true"></i>
                        </button>
                        @can('dividir mesas')
                            <button type="button" class="mesas-close-option mesas-close-option--split"
                                wire:click="confirmCloseMesa('split')" wire:loading.attr="disabled"
                                wire:target="confirmCloseMesa">
                                <span class="mesas-close-option__icon" aria-hidden="true"><i
                                        class="bx bx-git-branch"></i></span>
                                <span><strong>Dividir cuenta</strong><small>Asignar productos o partes a
                                        subcuentas.</small></span>
                                <i class="bx bx-chevron-right" aria-hidden="true"></i>
                            </button>
                        @endcan
                    </div>
                    <div class="mesas-close-warning" role="note">
                        <i class="bx bx-lock-alt" aria-hidden="true"></i>
                        <span>Si una subcuenta ya fue pagada, la división quedará bloqueada y no podrá reabrirse.</span>
                    </div>
                </div>
                <div class="mesas-modal-actions">
                    <button type="button" class="btn btn-outline-secondary" wire:click="closeCloseModal"
                        x-ref="closeCancel">Cancelar</button>
                </div>
            </section>
        </div>
    @endif

    {{-- ── Release modal ── --}}
    @if ($showReleaseModal)
        <div class="mesas-modal-backdrop" wire:click.self="$set('showReleaseModal', false)">
            <div class="mesas-modal mesas-modal--sm">
                <div class="mesas-modal-icon" data-ui="xui-rhcfj3">
                    <i class="bx bx-user-minus"></i>
                </div>
                <h5>Liberar mesa</h5>
                <p class="text-muted small">¿Confirmas liberar esta mesa? Quedará disponible para otro mesero.</p>
                <div class="mb-3">
                    <label class="form-label">Razón (opcional)</label>
                    <input type="text" class="form-control" wire:model="releaseReason"
                        placeholder="Ej: Mesa vacía, cliente se fue…">
                </div>
                <div class="mesas-modal-actions">
                    <button class="btn btn-outline-secondary"
                        wire:click="$set('showReleaseModal', false)">Cancelar</button>
                    <button class="btn btn-danger" wire:click="confirmRelease" wire:loading.attr="disabled">
                        <span wire:loading wire:target="confirmRelease"
                            class="spinner-border spinner-border-sm me-1"></span>
                        Liberar
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ── Status change modal ── --}}
    @if ($showStatusModal)
        <div class="mesas-modal-backdrop" wire:click.self="$set('showStatusModal', false)">
            <div class="mesas-modal mesas-modal--sm">
                <div class="mesas-modal-icon" data-ui="xui-1eoxxj">
                    <i class="bx bx-refresh"></i>
                </div>
                <h5>Cambiar estado</h5>
                <p class="text-muted small">
                    ¿Cambiar estado a
                    <strong>{{ match ($newStatus) {'disponible' => 'Disponible','ocupada' => 'Ocupada','reservada' => 'Reservada','en_cuenta' => 'En cuenta','bloqueada' => 'Bloqueada',default => $newStatus} }}</strong>?
                </p>
                <div class="mesas-modal-actions">
                    <button class="btn btn-outline-secondary"
                        wire:click="$set('showStatusModal', false)">Cancelar</button>
                    <button class="btn btn-primary" wire:click="confirmStatusChange"
                        wire:loading.attr="disabled">Confirmar</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ── Group modal ── --}}
    @if ($showGroupModal)
        <div class="mesas-modal-backdrop" wire:click.self="$set('showGroupModal', false)">
            <div class="mesas-modal mesas-modal--lg">
                <div class="mesas-modal-header">
                    <h5><i class="bx bx-merge me-2"></i>Agrupar mesas</h5>
                    <button class="mesas-modal-close" wire:click="$set('showGroupModal', false)">
                        <i class="bx bx-x"></i>
                    </button>
                </div>
                <div class="mesas-modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre del grupo <span
                                class="text-danger">*</span></label>
                        <input type="text" class="form-control" wire:model="groupName"
                            placeholder="Ej: Grupo Eventos, Mesa corrida…">
                        @error('groupName')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                    <label class="form-label fw-semibold">Selecciona las mesas a agrupar <span
                            class="text-danger">*</span></label>
                    @error('groupSelection')
                        <small class="text-danger d-block mb-2">{{ $message }}</small>
                    @enderror
                    @php
                        $groupableMesas = \App\Models\Mesa::with('area')
                            ->where('status', 'disponible')
                            ->whereDoesntHave('currentAssignment')
                            ->whereNull('mesa_group_id')
                            ->orderBy('number')
                            ->get()
                            ->groupBy(fn($m) => $m->area->name ?? 'Sin área');
                    @endphp
                    @foreach ($groupableMesas as $areaName => $areaMesasGroup)
                        <div class="mb-3">
                            <div class="mo-cat-label mb-2">
                                <i class="bx bx-map-pin"></i> {{ $areaName }}
                            </div>
                            <div class="mesas-grid mesas-grid--compact">
                                @foreach ($areaMesasGroup as $m)
                                    <div class="mesa-card-mini {{ in_array($m->id, $groupSelection) ? 'selected' : '' }}"
                                        wire:click="toggleGroupSelection({{ $m->id }})">
                                        <span class="mesa-mini-num">{{ $m->number }}</span>
                                        @if (in_array($m->id, $groupSelection))
                                            <i class="bx bx-check mesa-mini-check"></i>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                    <p class="text-muted small mt-2">
                        <i class="bx bx-info-circle me-1"></i>
                        Solo mesas disponibles pueden agruparse. Seleccionadas: {{ count($groupSelection) }}
                    </p>
                </div>
                <div class="mesas-modal-actions">
                    <button class="btn btn-outline-secondary"
                        wire:click="$set('showGroupModal', false)">Cancelar</button>
                    <button class="btn btn-primary" wire:click="confirmGroup" wire:loading.attr="disabled">
                        <span wire:loading wire:target="confirmGroup"
                            class="spinner-border spinner-border-sm me-1"></span>
                        <i class="bx bx-merge me-1"></i> Crear grupo
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ── Ungroup confirm modal ── --}}
    @if ($showUngroupModal)
        <div class="mesas-modal-backdrop" wire:click.self="$set('showUngroupModal', false)">
            <div class="mesas-modal mesas-modal--sm">
                <div class="mesas-modal-icon" data-ui="xui-1eoxxj">
                    <i class="bx bx-unlink"></i>
                </div>
                <h5>Desagrupar mesa</h5>
                <p class="text-muted small">¿Confirmas remover esta mesa del grupo? Si el grupo queda con menos de 2
                    mesas, se eliminará automáticamente.</p>
                <div class="mesas-modal-actions">
                    <button class="btn btn-outline-secondary"
                        wire:click="$set('showUngroupModal', false)">Cancelar</button>
                    <button class="btn btn-warning" wire:click="confirmUngroup"
                        wire:loading.attr="disabled">Desagrupar</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ── Area CRUD modal ── --}}
    @if ($showAreaModal)
        <div class="mesas-modal-backdrop" wire:click.self="$set('showAreaModal', false)">
            <div class="mesas-modal">
                <div class="mesas-modal-header">
                    <h5><i class="bx bx-map-pin me-2"></i>{{ $editAreaId ? 'Editar área' : 'Nueva área' }}</h5>
                    <button class="mesas-modal-close" wire:click="$set('showAreaModal', false)">
                        <i class="bx bx-x"></i>
                    </button>
                </div>
                <div class="mesas-modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" wire:model="areaName"
                            placeholder="Ej: Salón principal, Terraza…">
                        @error('areaName')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Color</label>
                            <div class="d-flex gap-2 align-items-center">
                                <input type="color" class="form-control form-control-color" wire:model="areaColor"
                                    data-ui="xui-pah3lq">
                                <span class="form-control text-muted small"
                                    data-ui="xui-ckcaff">{{ $areaColor }}</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Ícono (Boxicon)</label>
                            <input type="text" class="form-control" wire:model="areaIcon"
                                placeholder="bx-map-pin">
                            <small class="text-muted">Ej: bx-map-pin, bx-home, bx-store</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Orden de visualización</label>
                        <input type="number" class="form-control" wire:model="areaSort" min="0">
                    </div>
                    {{-- Existing areas list --}}
                    @if ($this->areas->count())
                        <div class="mt-3">
                            <label class="form-label fw-semibold text-muted small">Áreas existentes</label>
                            <div class="d-flex flex-column gap-1">
                                @foreach ($this->areas as $a)
                                    <div class="d-flex align-items-center justify-content-between p-2 rounded"
                                        data-ui="xui-6n8fwb">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bx {{ $a->icon }} mesas-area-list-icon"></i>
                                            <span class="fw-semibold">{{ $a->name }}</span>
                                            <span class="badge bg-label-primary">
                                                {{ $a->mesas_count }} mesa(s)
                                            </span>
                                        </div>
                                        <div class="d-flex gap-1">
                                            @if ($canEditAreas)
                                                <button class="btn btn-icon btn-sm btn-outline-secondary"
                                                    wire:click="openAreaModal({{ $a->id }})">
                                                    <i class="bx bx-edit"></i>
                                                </button>
                                            @endif
                                            @if ($canDeleteAreas && $a->mesas_count == 0)
                                                <button class="btn btn-icon btn-sm btn-outline-danger"
                                                    wire:click="deleteArea({{ $a->id }})">
                                                    <i class="bx bx-trash"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
                <div class="mesas-modal-actions">
                    <button class="btn btn-outline-secondary"
                        wire:click="$set('showAreaModal', false)">Cancelar</button>
                    <button class="btn btn-primary" wire:click="saveArea" wire:loading.attr="disabled">
                        <span wire:loading wire:target="saveArea"
                            class="spinner-border spinner-border-sm me-1"></span>
                        Guardar área
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ── Mesa CRUD modal ── --}}
    @if ($showMesaModal)
        <div class="mesas-modal-backdrop" wire:click.self="$set('showMesaModal', false)">
            <div class="mesas-modal">
                <div class="mesas-modal-header">
                    <h5><i class="bx bx-table me-2"></i>{{ $editMesaId ? 'Editar mesa' : 'Nueva mesa' }}</h5>
                    <button class="mesas-modal-close" wire:click="$set('showMesaModal', false)">
                        <i class="bx bx-x"></i>
                    </button>
                </div>
                <div class="mesas-modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Número <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" wire:model="mesaNumber" min="1"
                                max="999" placeholder="1">
                            @error('mesaNumber')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nombre (opcional)</label>
                            <input type="text" class="form-control" wire:model="mesaName"
                                placeholder="Ej: Barra, Ventana…">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Capacidad <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" wire:model="mesaCapacity" min="1"
                                max="50">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Área <span class="text-danger">*</span></label>
                            <select class="form-select" wire:model="mesaAreaId">
                                <option value="">— Selecciona —</option>
                                @foreach ($this->areas as $a)
                                    <option value="{{ $a->id }}">{{ $a->name }}</option>
                                @endforeach
                            </select>
                            @error('mesaAreaId')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="mesas-modal-actions">
                    <button class="btn btn-outline-secondary"
                        wire:click="$set('showMesaModal', false)">Cancelar</button>
                    <button class="btn btn-primary" wire:click="saveMesa" wire:loading.attr="disabled">
                        <span wire:loading wire:target="saveMesa"
                            class="spinner-border spinner-border-sm me-1"></span>
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ── Delete mesa confirm ── --}}
    @if ($showDeleteMesaModal)
        <div class="mesas-modal-backdrop" wire:click.self="$set('showDeleteMesaModal', false)">
            <div class="mesas-modal mesas-modal--sm">
                <div class="mesas-modal-icon" data-ui="xui-rhcfj3">
                    <i class="bx bx-trash"></i>
                </div>
                <h5>Eliminar mesa</h5>
                <p class="text-muted small">Esta acción es permanente. Las órdenes históricas se conservarán.</p>
                <div class="mesas-modal-actions">
                    <button class="btn btn-outline-secondary"
                        wire:click="$set('showDeleteMesaModal', false)">Cancelar</button>
                    <button class="btn btn-danger" wire:click="confirmDeleteMesa"
                        wire:loading.attr="disabled">Eliminar</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ── Mesa Detail modal ── --}}
    @if ($showDetailModal && $this->detailMesa)
        @php
            $dm = $this->detailMesa;
            $printableMesaAccount = auth()->user()?->can('reimprimir tickets')
                ? $this->printableMesaAccount
                : null;
        @endphp
        <div class="mesas-modal-backdrop" wire:click.self="$set('showDetailModal', false)">
            <div class="mesas-modal mesas-modal--xl">
                <div class="mesas-modal-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="mesa-detail-number mesa-detail-status--{{ $dm->status }}">
                            {{ $dm->number }}
                        </div>
                        <div>
                            <h5 class="mb-0">{{ $dm->display_name }}</h5>
                            <small class="text-muted">
                                {{ $dm->area->name ?? '–' }} · Capacidad {{ $dm->capacity }}p ·
                                <span class="badge mesa-detail-status--{{ $dm->status }}">
                                    <i class="bx {{ $dm->status_icon }}"></i> {{ $dm->status_label }}
                                </span>
                            </small>
                        </div>
                    </div>
                    <button class="mesas-modal-close" wire:click="$set('showDetailModal', false)">
                        <i class="bx bx-x"></i>
                    </button>
                </div>

                <div class="mesas-modal-body mesas-modal-tabs" x-data="{ tab: 'ordenes' }">
                    {{-- Inner tabs --}}
                    <div class="mesas-inner-tabs">
                        <button class="mesas-inner-tab" :class="{ active: tab === 'ordenes' }"
                            @click="tab = 'ordenes'">
                            <i class="bx bx-receipt"></i> Órdenes activas
                        </button>
                        <button class="mesas-inner-tab" :class="{ active: tab === 'historial' }"
                            @click="tab = 'historial'">
                            <i class="bx bx-history"></i> Historial
                        </button>
                        <button class="mesas-inner-tab" :class="{ active: tab === 'asignaciones' }"
                            @click="tab = 'asignaciones'">
                            <i class="bx bx-user-pin"></i> Asignaciones
                        </button>
                        @if ($printableMesaAccount)
                            <button class="mesas-inner-tab" :class="{ active: tab === 'imprimir' }"
                                @click="tab = 'imprimir'">
                                <i class="bx bx-printer"></i> Imprimir cuenta
                            </button>
                        @endif
                        @if ($dm->splits->isNotEmpty())
                            <button class="mesas-inner-tab" :class="{ active: tab === 'split' }"
                                @click="tab = 'split'" aria-label="Ver cuentas divididas">
                                <i class="bx bx-git-branch"></i> Cuenta dividida
                            </button>
                        @endif
                    </div>

                    {{-- Órdenes activas --}}
                    <div x-show="tab === 'ordenes'">
                        @if ($dm->activeOrders->isEmpty())
                            <div class="mesas-empty mesas-empty--sm">
                                <i class="bx bx-receipt text-muted"></i>
                                <p>No hay órdenes activas en esta mesa.</p>
                            </div>
                        @else
                            <div class="detail-orders-list">
                                @foreach ($dm->activeOrders as $order)
                                    <div class="detail-order-row">
                                        <div class="detail-order-header">
                                            <span class="detail-order-title">
                                                <strong>{{ $order->display_folio }}</strong>
                                                <small>{{ $order->display_name }}</small>
                                            </span>
                                            <span
                                                class="badge bg-label-{{ $order->status_color }}">{{ $order->status_label }}</span>
                                            <span class="detail-order-received" title="{{ $order->created_at->format('d/m/Y H:i:s') }}">
                                                <i class="bx bx-time-five" aria-hidden="true"></i>
                                                Recibida {{ $order->created_at->format('H:i') }} h
                                            </span>
                                        </div>
                                        <div class="detail-order-items">
                                            @foreach ($order->items as $item)
                                                <div class="detail-item">
                                                    <span class="detail-item-qty">{{ $item->quantity }}×</span>
                                                    <span class="detail-item-name">{{ $item->product_name }}</span>
                                                    <span
                                                        class="detail-item-price">${{ number_format($item->subtotal, 2) }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                        <div class="detail-order-footer">
                                            <div class="detail-order-audit">
                                                <div class="detail-order-audit__avatar">
                                                    @if ($order->seller?->avatar)
                                                        <img src="{{ Storage::url($order->seller->avatar) }}"
                                                            alt="Foto de {{ $order->seller->name }}" width="38" height="38" loading="lazy">
                                                    @elseif ($order->seller)
                                                        <span>{{ strtoupper(substr($order->seller->name, 0, 1)) }}</span>
                                                    @else
                                                        <i class="bx bx-desktop" aria-hidden="true"></i>
                                                    @endif
                                                </div>
                                                <span><small>Levantó la orden</small><strong>{{ $order->seller?->name ?? 'Sistema' }}</strong></span>
                                            </div>
                                            <span class="detail-order-total"><small>Total</small><strong>${{ number_format($order->total, 2) }}</strong></span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="detail-total-bar">
                                <span>Total mesa:</span>
                                <strong>${{ number_format($dm->activeOrders->sum('total'), 2) }}</strong>
                            </div>
                        @endif
                    </div>

                    {{-- Historial de órdenes --}}
                    <div x-show="tab === 'historial'">
                        @if ($dm->orders->isEmpty())
                            <div class="mesas-empty mesas-empty--sm">
                                <p>Sin historial de órdenes.</p>
                            </div>
                        @else
                            <div class="detail-history-list">
                                @foreach ($dm->orders as $order)
                                    <div class="detail-history-row">
                                        <div class="detail-history-icon bg-label-{{ $order->status_color }}">
                                            <i class="bx bx-receipt"></i>
                                        </div>
                                        <div class="flex-grow-1 min-width-0">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <span class="fw-semibold">#{{ $order->id }} ·
                                                    {{ $order->display_name }}</span>
                                                <span
                                                    class="badge bg-label-{{ $order->status_color }}">{{ $order->status_label }}</span>
                                            </div>
                                            <div class="d-flex gap-3 mt-1">
                                                <small class="text-muted"><i
                                                        class="bx bx-calendar me-1"></i>{{ $order->created_at->format('d/m/Y H:i') }}</small>
                                                @if ($order->paid_at)
                                                    <small class="text-success"><i class="bx bx-check me-1"></i>Pagada
                                                        {{ $order->paid_at->diffForHumans() }}</small>
                                                @endif
                                                <small
                                                    class="text-muted ms-auto fw-semibold">${{ number_format($order->total, 2) }}</small>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Historial de asignaciones --}}
                    <div x-show="tab === 'asignaciones'">
                        <div class="mesa-assignment-scope">
                            <i class="bx {{ $mesaUser?->can('ver historial completo de asignaciones mesas') ? 'bx-archive' : 'bx-lock-alt' }}" aria-hidden="true"></i>
                            <span>
                                <strong>{{ $mesaUser?->can('ver historial completo de asignaciones mesas') ? 'Historial completo' : 'Actividad de la caja abierta' }}</strong>
                                <small>{{ $mesaUser?->can('ver historial completo de asignaciones mesas') ? 'Incluye asignaciones y liberaciones de turnos anteriores.' : 'Las asignaciones de cajas anteriores requieren el permiso de historial completo.' }}</small>
                            </span>
                        </div>

                        @if ($dm->assignments->isEmpty())
                            <div class="mesas-empty mesas-empty--sm">
                                <p>Sin asignaciones visibles en este alcance.</p>
                            </div>
                        @else
                            <div class="detail-history-list">
                                @foreach ($dm->assignments->sortByDesc('assigned_at') as $asgn)
                                    <div class="detail-history-row">
                                        <div class="mesa-waiter-avatar">
                                            @if ($asgn->waiter?->avatar)
                                                <img src="{{ Storage::url($asgn->waiter->avatar) }}"
                                                    alt="{{ $asgn->waiter->name }}">
                                            @else
                                                <span>{{ strtoupper(substr($asgn->waiter?->name ?? 'M', 0, 1)) }}</span>
                                            @endif
                                        </div>
                                        <div class="flex-grow-1 min-width-0">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <span
                                                    class="fw-semibold">{{ $asgn->waiter?->name ?? 'Desconocido' }}</span>
                                                @if ($asgn->is_active)
                                                    <span class="badge bg-label-success">{{ $asgn->assignment_type_label }} activo</span>
                                                @else
                                                    <span class="badge bg-label-secondary">Finalizado</span>
                                                @endif
                                            </div>
                                            <div class="d-flex gap-3 mt-1 flex-wrap">
                                                <small class="text-muted">
                                                    <i class="bx bx-log-in me-1"></i>
                                                    {{ $asgn->assigned_at->format('d/m/Y H:i') }}
                                                </small>
                                                @if ($asgn->released_at)
                                                    <small class="text-muted">
                                                        <i class="bx bx-log-out me-1"></i>
                                                        {{ $asgn->released_at->format('d/m/Y H:i') }}
                                                    </small>
                                                    <small class="text-muted">
                                                        <i class="bx bx-time me-1"></i>{{ $asgn->duration }}
                                                    </small>
                                                @endif
                                            </div>
                                            @if ($asgn->release_reason)
                                                <small class="text-muted d-block mt-1">
                                                    <i class="bx bx-comment me-1"></i>{{ $asgn->release_reason }}
                                                </small>
                                            @endif
                                            <small class="text-muted">
                                                Asignado por: {{ $asgn->assignedBy?->name ?? '–' }}
                                                @if ($asgn->releasedBy)
                                                    · Liberado por: {{ $asgn->releasedBy->name }}
                                                @endif
                                            </small>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @if ($printableMesaAccount)
                        <div x-show="tab === 'imprimir'" x-cloak>
                            <section class="mesa-print-section" aria-labelledby="mesa-print-title">
                                <div class="mesa-print-section__heading">
                                    <span class="mesa-print-section__icon"><i class="bx bx-printer"></i></span>
                                    <div>
                                        <h6 id="mesa-print-title">Ticket de la cuenta vigente</h6>
                                        <p>La vista previa usa el consumo actual y no registra pagos ni cambia el estado de la mesa.</p>
                                    </div>
                                </div>

                                @if ($printableMesaAccount['is_split'])
                                    <div class="mesa-print-accounts" aria-label="Subcuentas disponibles para imprimir">
                                        @foreach ($printableMesaAccount['accounts'] as $account)
                                            <article class="mesa-print-account {{ $account['paid'] ? 'is-paid' : '' }}">
                                                <div class="mesa-print-account__status">
                                                    <i class="bx {{ $account['paid'] ? 'bx-check-circle' : 'bx-wallet' }}"></i>
                                                </div>
                                                <div class="mesa-print-account__summary">
                                                    <strong>{{ $account['label'] }}</strong>
                                                    <small>{{ $account['item_count'] }} producto(s) · {{ $account['paid'] ? 'Pagada · reimpresión disponible' : 'Pendiente de cobro' }}</small>
                                                </div>
                                                <strong class="mesa-print-account__total">${{ number_format($account['total'], 2) }}</strong>
                                                <button type="button" class="btn {{ $account['paid'] ? 'btn-outline-primary' : 'btn-primary' }} btn-sm"
                                                    wire:click="printActiveMesaAccount({{ $dm->id }}, {{ $printableMesaAccount['split_id'] }}, {{ $account['index'] }})"
                                                    wire:loading.attr="disabled" wire:target="printActiveMesaAccount({{ $dm->id }}, {{ $printableMesaAccount['split_id'] }}, {{ $account['index'] }})"
                                                    aria-label="Ver e imprimir {{ $account['label'] }}">
                                                    <span wire:loading.remove wire:target="printActiveMesaAccount({{ $dm->id }}, {{ $printableMesaAccount['split_id'] }}, {{ $account['index'] }})"><i class="bx {{ $account['paid'] ? 'bx-printer' : 'bx-show' }} me-1"></i> {{ $account['paid'] ? 'Reimprimir' : 'Vista previa' }}</span>
                                                    <span wire:loading wire:target="printActiveMesaAccount({{ $dm->id }}, {{ $printableMesaAccount['split_id'] }}, {{ $account['index'] }})"><span class="spinner-border spinner-border-sm me-1"></span>Preparando</span>
                                                </button>
                                            </article>
                                        @endforeach
                                    </div>
                                @else
                                    <article class="mesa-print-account mesa-print-account--full">
                                        <div class="mesa-print-account__status"><i class="bx bx-receipt"></i></div>
                                        <div class="mesa-print-account__summary">
                                            <strong>{{ $printableMesaAccount['service_label'] }}</strong>
                                            <small>Cuenta completa · pendiente de cobro</small>
                                        </div>
                                        <strong class="mesa-print-account__total">${{ number_format($printableMesaAccount['total'], 2) }}</strong>
                                        <button type="button" class="btn btn-primary btn-sm"
                                            wire:click="printActiveMesaAccount({{ $dm->id }})"
                                            wire:loading.attr="disabled" wire:target="printActiveMesaAccount({{ $dm->id }})"
                                            aria-label="Ver e imprimir la cuenta completa">
                                            <span wire:loading.remove wire:target="printActiveMesaAccount({{ $dm->id }})"><i class="bx bx-show me-1"></i> Vista previa</span>
                                            <span wire:loading wire:target="printActiveMesaAccount({{ $dm->id }})"><span class="spinner-border spinner-border-sm me-1"></span>Preparando</span>
                                        </button>
                                    </article>
                                @endif

                                <div class="mesa-print-section__notice">
                                    <i class="bx bx-shield-quarter"></i>
                                    <span>Solo se muestran cuentas del servicio activo, con caja abierta y estado <strong>En cuenta</strong>.</span>
                                </div>
                            </section>
                        </div>
                    @endif

                    @if ($dm->splits->isNotEmpty())
                        <div x-show="tab === 'split'" x-cloak>
                            @php $detailSplit = $dm->splits->first(); @endphp
                            <div class="detail-orders-list" aria-label="Resumen de la cuenta dividida">
                                <div class="detail-order-row">
                                    <div class="detail-order-header">
                                        <span class="fw-semibold"><i class="bx bx-git-branch me-1"></i>Split enviado a
                                            caja</span>
                                        <span class="badge bg-label-warning">{{ $detailSplit->status_label }}</span>
                                    </div>
                                    <p class="text-muted small mb-3">Caja cobrará cada subcuenta individualmente. La
                                        mesa se libera al pagar la última.</p>
                                    @foreach ($detailSplit->split_data as $account)
                                        <div class="detail-history-row py-2">
                                            <div
                                                class="detail-history-icon bg-label-{{ $account['paid'] ?? false ? 'success' : 'warning' }}">
                                                <i
                                                    class="bx {{ $account['paid'] ?? false ? 'bx-check' : 'bx-wallet' }}"></i>
                                            </div>
                                            <div class="flex-grow-1 min-width-0">
                                                <div class="d-flex align-items-center justify-content-between gap-2">
                                                    <strong>{{ $account['label'] ?? 'Cuenta' }}</strong>
                                                    <strong>${{ number_format((float) ($account['total'] ?? 0), 2) }}</strong>
                                                </div>
                                                <small class="text-muted">{{ count($account['items'] ?? []) }}
                                                    producto(s) ·
                                                    {{ $account['paid'] ?? false ? 'Pagada' : 'Pendiente de cobro en POS' }}</small>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="mesas-modal-actions">
                    <button class="btn btn-outline-secondary"
                        wire:click="$set('showDetailModal', false)">Cerrar</button>
                    @if ($dm->status === 'ocupada')
                        @can('cerrar mesas')
                            <button type="button" class="btn btn-outline-warning"
                                wire:click="openCloseMesa({{ $dm->id }})" wire:loading.attr="disabled"
                                wire:target="openCloseMesa" aria-label="Cerrar mesa">
                                <i class="bx bx-lock-alt me-1"></i> Cerrar mesa
                            </button>
                        @endcan
                        @can('ordenar mesas')
                            <button class="btn btn-primary" wire:click="goToOrden({{ $dm->id }})">
                                <i class="bx bx-receipt me-1"></i> Ordenar
                            </button>
                        @endcan
                    @endif
                    @can('dividir mesas')
                        @if ($dm->splits->isNotEmpty())
                            <button class="btn btn-primary" wire:click="goToSplit({{ $dm->id }})">
                                <i class="bx bx-check-circle me-1"></i> Ver cuenta dividida
                            </button>
                        @elseif($dm->status === 'en_cuenta')
                            <button class="btn btn-primary" wire:click="goToSplit({{ $dm->id }})">
                                <i class="bx bx-git-branch me-1"></i> Dividir cuenta
                            </button>
                        @endif
                    @endcan
                </div>
            </div>
        </div>
    @endif

    <div class="mesa-ticket-preview-backdrop" x-data="{ open: false, html: '', title: 'Cuenta de mesa' }"
        x-on:mesa-account-ticket-preview.window="html = $event.detail.html || ''; title = $event.detail.title || 'Cuenta de mesa'; open = true"
        x-on:keydown.escape.window="if (open) { open = false; html = '' }" x-show="open" x-cloak
        @click.self="open = false; html = ''" role="dialog" aria-modal="true" aria-labelledby="mesa-ticket-preview-title">
        <section class="mesa-ticket-preview">
            <header class="mesa-ticket-preview__header">
                <div>
                    <span>VISTA PREVIA</span>
                    <h5 id="mesa-ticket-preview-title" x-text="title"></h5>
                </div>
                <button type="button" class="mesas-modal-close" @click="open = false; html = ''"
                    aria-label="Cerrar vista previa"><i class="bx bx-x"></i></button>
            </header>
            <div class="mesa-ticket-preview__body">
                <iframe x-ref="ticketFrame" :srcdoc="html" title="Vista previa del ticket de mesa"></iframe>
            </div>
            <footer class="mesa-ticket-preview__actions">
                <button type="button" class="btn btn-outline-secondary" @click="open = false; html = ''">Cerrar</button>
                <button type="button" class="btn btn-primary"
                    @click="try { $refs.ticketFrame.contentWindow.print() } catch (error) {}">
                    <i class="bx bx-printer me-1"></i> Imprimir ticket
                </button>
            </footer>
        </section>
    </div>

</div>
</div>
