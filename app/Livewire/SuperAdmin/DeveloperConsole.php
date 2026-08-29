<?php

namespace App\Livewire\SuperAdmin;

use App\Mail\DeveloperTestMail;
use App\Models\AppNotification;
use App\Models\DeliveryModuleAudit;
use App\Services\DeveloperDiagnosticsService;
use App\Services\DeliveryModuleManager;
use App\Services\DeliveryModulePolicy;
use App\Services\Firebase\FirebaseRealtimeDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

class DeveloperConsole extends Component
{
    /** @var array<string, mixed> */
    public array $diagnostics = [];

    /** @var array<string, mixed>|null */
    public ?array $firebaseProbe = null;

    /** @var array<string, mixed>|null */
    public ?array $lastAction = null;

    /** @var array<string, mixed> */
    public array $firebaseNotifications = [];

    public string $testEmailRecipient = '';

    public function mount(DeveloperDiagnosticsService $diagnostics, FirebaseRealtimeDatabase $firebase): void
    {
        abort_unless(auth()->user()?->can('ver panel super admin'), 403);
        $this->diagnostics = $diagnostics->snapshot();
        $this->firebaseNotifications = $firebase->notificationSignals();
    }

    public function refreshDiagnostics(DeveloperDiagnosticsService $diagnostics): void
    {
        $this->authorizeDiagnostics();
        $this->diagnostics = $diagnostics->snapshot();
        $this->lastAction = ['ok' => true, 'message' => 'Diagnóstico actualizado con éxito.'];
    }

    public function runFirebaseProbe(DeveloperDiagnosticsService $diagnostics): void
    {
        $this->authorizeDiagnostics();
        $this->firebaseProbe = $diagnostics->probeFirebase(auth()->user());
        $this->diagnostics = $diagnostics->snapshot();
    }

    public function testLivewireNotification(DeveloperDiagnosticsService $diagnostics): void
    {
        $this->authorizeNotificationTests();
        $result = $diagnostics->createTestNotification(auth()->user(), false);
        $this->dispatch('notifications-check');
        $this->lastAction = [
            'ok' => true,
            'message' => 'Notificación guardada en MySQL y enviada al centro mediante Livewire.',
            'id' => $result['notification']->getKey(),
        ];
        $this->diagnostics = $diagnostics->snapshot();
    }

    public function testRealtimeNotification(
        DeveloperDiagnosticsService $diagnostics,
        FirebaseRealtimeDatabase $firebase,
    ): void
    {
        $this->authorizeNotificationTests();
        $result = $diagnostics->createTestNotification(auth()->user(), true);
        $this->lastAction = [
            'ok' => $result['realtime'],
            'message' => $result['realtime']
                ? 'Se publicó la señal. El centro debe actualizarse mediante Firebase, sin intervención manual.'
                : 'La notificación quedó en MySQL, pero Firebase falló. El fallback Livewire permanece disponible.',
            'id' => $result['notification']->getKey(),
        ];
        $this->diagnostics = $diagnostics->snapshot();
        $this->firebaseNotifications = $firebase->notificationSignals();
    }

    public function refreshFirebaseNotifications(FirebaseRealtimeDatabase $firebase): void
    {
        $this->authorizeDiagnostics();
        $this->firebaseNotifications = $firebase->notificationSignals();
        $this->lastAction = [
            'ok' => (bool) ($this->firebaseNotifications['ok'] ?? false),
            'message' => (string) ($this->firebaseNotifications['message'] ?? 'Consulta de Firebase completada.'),
        ];
    }

    public function clearFirebaseNotifications(FirebaseRealtimeDatabase $firebase): void
    {
        $this->authorizeDiagnostics();
        $cleared = $firebase->clearNotifications('manual_cleanup');
        $this->firebaseNotifications = $firebase->notificationSignals();
        $this->lastAction = [
            'ok' => $cleared,
            'message' => $cleared
                ? 'Las señales pendientes de Firebase fueron eliminadas manualmente.'
                : 'No se pudo limpiar Firebase. Revisa la configuración, el registro y Laravel Pulse.',
        ];
    }

