<?php

namespace Tests\Feature;

use App\Livewire\Admin\RolePermissionManager;
use App\Models\Order;
use App\Models\RoleNotificationSetting;
use App\Models\User;
use App\Services\OperationalNotificationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleNotificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_dynamic_role_can_be_configured_to_receive_a_compatible_event(): void
    {
        $role = Role::create(['name' => 'supervisor-nocturno', 'guard_name' => 'web']);
        $role->givePermissionTo('ver ordenes');
        $recipient = User::factory()->create();
        $recipient->assignRole($role);
        RoleNotificationSetting::create(['role_id' => $role->id, 'event_keys' => ['order.created']]);

        $actor = User::factory()->create();
        $actor->assignRole('cocinero');
        $this->actingAs($actor);
        $order = new Order(['type' => 'ventanilla', 'status' => 'pendiente', 'subtotal' => 90, 'total' => 90]);
        $order->id = 901;

        app(OperationalNotificationService::class)->orderCreated($order);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $recipient->id,
            'event_key' => 'order.created',
        ]);
    }

    public function test_explicit_empty_role_configuration_disables_legacy_notifications_for_that_role(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        RoleNotificationSetting::create([
            'role_id' => Role::findByName('owner')->id,
            'event_keys' => [],
        ]);

        $actor = User::factory()->create();
        $actor->assignRole('cocinero');
        $this->actingAs($actor);
        $order = new Order(['type' => 'ventanilla', 'status' => 'pendiente', 'subtotal' => 90, 'total' => 90]);
        $order->id = 902;

        app(OperationalNotificationService::class)->orderCreated($order);

        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $owner->id,
            'event_key' => 'order.created',
        ]);
    }

    public function test_role_manager_rejects_an_event_when_the_role_lacks_required_permissions(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $role = Role::create(['name' => 'consulta-basica', 'guard_name' => 'web']);

        Livewire::actingAs($owner)
            ->test(RolePermissionManager::class)
            ->call('selectNotificationRole', $role->id)
            ->set('roleNotificationEvents', ['delivery.available'])
            ->call('saveRoleNotifications')
            ->assertHasErrors(['roleNotificationEvents']);

        $this->assertDatabaseMissing('role_notification_settings', ['role_id' => $role->id]);
    }

    public function test_notification_tab_lists_roles_created_dynamically(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $role = Role::create(['name' => 'capitan-de-piso', 'guard_name' => 'web']);

        Livewire::actingAs($owner)
            ->test(RolePermissionManager::class)
            ->set('activeTab', 'notifications')
            ->call('selectNotificationRole', $role->id)
            ->assertSee('Capitan De Piso')
            ->assertSee('Pedido nuevo')
            ->assertSee('Solicitud de cancelación');
    }
}
