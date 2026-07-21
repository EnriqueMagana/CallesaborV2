<div class="kiosk-admin-page">
    <header class="kiosk-admin-hero">
        <div>
            <span class="kiosk-admin-eyebrow"><i class="bx bx-devices"></i> Autoservicio</span>
            <h1>Configuración de kioscos</h1>
            <p>Administra terminales, modalidades, seguridad y la experiencia que verá cada cliente.</p>
        </div>
        <button type="button" class="btn btn-primary kiosk-admin-main-action" wire:click="createTerminal">
            <i class="bx bx-plus"></i> Nuevo terminal
        </button>
    </header>

    @if(session('kioskNotice'))
        <div class="kiosk-admin-notice" role="status"><i class="bx bx-check-circle"></i>{{ session('kioskNotice') }}</div>
    @endif

    @if($issuedUrl)
        <section class="kiosk-token-banner" role="alert">
            <div class="kiosk-token-icon"><i class="bx bx-key"></i></div>
            <div class="kiosk-token-copy">
                <span>Token emitido para {{ $issuedTerminalName }}</span>
                <h2>Guarda esta URL ahora</h2>
                <p>Por seguridad, el token no podrá consultarse nuevamente. Al rotarlo, la URL anterior deja de funcionar.</p>
                <code>{{ $issuedUrl }}</code>
            </div>
            <div class="kiosk-token-actions">
                <button type="button" class="btn btn-primary" onclick="navigator.clipboard.writeText(@js($issuedUrl))"><i class="bx bx-copy"></i> Copiar URL</button>
                <a class="btn btn-outline-primary" href="{{ $issuedUrl }}" target="_blank" rel="noopener"><i class="bx bx-link-external"></i> Abrir</a>
                <button type="button" class="btn btn-icon btn-outline-secondary" wire:click="dismissIssuedUrl" aria-label="Cerrar aviso"><i class="bx bx-x"></i></button>
            </div>
        </section>
    @endif

    <section class="kiosk-admin-stats" aria-label="Resumen de kioscos">
        <article><span class="is-purple"><i class="bx bx-devices"></i></span><div><small>Terminales</small><strong>{{ $this->stats['total'] }}</strong></div></article>
        <article><span class="is-green"><i class="bx bx-wifi"></i></span><div><small>Activos</small><strong>{{ $this->stats['active'] }}</strong></div></article>
        <article><span class="is-blue"><i class="bx bx-receipt"></i></span><div><small>Pedidos hoy</small><strong>{{ $this->stats['today'] }}</strong></div></article>
        <article><span class="is-orange"><i class="bx bx-dollar-circle"></i></span><div><small>Venta kiosco hoy</small><strong>${{ number_format($this->stats['sales'], 2) }}</strong></div></article>
    </section>

    <section class="kiosk-admin-card">
        <div class="kiosk-admin-card-head">
            <div><h2>Terminales configurados</h2><p>Cada terminal utiliza una URL privada con token revocable.</p></div>
            <span>{{ $this->terminals->count() }} registrados</span>
        </div>

        <div class="kiosk-terminal-grid">
            @forelse($this->terminals as $terminal)
                <article class="kiosk-terminal-card {{ $terminal->is_active ? '' : 'is-paused' }}" wire:key="terminal-{{ $terminal->id }}">
                    <div class="kiosk-terminal-head">
                        <span class="kiosk-terminal-device"><i class="bx bx-desktop"></i></span>
                        <div><h3>{{ $terminal->name }}</h3><p>Token ····{{ $terminal->token_hint ?: 'sin pista' }}</p></div>
                        <span class="kiosk-status-badge {{ $terminal->is_active ? 'is-active' : 'is-inactive' }}"><i class="bx bxs-circle"></i>{{ $terminal->is_active ? 'Activo' : 'Pausado' }}</span>
                    </div>
                    <dl class="kiosk-terminal-meta">
                        <div><dt>Responsable</dt><dd>{{ $terminal->user?->name ?: 'Sin asignar' }}</dd></div>
                        <div><dt>Modalidades</dt><dd>{{ collect([$terminal->allow_dine_in ? 'Comer aquí' : null, $terminal->allow_takeaway ? 'Para llevar' : null, $terminal->allow_delivery ? 'Para domicilio' : null])->filter()->implode(' · ') }}</dd></div>
                        <div><dt>Pedidos hoy</dt><dd>{{ $terminal->today_orders_count }}</dd></div>
                        <div><dt>Último acceso</dt><dd>{{ $terminal->last_used_at?->diffForHumans() ?: 'Nunca' }}</dd></div>
                    </dl>
                    <div class="kiosk-terminal-actions">
                        <button type="button" class="btn btn-primary" wire:click="editTerminal({{ $terminal->id }})"><i class="bx bx-slider-alt"></i> Ajustes</button>
                        <button type="button" class="btn btn-outline-primary" wire:click="confirmRotateToken({{ $terminal->id }})"><i class="bx bx-refresh"></i> Rotar token</button>
                        <button type="button" class="btn btn-icon {{ $terminal->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}" wire:click="toggleTerminal({{ $terminal->id }})" aria-label="{{ $terminal->is_active ? 'Pausar terminal' : 'Activar terminal' }}"><i class="bx {{ $terminal->is_active ? 'bx-pause' : 'bx-play' }}"></i></button>
                    </div>
                </article>
            @empty
                <div class="kiosk-admin-empty"><span><i class="bx bx-devices"></i></span><h2>Aún no hay terminales</h2><p>Crea el primero para obtener una URL segura de acceso al kiosco.</p><button type="button" class="btn btn-primary" wire:click="createTerminal">Crear primer terminal</button></div>
            @endforelse
        </div>
    </section>

    <section class="kiosk-security-note">
        <i class="bx bx-shield-quarter"></i>
        <div><h2>Seguridad por diseño</h2><p>Los tokens se almacenan como hash. Solo un owner, super-admin o usuario con el permiso <strong>gestionar kioscos</strong> puede modificar estos ajustes.</p></div>
    </section>

    @if($showForm)
        <div class="kiosk-admin-modal-backdrop" wire:click.self="closeForm">
            <section class="kiosk-admin-modal" role="dialog" aria-modal="true" aria-labelledby="kiosk-form-title">
                <header>
                    <div><span>{{ $editingId ? 'Editar terminal' : 'Nuevo terminal' }}</span><h2 id="kiosk-form-title">{{ $editingId ? $name : 'Configura el kiosco' }}</h2></div>
                    <button type="button" class="btn btn-icon btn-outline-secondary" wire:click="closeForm" aria-label="Cerrar"><i class="bx bx-x"></i></button>
                </header>

                <div class="kiosk-admin-form-body">
                    <section class="kiosk-form-section">
                        <div class="kiosk-form-section-title"><span><i class="bx bx-desktop"></i></span><div><h3>Identidad y responsable</h3><p>Información interna para reconocer el dispositivo.</p></div></div>
                        <div class="kiosk-form-grid">
                            <label class="kiosk-admin-field"><span>Nombre del terminal</span><input type="text" class="form-control" wire:model="name" maxlength="100" placeholder="Ej. Kiosco entrada"></label>
                            <label class="kiosk-admin-field"><span>Persona responsable</span><select class="form-select" wire:model="userId"><option value="">Selecciona una persona</option>@foreach($this->users as $user)<option value="{{ $user->id }}">{{ $user->name }} · {{ $user->email }}</option>@endforeach</select></label>
                        </div>
                        @error('name')<p class="kiosk-admin-error"><i class="bx bx-error-circle"></i>{{ $message }}</p>@enderror
                        @error('userId')<p class="kiosk-admin-error"><i class="bx bx-error-circle"></i>{{ $message }}</p>@enderror
                    </section>

                    <section class="kiosk-form-section">
                        <div class="kiosk-form-section-title"><span><i class="bx bx-toggle-right"></i></span><div><h3>Operación</h3><p>Define qué opciones estarán disponibles para el cliente.</p></div></div>
                        <div class="kiosk-switch-grid">
                            <label class="kiosk-setting-switch"><input type="checkbox" wire:model="isActive"><span><i class="bx bx-power-off"></i></span><div><strong>Terminal activo</strong><small>Permite abrir el wizard con su token.</small></div></label>
                            <label class="kiosk-setting-switch"><input type="checkbox" wire:model="allowDineIn"><span><i class="bx bx-restaurant"></i></span><div><strong>Comer aquí</strong><small>Muestra consumo dentro del restaurante.</small></div></label>
                            <label class="kiosk-setting-switch"><input type="checkbox" wire:model="allowTakeaway"><span><i class="bx bx-shopping-bag"></i></span><div><strong>Para llevar</strong><small>Permite pedidos preparados para salir.</small></div></label>
                            <label class="kiosk-setting-switch"><input type="checkbox" wire:model="allowDelivery"><span><i class="bx bx-cycling"></i></span><div><strong>Para domicilio</strong><small>Solicita teléfono y dirección para entregar.</small></div></label>
                            <label class="kiosk-setting-switch"><input type="checkbox" wire:model="requireCustomerPhone"><span><i class="bx bx-phone"></i></span><div><strong>Teléfono obligatorio</strong><small>Exige teléfono antes de confirmar.</small></div></label>
                        </div>
                        @error('fulfillment')<p class="kiosk-admin-error"><i class="bx bx-error-circle"></i>{{ $message }}</p>@enderror
                        <div class="kiosk-form-grid kiosk-form-grid-numeric">
                            <label class="kiosk-admin-field"><span>Pedidos por minuto</span><input type="number" class="form-control" wire:model="ordersPerMinute" min="1" max="60"><small>Protección contra solicitudes excesivas.</small></label>
                            <label class="kiosk-admin-field"><span>Reinicio automático</span><div class="input-group"><input type="number" class="form-control" wire:model="autoResetSeconds" min="10" max="600"><span class="input-group-text">segundos</span></div><small>Tiempo visible del QR antes de volver al inicio.</small></label>
                        </div>
                    </section>

                    <section class="kiosk-form-section">
                        <div class="kiosk-form-section-title"><span><i class="bx bx-message-square-detail"></i></span><div><h3>Mensajes para el cliente</h3><p>Personaliza el contenido sin modificar las vistas.</p></div></div>
                        <label class="kiosk-admin-field"><span>Título de bienvenida</span><input type="text" class="form-control" wire:model="welcomeTitle" maxlength="100"></label>
                        <label class="kiosk-admin-field"><span>Descripción de bienvenida</span><textarea class="form-control" wire:model="welcomeMessage" maxlength="240" rows="2"></textarea></label>
                        <label class="kiosk-admin-field"><span>Instrucciones de pago</span><input type="text" class="form-control" wire:model="paymentInstructions" maxlength="180"></label>
                        <label class="kiosk-admin-field"><span>Mensaje al finalizar</span><textarea class="form-control" wire:model="successMessage" maxlength="180" rows="2"></textarea></label>
                        @foreach(['welcomeTitle','welcomeMessage','paymentInstructions','successMessage','ordersPerMinute','autoResetSeconds'] as $field)@error($field)<p class="kiosk-admin-error"><i class="bx bx-error-circle"></i>{{ $message }}</p>@enderror @endforeach
                    </section>
                </div>

                <footer>
                    <button type="button" class="btn btn-outline-secondary" wire:click="closeForm">Cancelar</button>
                    <button type="button" class="btn btn-primary" wire:click="saveTerminal" wire:loading.attr="disabled" wire:target="saveTerminal"><span wire:loading.remove wire:target="saveTerminal"><i class="bx bx-check"></i> {{ $editingId ? 'Guardar ajustes' : 'Crear y emitir token' }}</span><span wire:loading wire:target="saveTerminal">Guardando…</span></button>
                </footer>
            </section>
        </div>
    @endif
</div>
