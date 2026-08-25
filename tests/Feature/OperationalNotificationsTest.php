<?php

namespace Tests\Feature;

use App\Livewire\Delivery\DeliveryBoard;
use App\Livewire\Layout\NotificationCenter;
use App\Livewire\Profile\NotificationPreferencesForm;
use App\Models\AppNotification;
use App\Models\Area;
use App\Models\CashRegister;
use App\Models\Mesa;
use App\Models\NotificationPreference;
use App\Models\Order;
use App\Models\User;
use App\Services\OperationalNotificationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class OperationalNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_table_notifications_follow_operational_responsibilities(): void
    {
        $owner = $this->userWithRole('owner');
        $waiter = $this->userWithRole('mesero');
        $cook = $this->userWithRole('cocinero');
        $driver = $this->userWithRole('repartidor');
        $this->actingAs($cook);

        $order = new Order([
            'served_by' => $waiter->id,
            'type' => 'mesa',
            'status' => 'pendiente',
            'subtotal' => 180,
            'total' => 180,
        ]);
        $order->id = 501;
        $order->folio = 17;

        app(OperationalNotificationService::class)->orderCreated($order);

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $owner->id, 'event_key' => 'order.created', 'category' => 'tables']);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $waiter->id, 'event_key' => 'order.created', 'category' => 'tables']);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $driver->id, 'event_key' => 'order.created']);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $cook->id, 'event_key' => 'order.created']);
    }

    public function test_driver_is_not_notified_until_delivery_is_ready(): void
    {
        $cook = $this->userWithRole('cocinero');
        $driver = $this->userWithRole('repartidor');
        $this->actingAs($cook);

        $order = new Order([
            'served_by' => $cook->id,
            'type' => 'delivery',
            'status' => 'pendiente',
            'subtotal' => 220,
            'total' => 220,
        ]);
        $order->id = 502;
        $order->folio = 18;

        app(OperationalNotificationService::class)->orderCreated($order);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $driver->id, 'event_key' => 'order.created']);

        $order->status = 'lista';
        app(OperationalNotificationService::class)->orderStatusChanged($order, 'en_preparacion');

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $driver->id,
            'event_key' => 'delivery.available',
            'category' => 'delivery',
        ]);

        $notification = $driver->fresh()->notifications()->where('event_key', 'delivery.available')->firstOrFail();
        $this->assertSame('/app/delivery?order=502', $notification->data['url']);
    }

    public function test_duplicate_operational_events_are_ignored(): void
    {
        $owner = $this->userWithRole('owner');
        $cook = $this->userWithRole('cocinero');
        $this->actingAs($cook);
        $order = new Order(['type' => 'ventanilla', 'status' => 'pendiente', 'subtotal' => 90, 'total' => 90]);
        $order->id = 503;

        $service = app(OperationalNotificationService::class);
        $service->orderCreated($order);
        $service->orderCreated($order);

        $this->assertSame(1, $owner->fresh()->notifications()->where('event_key', 'order.created')->count());
    }

    public function test_user_can_read_notifications_from_the_center(): void
    {
        $owner = $this->userWithRole('owner');
        $cook = $this->userWithRole('cocinero');
        $this->actingAs($cook);
        $order = new Order(['type' => 'ventanilla', 'status' => 'pendiente', 'subtotal' => 90, 'total' => 90]);
        $order->id = 504;
        app(OperationalNotificationService::class)->orderCreated($order);
        $notification = $owner->fresh()->notifications()->firstOrFail();

        $this->actingAs($owner);
        Livewire::test(NotificationCenter::class)
            ->assertSet('soundEnabled', true)
            ->call('markRead', $notification->id)
            ->assertSet('open', false);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_notification_center_has_event_icons_without_category_filters(): void
    {
        $waiter = $this->userWithRole('mesero');
        $notification = $this->notificationFor($waiter, 'order.ready', 'tables');

        $this->actingAs($waiter);
        Livewire::test(NotificationCenter::class)
            ->set('open', true)
            ->assertSeeHtml('bx bx-check-circle')
            ->assertSee('Eliminar todas las notificaciones')
            ->assertDontSee('Filtrar notificaciones');

        $this->assertSame('ready', $notification->fresh()->tone);
    }

    public function test_clear_all_permanently_deletes_only_the_authenticated_users_notifications(): void
    {
        $owner = $this->userWithRole('owner');
        $waiter = $this->userWithRole('mesero');
        $ownerNotification = $this->notificationFor($owner, 'order.created', 'orders');
        $waiterNotification = $this->notificationFor($waiter, 'order.ready', 'tables');

        $this->actingAs($owner);
        Livewire::test(NotificationCenter::class)->call('clearAll');

        $this->assertDatabaseMissing('notifications', ['id' => $ownerNotification->id]);
        $this->assertDatabaseHas('notifications', ['id' => $waiterNotification->id]);
    }

    public function test_table_notification_opens_the_exact_table(): void
    {
        $waiter = $this->userWithRole('mesero');
        $register = CashRegister::query()->create([
            'name' => 'Turno prueba',
            'opened_by' => $waiter->id,
            'opened_at' => now(),
            'is_open' => true,
        ]);
        $area = Area::query()->create(['name' => 'Salón']);
        $mesa = Mesa::query()->create(['area_id' => $area->id, 'number' => 7, 'status' => 'ocupada']);
        $order = Order::query()->create([
            'cash_register_id' => $register->id,
            'mesa_id' => $mesa->id,
            'served_by' => $waiter->id,
            'type' => 'mesa',
            'status' => 'lista',
            'subtotal' => 180,
            'total' => 180,
        ]);
        $notification = $this->notificationFor($waiter, 'order.ready', 'tables', $order);

        $this->actingAs($waiter);
        Livewire::test(NotificationCenter::class)
            ->call('openNotification', $notification->id)
            ->assertRedirect(route('app.mesas.ordenes', $mesa));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_delivery_notification_focuses_the_exact_order_card(): void
    {
        $driver = $this->userWithRole('repartidor');
        $register = CashRegister::query()->create([
            'name' => 'Turno delivery',
            'opened_by' => $driver->id,
            'opened_at' => now(),
            'is_open' => true,
        ]);
        $order = Order::query()->create([
            'cash_register_id' => $register->id,
            'served_by' => $driver->id,
            'customer_name' => 'Cliente delivery',
            'customer_address' => 'Calle 10',
            'type' => 'delivery',
            'status' => 'lista',
            'subtotal' => 220,
            'total' => 220,
        ]);

        $this->actingAs($driver);
        Livewire::withQueryParams(['order' => $order->id])
            ->test(DeliveryBoard::class)
            ->assertSet('highlightOrderId', $order->id)
            ->assertSet('tab', 'available')
            ->assertSeeHtml('id="delivery-order-'.$order->id.'"')
            ->assertSeeHtml('is-highlighted');
    }

    public function test_each_user_can_disable_operational_notifications(): void
    {
        $owner = $this->userWithRole('owner');
        $cook = $this->userWithRole('cocinero');
        $this->actingAs($cook);
        NotificationPreference::query()->create([
            'user_id' => $owner->id,
            'notifications_enabled' => false,
        ]);
        $order = new Order(['type' => 'ventanilla', 'status' => 'pendiente', 'subtotal' => 90, 'total' => 90]);
        $order->id = 505;

        app(OperationalNotificationService::class)->orderCreated($order);

        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $owner->id]);
    }

    public function test_profile_preferences_only_show_events_relevant_to_the_user(): void
    {
        $waiter = $this->userWithRole('mesero');
        $this->actingAs($waiter);

        Livewire::test(NotificationPreferencesForm::class)
            ->assertSee('Pedidos listos')
            ->assertDontSee('Delivery disponible')
            ->set('soundEnabled', false)
            ->set('volume', 35)
            ->set('quietHoursEnabled', true)
            ->set('quietHoursStart', '21:30')
            ->set('quietHoursEnd', '08:00')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $waiter->id,
            'sound_enabled' => false,
            'volume' => 35,
            'quiet_hours_enabled' => true,
            'quiet_hours_start' => '21:30',
            'quiet_hours_end' => '08:00',
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function notificationFor(User $user, string $eventKey, string $category, ?Order $order = null): AppNotification
    {
        return AppNotification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'event_key' => $eventKey,
            'category' => $category,
            'priority' => 'normal',
            'subject_type' => $order ? Order::class : null,
            'subject_id' => $order?->id,
            'dedupe_key' => 'test:'.Str::uuid(),
            'data' => [
                'title' => 'Notificación de prueba',
                'message' => 'Mensaje de prueba',
                'url' => '/app/ordenes',
                'sound' => 'order',
            ],
        ]);
    }
}
