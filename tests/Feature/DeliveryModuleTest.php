<?php

namespace Tests\Feature;

use App\Livewire\Delivery\DeliveryBoard;
use App\Livewire\Kiosk\OrderTracking;
use App\Models\CashRegister;
use App\Models\DeliveryAssignment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class DeliveryModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_repartidor_only_sees_delivery_orders_from_the_open_register(): void
    {
        $driver = $this->driver();
        $open = $this->register($driver, true);
        $closed = $this->register($driver, false);
        $currentOrder = $this->deliveryOrder($open, $driver, ['customer_name' => 'Cliente actual', 'status' => 'lista']);
        $this->deliveryOrder($closed, $driver, ['customer_name' => 'Cliente anterior', 'status' => 'lista']);

        $this->actingAs($driver)
            ->get(route('app.delivery'))
            ->assertOk()
            ->assertSee('Cliente actual')
            ->assertDontSee('Cliente anterior');

        Livewire::actingAs($driver)
            ->test(DeliveryBoard::class)
            ->assertSee('Pedido #'.$currentOrder->display_folio)
            ->assertSee('Tomar pedido');
    }

    public function test_driver_can_take_a_ready_order_and_only_the_assigned_driver_can_deliver_it(): void
    {
        $driver = $this->driver();
        $otherDriver = $this->driver();
        $register = $this->register($driver, true);
        $order = $this->deliveryOrder($register, $driver, ['status' => 'lista']);

        Livewire::actingAs($driver)
            ->test(DeliveryBoard::class)
            ->call('takeOrder', $order->id)
            ->assertHasNoErrors()
            ->assertSet('tab', 'assigned');

        $this->assertDatabaseHas('delivery_assignments', [
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'status' => 'asignado',
        ]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'en_reparto']);

        Livewire::actingAs($otherDriver)
            ->test(DeliveryBoard::class)
            ->set('confirmingDeliveryOrderId', $order->id)
            ->call('markDelivered')
            ->assertForbidden();

        Livewire::actingAs($driver)
            ->test(DeliveryBoard::class)
            ->call('askToMarkDelivered', $order->id)
            ->call('markDelivered')
            ->assertHasNoErrors()
            ->assertSet('tab', 'delivered');

        $this->assertDatabaseHas('delivery_assignments', [
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'delivered_by' => $driver->id,
            'status' => 'entregado',
        ]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'entregada']);
    }

    public function test_an_order_cannot_be_taken_twice_or_before_it_is_ready(): void
    {
        $driver = $this->driver();
        $otherDriver = $this->driver();
        $register = $this->register($driver, true);
        $preparing = $this->deliveryOrder($register, $driver, ['status' => 'en_preparacion']);
        $ready = $this->deliveryOrder($register, $driver, ['status' => 'lista']);

        Livewire::actingAs($driver)
            ->test(DeliveryBoard::class)
            ->call('takeOrder', $preparing->id)
            ->assertHasErrors('delivery');

        Livewire::actingAs($driver)->test(DeliveryBoard::class)->call('takeOrder', $ready->id);

        Livewire::actingAs($otherDriver)
            ->test(DeliveryBoard::class)
            ->call('takeOrder', $ready->id)
            ->assertHasErrors('delivery');

        $this->assertSame(1, DeliveryAssignment::where('order_id', $ready->id)->count());
    }

    public function test_delivery_board_filters_locally_and_exposes_accessible_loading_states(): void
    {
        $driver = $this->driver();
        $register = $this->register($driver, true);
        $this->deliveryOrder($register, $driver, ['status' => 'lista']);

        Livewire::actingAs($driver)
            ->test(DeliveryBoard::class)
            ->assertSee('role="tablist"', false)
            ->assertSee('x-model.debounce.120ms="query"', false)
            ->assertSee('data-delivery-search=', false)
            ->assertSee('delivery-detail-loading', false)
            ->assertSee('wire:loading.grid', false)
            ->assertSee('delivery-skeleton-card__address', false)
            ->assertSee('delivery-skeleton-card__actions', false)
            ->assertSee('Saltar a los pedidos')
            ->assertDontSee('wire:model', false)
            ->assertDontSee('wire:poll', false)
            ->assertDontSee('<style', false);
    }

    public function test_public_tracking_shows_delivery_assignment_and_only_refreshes_manually(): void
    {
        $driver = $this->driver();
        $register = $this->register($driver, true);
        $order = $this->deliveryOrder($register, $driver, [
            'source' => 'kiosk',
            'fulfillment' => 'delivery',
            'public_token' => Str::random(64),
            'status' => 'en_reparto',
        ]);
        DeliveryAssignment::create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'assigned_by' => $driver->id,
            'status' => 'asignado',
            'assigned_at' => now(),
        ]);

        $tracking = Livewire::test(OrderTracking::class, ['publicToken' => $order->public_token])
            ->assertSee('Tu pedido va en camino')
            ->assertSee('Actualizar estado')
            ->assertDontSee('wire:poll', false);

        $order->update(['status' => 'entregada']);
        $order->deliveryAssignment->update(['status' => 'entregado', 'delivered_at' => now(), 'delivered_by' => $driver->id]);

        $tracking->call('refreshStatus')->assertSee('Pedido entregado');
    }

    private function driver(): User
    {
        $user = User::factory()->create();
        $user->assignRole('repartidor');

        return $user;
    }

    private function register(User $user, bool $open): CashRegister
    {
        return CashRegister::create([
            'name' => $open ? 'Caja actual' : 'Caja anterior',
            'opened_by' => $user->id,
            'closed_by' => $open ? null : $user->id,
            'initial_amount' => 0,
            'opened_at' => $open ? now() : now()->subDay(),
            'closed_at' => $open ? null : now()->subDay()->addHours(8),
            'is_open' => $open,
        ]);
    }

    private function deliveryOrder(CashRegister $register, User $seller, array $overrides = []): Order
    {
        $order = Order::create(array_merge([
            'cash_register_id' => $register->id,
            'served_by' => $seller->id,
            'customer_name' => 'Ana López',
            'customer_phone' => '5512345678',
            'customer_address' => 'Av. Reforma 120, Centro',
            'customer_references' => 'Portón negro',
            'type' => 'delivery',
            'delivery_method' => 'contra_entrega',
            'status' => 'lista',
            'subtotal' => 210,
            'total' => 195,
        ], $overrides));

        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Pasta grande',
            'product_price' => 210,
            'quantity' => 1,
            'subtotal' => 210,
        ]);

        return $order;
    }
}
