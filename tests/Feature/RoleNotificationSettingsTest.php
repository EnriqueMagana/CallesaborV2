<?php

namespace Tests\Feature;

use App\Livewire\Admin\RoleNotificationManager;
use App\Livewire\Admin\RolePermissionManager;
use App\Livewire\Profile\NotificationPreferencesForm;
use App\Models\NotificationPreference;
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
        RoleNotificationSetting::create(['role_id' => $role->id, 'event_keys' => ['counter.order_created']]);

        $actor = User::factory()->create();
        $actor->assignRole('cocinero');
        $this->actingAs($actor);
        $order = new Order(['type' => 'ventanilla', 'status' => 'pendiente', 'subtotal' => 90, 'total' => 90]);
        $order->id = 901;

        app(OperationalNotificationService::class)->orderCreated($order);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $recipient->id,
            'event_key' => 'counter.order_created',
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
            'event_key' => 'counter.order_created',
        ]);
    }

    public function test_role_manager_rejects_an_event_when_the_role_lacks_required_permissions(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $role = Role::create(['name' => 'consulta-basica', 'guard_name' => 'web']);

        Livewire::actingAs($owner)
            ->test(RoleNotificationManager::class)
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
            ->test(RoleNotificationManager::class)
            ->call('selectNotificationRole', $role->id)
            ->assertSee('Capitan De Piso')
            ->assertSee('Nuevo pedido de mesa')
            ->assertSee('Nuevo pedido de ventanilla')
            ->assertSee('Nuevo pedido en espera para tomar (delivery)')
            ->assertSee('Solicitud de cancelación');
    }

    public function test_role_notification_module_opens_the_notification_editor_directly(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $this->actingAs($owner)
            ->get(route('app.notificaciones-roles'))
            ->assertOk()
            ->assertSee('Notificaciones por rol')
            ->assertSee('Administración · Comunicaciones')
            ->assertSee('Roles del sistema');

        $unauthorized = User::factory()->create();

        $this->actingAs($unauthorized)
            ->get(route('app.notificaciones-roles'))
            ->assertForbidden();
    }

    public function test_role_and_permission_module_no_longer_contains_notification_configuration(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        Livewire::actingAs($owner)
            ->test(RolePermissionManager::class)
            ->assertDontSee('Avisos por responsabilidad')
            ->assertDontSee('Guardar notificaciones');
    }

    public function test_role_can_receive_table_ready_without_receiving_counter_ready(): void
    {
        $role = Role::create(['name' => 'capitan-mesas', 'guard_name' => 'web']);
        $role->givePermissionTo('ver mesas');
        $recipient = User::factory()->create();
        $recipient->assignRole($role);
        RoleNotificationSetting::create([
            'role_id' => $role->id,
            'event_keys' => ['table.order_ready'],
        ]);

        $actor = User::factory()->create();
        $actor->assignRole('cocinero');
        $this->actingAs($actor);

        $tableOrder = new Order([
            'served_by' => $recipient->id,
            'type' => 'mesa',
            'status' => 'lista',
            'subtotal' => 80,
            'total' => 80,
        ]);
        $tableOrder->id = 903;
        app(OperationalNotificationService::class)->orderStatusChanged($tableOrder, 'en_preparacion');

        $counterOrder = new Order([
            'type' => 'ventanilla',
            'status' => 'lista',
            'subtotal' => 90,
            'total' => 90,
        ]);
        $counterOrder->id = 904;
        app(OperationalNotificationService::class)->orderStatusChanged($counterOrder, 'en_preparacion');

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $recipient->id,
            'event_key' => 'table.order_ready',
            'subject_id' => 903,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $recipient->id,
            'event_key' => 'counter.order_ready',
            'subject_id' => 904,
        ]);
    }

    public function test_notification_catalog_exposes_events_according_to_dynamic_role_permissions(): void
    {
        $tableRole = Role::create(['name' => 'monitor-mesas', 'guard_name' => 'web']);
        $tableRole->givePermissionTo('ver mesas');
        $tableUser = User::factory()->create();
        $tableUser->assignRole($tableRole);

        Livewire::actingAs($tableUser)
            ->test(NotificationPreferencesForm::class)
            ->assertSee('Nuevo pedido de mesa')
            ->assertSee('Pedido de mesa listo')
            ->assertDontSee('Nuevo pedido de ventanilla')
            ->assertDontSee('Nuevo pedido en espera para tomar (delivery)');

        $deliveryRole = Role::create(['name' => 'monitor-delivery', 'guard_name' => 'web']);
        $deliveryRole->givePermissionTo('ver delivery');
        $deliveryUser = User::factory()->create();
        $deliveryUser->assignRole($deliveryRole);

        Livewire::actingAs($deliveryUser)
            ->test(NotificationPreferencesForm::class)
            ->assertSee('Nuevo pedido de delivery')
            ->assertSee('Nuevo pedido en espera para tomar (delivery)')
            ->assertDontSee('Pedido de mesa listo');
    }

    public function test_legacy_general_preferences_are_expanded_without_losing_their_value(): void
    {
        $role = Role::create(['name' => 'rol-legado', 'guard_name' => 'web']);
        $setting = RoleNotificationSetting::create([
            'role_id' => $role->id,
            'event_keys' => ['order.created', 'order.ready', 'order.cancelled'],
        ]);
        $user = User::factory()->create();
        $preference = NotificationPreference::create([
            'user_id' => $user->id,
            'event_preferences' => [
                'order_created' => false,
                'order_ready' => true,
                'order_cancelled' => true,
            ],
        ]);

        $migration = require database_path('migrations/2026_09_02_000003_expand_role_notification_event_keys.php');
        $migration->up();

        $events = $setting->fresh()->event_keys;
        $this->assertNotContains('order.created', $events);
        $this->assertNotContains('order.ready', $events);
        $this->assertContains('table.order_created', $events);
        $this->assertContains('counter.order_created', $events);
        $this->assertContains('table.order_ready', $events);
        $this->assertContains('kiosk.order_ready', $events);
        $this->assertContains('order.cancelled', $events);

        $savedPreferences = $preference->fresh()->event_preferences;
        $this->assertArrayNotHasKey('order_created', $savedPreferences);
        $this->assertArrayNotHasKey('order_ready', $savedPreferences);
        $this->assertFalse($savedPreferences['table_order_created']);
        $this->assertFalse($savedPreferences['delivery_order_created']);
        $this->assertTrue($savedPreferences['table_order_ready']);
        $this->assertTrue($savedPreferences['delivery_order_ready']);
    }
}
