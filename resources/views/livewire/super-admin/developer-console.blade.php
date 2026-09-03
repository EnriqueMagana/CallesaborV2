<div class="developer-console">
    <header class="developer-console__hero">
        <div>
            <span class="developer-console__eyebrow"><i class="bx bx-code-alt" aria-hidden="true"></i> Super Admin</span>
            <h1>Centro técnico</h1>
            <p>Observabilidad, salud de servicios y pruebas controladas antes de producción.</p>
        </div>
        <div class="developer-console__hero-actions">
            @can('gestionar variables de entorno')
                <a href="{{ route('app.super-admin.environment') }}" class="developer-button developer-button--secondary">
                    <i class="bx bx-slider-alt" aria-hidden="true"></i> Variables de entorno
                </a>
            @endcan
            <a href="{{ route('pulse') }}" target="_blank" rel="noopener" class="developer-button developer-button--secondary">
                <i class="bx bx-pulse" aria-hidden="true"></i> Abrir Laravel Pulse
            </a>
            <button type="button" class="developer-button developer-button--primary" wire:click="refreshDiagnostics"
                wire:loading.attr="disabled" wire:target="refreshDiagnostics">
                <i class="bx bx-refresh" aria-hidden="true"></i>
                <span wire:loading.remove wire:target="refreshDiagnostics">Actualizar diagnóstico</span>
                <span wire:loading wire:target="refreshDiagnostics">Comprobando…</span>
            </button>
        </div>
    </header>

    @if ($lastAction)
        <div class="developer-alert developer-alert--{{ $lastAction['ok'] ? 'success' : 'warning' }}" role="status">
            <i class="bx {{ $lastAction['ok'] ? 'bx-check-circle' : 'bx-error-circle' }}" aria-hidden="true"></i>
            <div><strong>{{ $lastAction['ok'] ? 'Prueba completada' : 'Atención' }}</strong><span>{{ $lastAction['message'] }}</span></div>
        </div>
    @endif

    @php
        $deliveryState = $this->deliveryModuleState;
        $deliveryEnabled = $deliveryState['enabled'];
        $deliveryImpact = $deliveryState['impact'];
        $cannotDisable = $deliveryEnabled && ($deliveryImpact['assigned_orders'] > 0 || $deliveryImpact['in_route_orders'] > 0);
        $lastDeliveryChange = $deliveryState['last_change'];
    @endphp
    <section class="developer-panel developer-module-control {{ $deliveryEnabled ? 'is-enabled' : 'is-manual' }}"
        aria-labelledby="delivery-module-title">
        <header class="developer-panel__header developer-panel__header--split">
            <div>
                <span class="developer-panel__icon"><i class="bx bx-cycling" aria-hidden="true"></i></span>
                <div>
                    <h2 id="delivery-module-title">Gestión operativa de Delivery</h2>
                    <p>Define si los repartidores usan asignación digital o si el efectivo se concilia directamente en el corte global.</p>
                </div>
            </div>
            <span class="developer-module-control__state" role="status">
                <i class="bx {{ $deliveryEnabled ? 'bx-check-shield' : 'bx-hand' }}" aria-hidden="true"></i>
                {{ $deliveryEnabled ? 'Administrado' : 'Gestión manual' }}
            </span>
        </header>
        <div class="developer-module-control__body">
            <div class="developer-module-control__summary">
                <strong>{{ $deliveryEnabled ? 'Asignación, seguimiento y mini cortes activos' : 'Sin asignaciones ni mini cortes individuales' }}</strong>
                <p>{{ $deliveryEnabled
                    ? 'Cada repartidor toma el pedido, confirma la entrega y entrega su arqueo a caja.'
                    : 'Los pedidos contra entrega se contabilizan automáticamente como efectivo esperado y se cuadran con el responsable del corte.' }}</p>
                @if($lastDeliveryChange)
                    <small>Último cambio: {{ $lastDeliveryChange->changed_at?->format('d/m/Y H:i') }} por {{ $lastDeliveryChange->changedBy?->name ?? 'Usuario eliminado' }}.</small>
                @endif
            </div>
            <dl class="developer-module-control__metrics" aria-label="Impacto del módulo Delivery en la caja activa">
                <div><dt>Sin asignar</dt><dd>{{ $deliveryImpact['unassigned_orders'] }}</dd></div>
                <div><dt>Asignados</dt><dd>{{ $deliveryImpact['assigned_orders'] }}</dd></div>
                <div><dt>En ruta</dt><dd>{{ $deliveryImpact['in_route_orders'] }}</dd></div>
                <div><dt>Manuales</dt><dd>{{ $deliveryImpact['manual_orders'] }}</dd></div>
            </dl>
            <div class="developer-module-control__action">
                <button type="button" class="developer-module-switch"
                    role="switch" aria-checked="{{ $deliveryEnabled ? 'true' : 'false' }}"
                    aria-describedby="delivery-module-help"
                    wire:click="confirmToggleDeliveryModule"
                    wire:loading.attr="disabled"
                    wire:target="confirmToggleDeliveryModule,toggleDeliveryModule">
                    <span aria-hidden="true"><i class="bx {{ $deliveryEnabled ? 'bx-check' : 'bx-x' }}"></i></span>
                    <strong>{{ $deliveryEnabled ? 'Desactivar Delivery' : 'Activar Delivery' }}</strong>
                </button>
                <p id="delivery-module-help">
                    @if($cannotDisable)
                        Debes resolver los pedidos asignados o en ruta antes de desactivar el módulo.
                    @elseif($deliveryEnabled)
                        Los pedidos sin asignar se convertirán a gestión manual al confirmar.
                    @else
                        El cambio aplicará a pedidos nuevos; los manuales conservarán su historial.
                    @endif
                </p>
                @error('deliveryModule')
                    <div class="developer-module-control__error" role="alert"><i class="bx bx-error-circle" aria-hidden="true"></i>{{ $message }}</div>
                @enderror
            </div>
        </div>
    </section>

    <section class="developer-health-grid" aria-label="Estado general de servicios">
        @php
            $healthCards = [
                ['label' => 'Base de datos', 'ok' => $diagnostics['database']['ok'], 'value' => $diagnostics['database']['connection'], 'meta' => $diagnostics['database']['latency_ms'].' ms', 'icon' => 'bx-data'],
                ['label' => 'Firebase RTDB', 'ok' => $diagnostics['firebase']['ready'], 'value' => $diagnostics['firebase']['ready'] ? 'Operativo' : 'Fallback activo', 'meta' => $diagnostics['firebase']['database_host'] ?: 'Sin host', 'icon' => 'bx-broadcast'],
                ['label' => 'Laravel Pulse', 'ok' => $diagnostics['pulse']['enabled'] && $diagnostics['pulse']['tables_ready'], 'value' => $diagnostics['pulse']['enabled'] ? 'Grabando' : 'Desactivado', 'meta' => number_format((int) ($diagnostics['pulse']['entries'] ?? 0)).' entradas', 'icon' => 'bx-pulse'],
                ['label' => 'Cola', 'ok' => (int) ($diagnostics['queue']['failed'] ?? 0) === 0, 'value' => $diagnostics['queue']['connection'], 'meta' => (int) ($diagnostics['queue']['pending'] ?? 0).' pendientes · '.(int) ($diagnostics['queue']['failed'] ?? 0).' fallidos', 'icon' => 'bx-layer'],
            ];
        @endphp
        @foreach ($healthCards as $card)
            <article class="developer-health-card {{ $card['ok'] ? 'is-healthy' : 'needs-attention' }}">
                <span class="developer-health-card__icon"><i class="bx {{ $card['icon'] }}" aria-hidden="true"></i></span>
                <div><span>{{ $card['label'] }}</span><strong>{{ $card['value'] }}</strong><small>{{ $card['meta'] }}</small></div>
                <span class="developer-status"><i class="bx {{ $card['ok'] ? 'bx-check' : 'bx-error' }}" aria-hidden="true"></i>{{ $card['ok'] ? 'OK' : 'Revisar' }}</span>
            </article>
        @endforeach
    </section>

    @php
        $mailFrom = (string) config('mail.from.address');
        $usesPlaceholderSender = str_ends_with(strtolower($mailFrom), '@example.com');
        $resendReady = config('mail.default') === 'resend' && filled(config('services.resend.key')) && ! $usesPlaceholderSender;
        $usesResendSandbox = str_ends_with(strtolower($mailFrom), '@resend.dev');
    @endphp
    <section class="developer-panel developer-email-test">
        <header class="developer-panel__header developer-panel__header--split">
            <div>
                <span class="developer-panel__icon"><i class="bx bx-envelope" aria-hidden="true"></i></span>
                <div><h2>Prueba de correo</h2><p>Envía un mensaje real para validar Resend, el remitente y la entrega.</p></div>
            </div>
            <span class="developer-state {{ $resendReady ? 'is-announced' : 'is-pending' }}">
                {{ $resendReady ? 'Listo para probar' : 'Configuración incompleta' }}
            </span>
        </header>
        <div class="developer-email-test__body">
            <form wire:submit="sendTestEmail" class="developer-email-test__form" novalidate>
                <label for="developer-test-email">Correo destinatario</label>
                <div class="developer-email-test__control">
                    <span aria-hidden="true"><i class="bx bx-at"></i></span>
                    <input id="developer-test-email" type="email" wire:model="testEmailRecipient"
                        autocomplete="email" inputmode="email" placeholder="nombre@dominio.com"
                        aria-describedby="developer-test-email-help @error('testEmailRecipient') developer-test-email-error @enderror"
                        @error('testEmailRecipient') aria-invalid="true" @enderror>
                    <button type="submit" class="developer-button developer-button--primary"
                        wire:loading.attr="disabled" wire:target="sendTestEmail">
                        <i class="bx bx-send" aria-hidden="true"></i>
                        <span wire:loading.remove wire:target="sendTestEmail">Enviar prueba</span>
                        <span wire:loading wire:target="sendTestEmail">Enviando…</span>
                    </button>
                </div>
                <small id="developer-test-email-help">Máximo 3 envíos por minuto para esta cuenta.</small>
                @error('testEmailRecipient')
                    <p id="developer-test-email-error" class="developer-email-test__error" role="alert"><i class="bx bx-error-circle" aria-hidden="true"></i>{{ $message }}</p>
                @enderror
            </form>
            <dl class="developer-email-test__status" aria-label="Configuración de correo">
                <div><dt>Transportador</dt><dd>{{ config('mail.default') ?: 'Sin configurar' }}</dd></div>
                <div><dt>Remitente</dt><dd>{{ $mailFrom ?: 'Sin configurar' }}</dd></div>
                <div><dt>API key</dt><dd>{{ filled(config('services.resend.key')) ? 'Configurada' : 'No configurada' }}</dd></div>
            </dl>
        </div>
        @if ($usesResendSandbox)
            <div class="developer-email-test__notice" role="note">
                <i class="bx bx-info-circle" aria-hidden="true"></i>
                <span><strong>Remitente de prueba de Resend.</strong> Con <code>resend.dev</code> solo puedes enviar al correo propietario de la cuenta. Verifica un dominio para probar otros destinatarios.</span>
            </div>
        @elseif ($usesPlaceholderSender)
            <div class="developer-email-test__notice" role="alert">
                <i class="bx bx-error-circle" aria-hidden="true"></i>
                <span><strong>El remitente todavía es un ejemplo.</strong> Sustituye <code>{{ $mailFrom }}</code> en <code>MAIL_FROM_ADDRESS</code> por una dirección de tu dominio verificado en Resend.</span>
            </div>
        @endif
    </section>

    <div class="developer-console__columns">
        <section class="developer-panel">
            <header class="developer-panel__header">
                <div><span class="developer-panel__icon"><i class="bx bx-bell" aria-hidden="true"></i></span><div><h2>Laboratorio de notificaciones</h2><p>Prueba cada ruta de entrega de forma independiente.</p></div></div>
            </header>
            <div class="developer-test-list">
                <article class="developer-test-item">
                    <div><strong>Fallback Livewire</strong><span>Guarda en MySQL y fuerza la actualización del centro actual.</span></div>
                    <button type="button" wire:click="testLivewireNotification" wire:loading.attr="disabled" class="developer-button developer-button--secondary">Probar Livewire</button>
                </article>
                <article class="developer-test-item">
                    <div><strong>Ciclo Firebase en tiempo real</strong><span>Publica una señal privada y espera que el listener actualice el centro.</span></div>
                    <button type="button" wire:click="testRealtimeNotification" wire:loading.attr="disabled" class="developer-button developer-button--primary">Probar Firebase</button>
                </article>
                <article class="developer-test-item">
                    <div><strong>Autenticación y reglas</strong><span>Valida token personalizado, lectura privada y latencia externa.</span></div>
                    <button type="button" wire:click="runFirebaseProbe" wire:loading.attr="disabled" class="developer-button developer-button--secondary">Ejecutar validación</button>
                </article>
                <article class="developer-test-item">
                    <div><strong>Registro personalizado Pulse</strong><span>Genera un evento técnico identificable como developer_diagnostic.</span></div>
                    <button type="button" wire:click="testPulse" wire:loading.attr="disabled" class="developer-button developer-button--secondary">Enviar a Pulse</button>
                </article>
            </div>
            @if ($firebaseProbe)
                <div class="developer-probe {{ $firebaseProbe['ok'] ? 'is-success' : 'is-error' }}" role="status">
                    <strong><i class="bx {{ $firebaseProbe['ok'] ? 'bx-check-shield' : 'bx-shield-x' }}" aria-hidden="true"></i>{{ $firebaseProbe['ok'] ? 'Firebase validado' : 'Validación fallida' }}</strong>
                    <span>{{ $firebaseProbe['message'] }}</span>
                    <small>
                        {{ $firebaseProbe['latency_ms'] }} ms · {{ $firebaseProbe['checked_at'] }}
                        {{ isset($firebaseProbe['signals']) ? ' · '.$firebaseProbe['signals'].' señales' : '' }}
                    </small>
                </div>
            @endif
        </section>

        <aside class="developer-panel">
            <header class="developer-panel__header">
                <div><span class="developer-panel__icon"><i class="bx bx-cog" aria-hidden="true"></i></span><div><h2>Entorno</h2><p>Datos no sensibles de ejecución.</p></div></div>
            </header>
            <dl class="developer-definition-list">
                <div><dt>Entorno</dt><dd>{{ $diagnostics['application']['environment'] }}</dd></div>
                <div><dt>APP_DEBUG</dt><dd class="{{ $diagnostics['application']['debug'] ? 'text-danger' : '' }}">{{ $diagnostics['application']['debug'] ? 'Activo' : 'Desactivado' }}</dd></div>
                <div><dt>Laravel</dt><dd>{{ $diagnostics['application']['laravel'] }}</dd></div>
                <div><dt>PHP</dt><dd>{{ $diagnostics['application']['php'] }}</dd></div>
                <div><dt>Dominio</dt><dd>{{ $diagnostics['application']['url_host'] }}</dd></div>
                <div><dt>Limpieza RTDB</dt><dd>{{ $diagnostics['firebase']['cleanup'] }}</dd></div>
                <div><dt>Fallos Firebase en Pulse</dt><dd>{{ (int) ($diagnostics['pulse']['firebase_failures'] ?? 0) }}</dd></div>
                <div><dt>Actualizado</dt><dd>{{ $diagnostics['generated_at'] }}</dd></div>
            </dl>
        </aside>
    </div>

    <section class="developer-panel developer-firebase-records">
        <header class="developer-panel__header developer-panel__header--split">
            <div>
                <span class="developer-panel__icon"><i class="bx bx-data" aria-hidden="true"></i></span>
                <div>
                    <h2>Señales pendientes en Firebase</h2>
                    <p>Información efímera que debe eliminar el cron diario del nodo de notificaciones.</p>
                </div>
            </div>
            <div class="developer-firebase-records__actions">
                <button type="button" class="developer-button developer-button--secondary"
                    wire:click="refreshFirebaseNotifications" wire:loading.attr="disabled"
                    wire:target="refreshFirebaseNotifications,clearFirebaseNotifications">
                    <i class="bx bx-refresh" aria-hidden="true"></i>
                    <span wire:loading.remove wire:target="refreshFirebaseNotifications">Actualizar</span>
                    <span wire:loading wire:target="refreshFirebaseNotifications">Consultando…</span>
                </button>
                <button type="button" class="developer-button developer-button--danger"
                    wire:click="confirmClearFirebaseNotifications"
                    wire:loading.attr="disabled" wire:target="confirmClearFirebaseNotifications,clearFirebaseNotifications"
                    @disabled(!($firebaseNotifications['available'] ?? false) || (int) ($firebaseNotifications['total'] ?? 0) === 0)>
                    <i class="bx bx-trash" aria-hidden="true"></i>
                    <span wire:loading.remove wire:target="confirmClearFirebaseNotifications,clearFirebaseNotifications">Eliminar señales</span>
                    <span wire:loading wire:target="clearFirebaseNotifications">Eliminando…</span>
                </button>
            </div>
        </header>

        <dl class="developer-firebase-summary" aria-label="Resumen de Firebase Realtime Database">
            <div><dt>Ruta consultada</dt><dd><code>{{ $firebaseNotifications['root'] ?? 'notifications' }}</code></dd></div>
            <div><dt>Señales pendientes</dt><dd>{{ number_format((int) ($firebaseNotifications['total'] ?? 0)) }}</dd></div>
            <div><dt>Última consulta</dt><dd>{{ $firebaseNotifications['fetched_at'] ?? 'Sin consultar' }}</dd></div>
        </dl>

        @if (!($firebaseNotifications['ok'] ?? false))
            <div class="developer-firebase-records__notice" role="status">
                <i class="bx bx-error-circle" aria-hidden="true"></i>
                <span>{{ $firebaseNotifications['message'] ?? 'No se pudo consultar Firebase.' }}</span>
            </div>
        @endif

        <div class="developer-table-wrap" wire:loading.class="is-loading"
            wire:target="refreshFirebaseNotifications,clearFirebaseNotifications">
            <table class="developer-table developer-firebase-table">
                <thead><tr><th>Evento</th><th>Usuario Firebase</th><th>ID de notificación</th><th>Fecha</th></tr></thead>
                <tbody>
                    @forelse (($firebaseNotifications['signals'] ?? []) as $signal)
                        <tr wire:key="firebase-signal-{{ $signal['user_uid'] }}-{{ $signal['id'] }}">
                            <td><code>{{ $signal['event_key'] }}</code></td>
                            <td>{{ $signal['user_uid'] }}</td>
                            <td><small class="developer-firebase-table__id">{{ $signal['id'] }}</small></td>
                            <td>{{ $signal['created_at'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="developer-table__empty">
                            {{ ($firebaseNotifications['ok'] ?? false) ? 'Firebase no tiene señales pendientes.' : 'La información aparecerá cuando Firebase esté disponible.' }}
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ((int) ($firebaseNotifications['total'] ?? 0) > (int) ($firebaseNotifications['shown'] ?? 0))
            <p class="developer-firebase-records__limit">Se muestran las {{ $firebaseNotifications['shown'] }} señales más recientes de {{ $firebaseNotifications['total'] }}.</p>
        @endif
    </section>

    <section class="developer-panel">
        <header class="developer-panel__header developer-panel__header--split">
            <div><span class="developer-panel__icon"><i class="bx bx-git-branch" aria-hidden="true"></i></span><div><h2>Validación de responsabilidades</h2><p>Referencia rápida para evitar notificaciones dirigidas al rol incorrecto.</p></div></div>
            <span class="developer-panel__counter">{{ count($responsibilityMatrix) }} eventos críticos</span>
        </header>
        <div class="developer-table-wrap">
            <table class="developer-table">
                <thead><tr><th>Evento</th><th>Clave</th><th>Destinatarios esperados</th></tr></thead>
                <tbody>
                    @foreach ($responsibilityMatrix as $row)
                        <tr><td><strong>{{ $row['event'] }}</strong></td><td><code>{{ $row['key'] }}</code></td><td>{{ $row['recipients'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="developer-panel">
        <header class="developer-panel__header developer-panel__header--split">
            <div><span class="developer-panel__icon"><i class="bx bx-history" aria-hidden="true"></i></span><div><h2>Notificaciones recientes</h2><p>Últimos registros persistidos en MySQL.</p></div></div>
            <span class="developer-panel__counter">{{ number_format((int) ($diagnostics['notifications']['total'] ?? 0)) }} totales</span>
        </header>
        <div class="developer-table-wrap">
            <table class="developer-table">
                <thead><tr><th>Evento</th><th>Usuario</th><th>Estado</th><th>Fecha</th></tr></thead>
                <tbody>
                    @forelse ($recentNotifications as $notification)
                        <tr>
                            <td><strong>{{ $notification->data['title'] ?? 'Notificación' }}</strong><small>{{ $notification->event_key }}</small></td>
                            <td>{{ $notification->notifiable?->name ?? 'Usuario eliminado' }}</td>
                            <td><span class="developer-state {{ $notification->announced_at ? 'is-announced' : 'is-pending' }}">{{ $notification->announced_at ? 'Anunciada' : 'Pendiente' }}</span></td>
                            <td>{{ $notification->created_at?->format('d/m/Y H:i:s') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="developer-table__empty">Todavía no existen notificaciones.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