    public function confirmClearFirebaseNotifications(): void
    {
        $this->authorizeDiagnostics();

        if ((int) ($this->firebaseNotifications['total'] ?? 0) === 0) {
            return;
        }

        $this->dispatch(
            'open-confirm',
            type: 'danger',
            title: 'Limpiar señales de Firebase',
            message: 'Se eliminarán todas las señales efímeras del nodo <strong>'.e((string) ($this->firebaseNotifications['root'] ?? 'notifications')).'</strong>. Las notificaciones históricas de MySQL no serán afectadas.',
            action: 'clearFirebaseNotifications',
            confirmText: 'Eliminar señales',
            cancelText: 'Conservar datos',
        );
    }

    #[Computed]
    public function deliveryModuleState(): array
    {
        return [
            'enabled' => app(DeliveryModulePolicy::class)->enabled(),
            'impact' => app(DeliveryModuleManager::class)->impact(),
            'last_change' => DeliveryModuleAudit::query()
                ->with('changedBy:id,name')
                ->latest('changed_at')
                ->first(),
        ];
    }

    public function confirmToggleDeliveryModule(): void
    {
        $this->authorizeDiagnostics();
        $state = $this->deliveryModuleState;
        $enable = ! $state['enabled'];
        $impact = $state['impact'];

        if (! $enable && ($impact['assigned_orders'] > 0 || $impact['in_route_orders'] > 0)) {
            $this->addError(
                'deliveryModule',
                "Resuelve primero {$impact['assigned_orders']} pedido(s) asignado(s) y {$impact['in_route_orders']} en ruta.",
            );

            return;
        }

        $this->resetErrorBag('deliveryModule');
        $message = $enable
            ? 'Se habilitarán la asignación, el seguimiento y los mini cortes de repartidores para los pedidos nuevos.'
            : "Se ocultará el módulo y {$impact['unassigned_orders']} pedido(s) sin asignar pasarán a gestión manual. Su efectivo se incluirá en el corte global.";

        $this->dispatch(
            'open-confirm',
            type: $enable ? 'warning' : 'danger',
            title: $enable ? 'Activar gestión de Delivery' : 'Desactivar gestión de Delivery',
            message: $message,
            action: 'toggle-delivery-module',
            params: ['enabled' => $enable],
            confirmText: $enable ? 'Activar módulo' : 'Usar gestión manual',
            cancelText: 'Cancelar',
        );
    }

    #[On('modal-confirmed')]
    public function handleModalConfirmed(string $action, array $params = []): void
    {
        if ($action === 'clearFirebaseNotifications') {
            $this->clearFirebaseNotifications(app(FirebaseRealtimeDatabase::class));

            return;
        }

        if ($action === 'toggle-delivery-module') {
            $this->toggleDeliveryModule((bool) ($params['enabled'] ?? false));
        }
    }

    public function toggleDeliveryModule(bool $enabled): void
    {
        $this->authorizeDiagnostics();

        try {
            $result = app(DeliveryModuleManager::class)->setEnabled($enabled, auth()->user());
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->addError('deliveryModule', $exception->validator->errors()->first('deliveryModule'));

            return;
        }

        unset($this->deliveryModuleState);
        $converted = (int) $result['converted_orders'];
        $this->lastAction = [
            'ok' => true,
            'message' => $enabled
                ? 'Delivery administrado fue activado para los pedidos nuevos.'
                : "Delivery manual activado. {$converted} pedido(s) se incorporaron al corte global.",
        ];
        $this->dispatch('sidebar-menu-updated');
    }

    public function testPulse(DeveloperDiagnosticsService $diagnostics): void
    {
        $this->authorizeDiagnostics();
        $diagnostics->recordPulseTest(auth()->user());
        $this->lastAction = ['ok' => true, 'message' => 'Evento developer_diagnostic enviado a Laravel Pulse.'];
        $this->diagnostics = $diagnostics->snapshot();
    }

