<?php

namespace App\Livewire\SuperAdmin;

use App\Models\EnvironmentChangeAudit;
use App\Services\EnvironmentConfigurationService;
use DateTimeZone;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

class EnvironmentSettings extends Component
{
    /** @var array<string, string> */
    public array $values = [];

    /** @var array<string, bool> */
    public array $secretConfigured = [];

    /** @var array<string, array<string, mixed>> */
    public array $definitions = [];

    /** @var array<int, string> */
    public array $pendingChangedKeys = [];

    /** @var array<string, mixed>|null */
    public ?array $lastAction = null;

    public bool $acknowledgeRisk = false;

    public bool $writable = false;

    public string $environmentFile = '.env';

    public function mount(EnvironmentConfigurationService $environment): void
    {
        $this->authorizeAccess();
        $this->definitions = $environment->definitions();
        $this->loadSnapshot($environment);
    }

    public function confirmSave(EnvironmentConfigurationService $environment): void
    {
        $this->authorizeAccess();
        $this->resetErrorBag();

        if (! $this->writable) {
            $this->addError('environment', 'PHP no tiene permiso de escritura sobre el archivo de entorno.');

            return;
        }

        if (! $this->acknowledgeRisk) {
            $this->addError('acknowledgeRisk', 'Confirma que revisaste los cambios y tienes un respaldo operativo.');

            return;
        }

        $validated = $environment->validated($this->values);
        $this->pendingChangedKeys = $environment->changedKeys($validated);

        if ($this->pendingChangedKeys === []) {
            $this->lastAction = ['ok' => true, 'message' => 'No hay cambios pendientes por guardar.'];

            return;
        }

        session()->put($this->confirmationSessionKey(), $this->confirmationHash($validated));

        $keys = collect($this->pendingChangedKeys)
            ->map(fn (string $key): string => '<code>'.e($key).'</code>')
            ->implode(', ');

        $this->dispatch(
            'open-confirm',
            type: 'warning',
            title: 'Aplicar configuración técnica',
            message: 'Se modificarán únicamente estas variables: '.$keys.'. Se creará un respaldo privado antes de escribir y los valores secretos no quedarán en el historial.',
            action: 'save-environment-settings',
            confirmText: 'Crear respaldo y aplicar',
            cancelText: 'Seguir revisando',
        );
    }

    #[On('modal-confirmed')]
    public function handleModalConfirmed(string $action): void
    {
        if ($action === 'save-environment-settings') {
            $this->save(app(EnvironmentConfigurationService::class));
        }
    }

    public function save(EnvironmentConfigurationService $environment): void
    {
        $this->authorizeAccess();

        if (! $this->acknowledgeRisk) {
            $this->addError('acknowledgeRisk', 'Confirma que revisaste los cambios antes de aplicarlos.');

            return;
        }

        $rateLimitKey = 'environment-settings:'.auth()->id();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 3)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            $this->addError('environment', "Espera {$seconds} segundos antes de volver a modificar la configuración.");

            return;
        }
        RateLimiter::hit($rateLimitKey, 60);

        try {
            $validated = $environment->validated($this->values);
            $currentChangedKeys = $environment->changedKeys($validated);
            $confirmationHash = (string) session()->pull($this->confirmationSessionKey(), '');

            if ($currentChangedKeys === []
                || $currentChangedKeys !== $this->pendingChangedKeys
                || ! hash_equals($this->confirmationHash($validated), $confirmationHash)) {
                $this->addError('environment', 'La configuración cambió después de la confirmación. Revísala y confirma nuevamente.');
                $this->pendingChangedKeys = [];

                return;
            }

            $result = $environment->update($validated);

            EnvironmentChangeAudit::query()->create([
                'changed_by' => auth()->id(),
                'changed_keys' => $result['changed'],
                'backup_file' => $result['backup'],
                'ip_address' => request()->ip(),
                'user_agent_hash' => request()->userAgent() ? hash('sha256', request()->userAgent()) : null,
            ]);

            Log::notice('Variables de entorno actualizadas desde el módulo de desarrollo.', [
                'user_id' => auth()->id(),
                'changed_keys' => $result['changed'],
            ]);

            $this->lastAction = [
                'ok' => true,
                'message' => count($result['changed']).' variable(s) actualizada(s). Se limpió la caché de configuración.',
            ];
            $this->acknowledgeRisk = false;
            $this->pendingChangedKeys = [];
            $this->loadSnapshot($environment);
        } catch (Throwable $exception) {
            report($exception);
            $this->lastAction = ['ok' => false, 'message' => 'No fue posible guardar la configuración. Revisa permisos y registros del servidor.'];
            $this->addError('environment', $this->lastAction['message']);
        }
    }

    public function render()
    {
        $configuredTimezone = (string) ($this->values['BUSINESS_TIMEZONE'] ?? '');
        $timezones = DateTimeZone::listIdentifiers();
        $clockTimezone = in_array($configuredTimezone, $timezones, true)
            ? $configuredTimezone
            : (string) config('app.business_timezone', 'America/Mexico_City');

        $recentAudits = Schema::hasTable('environment_change_audits')
            ? EnvironmentChangeAudit::query()->with('changedBy:id,name')->latest()->limit(8)->get()
            : collect();

        return view('livewire.super-admin.environment-settings', compact('clockTimezone', 'timezones', 'recentAudits'))
            ->layout('layouts.app');
    }

    private function authorizeAccess(): void
    {
        $user = auth()->user();
        abort_unless(
            $user?->hasAnyRole(['owner', 'super-admin']) && $user->can('gestionar variables de entorno'),
            403,
        );
        $confirmedAt = (int) session()->get('auth.password_confirmed_at', 0);
        abort_if(now()->timestamp - $confirmedAt > (int) config('auth.password_timeout', 10800), 423, 'Vuelve a confirmar tu contraseña.');
    }

    private function loadSnapshot(EnvironmentConfigurationService $environment): void
    {
        $snapshot = $environment->snapshot();
        $this->values = $snapshot['values'];
        $this->secretConfigured = $snapshot['secrets'];
        $this->writable = $snapshot['writable'];
        $this->environmentFile = $snapshot['path'];
    }

    private function confirmationSessionKey(): string
    {
        return 'environment-change-confirmation.'.auth()->id();
    }

    /** @param array<string, string> $validated */
    private function confirmationHash(array $validated): string
    {
        return hash_hmac('sha256', json_encode($validated, JSON_THROW_ON_ERROR), (string) config('app.key'));
    }
}
