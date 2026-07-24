<?php

namespace Tests\Feature;

use App\Livewire\Caja\Dashboard as CashDashboard;
use App\Livewire\Caja\CorteDeCaja;
use App\Livewire\Orders\OrderDetail;
use App\Livewire\Orders\OrderList;
use App\Livewire\Pos\PointOfSale;
use App\Models\CashRegister;
use App\Models\Order;
use App\Models\OrderItem;
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

    public function test_cancel_and_permanent_delete_are_independent_order_permissions(): void
    {
        [$viewer, $register, $order] = $this->orderContext(['ver ordenes']);

        Livewire::actingAs($viewer)
            ->test(OrderList::class)
            ->call('openCancelModal', $order->id)
            ->assertForbidden();

        $canceller = $this->employee(['ver ordenes', 'cancelar ordenes']);
        Livewire::actingAs($canceller)
            ->test(OrderList::class)
            ->call('openCancelModal', $order->id)
            ->set('cancelReason', 'Solicitud del cliente')
            ->call('confirmCancel')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelada']);

        Livewire::actingAs($canceller)
            ->test(OrderList::class)
            ->call('confirmDeleteOrder', $order->id)
            ->assertForbidden();

        $deleter = $this->employee(['ver ordenes', 'eliminar ordenes']);
        Livewire::actingAs($deleter)
            ->test(OrderList::class)
            ->call('handleModalConfirmed', 'deleteOrder', ['id' => $order->id])
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    public function test_cancel_item_and_delete_item_use_separate_permissions(): void
    {
        [$viewer, $register, $order] = $this->orderContext(['ver ordenes']);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Producto',
            'product_price' => 50,
            'quantity' => 1,
            'subtotal' => 50,
        ]);

        Livewire::actingAs($viewer)
            ->test(OrderDetail::class, ['order' => $order])
            ->call('openCancelItemModal', $item->id)
            ->assertForbidden();

        $canceller = $this->employee(['ver ordenes', 'cancelar ordenes']);
        Livewire::actingAs($canceller)
            ->test(OrderDetail::class, ['order' => $order])
            ->call('openCancelItemModal', $item->id)
            ->call('confirmCancelItem')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('order_items', ['id' => $item->id, 'is_cancelled' => true]);

        Livewire::actingAs($canceller)
            ->test(OrderDetail::class, ['order' => $order])
            ->call('confirmDeleteItem', $item->id)
            ->assertForbidden();

        $deleter = $this->employee(['ver ordenes', 'eliminar items de ordenes']);
        Livewire::actingAs($deleter)
            ->test(OrderDetail::class, ['order' => $order])
            ->call('handleModalConfirmed', 'deleteItem', ['id' => $item->id])
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('order_items', ['id' => $item->id]);
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

    public function test_creating_an_order_requires_crear_ordenes(): void
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

        $creator = $this->employee(['crear ordenes']);
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

    public function test_reprint_and_kitchen_status_are_independent_permissions(): void
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

        $editor = $this->employee(['editar ordenes']);
        Livewire::actingAs($editor)
            ->test(PointOfSale::class)
            ->call('markKitchenReady', $order->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'en_preparacion']);
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

    public function test_charging_a_ready_order_requires_cerrar_ordenes(): void
    {
        [$employee, $register, $order] = $this->orderContext([], ['status' => 'lista']);

        Livewire::actingAs($employee)
            ->test(PointOfSale::class)
            ->call('openPickupPayModal', $order->id)
            ->assertForbidden();

        $cashier = $this->employee(['cerrar ordenes']);
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