    public function sendTestEmail(): void
    {
        $this->authorizeDiagnostics();

        $validated = $this->validate([
            'testEmailRecipient' => ['required', 'string', 'email:rfc', 'max:254'],
        ], [
            'testEmailRecipient.required' => 'Escribe el correo que recibirá la prueba.',
            'testEmailRecipient.email' => 'Escribe una dirección de correo válida.',
            'testEmailRecipient.max' => 'El correo no puede superar 254 caracteres.',
        ]);

        if (config('mail.default') !== 'resend') {
            $this->emailConfigurationError('El transportador activo no es Resend. Configura MAIL_MAILER=resend.');

            return;
        }

        if (blank(config('services.resend.key'))) {
            $this->emailConfigurationError('RESEND_API_KEY no está configurada en el entorno.');

            return;
        }

        if (str_ends_with(strtolower((string) config('mail.from.address')), '@example.com')) {
            $this->emailConfigurationError('MAIL_FROM_ADDRESS todavía usa example.com. Configura un remitente autorizado por Resend.');

            return;
        }

        $rateLimitKey = 'developer-email-test:'.auth()->id();

        if (RateLimiter::tooManyAttempts($rateLimitKey, 3)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            $this->addError('testEmailRecipient', "Alcanzaste el límite de pruebas. Intenta de nuevo en {$seconds} segundos.");
            $this->lastAction = ['ok' => false, 'message' => 'La prueba no se envió por el límite temporal de seguridad.'];

            return;
        }

        RateLimiter::hit($rateLimitKey, 60);

        try {
            Mail::to($validated['testEmailRecipient'])->send(new DeveloperTestMail(
                testerName: auth()->user()?->name ?? 'Super Admin',
                sentAt: now()->format('d/m/Y H:i:s T'),
            ));

            $this->resetErrorBag('testEmailRecipient');
            $this->lastAction = [
                'ok' => true,
                'message' => "Correo de prueba enviado a {$validated['testEmailRecipient']}.",
            ];
        } catch (Throwable $exception) {
            Log::warning('Falló el correo de prueba del Centro técnico.', [
                'recipient' => $validated['testEmailRecipient'],
                'mailer' => config('mail.default'),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            $this->addError('testEmailRecipient', 'Resend rechazó o no pudo completar el envío. Revisa el remitente, el dominio y el registro de Laravel.');
            $this->lastAction = ['ok' => false, 'message' => 'No se pudo enviar el correo de prueba.'];
        }
    }

    private function emailConfigurationError(string $message): void
    {
        $this->addError('testEmailRecipient', $message);
        $this->lastAction = ['ok' => false, 'message' => $message];
    }

    private function authorizeDiagnostics(): void
    {
        abort_unless(auth()->user()?->can('ejecutar diagnosticos'), 403);
    }

    private function authorizeNotificationTests(): void
    {
        abort_unless(auth()->user()?->can('probar notificaciones'), 403);
    }

    public function render()
    {
        return view('livewire.super-admin.developer-console', [
            'recentNotifications' => AppNotification::query()
                ->with('notifiable:id,name')
                ->latest()
                ->limit(10)
                ->get(),
            'responsibilityMatrix' => [
                ['event' => 'Pedido nuevo', 'key' => 'order.created', 'recipients' => 'Supervisión, cocina, caja y mesero responsable'],
                ['event' => 'Pedido de mesa listo', 'key' => 'order.ready', 'recipients' => 'Mesero responsable y supervisión'],
                ['event' => 'Delivery disponible', 'key' => 'delivery.available', 'recipients' => 'Repartidor asignado, caja y supervisión'],
                ['event' => 'Cancelación', 'key' => 'order.cancelled', 'recipients' => 'Responsables operativos y supervisión'],
            ],
        ])->layout('layouts.app');
    }
}
