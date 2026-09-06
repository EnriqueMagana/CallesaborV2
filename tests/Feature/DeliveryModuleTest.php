<?php

namespace Tests\Feature;

use App\Livewire\Delivery\DeliveryBoard;
use App\Livewire\Kiosk\OrderTracking;
use App\Livewire\Pos\PointOfSale;
use App\Models\CashRegister;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryAssignmentEvent;
use App\Models\Order;
use App\Models\OrderDataChangeAudit;
use App\Models\OrderItem;
use App\Models\OrderPayment;
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
            ->assertSee($currentOrder->display_folio)
            ->assertSee('Asignarme')
            ->assertSee('Ver detalles');
    }

    public function test_driver_assigns_picks_up_and_delivers_an_order_in_separate_steps(): void
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
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'lista']);

        Livewire::actingAs($otherDriver)
            ->test(DeliveryBoard::class)
            ->call('markPickedUp', $order->id)
            ->assertForbidden();

        $driverBoard = Livewire::actingAs($driver)
            ->test(DeliveryBoard::class)
            ->call('markPickedUp', $order->id)
            ->assertHasNoErrors()
            ->assertSee('Marcar entregado');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'en_reparto']);

        $driverBoard
            ->call('askToMarkDelivered', $order->id)
            ->call('markDelivered')
            ->assertHasNoErrors()
            ->assertSet('tab', 'delivered')
            ->assertSee('Entregado');

        $this->assertDatabaseHas('delivery_assignments', [
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'delivered_by' => $driver->id,
            'status' => 'entregado',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pagada',
        ]);
        $this->assertNotNull($order->fresh()->paid_at);
        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'method' => 'efectivo',
            'amount' => 195,
        ]);
    }

    public function test_delivery_uses_the_payment_method_defined_by_counter_when_completed(): void
    {
        $driver = $this->driver();
        $register = $this->register($driver, true);
        $order = $this->deliveryOrder($register, $driver, [
            'status' => 'lista',
            'delivery_method' => 'transferencia',
        ]);

        Livewire::actingAs($driver)
            ->test(DeliveryBoard::class)
            ->call('takeOrder', $order->id)
            ->call('markPickedUp', $order->id)
            ->call('askToMarkDelivered', $order->id)
            ->call('markDelivered')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pagada',
        ]);
        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'method' => 'transferencia',
            'amount' => 195,
        ]);
    }

    public function test_a_preparing_order_can_be_reserved_but_cannot_be_taken_twice(): void
    {
        $driver = $this->driver();
        $otherDriver = $this->driver();
        $register = $this->register($driver, true);
        $preparing = $this->deliveryOrder($register, $driver, ['status' => 'en_preparacion']);
        $ready = $this->deliveryOrder($register, $driver, ['status' => 'lista']);

        Livewire::actingAs($driver)
            ->test(DeliveryBoard::class)
            ->call('takeOrder', $preparing->id)
            ->assertHasNoErrors();

        Livewire::actingAs($otherDriver)
            ->test(DeliveryBoard::class)
            ->call('takeOrder', $preparing->id)
            ->assertHasErrors('delivery');

        $this->assertSame(1, DeliveryAssignment::where('order_id', $preparing->id)->count());
        $this->assertDatabaseHas('orders', ['id' => $preparing->id, 'status' => 'en_preparacion']);
        $this->assertDatabaseMissing('delivery_assignments', ['order_id' => $ready->id]);
    }

    public function test_authorized_user_can_reassign_an_active_delivery_without_changing_the_order(): void
    {
        $firstDriver = $this->driver();
        $nextDriver = $this->driver();
        $register = $this->register($firstDriver, true);
        $order = $this->deliveryOrder($register, $firstDriver, ['status' => 'en_reparto']);
        $assignment = DeliveryAssignment::create([
            'order_id' => $order->id,
            'driver_id' => $firstDriver->id,
            'assigned_by' => $firstDriver->id,
            'status' => 'asignado',
            'assigned_at' => now()->subMinutes(12),
        ]);

        Livewire::actingAs($firstDriver)
            ->withQueryParams(['tab' => 'drivers'])
            ->test(DeliveryBoard::class)
            ->assertSet('tab', 'drivers')
            ->assertSee('Repartidores y pedidos asignados')
            ->assertSee($nextDriver->name)
            ->call('openReassign', $order->id)
            ->set('reassignDriverId', $nextDriver->id)
            ->set('reassignReason', 'El pedido fue tomado por el repartidor equivocado.')
            ->call('confirmReassign')
            ->assertHasNoErrors()
            ->assertSet('tab', 'drivers')
            ->assertSet('reassignOrderId', null);

        $this->assertDatabaseHas('delivery_assignments', [
            'id' => $assignment->id,
            'driver_id' => $nextDriver->id,
            'assigned_by' => $firstDriver->id,
            'status' => 'asignado',
        ]);
        $this->assertDatabaseHas('delivery_assignment_events', [
            'delivery_assignment_id' => $assignment->id,
            'order_id' => $order->id,
            'from_driver_id' => $firstDriver->id,
            'to_driver_id' => $nextDriver->id,
            'actor_id' => $firstDriver->id,
            'event_type' => 'reassigned',
        ]);
        $this->assertSame(1, DeliveryAssignmentEvent::query()->where('order_id', $order->id)->count());
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'en_reparto',
            'total' => 195,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $nextDriver->id,
            'event_key' => 'delivery.reassigned',
            'subject_type' => Order::class,
            'subject_id' => $order->id,
        ]);
    }

    public function test_cashier_can_review_and_reassign_a_delivery_inside_the_pos_modal(): void
    {
        $cashier = User::factory()->create();
        $cashier->assignRole('cajero');
        $firstDriver = $this->driver();
        $nextDriver = $this->driver();
        $register = $this->register($cashier, true);
        $order = $this->deliveryOrder($register, $cashier, ['status' => 'lista']);
        $assignment = DeliveryAssignment::create([
            'order_id' => $order->id,
            'driver_id' => $firstDriver->id,
            'assigned_by' => $cashier->id,
            'status' => 'asignado',
            'assigned_at' => now()->subMinutes(5),
        ]);

        Livewire::actingAs($cashier)
            ->test(PointOfSale::class)
            ->call('openDeliveryDispatchModal')
            ->assertSet('showDeliveryDispatchModal', true)
            ->assertSee('Repartidores y pedidos')
            ->assertSee($firstDriver->name)
            ->assertSee($nextDriver->name)
            ->assertSee($order->display_folio)
            ->call('selectDeliveryDispatchOrder', $order->id)
            ->assertSet('deliveryDispatchOrderId', $order->id)
            ->call('selectDeliveryDispatchAction', 'reassign')
            ->assertSet('deliveryDispatchAction', 'reassign')
            ->set('deliveryDispatchDriverId', $nextDriver->id)
            ->set('deliveryDispatchReason', 'Corrección operativa desde el punto de venta.')
            ->call('reassignDeliveryFromPos')
            ->assertHasNoErrors()
            ->assertSet('showDeliveryDispatchModal', true)
            ->assertSet('deliveryDispatchDriverId', null);

        $this->assertDatabaseHas('delivery_assignments', [
            'id' => $assignment->id,
            'driver_id' => $nextDriver->id,
            'assigned_by' => $cashier->id,
            'status' => 'asignado',
        ]);
        $this->assertDatabaseHas('delivery_assignment_events', [
            'delivery_assignment_id' => $assignment->id,
            'order_id' => $order->id,
            'from_driver_id' => $firstDriver->id,
            'to_driver_id' => $nextDriver->id,
            'actor_id' => $cashier->id,
            'event_type' => 'reassigned',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'lista',
            'total' => 195,
        ]);
    }

    public function test_cashier_can_change_delivery_address_and_payment_inside_the_dispatch_wizard(): void
    {
        $cashier = User::factory()->create();
        $cashier->assignRole('cajero');
        $driver = $this->driver();
        $register = $this->register($cashier, true);
        $order = $this->deliveryOrder($register, $cashier, [
            'status' => 'lista',
            'customer_neighborhood' => 'Centro',
            'delivery_method' => 'transferencia',
        ]);
        $assignment = DeliveryAssignment::create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'assigned_by' => $cashier->id,
            'status' => 'asignado',
            'assigned_at' => now(),
        ]);
        $payment = OrderPayment::create([
            'order_id' => $order->id,
            'method' => 'transferencia',
            'amount' => 195,
            'transfer_reference' => 'REF-ORIGINAL',
        ]);

        $component = Livewire::actingAs($cashier)
            ->test(PointOfSale::class)
            ->call('openDeliveryDispatchModal')
            ->call('selectDeliveryDispatchOrder', $order->id)
            ->assertSee('Modificar datos de la orden')
            ->call('selectDeliveryDispatchAction', 'edit_order')
            ->assertSet('deliveryDispatchAction', 'edit_order')
            ->assertSet('orderDataCustomerAddress', 'Av. Reforma 120, Centro');

        $component
            ->assertSee('x-on:click.stop="void 0"', false)
            ->assertDontSee('wire:click.stop', false);

        $component
            ->set('orderDataCustomerAddress', 'Calle Nueva 45')
            ->set('orderDataCustomerNeighborhood', 'Colonia Norte')
            ->set('orderDataCustomerReferences', 'Fachada azul')
            ->set('orderDataPayments.0.method', 'efectivo')
            ->set('orderDataPayments.0.received_amount', '200.00')
            ->call('saveDeliveryDispatchOrderData')
            ->assertHasNoErrors()
            ->assertSet('showDeliveryDispatchModal', true)
            ->assertSet('deliveryDispatchAction', '');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'customer_address' => 'Calle Nueva 45',
            'customer_neighborhood' => 'Colonia Norte',
            'customer_references' => 'Fachada azul',
            'status' => 'lista',
            'total' => 195,
        ]);
        $this->assertDatabaseHas('order_payments', [
            'id' => $payment->id,
            'method' => 'efectivo',
            'amount' => 195,
            'received_amount' => 200,
            'change_amount' => 5,
        ]);
        $this->assertDatabaseHas('delivery_assignments', [
            'id' => $assignment->id,
            'driver_id' => $driver->id,
        ]);
        $this->assertSame(1, OrderDataChangeAudit::where('order_id', $order->id)->count());
    }

    public function test_delivery_cannot_be_reassigned_to_a_user_without_delivery_capability(): void
    {
        $driver = $this->driver();
        $ineligible = User::factory()->create();
        $register = $this->register($driver, true);
        $order = $this->deliveryOrder($register, $driver, ['status' => 'lista']);
        DeliveryAssignment::create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'assigned_by' => $driver->id,
            'status' => 'asignado',
            'assigned_at' => now(),
        ]);

        Livewire::actingAs($driver)
            ->test(DeliveryBoard::class)
            ->call('openReassign', $order->id)
            ->set('reassignDriverId', $ineligible->id)
            ->set('reassignReason', 'CorrecciÃ³n por confusiÃ³n en la asignaciÃ³n.')
            ->call('confirmReassign')
            ->assertHasErrors('reassignDriverId');

        $this->assertDatabaseHas('delivery_assignments', [
            'order_id' => $order->id,
            'driver_id' => $driver->id,
        ]);
        $this->assertDatabaseCount('delivery_assignment_events', 0);
    }

    public function test_reassignment_permission_is_available_to_pos_and_delivery_default_roles(): void
    {
        foreach (['admin', 'gerente', 'cajero', 'repartidor'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);

            $this->assertTrue($user->can('reasignar pedidos delivery'), "El rol {$role} debe poder reasignar delivery.");
        }
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
            ->assertSee('aria-expanded="expanded"', false)
            ->assertSee('wire:loading.grid', false)
            ->assertSee('delivery-skeleton-card__address', false)
            ->assertSee('delivery-skeleton-card__actions', false)
            ->assertSee('Saltar al banco de pedidos')
            ->assertSee('Banco de pedidos')
            ->assertDontSee('Listos para tomar')
            ->assertDontSee('Método definido por ventanilla')
            ->assertDontSee('wire:model', false)
            ->assertDontSee('wire:poll', false)
            ->assertDontSee('<style', false);
    }

    public function test_driver_sees_a_read_only_reconciliation_of_only_their_delivered_notes(): void
    {
        $driver = $this->driver();
        $otherDriver = $this->driver();
        $register = $this->register($driver, true);

        $cashOrder = $this->deliveredOrder($register, $driver, 'efectivo', 195, 'Nota efectivo');
        $transferOrder = $this->deliveredOrder($register, $driver, 'transferencia', 120, 'Nota transferencia');
        $cardOrder = $this->deliveredOrder($register, $driver, 'tarjeta', 80, 'Nota tarjeta');
        $hiddenOrder = $this->deliveredOrder($register, $otherDriver, 'efectivo', 999, 'Nota de otro repartidor');

        Livewire::actingAs($driver)
            ->test(DeliveryBoard::class)
            ->set('tab', 'reconciliation')
            ->assertSee('Mi arqueo')
            ->assertSee('Solo lectura')
            ->assertSee('Notas entregadas')
            ->assertSee('$195.00')
            ->assertSee('$120.00')
            ->assertSee('$80.00')
            ->assertSee('$395.00')
            ->assertSee($cashOrder->display_folio)
            ->assertSee($transferOrder->display_folio)
            ->assertSee($cardOrder->display_folio)
            ->assertDontSee($hiddenOrder->display_folio)
            ->assertSee('Contra entrega siempre se contabiliza como efectivo.')
            ->assertDontSee('Conciliar')
            ->assertDontSee('Cambiar método');
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

    private function deliveredOrder(
        CashRegister $register,
        User $driver,
        string $method,
        float $amount,
        string $customerName,
    ): Order {
        $order = $this->deliveryOrder($register, $driver, [
            'customer_name' => $customerName,
            'status' => 'pagada',
            'total' => $amount,
            'paid_at' => now(),
        ]);

        DeliveryAssignment::create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'assigned_by' => $driver->id,
            'delivered_by' => $driver->id,
            'status' => 'entregado',
            'assigned_at' => now()->subMinutes(30),
            'delivered_at' => now(),
        ]);

        OrderPayment::create([
            'order_id' => $order->id,
            'method' => $method,
            'amount' => $amount,
        ]);

        return $order;
    }
}
