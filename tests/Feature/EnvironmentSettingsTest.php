<?php

namespace Tests\Feature;

use App\Livewire\SuperAdmin\EnvironmentSettings;
use App\Models\EnvironmentChangeAudit;
use App\Models\SidebarMenuItem;
use App\Models\User;
use App\Services\EnvironmentConfigurationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SidebarMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EnvironmentSettingsTest extends TestCase
{
    use RefreshDatabase;

    private string $temporaryDirectory;

    private string $environmentPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SidebarMenuSeeder::class);

        $this->temporaryDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'callesabor-env-'.bin2hex(random_bytes(5));
        File::ensureDirectoryExists($this->temporaryDirectory);
        $this->environmentPath = $this->temporaryDirectory.DIRECTORY_SEPARATOR.'.env';
        File::put($this->environmentPath, implode(PHP_EOL, [
            'APP_NAME="Calle sabor"',
            'APP_ENV=local',
            'APP_DEBUG=false',
            'APP_URL="http://localhost"',
            'APP_KEY="base64:key-that-must-never-change"',
            'BUSINESS_TIMEZONE="America/Mexico_City"',
            'APP_LOCALE=es',
            'APP_FALLBACK_LOCALE=es',
            'DB_CONNECTION=mysql',
            'DB_PASSWORD="super-secret-value"',
            '',
        ]));

        $this->app->instance(EnvironmentConfigurationService::class, $this->service());
    }

    protected function tearDown(): void
    {
        if (isset($this->temporaryDirectory) && File::isDirectory($this->temporaryDirectory)) {
            File::deleteDirectory($this->temporaryDirectory);
        }

        parent::tearDown();
    }

    public function test_environment_service_masks_secrets_and_preserves_unexposed_variables(): void
    {
        $service = $this->service();
        $snapshot = $service->snapshot();

        $this->assertSame('', $snapshot['values']['DB_PASSWORD']);
        $this->assertTrue($snapshot['secrets']['DB_PASSWORD']);
        $this->assertArrayNotHasKey('APP_KEY', $snapshot['values']);

        $validated = $service->validated(array_replace($snapshot['values'], [
            'APP_NAME' => 'Calle Sabor Producción',
            'DB_PASSWORD' => '',
        ]));
        $result = $service->update($validated);
        $content = File::get($this->environmentPath);

        $this->assertContains('APP_NAME', $result['changed']);
        $this->assertStringContainsString('APP_KEY="base64:key-that-must-never-change"', $content);
        $this->assertStringContainsString('DB_PASSWORD="super-secret-value"', $content);
        $this->assertNotNull($result['backup']);
        $this->assertFileExists($this->temporaryDirectory.DIRECTORY_SEPARATOR.'backups'.DIRECTORY_SEPARATOR.$result['backup']);
    }

    public function test_secret_with_dollar_signs_is_written_without_losing_characters(): void
    {
        $service = $this->service();
        $values = $service->snapshot()['values'];
        $values['DB_PASSWORD'] = 'pa$$word$2026';

        $service->update($service->validated($values));

        $this->assertSame('pa$$word$2026', $service->snapshot()['secrets']['DB_PASSWORD'] ? $this->readEnvValue('DB_PASSWORD') : null);
    }

    public function test_environment_route_requires_super_admin_permission_and_recent_password_confirmation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $customDeveloper = User::factory()->create();
        $customDeveloper->givePermissionTo(Permission::findByName('gestionar variables de entorno'));
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->get(route('app.super-admin.environment'))
            ->assertForbidden();

        $this->actingAs($customDeveloper)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->get(route('app.super-admin.environment'))
            ->assertForbidden();

        session()->forget('auth.password_confirmed_at');
        $this->actingAs($superAdmin)
            ->get(route('app.super-admin.environment'))
            ->assertRedirect(route('password.confirm'));

        $this->actingAs($superAdmin)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->get(route('app.super-admin.environment'))
            ->assertOk()
            ->assertSee('Variables de entorno')
            ->assertDontSee('super-secret-value');
    }

    public function test_confirmed_change_creates_backup_and_audit_without_secret_values(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        RateLimiter::clear('environment-settings:'.$user->id);
        $this->withSession(['auth.password_confirmed_at' => now()->timestamp]);

        $component = Livewire::actingAs($user)
            ->test(EnvironmentSettings::class)
            ->set('values.APP_NAME', 'Nuevo nombre')
            ->set('acknowledgeRisk', true)
            ->call('confirmSave')
            ->assertDispatched('open-confirm')
            ->call('handleModalConfirmed', 'save-environment-settings')
            ->assertHasNoErrors()
            ->assertSet('lastAction.ok', true);

        $component->assertDontSee('super-secret-value');
        $audit = EnvironmentChangeAudit::query()->sole();
        $this->assertSame(['APP_NAME'], $audit->changed_keys);
        $this->assertSame($user->id, $audit->changed_by);
        $this->assertStringNotContainsString('Nuevo nombre', json_encode($audit->toArray(), JSON_THROW_ON_ERROR));
    }

    public function test_sidebar_registers_the_separate_environment_module(): void
    {
        $item = SidebarMenuItem::query()->where('system_key', 'development.environment')->firstOrFail();

        $this->assertSame('app.super-admin.environment', $item->route_name);
        $this->assertSame('gestionar variables de entorno', $item->permission);
        $this->assertSame(20, $item->sort_order);
    }

    private function service(): EnvironmentConfigurationService
    {
        return new EnvironmentConfigurationService(
            $this->environmentPath,
            $this->temporaryDirectory.DIRECTORY_SEPARATOR.'backups',
        );
    }

    private function readEnvValue(string $key): ?string
    {
        $line = collect(preg_split('/\R/', File::get($this->environmentPath)) ?: [])
            ->first(fn (string $line): bool => str_starts_with($line, $key.'='));

        if (! $line) {
            return null;
        }

        $value = trim(substr($line, strlen($key) + 1), '"');

        return str_replace('\\$', '$', $value);
    }
}
