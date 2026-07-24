<div class="customers-page">
    <header class="customers-hero">
        <div class="customers-hero__copy">
            <span class="customers-eyebrow"><i class="bx bx-group" aria-hidden="true"></i> Directorio compartido con el POS</span>
            <h1>Mis clientes</h1>
            <p>Consulta y mantén actualizados los datos que se utilizan para prellenar cada venta.</p>
        </div>
        @can('crear clientes')
            <button type="button" class="customers-primary-action" wire:click="openCreate" wire:loading.attr="disabled" wire:target="openCreate">
                <span wire:loading.remove wire:target="openCreate"><i class="bx bx-user-plus" aria-hidden="true"></i>Agregar cliente</span>
                <span wire:loading wire:target="openCreate"><i class="bx bx-loader-alt bx-spin" aria-hidden="true"></i>Preparando…</span>
            </button>
        @endcan
    </header>

    <section class="customers-stats" aria-label="Resumen de clientes">
        <article>
            <span class="customers-stat__icon"><i class="bx bx-group" aria-hidden="true"></i></span>
            <span><small>Clientes registrados</small><strong>{{ number_format($stats['total']) }}</strong></span>
        </article>
        <article>
            <span class="customers-stat__icon customers-stat__icon--success"><i class="bx bx-receipt" aria-hidden="true"></i></span>
            <span><small>Con historial de compra</small><strong>{{ number_format($stats['with_orders']) }}</strong></span>
        </article>
        <article>
            <span class="customers-stat__icon customers-stat__icon--info"><i class="bx bx-calendar-plus" aria-hidden="true"></i></span>
            <span><small>Nuevos este mes</small><strong>{{ number_format($stats['new_this_month']) }}</strong></span>
        </article>
    </section>

    <section class="customers-directory" aria-labelledby="customers-directory-title">
        <div class="customers-directory__header">
            <div>
                <span class="customers-eyebrow">Directorio</span>
                <h2 id="customers-directory-title">Lista de clientes</h2>
                <p>{{ $customers->total() }} {{ $customers->total() === 1 ? 'resultado' : 'resultados' }}</p>
            </div>
            <div class="customers-search">
                <i class="bx bx-search" aria-hidden="true"></i>
                <label for="customer-search" class="visually-hidden">Buscar clientes</label>
                <input
                    id="customer-search"
                    type="search"
                    wire:model.live.debounce.450ms="search"
                    placeholder="Nombre, teléfono, correo o dirección"
                    autocomplete="off"
                >
                @if($search !== '')
                    <button type="button" wire:click="clearSearch" aria-label="Limpiar búsqueda"><i class="bx bx-x" aria-hidden="true"></i></button>
                @endif
            </div>
        </div>

        <div class="customers-list-wrap">
            <div
                class="customers-list-loader"
                wire:loading.flex
                wire:target="search,clearSearch,gotoPage,nextPage,previousPage"
                role="status"
                aria-live="polite"
            >
                <i class="bx bx-loader-alt bx-spin" aria-hidden="true"></i>
                <span>Actualizando clientes…</span>
            </div>

            @if($customers->isEmpty())
                <div class="customers-empty">
                    <span><i class="bx {{ $search !== '' ? 'bx-search-alt' : 'bx-user-plus' }}" aria-hidden="true"></i></span>
                    <h3>{{ $search !== '' ? 'No encontramos coincidencias' : 'Aún no hay clientes registrados' }}</h3>
                    <p>{{ $search !== '' ? 'Prueba con otro nombre, teléfono, correo o dirección.' : 'Los clientes que se registren aquí también estarán disponibles en el POS.' }}</p>
                    @if($search !== '')
                        <button type="button" wire:click="clearSearch">Limpiar búsqueda</button>
                    @elseif(auth()->user()->can('crear clientes'))
                        <button type="button" wire:click="openCreate">Agregar primer cliente</button>
                    @endif
                </div>
            @else
                <div class="customers-table-scroll">
                    <table class="customers-table">
                        <thead>
                            <tr>
                                <th scope="col">Cliente</th>
                                <th scope="col">Contacto</th>
                                <th scope="col">Dirección</th>
                                <th scope="col">Pedidos</th>
                                <th scope="col">Última actividad</th>
                                <th scope="col"><span class="visually-hidden">Acciones</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($customers as $customer)
                                @php
                                    $initials = collect(explode(' ', trim($customer->name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->join('');
                                @endphp
                                <tr wire:key="customer-{{ $customer->id }}">
                                    <td data-label="Cliente">
                                        <button type="button" class="customer-identity" wire:click="openDetails({{ $customer->id }})">
                                            <span class="customer-avatar" aria-hidden="true">{{ $initials }}</span>
                                            <span><strong>{{ $customer->name }}</strong><small>Cliente #{{ $customer->id }}</small></span>
                                        </button>
                                    </td>
                                    <td data-label="Contacto">
                                        <span class="customer-contact"><i class="bx bx-phone" aria-hidden="true"></i>{{ $customer->phone ?: 'Sin teléfono' }}</span>
                                        <span class="customer-contact"><i class="bx bx-envelope" aria-hidden="true"></i>{{ $customer->email ?: 'Sin correo' }}</span>
                                    </td>
                                    <td data-label="Dirección"><span class="customer-address">{{ $customer->address ?: 'Sin dirección registrada' }}</span></td>
                                    <td data-label="Pedidos"><span class="customer-order-count">{{ $customer->orders_count }}</span></td>
                                    <td data-label="Última actividad">
                                        <span class="customer-last-seen">
                                            {{ $customer->orders_max_created_at ? \Carbon\Carbon::parse($customer->orders_max_created_at)->diffForHumans() : 'Sin compras' }}
                                        </span>
                                    </td>
                                    <td data-label="Acciones">
                                        <div class="customer-row-actions">
                                            <button type="button" wire:click="openDetails({{ $customer->id }})" aria-label="Ver detalles de {{ $customer->name }}"><i class="bx bx-show" aria-hidden="true"></i></button>
                                            @can('editar clientes')
                                                <button type="button" wire:click="openEdit({{ $customer->id }})" aria-label="Editar a {{ $customer->name }}"><i class="bx bx-edit-alt" aria-hidden="true"></i></button>
                                            @endcan
                                            @can('eliminar clientes')
                                                <button type="button" class="is-danger" wire:click="confirmDelete({{ $customer->id }})" aria-label="Eliminar a {{ $customer->name }}"><i class="bx bx-trash" aria-hidden="true"></i></button>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($customers->hasPages())
                    <div class="customers-pagination">{{ $customers->links() }}</div>
                @endif
            @endif
        </div>
    </section>

    @if($showModal)
        <div class="customer-modal-layer" wire:click.self="closeModal" @keydown.escape.window="$wire.closeModal()" role="presentation">
            <section class="customer-modal" role="dialog" aria-modal="true" aria-labelledby="customer-modal-title">
                @if($modalMode === 'view' && $selectedCustomer)
                    @php
                        $modalInitials = collect(explode(' ', trim($selectedCustomer->name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->join('');
                    @endphp
                    <header class="customer-modal__profile">
                        <span class="customer-modal__avatar" aria-hidden="true">{{ $modalInitials }}</span>
                        <div>
                            <span class="customers-eyebrow">Ficha del cliente</span>
                            <h2 id="customer-modal-title">{{ $selectedCustomer->name }}</h2>
                            <p>Registrado {{ $selectedCustomer->created_at->diffForHumans() }}</p>
                        </div>
                        <button type="button" class="customer-modal__close" wire:click="closeModal" aria-label="Cerrar detalles"><i class="bx bx-x" aria-hidden="true"></i></button>
                    </header>

                    <div class="customer-modal__body">
                        <div class="customer-detail-grid">
                            <article><i class="bx bx-phone" aria-hidden="true"></i><span><small>Teléfono</small><strong>{{ $selectedCustomer->phone ?: 'No registrado' }}</strong></span></article>
                            <article><i class="bx bx-envelope" aria-hidden="true"></i><span><small>Correo</small><strong>{{ $selectedCustomer->email ?: 'No registrado' }}</strong></span></article>
                            <article class="is-wide"><i class="bx bx-map" aria-hidden="true"></i><span><small>Dirección</small><strong>{{ $selectedCustomer->address ?: 'No registrada' }}</strong></span></article>
                            <article class="is-wide"><i class="bx bx-navigation" aria-hidden="true"></i><span><small>Referencias</small><strong>{{ $selectedCustomer->references ?: 'Sin referencias adicionales' }}</strong></span></article>
                        </div>

                        <div class="customer-history-summary">
                            <div><span>Pedidos</span><strong>{{ $selectedCustomer->orders_count }}</strong></div>
                            <div><span>Última compra</span><strong>{{ $selectedCustomer->orders_max_created_at ? \Carbon\Carbon::parse($selectedCustomer->orders_max_created_at)->diffForHumans() : 'Sin compras' }}</strong></div>
                            @can('ver reportes financieros')
                                <div><span>Total comprado</span><strong>${{ number_format((float) ($selectedCustomer->paid_orders_total ?? 0), 2) }}</strong></div>
                            @endcan
                        </div>

                        <section class="customer-recent-orders" aria-labelledby="customer-orders-title">
                            <div class="customer-recent-orders__heading">
                                <div><span class="customers-eyebrow">Actividad</span><h3 id="customer-orders-title">Pedidos recientes</h3></div>
                                <small>Últimos {{ $selectedCustomer->orders->count() }}</small>
                            </div>
                            @forelse($selectedCustomer->orders as $order)
                                <div class="customer-recent-order">
                                    <span class="customer-recent-order__icon"><i class="bx {{ $order->type_icon }}" aria-hidden="true"></i></span>
                                    <span><strong>Pedido #{{ $order->display_folio }}</strong><small>{{ $order->type_label }} · {{ $order->created_at->translatedFormat('d M Y, H:i') }}</small></span>
                                    <span class="customer-recent-order__status customer-recent-order__status--{{ $order->status_color }}">{{ $order->status_label }}</span>
                                </div>
                            @empty
                                <div class="customer-orders-empty"><i class="bx bx-receipt" aria-hidden="true"></i><span><strong>Sin pedidos todavía</strong><small>Su primera compra aparecerá aquí.</small></span></div>
                            @endforelse
                        </section>
                    </div>

                    <footer class="customer-modal__actions">
                        @can('eliminar clientes')
                            <button type="button" class="customer-danger-action" wire:click="confirmDelete({{ $selectedCustomer->id }})"><i class="bx bx-trash" aria-hidden="true"></i>Eliminar</button>
                        @endcan
                        <span></span>
                        <button type="button" class="customer-secondary-action" wire:click="closeModal">Cerrar</button>
                        @can('editar clientes')
                            <button type="button" class="customer-primary-action" wire:click="openEdit({{ $selectedCustomer->id }})"><i class="bx bx-edit-alt" aria-hidden="true"></i>Editar cliente</button>
                        @endcan
                    </footer>
                @else
                    <header class="customer-modal__form-header">
                        <span class="customer-modal__form-icon"><i class="bx {{ $modalMode === 'edit' ? 'bx-edit-alt' : 'bx-user-plus' }}" aria-hidden="true"></i></span>
                        <div>
                            <span class="customers-eyebrow">{{ $modalMode === 'edit' ? 'Actualizar información' : 'Nuevo registro' }}</span>
                            <h2 id="customer-modal-title">{{ $modalMode === 'edit' ? 'Editar cliente' : 'Agregar cliente' }}</h2>
                            <p>Estos datos estarán disponibles inmediatamente en el punto de venta.</p>
                        </div>
                        <button type="button" class="customer-modal__close" wire:click="closeModal" aria-label="Cerrar formulario"><i class="bx bx-x" aria-hidden="true"></i></button>
                    </header>

                    <form wire:submit="save">
                        <div class="customer-form">
                            <div class="customer-field is-wide">
                                <label for="customer-name">Nombre completo <span>*</span></label>
                                <div class="customer-input"><i class="bx bx-user" aria-hidden="true"></i><input id="customer-name" type="text" wire:model="name" autocomplete="name" placeholder="Ej. Andrea Hernández" autofocus></div>
                                @error('name')<small class="customer-field-error" role="alert">{{ $message }}</small>@enderror
                            </div>
                            <div class="customer-field">
                                <label for="customer-phone">Teléfono <span>*</span></label>
                                <div class="customer-input"><i class="bx bx-phone" aria-hidden="true"></i><input id="customer-phone" type="tel" wire:model="phone" autocomplete="tel" placeholder="+52 999 000 0000"></div>
                                @error('phone')<small class="customer-field-error" role="alert">{{ $message }}</small>@enderror
                            </div>
                            <div class="customer-field">
                                <label for="customer-email">Correo <em>Opcional</em></label>
                                <div class="customer-input"><i class="bx bx-envelope" aria-hidden="true"></i><input id="customer-email" type="email" wire:model="email" autocomplete="email" placeholder="cliente@correo.com"></div>
                                @error('email')<small class="customer-field-error" role="alert">{{ $message }}</small>@enderror
                            </div>
                            <div class="customer-field is-wide">
                                <label for="customer-address">Dirección <em>Opcional</em></label>
                                <div class="customer-input"><i class="bx bx-map" aria-hidden="true"></i><input id="customer-address" type="text" wire:model="address" autocomplete="street-address" placeholder="Calle, número, colonia"></div>
                                @error('address')<small class="customer-field-error" role="alert">{{ $message }}</small>@enderror
                            </div>
                            <div class="customer-field is-wide">
                                <label for="customer-references">Referencias <em>Opcional</em></label>
                                <textarea id="customer-references" wire:model="references" rows="3" placeholder="Indicaciones útiles para ubicar el domicilio"></textarea>
                                <small class="customer-field-help">Máximo 500 caracteres.</small>
                                @error('references')<small class="customer-field-error" role="alert">{{ $message }}</small>@enderror
                            </div>
                        </div>
                        <footer class="customer-modal__actions">
                            <span></span><span></span>
                            <button type="button" class="customer-secondary-action" wire:click="closeModal" wire:loading.attr="disabled" wire:target="save">Cancelar</button>
                            <button type="submit" class="customer-primary-action" wire:loading.attr="disabled" wire:target="save">
                                <span wire:loading.remove wire:target="save"><i class="bx bx-save" aria-hidden="true"></i>{{ $modalMode === 'edit' ? 'Guardar cambios' : 'Agregar cliente' }}</span>
                                <span wire:loading wire:target="save"><i class="bx bx-loader-alt bx-spin" aria-hidden="true"></i>Guardando…</span>
                            </button>
                        </footer>
                    </form>
                @endif
            </section>
        </div>
    @endif
</div>
