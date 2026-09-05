<?php

namespace Tests\Feature;

use App\Livewire\Orders\OrderChangeRequestWizard;
use App\Models\AppNotification;
use App\Models\CashRegister;
use App\Models\Order;
use App\Models\OrderChangeRequest;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\OrderRefund;
use App\Models\User;
use App\Services\OrderChangeRequestService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class OrderChangeRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_request_notifies_only_owner_and_super_admin_and_rejection_preserves_order(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super-admin');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $requester = User::factory()->create();
        $requester->givePermissionTo(['ver ordenes', 'solicitar cancelacion de ordenes']);

        $register = CashRegister::create([
            'name' => 'Caja de prueba', 'opened_by' => $requester->id,
            'initial_amount' => 0, 'opened_at' => now(), 'is_open' => true,
        ]);
        $order = Order::create([
            'cash_register_id' => $register->id, 'served_by' => $requester->id,
            'customer_name' => 'Cliente', 'type' => 'ventanilla', 'status' => 'pendiente',
            'subtotal' => 120, 'total' => 120,
        ]);

        $this->actingAs($requester);
        $request = app(OrderChangeRequestService::class)->create(
            $order, $requester, OrderChangeRequest::TYPE_CANCELLATION,
            'El cliente ya no desea recibir el pedido'
        );

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $owner->id, 'event_key' => 'order.cancellation_requested', 'subject_id' => $request->id]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $superAdmin->id, 'event_key' => 'order.cancellation_requested', 'subject_id' => $request->id]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $admin->id, 'event_key' => 'order.cancellation_requested']);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $requester->id, 'event_key' => 'order.cancellation_requested']);
        $notification = AppNotification::where('notifiable_id', $owner->id)->where('event_key', 'order.cancellation_requested')->firstOrFail();
        $this->assertSame(route('app.solicitudes-ordenes', ['request' => $request->id], false), $notification->data['url']);

        app(OrderChangeRequestService::class)->reject($request, $owner, 'No se autoriza sin confirmar con el cliente');

        $this->assertDatabaseHas('order_change_requests', ['id' => $request->id, 'status' => 'rejected', 'reviewed_by' => $owner->id]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pendiente', 'total' => 120]);
    }

    public function test_paid_order_can_start_the_same_wizard_and_create_a_refund_request(): void
    {
        $requester = User::factory()->create();
        $requester->givePermissionTo(['ver ordenes', 'solicitar cancelacion de ordenes']);
        $register = CashRegister::create(['name' => 'Caja', 'opened_by' => $requester->id, 'initial_amount' => 0, 'opened_at' => now(), 'is_open' => true]);
        $order = Order::create(['cash_register_id' => $register->id, 'served_by' => $requester->id, 'customer_name' => 'Cliente', 'type' => 'ventanilla', 'status' => 'pagada', 'subtotal' => 100, 'total' => 100]);
        OrderItem::create(['order_id' => $order->id, 'product_name' => 'Producto', 'product_price' => 100, 'quantity' => 1, 'subtotal' => 100]);
        OrderPayment::create(['order_id' => $order->id, 'method' => 'efectivo', 'amount' => 100, 'received_amount' => 100, 'change_amount' => 0]);

        $this->actingAs($requester)
            ->get(route('app.ordenes.solicitud', $order))
            ->assertOk()
            ->assertSee('Esta orden ya fue pagada');

        $request = app(OrderChangeRequestService::class)->create(
            $order,
            $requester,
            OrderChangeRequest::TYPE_CANCELLATION,
            'El cliente solicitó cancelar el pedido completo',
            [],
            ['scope' => 'full', 'inventory_disposition' => 'waste']
        );

        $this->assertSame('paid', data_get($request->proposed_changes, 'request_context.payment_state'));
        $this->assertSame(100.0, (float) data_get($request->proposed_changes, 'request_context.refund_amount'));
        $this->assertSame(100.0, (float) data_get($request->proposed_changes, 'request_context.refund_allocations.efectivo'));
    }

    public function test_approving_paid_cash_cancellation_keeps_payment_and_records_refund_and_cash_expense(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $requester = User::factory()->create();
        $requester->givePermissionTo(['ver ordenes', 'solicitar cancelacion de ordenes']);
        $register = CashRegister::create(['name' => 'Caja', 'opened_by' => $requester->id, 'initial_amount' => 0, 'opened_at' => now(), 'is_open' => true]);
        $order = Order::create(['cash_register_id' => $register->id, 'served_by' => $requester->id, 'customer_name' => 'Cliente', 'type' => 'ventanilla', 'status' => 'pagada', 'subtotal' => 100, 'total' => 100]);
        OrderItem::create(['order_id' => $order->id, 'product_name' => 'Producto', 'product_price' => 100, 'quantity' => 1, 'subtotal' => 100]);
        OrderPayment::create(['order_id' => $order->id, 'method' => 'efectivo', 'amount' => 100, 'received_amount' => 100, 'change_amount' => 0]);

        $request = app(OrderChangeRequestService::class)->create(
            $order,
            $requester,
            OrderChangeRequest::TYPE_CANCELLATION,
            'El cliente solicitó cancelar el pedido completo',
            [],
            ['scope' => 'full', 'inventory_disposition' => 'waste']
        );
        app(OrderChangeRequestService::class)->approve($request, $owner, 'Devolución entregada al cliente');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelada']);
        $this->assertDatabaseHas('order_payments', ['order_id' => $order->id, 'method' => 'efectivo', 'amount' => 100]);
        $this->assertDatabaseHas('order_refunds', ['order_id' => $order->id, 'amount' => 100, 'inventory_disposition' => 'waste']);
        $this->assertDatabaseHas('expenses', ['cash_register_id' => $register->id, 'type' => 'expense', 'category' => 'reembolso_orden', 'amount' => 100]);
    }

    public function test_paid_card_refund_requires_external_reference(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $requester = User::factory()->create();
        $requester->givePermissionTo(['ver ordenes', 'solicitar cancelacion de ordenes']);
        $register = CashRegister::create(['name' => 'Caja', 'opened_by' => $requester->id, 'initial_amount' => 0, 'opened_at' => now(), 'is_open' => true]);
        $order = Order::create(['cash_register_id' => $register->id, 'served_by' => $requester->id, 'customer_name' => 'Cliente', 'type' => 'ventanilla', 'status' => 'pagada', 'subtotal' => 80, 'total' => 80]);
        OrderItem::create(['order_id' => $order->id, 'product_name' => 'Producto', 'product_price' => 80, 'quantity' => 1, 'subtotal' => 80]);
        OrderPayment::create(['order_id' => $order->id, 'method' => 'tarjeta', 'amount' => 80]);
        $request = app(OrderChangeRequestService::class)->create($order, $requester, OrderChangeRequest::TYPE_CANCELLATION, 'Cancelación confirmada por el cliente', [], ['scope' => 'full', 'inventory_disposition' => 'restock']);

        try {
            app(OrderChangeRequestService::class)->approve($request, $owner);
            $this->fail('La aprobación debía exigir una referencia externa.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('refundReference', $exception->errors());
        }

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pagada']);
        $this->assertDatabaseMissing('order_refunds', ['order_id' => $order->id]);

        app(OrderChangeRequestService::class)->approve($request, $owner, null, ['external_reference' => 'REF-12345']);
        $refund = OrderRefund::where('order_id', $order->id)->firstOrFail();
        $this->assertSame('REF-12345', $refund->external_reference);
        $this->assertDatabaseMissing('expenses', ['category' => 'reembolso_orden']);
    }

    public function test_paid_partial_change_recalculates_total_and_refunds_only_the_difference(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $requester = User::factory()->create();
        $requester->givePermissionTo(['ver ordenes', 'solicitar modificacion de ordenes']);
        $register = CashRegister::create(['name' => 'Caja', 'opened_by' => $requester->id, 'initial_amount' => 0, 'opened_at' => now(), 'is_open' => true]);
        $order = Order::create(['cash_register_id' => $register->id, 'served_by' => $requester->id, 'customer_name' => 'Cliente', 'type' => 'ventanilla', 'status' => 'pagada', 'subtotal' => 100, 'total' => 100]);
        $item = OrderItem::create(['order_id' => $order->id, 'product_name' => 'Producto', 'product_price' => 50, 'quantity' => 2, 'subtotal' => 100]);
        OrderPayment::create(['order_id' => $order->id, 'method' => 'efectivo', 'amount' => 100, 'received_amount' => 100, 'change_amount' => 0]);

        $request = app(OrderChangeRequestService::class)->create(
            $order,
            $requester,
            OrderChangeRequest::TYPE_MODIFICATION,
            'El cliente pidió retirar una unidad del producto',
            [['kind' => 'existing', 'order_item_id' => $item->id, 'quantity' => 1]],
            ['scope' => 'partial', 'inventory_disposition' => 'restock']
        );
        app(OrderChangeRequestService::class)->approve($request, $owner);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pagada', 'total' => 50]);
        $this->assertDatabaseHas('order_items', ['id' => $item->id, 'quantity' => 1, 'subtotal' => 50]);
        $this->assertDatabaseHas('order_refunds', ['order_id' => $order->id, 'amount' => 50, 'type' => 'partial']);
        $this->assertDatabaseHas('order_payments', ['order_id' => $order->id, 'amount' => 100]);
    }

    public function test_paid_change_cannot_increase_the_already_collected_total(): void
    {
        $requester = User::factory()->create();
        $requester->givePermissionTo(['ver ordenes', 'solicitar modificacion de ordenes']);
        $register = CashRegister::create(['name' => 'Caja', 'opened_by' => $requester->id, 'initial_amount' => 0, 'opened_at' => now(), 'is_open' => true]);
        $order = Order::create(['cash_register_id' => $register->id, 'served_by' => $requester->id, 'customer_name' => 'Cliente', 'type' => 'ventanilla', 'status' => 'pagada', 'subtotal' => 50, 'total' => 50]);
        $item = OrderItem::create(['order_id' => $order->id, 'product_name' => 'Producto', 'product_price' => 50, 'quantity' => 1, 'subtotal' => 50]);
        OrderPayment::create(['order_id' => $order->id, 'method' => 'efectivo', 'amount' => 50, 'received_amount' => 50, 'change_amount' => 0]);

        $this->expectException(ValidationException::class);
        app(OrderChangeRequestService::class)->create(
            $order,
            $requester,
            OrderChangeRequest::TYPE_MODIFICATION,
            'El cliente solicitó agregar otra unidad del producto',
            [['kind' => 'existing', 'order_item_id' => $item->id, 'quantity' => 2]],
            ['scope' => 'adjustment', 'inventory_disposition' => 'not_applicable']
        );
    }

    public function test_wizard_exposes_only_scopes_allowed_by_the_user(): void
    {
        $requester = User::factory()->create();
        $requester->givePermissionTo(['ver ordenes', 'solicitar cancelacion de ordenes']);
        $register = CashRegister::create(['name' => 'Caja', 'opened_by' => $requester->id, 'initial_amount' => 0, 'opened_at' => now(), 'is_open' => true]);
        $order = Order::create(['cash_register_id' => $register->id, 'served_by' => $requester->id, 'customer_name' => 'Cliente', 'type' => 'ventanilla', 'status' => 'pendiente', 'subtotal' => 100, 'total' => 100]);
        OrderItem::create(['order_id' => $order->id, 'product_name' => 'Producto', 'product_price' => 100, 'quantity' => 1, 'subtotal' => 100]);

        $this->actingAs($requester)
            ->get(route('app.ordenes.solicitud', $order))
            ->assertOk()
            ->assertSee('Cancelar toda la orden')
            ->assertDontSee('Cancelación parcial');

        Livewire::actingAs($requester)
            ->test(OrderChangeRequestWizard::class, ['order' => $order])
            ->call('chooseScope', 'partial')
            ->assertForbidden();
    }

    public function test_partial_cancellation_cannot_leave_the_order_empty(): void
    {
        $requester = User::factory()->create();
        $requester->givePermissionTo(['ver ordenes', 'solicitar modificacion de ordenes']);
        $register = CashRegister::create(['name' => 'Caja', 'opened_by' => $requester->id, 'initial_amount' => 0, 'opened_at' => now(), 'is_open' => true]);
        $order = Order::create(['cash_register_id' => $register->id, 'served_by' => $requester->id, 'customer_name' => 'Cliente', 'type' => 'ventanilla', 'status' => 'pendiente', 'subtotal' => 100, 'total' => 100]);
        OrderItem::create(['order_id' => $order->id, 'product_name' => 'Producto', 'product_price' => 100, 'quantity' => 1, 'subtotal' => 100]);

        Livewire::actingAs($requester)
            ->test(OrderChangeRequestWizard::class, ['order' => $order])
            ->call('chooseScope', 'partial')
            ->call('adjustRequestItem', 0, -1)
            ->call('selectReason', 'customer_changed_mind')
            ->set('customerConfirmed', 'yes')
            ->call('nextStep')
            ->assertHasErrors('requestItems');
    }

    public function test_approved_payment_change_reclassifies_the_existing_delivery_payment_without_duplicating_it(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $requester = User::factory()->create();
        $requester->givePermissionTo(['ver ordenes', 'solicitar cambio de metodo de pago']);
        $register = CashRegister::create(['name' => 'Caja', 'opened_by' => $requester->id, 'initial_amount' => 0, 'opened_at' => now(), 'is_open' => true]);
        $order = Order::create([
            'cash_register_id' => $register->id,
            'served_by' => $requester->id,
            'customer_name' => 'Cliente delivery',
            'type' => 'delivery',
            'delivery_method' => 'transferencia',
            'status' => 'pagada',
            'subtotal' => 150,
            'total' => 150,
        ]);
        $payment = OrderPayment::create(['order_id' => $order->id, 'method' => 'transferencia', 'amount' => 150, 'transfer_reference' => 'TR-ERROR']);

        $request = app(OrderChangeRequestService::class)->create(
            $order,
            $requester,
            OrderChangeRequest::TYPE_PAYMENT_CHANGE,
            'El cliente pagará en efectivo al llegar al local',
            [],
            ['scope' => 'payment', 'previous_payment_received' => 'no', 'new_payment_method' => 'cash', 'cash_received' => 200]
        );

        app(OrderChangeRequestService::class)->approve($request, $owner, 'Método confirmado con el cliente');

        $this->assertSame(1, OrderPayment::where('order_id', $order->id)->count());
        $this->assertDatabaseHas('order_payments', [
            'id' => $payment->id,
            'method' => 'efectivo',
            'amount' => 150,
            'received_amount' => 200,
            'change_amount' => 50,
            'transfer_reference' => null,
        ]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'delivery_method' => 'contra_entrega', 'total' => 150]);
    }

    public function test_approved_address_change_updates_delivery_destination_without_changing_total(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $requester = User::factory()->create();
        $requester->givePermissionTo(['ver ordenes', 'solicitar cambio de direccion']);
        $register = CashRegister::create(['name' => 'Caja', 'opened_by' => $requester->id, 'initial_amount' => 0, 'opened_at' => now(), 'is_open' => true]);
        $order = Order::create([
            'cash_register_id' => $register->id,
            'served_by' => $requester->id,
            'customer_name' => 'Cliente delivery',
            'customer_address' => 'Calle anterior 10',
            'customer_neighborhood' => 'Centro',
            'customer_phone' => '9991112233',
            'type' => 'delivery',
            'delivery_method' => 'contra_entrega',
            'status' => 'pendiente',
            'subtotal' => 95,
            'total' => 95,
        ]);

        $request = app(OrderChangeRequestService::class)->create(
            $order,
            $requester,
            OrderChangeRequest::TYPE_ADDRESS_CHANGE,
            'El cliente confirmó una dirección de entrega distinta',
            [],
            [
                'scope' => 'address',
                'new_address' => 'Avenida Nueva 245',
                'new_neighborhood' => 'San Juan',
                'new_references' => 'Fachada azul',
                'new_phone' => '9994445566',
            ]
        );

        app(OrderChangeRequestService::class)->approve($request, $owner, 'Destino confirmado');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'customer_address' => 'Avenida Nueva 245',
            'customer_neighborhood' => 'San Juan',
            'customer_references' => 'Fachada azul',
            'customer_phone' => '9994445566',
            'total' => 95,
        ]);
    }
}
