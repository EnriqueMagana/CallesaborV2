<?php

namespace Tests\Feature;

use App\Livewire\Caja\CorteDeCaja;
use App\Livewire\Delivery\DeliveryBoard;
use App\Livewire\Pos\PointOfSale;
use App\Livewire\SuperAdmin\DeveloperConsole;
use App\Models\BusinessSetting;
use App\Models\CashRegister;
use App\Models\DeliveryAssignment;
use App\Models\Order;
use App\Models\Product;
use App\Models\SidebarMenuItem;
use App\Models\User;
use App\Services\CashRegisterClosingGuard;
use App\Services\DeliveryModuleManager;
use App\Services\DeliveryWorkflow;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SidebarMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class DeliveryModuleToggleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SidebarMenuSeeder::class);
    }

    public function test_disabling_delivery_converts_unassigned_orders_and_includes_them_in_global_cut(): void
    {
        [$user, $register] = $this->cashContext();
        $order = $this->deliveryOrder($register, $user, 175);

        $result = app(DeliveryModuleManager::class)->setEnabled(false, $user);

        $this->assertTrue($result['changed']);
        $this->assertSame(1, $result['converted_orders']);
        $this->assertFalse(BusinessSetting::current()->fresh()->delivery_management_enabled);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pendiente',
            'delivery_flow_mode' => 'manual',
        ]);
        $this->assertNotNull($order->fresh()->accounted_at);
        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'method' => 'efectivo',
            'amount' => 175,
        ]);
        $this->assertDatabaseHas('delivery_module_audits', [
            'new_enabled' => false,
            'changed_by' => $user->id,
            'converted_orders' => 1,
        ]);
        $this->assertFalse(app(CashRegisterClosingGuard::class)->blockers($register->id)['has_blockers']);

        Livewire::actingAs($user)
            ->test(CorteDeCaja::class)
            ->assertSee('Delivery en corte global')
            ->assertDontSee('Arqueos de delivery')
            ->assertSet('expectedCash', 675.0)
            ->set('declaredCash', '675.00')
            ->call('confirmCut')
            ->assertHasNoErrors()
            ->assertSet('showConfirm', true);
    }

    public function test_disabling_delivery_is_rejected_while_an_assignment_is_active(): void
    {
        [$user, $register] = $this->cashContext();
        $driver = User::factory()->create();
        $order = $this->deliveryOrder($register, $user, 120);
        DeliveryAssignment::create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'assigned_by' => $user->id,
            'status' => 'asignado',
            'assigned_at' => now(),
        ]);

        try {
            app(DeliveryModuleManager::class)->setEnabled(false, $user);
            $this->fail('La desactivación debió ser rechazada.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('No se puede desactivar', $exception->getMessage());
        }

        $this->assertTrue(BusinessSetting::current()->fresh()->delivery_management_enabled);
        $this->assertDatabaseMissing('delivery_module_audits', ['new_enabled' => false]);
        $this->assertDatabaseMissing('order_payments', ['order_id' => $order->id]);
    }

    public function test_pos_keeps_kitchen_status_but_accounts_cash_on_delivery_in_manual_mode(): void
    {
        [$user] = $this->cashContext();
        BusinessSetting::current()->update(['delivery_management_enabled' => false]);
        $product = Product::create([
            'name' => 'Pedido delivery manual',
            'price' => 145,
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(PointOfSale::class)
            ->set('orderType', 'delivery')
            ->set('deliveryMethod', 'contra_entrega')
            ->set('customerName', 'Cliente manual')
            ->set('customerPhone', '5512345678')
            ->set('customerAddress', 'Calle Principal 10')
            ->set('customerNeighborhood', 'Centro')
            ->set('cart', [[
                'cart_id' => 'manual-delivery-test',
                'product_id' => $product->id,
                'promotion_id' => null,
                'product_name' => $product->name,
                'product_price' => 145,
                'product_image' => null,
                'quantity' => 1,
                'unit_extra' => 0,
                'unit_total' => 145,
                'subtotal' => 145,
                'notes' => '',
                'addons' => [],
                'ingredients' => [],
            ]])
            ->call('submitOrder')
            ->assertHasNoErrors()
            ->assertSet('showOrderSuccess', true);

        $order = Order::query()->latest('id')->firstOrFail();
        $this->assertSame('delivery', $order->type);
        $this->assertSame('pendiente', $order->status);
        $this->assertSame('manual', $order->delivery_flow_mode);
        $this->assertNotNull($order->accounted_at);
        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'method' => 'efectivo',
            'amount' => 145,
        ]);
    }

    public function test_disabled_delivery_is_hidden_and_cannot_be_opened_directly(): void
    {
        $driver = User::factory()->create();
        $driver->assignRole('repartidor');
        BusinessSetting::current()->update(['delivery_management_enabled' => false]);

        $visibleRoutes = SidebarMenuItem::visibleTreeFor($driver)
            ->flatMap(fn (SidebarMenuItem $item) => $item->children)
            ->pluck('route_name');

        $this->assertNotContains('app.delivery', $visibleRoutes);
        $this->actingAs($driver)->get(route('app.delivery'))->assertForbidden();
    }

    public function test_super_admin_can_toggle_delivery_and_sees_the_operational_state(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        BusinessSetting::current()->update(['delivery_management_enabled' => true]);

        Livewire::actingAs($user)
            ->test(DeveloperConsole::class)
            ->assertSee('Gestión operativa de Delivery')
            ->assertSee('Administrado')
            ->call('toggleDeliveryModule', false)
            ->assertHasNoErrors()
            ->assertSet('lastAction.ok', true)
            ->assertSee('Gestión manual');

        $this->assertFalse(BusinessSetting::current()->fresh()->delivery_management_enabled);
    }

    public function test_manual_orders_remain_outside_the_delivery_board_after_reactivation(): void
    {
        [$user, $register] = $this->cashContext();
        $user->givePermissionTo(['ver delivery', 'gestionar delivery']);
        $manualOrder = $this->deliveryOrder($register, $user, 95);

        $manager = app(DeliveryModuleManager::class);
        $manager->setEnabled(false, $user);
        $manager->setEnabled(true, $user);

        Livewire::actingAs($user)
            ->test(DeliveryBoard::class)
            ->assertSet('orders', fn ($orders): bool => $orders->doesntContain('id', $manualOrder->id));

        $this->expectException(ValidationException::class);
        app(DeliveryWorkflow::class)->assignTo($manualOrder->fresh(), $user);
    }

    private function cashContext(): array
    {
        $user = User::factory()->create();
        $user->assignRole('owner');
        $register = CashRegister::create([
            'name' => 'Caja principal',
            'opened_by' => $user->id,
            'initial_amount' => 500,
            'opened_at' => now(),
            'is_open' => true,
        ]);

        return [$user, $register];
    }

    private function deliveryOrder(CashRegister $register, User $user, float $total): Order
    {
        return Order::create([
            'cash_register_id' => $register->id,
            'served_by' => $user->id,
            'customer_name' => 'Cliente delivery',
            'customer_phone' => '5512345678',
            'customer_address' => 'Calle Principal 10',
            'type' => 'delivery',
            'delivery_method' => 'contra_entrega',
            'status' => 'pendiente',
            'subtotal' => $total,
            'total' => $total,
        ]);
    }
}
