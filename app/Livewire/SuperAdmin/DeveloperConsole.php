<?php

namespace App\Livewire\SuperAdmin;

use App\Models\AppNotification;
use App\Services\DeveloperDiagnosticsService;
use Livewire\Component;

class DeveloperConsole extends Component
{
    /** @var array<string, mixed> */
    public array $diagnostics = [];

    /** @var array<string, mixed>|null */
    public ?array $firebaseProbe = null;

    /** @var array<string, mixed>|null */
    public ?array $lastAction = null;

    public function mount(DeveloperDiagnosticsService $diagnostics): void
    {
        abort_unless(auth()->user()?->can('ver panel super admin'), 403);
        $this->diagnostics = $diagnostics->snapshot();
    }

    public function refreshDiagnostics(DeveloperDiagnosticsService $diagnostics): void
    {
        $this->authorizeDiagnostics();
        $this->diagnostics = $diagnostics->snapshot();
        $this->lastAction = ['ok' => true, 'message' => 'Diagnóstico actualizado sin modificar la operación.'];
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

    public function testRealtimeNotification(DeveloperDiagnosticsService $diagnostics): void
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
    }

    public function testPulse(DeveloperDiagnosticsService $diagnostics): void
    {
        $this->authorizeDiagnostics();
        $diagnostics->recordPulseTest(auth()->user());
        $this->lastAction = ['ok' => true, 'message' => 'Evento developer_diagnostic enviado a Laravel Pulse.'];
        $this->diagnostics = $diagnostics->snapshot();
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
