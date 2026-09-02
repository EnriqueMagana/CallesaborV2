<?php

namespace Tests\Feature;

use App\Livewire\Caja\CorteDeCaja;
use App\Livewire\Caja\Dashboard as CashDashboard;
use App\Livewire\Orders\OrderChangeRequestInbox;
use App\Livewire\Orders\OrderChangeRequestWizard;
use App\Livewire\Orders\OrderList;
use App\Livewire\Pos\PointOfSale;
use App\Models\CashRegister;
use App\Models\InventoryItem;
use App\Models\MesaService;
use App\Models\Order;
use App\Models\OrderChangeRequest;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OperationalPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_order_status_requires_edit_permission_and_allows_an_employee_with_only_that_action(): void
    {
        [$viewer, $register, $order] = $this->orderContext(['ver ordenes']);

        Livewire::actingAs($viewer)
            ->test(OrderList::class)
            ->call('openStatusModal', $order->id)
            ->assertForbidden();

        $editor = $this->employee(['ver ordenes', 'editar ordenes']);
        Livewire::actingAs($editor)
            ->test(OrderList::class)
            ->call('openStatusModal', $order->id)
            ->set('editStatus', 'en_preparacion')
            ->call('saveStatus')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'en_preparacion']);
    }

    public function test_cancellation_requires_a_request_and_owner_approval_without_deleting_the_order(): void
    {
        [$viewer, $register, $order] = $this->orderContext(['ver ordenes']);

        Livewire::actingAs($viewer)
            ->test(OrderChangeRequestWizard::class, ['order' => $order])
            ->assertForbidden();

        $requester = $this->employee(['ver ordenes', 'solicitar cancelacion de ordenes']);
        Livewire::actingAs($requester)
            ->test(OrderChangeRequestWizard::class, ['order' => $order])
            ->call('chooseScope', 'full')
            ->call('selectReason', 'customer_changed_mind')
            ->set('customerConfirmed', 'yes')
            ->call('nextStep')
            ->call('submit')
            ->assertHasNoErrors();

        $request = OrderChangeRequest::firstOrFail();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pendiente']);
        $this->assertDatabaseHas('order_change_requests', ['id' => $request->id, 'status' => 'pending']);
        $this->assertSame('full', data_get($request->proposed_changes, 'request_context.scope'));
        $this->assertSame('yes', data_get($request->proposed_changes, 'request_context.customer_confirmed'));

        Livewire::actingAs($requester)
            ->test(OrderChangeRequestInbox::class)
            ->assertForbidden();

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        Livewire::actingAs($owner)
            ->test(OrderChangeRequestInbox::class)
            ->set('selectedRequestId', $request->id)
            ->call('approveRequest')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelada']);
        $this->assertDatabaseHas('order_change_requests', ['id' => $request->id, 'status' => 'approved', 'reviewed_by' => $owner->id]);
    }

    public function test_modification_request_recalculates_total_and_preserves_removed_items(): void
    {
        [$requester, $register, $order] = $this->orderContext(['ver ordenes', 'solicitar modificacion de ordenes']);
        $service = MesaService::create([
            'cash_register_id' => $register->id,
            'opened_by' => $requester->id,
            'status' => 'en_cuenta',
            'service_label' => 'Mesa de prueba',
            'total_snapshot' => 100,
            'opened_at' => now(),
        ]);
        $order->update(['mesa_service_id' => $service->id]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Producto anterior',
            'product_price' => 50,
            'quantity' => 2,
            'subtotal' => 100,
        ]);
        $product = Product::create(['name' => 'Producto nuevo', 'description' => 'Producto de prueba', 'price' => 75, 'is_active' => true]);

        Livewire::actingAs($requester)
            ->test(OrderChangeRequestWizard::class, ['order' => $order])
            ->call('chooseScope', 'partial')
            ->call('adjustRequestItem', 0, -1)
            ->call('adjustRequestItem', 0, -1)
            ->call('addProductToRequest', $product->id)
            ->call('selectReason', 'customer_changed_mind')
            ->set('customerConfirmed', 'yes')
            ->call('nextStep')
            ->call('submit')
            ->assertHasNoErrors();

        $request = OrderChangeRequest::firstOrFail();
        $this->assertSame('75.00', $request->proposed_total);
        $this->assertSame('partial', data_get($request->proposed_changes, 'request_context.scope'));
        $this->assertDatabaseHas('order_items', ['id' => $item->id, 'is_cancelled' => false]);

        $owner = User::factory()->create();
        $owner->assignRole('owner');
        Livewire::actingAs($owner)
            ->test(OrderChangeRequestInbox::class)
            ->set('selectedRequestId', $request->id)
            ->call('approveRequest')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('order_items', ['id' => $item->id, 'is_cancelled' => true]);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 1, 'subtotal' => 75]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'subtotal' => 75, 'total' => 75]);
        $this->assertDatabaseHas('mesa_services', ['id' => $service->id, 'total_snapshot' => 75]);
    }

    public function test_opening_cash_register_requires_the_specific_permission(): void
    {
        $viewer = $this->employee(['ver caja']);

        Livewire::actingAs($viewer)
            ->test(CashDashboard::class)
            ->set('registerName', 'Caja principal')
            ->set('initialAmount', '500')
            ->call('openRegister')
            ->assertForbidden();

        $opener = $this->employee(['ver caja', 'abrir caja']);
        Livewire::actingAs($opener)
            ->test(CashDashboard::class)
            ->set('registerName', 'Caja principal')
            ->set('initialAmount', '500')
            ->call('openRegister')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cash_registers', [
            'name' => 'Caja principal',
            'opened_by' => $opener->id,
            'is_open' => true,
        ]);
    }

    public function test_closing_cash_register_requires_cerrar_caja(): void
    {
        $viewer = $this->employee(['ver caja']);
        $register = $this->register($viewer);

        Livewire::actingAs($viewer)
            ->test(CorteDeCaja::class, ['id' => $register->id])
            ->assertForbidden();

        $closer = $this->employee(['cerrar caja']);
        Livewire::actingAs($closer)
            ->test(CorteDeCaja::class, ['id' => $register->id])
            ->set('declaredCash', '0')
            ->call('confirmCut')
            ->call('generateCut')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cash_registers', [
            'id' => $register->id,
            'closed_by' => $closer->id,
            'is_open' => false,
        ]);
    }

    public function test_creating_an_order_from_pos_requires_its_specific_permission(): void
    {
        $withoutPermission = $this->employee([]);
        $this->register($withoutPermission);
        $cart = [[
            'cart_id' => 'permission-test',
            'product_id' => null,
            'product_name' => 'Producto de prueba',
            'product_price' => 100.0,
            'product_image' => null,
            'quantity' => 1,
            'unit_extra' => 0.0,
            'unit_total' => 100.0,
            'subtotal' => 100.0,
            'notes' => '',
            'addons' => [],
            'ingredients' => [],
        ]];

        Livewire::actingAs($withoutPermission)
            ->test(PointOfSale::class)
            ->set('cart', $cart)
            ->call('submitOrder')
            ->assertForbidden();

        $creator = $this->employee(['crear ventas en punto de venta']);
        Livewire::actingAs($creator)
            ->test(PointOfSale::class)
            ->set('cart', $cart)
            ->set('payments', [['method' => 'cash', 'amount' => 100, 'cash_received' => 100]])
            ->call('submitOrder')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('orders', [
            'served_by' => $creator->id,
            'status' => 'pagada',
            'total' => 100,
        ]);
    }

    public function test_expenses_require_registrar_gastos_permission(): void
    {
        $withoutPermission = $this->employee(['crear ordenes']);
        $this->register($withoutPermission);

        Livewire::actingAs($withoutPermission)
            ->test(PointOfSale::class)
            ->set('expenseAmount', '125.50')
            ->set('expenseDescription', 'Compra urgente')
            ->call('saveExpense')
            ->assertForbidden();

        $employee = $this->employee(['registrar gastos']);
        Livewire::actingAs($employee)
            ->test(PointOfSale::class)
            ->set('expenseAmount', '125.50')
            ->set('expenseDescription', 'Compra urgente')
            ->call('saveExpense')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('expenses', [
            'created_by' => $employee->id,
            'description' => 'Compra urgente',
        ]);
    }

    public function test_cash_income_is_audited_and_added_to_expected_cash(): void
    {
        $employee = $this->employee(['registrar movimientos de caja', 'cerrar caja']);
        $register = $this->register($employee);
        $register->update(['initial_amount' => 100]);

        Livewire::actingAs($employee)
            ->test(PointOfSale::class)
            ->set('operationType', 'income')
            ->set('expenseAmount', '75.50')
            ->set('expenseCategory', 'fondo')
            ->set('expenseDescription', 'Cambio adicional para el turno')
            ->call('saveOperation')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('expenses', [
            'cash_register_id' => $register->id,
            'created_by' => $employee->id,
            'type' => 'income',
            'amount' => 75.50,
            'payment_method' => 'cash',
        ]);

        Livewire::actingAs($employee)
            ->test(CorteDeCaja::class)
            ->assertSet('totalCashIncome', 75.50)
            ->assertSet('expectedCash', 175.50);
    }

    public function test_pos_exposes_only_the_authorized_operational_shortcuts(): void
    {
        $employee = $this->employee(['registrar movimientos de caja', 'registrar salida de insumos']);
        $this->register($employee);

        Livewire::actingAs($employee)
            ->test(PointOfSale::class)
            ->assertSee('Movimientos')
            ->assertSee('Caja e insumos')
            ->assertSee('Registrar gasto')
            ->assertSee('Ingreso de caja')
            ->assertSee('Salida de insumos')
            ->call('openOperationsModal', 'expense')
            ->assertSet('showExpenseModal', true)
            ->assertSee('Salida de caja')
            ->assertSee('Ingreso de caja')
            ->assertSee('Salida de insumos');
    }

    public function test_expense_only_permission_does_not_unlock_cash_income(): void
    {
        $employee = $this->employee(['registrar gastos']);
        $this->register($employee);

        Livewire::actingAs($employee)
            ->test(PointOfSale::class)
            ->assertSee('Registrar gasto')
            ->assertDontSee('Ingreso de caja')
            ->call('openOperationsModal', 'income')
            ->assertForbidden();
    }

    public function test_pos_supply_outflow_updates_stock_and_keeps_an_audit_movement(): void
    {
        $employee = $this->employee(['registrar salida de insumos']);
        $this->register($employee);
        $item = InventoryItem::create([
            'name' => 'Aceite vegetal',
            'unit' => 'liter',
            'current_stock' => 10,
            'minimum_stock' => 2,
            'is_active' => true,
        ]);

        Livewire::actingAs($employee)
            ->test(PointOfSale::class)
            ->set('operationType', 'inventory_out')
            ->set('inventoryItemId', $item->id)
            ->set('adjustQuantity', '2.5')
            ->set('inventoryReason', 'Consumo interno')
            ->call('saveOperation')
            ->assertHasNoErrors();

        $this->assertEquals(7.5, (float) $item->fresh()->current_stock);
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_item_id' => $item->id,
            'user_id' => $employee->id,
            'type' => 'pos_supply_outflow',
            'quantity' => -2.5,
            'stock_before' => 10,
            'stock_after' => 7.5,
            'reason' => 'Consumo interno',
        ]);
    }

    public function test_reprint_and_each_pos_kitchen_transition_are_independent_permissions(): void
    {
        [$viewer, $register, $order] = $this->orderContext([]);

        Livewire::actingAs($viewer)
            ->test(PointOfSale::class)
            ->call('openReprintModal', $order->id)
            ->assertForbidden();

        $printer = $this->employee(['reimprimir tickets']);
        Livewire::actingAs($printer)
            ->test(PointOfSale::class)
            ->call('openReprintModal', $order->id)
            ->assertDispatched('pos-reprint-show')
            ->call('markKitchenReady', $order->id)
            ->assertForbidden();

        $starter = $this->employee(['iniciar preparacion en punto de venta']);
        Livewire::actingAs($starter)
            ->test(PointOfSale::class)
            ->call('markKitchenReady', $order->id)
            ->assertHasNoErrors()
            ->call('markKitchenReady', $order->id)
            ->assertForbidden();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'en_preparacion']);

        $finisher = $this->employee(['marcar pedidos listos en punto de venta']);
        Livewire::actingAs($finisher)
            ->test(PointOfSale::class)
            ->call('markKitchenReady', $order->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'lista']);
    }

    public function test_print_routes_require_reprint_permission(): void
    {
        [$viewer, $register, $order] = $this->orderContext([]);

        $this->actingAs($viewer)
            ->get(route('print.cliente', $order))
            ->assertForbidden();

        $printer = $this->employee(['reimprimir tickets']);
        $this->actingAs($printer)
            ->get(route('print.cliente', $order))
            ->assertOk()
            ->assertSee('CUENTA DEL CLIENTE');
    }

    public function test_operational_users_cannot_open_or_print_orders_from_a_closed_register(): void
    {
        [$viewer, $closedRegister, $oldOrder] = $this->orderContext(['ver ordenes', 'reimprimir tickets']);
        $closedRegister->update([
            'is_open' => false,
            'closed_at' => now(),
        ]);
        $this->register($viewer);

        $this->actingAs($viewer)
            ->get(route('app.ordenes.show', $oldOrder))
            ->assertNotFound();

        $this->actingAs($viewer)
            ->get(route('print.cliente', $oldOrder))
            ->assertNotFound();
    }

    public function test_charging_a_ready_order_requires_the_specific_pos_permission(): void
    {
        [$employee, $register, $order] = $this->orderContext([], ['status' => 'lista']);

        Livewire::actingAs($employee)
            ->test(PointOfSale::class)
            ->call('openPickupPayModal', $order->id)
            ->assertForbidden();

        $cashier = $this->employee(['cobrar pedidos en punto de venta']);
        Livewire::actingAs($cashier)
            ->test(PointOfSale::class)
            ->call('openPickupPayModal', $order->id)
            ->set('pickupPayAmount', '100')
            ->set('pickupPayReceived', '100')
            ->call('addPickupPayment')
            ->call('confirmPickupPayment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pagada']);
    }

    private function employee(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function register(User $user): CashRegister
    {
        return CashRegister::create([
            'name' => 'Caja operativa',
            'opened_by' => $user->id,
            'initial_amount' => 0,
            'opened_at' => now(),
            'is_open' => true,
        ]);
    }

    private function orderContext(array $permissions, array $overrides = []): array
    {
        $user = $this->employee($permissions);
        $register = CashRegister::query()->where('is_open', true)->first() ?? $this->register($user);
        $order = Order::create(array_merge([
            'cash_register_id' => $register->id,
            'served_by' => $user->id,
            'customer_name' => 'Cliente',
            'type' => 'ventanilla',
            'status' => 'pendiente',
            'subtotal' => 100,
            'total' => 100,
        ], $overrides));

        return [$user, $register, $order];
    }
}
