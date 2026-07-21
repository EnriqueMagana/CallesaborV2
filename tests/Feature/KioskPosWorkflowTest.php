<?php

namespace Tests\Feature;

use App\Livewire\Pos\PointOfSale;
use App\Models\Area;
use App\Models\BusinessSetting;
use App\Models\CashRegister;
use App\Models\KioskTerminal;
use App\Models\Mesa;
use App\Models\MesaSplit;
use App\Models\Order;
use App\Models\OrderItem;
use App\Livewire\Mesas\SplitCuenta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class KioskPosWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_kiosk_orders_are_separated_by_operational_area_and_follow_the_status_flow(): void
    {
        [$user, $register, $terminal, $mesa] = $this->posContext();
        $pickup = $this->kioskOrder($register->id, $user->id, $terminal->id, 'Cliente Pickup', 'takeaway');
        $delivery = $this->kioskOrder($register->id, $user->id, $terminal->id, 'Cliente Delivery', 'delivery');
        $dineIn = $this->kioskOrder($register->id, $user->id, $terminal->id, 'Cliente Mesa', 'dine_in', $mesa->id);

        $this->actingAs($user);

        $component = Livewire::test(PointOfSale::class)
            ->assertSee('Cliente Pickup')
            ->assertSee('Cliente Delivery')
            ->assertSee('Cliente Mesa')
            ->assertSee('Mesa '.$mesa->number)
            ->call('openPickupPayModal', $pickup->id)
            ->assertSet('showPickupPayModal', false)
            ->assertDispatched('notify');

        $component->call('markKitchenReady', $pickup->id);
        $this->assertDatabaseHas('orders', ['id' => $pickup->id, 'status' => 'en_preparacion']);

        $component->call('markKitchenReady', $pickup->id);
        $this->assertDatabaseHas('orders', ['id' => $pickup->id, 'status' => 'lista']);

        $this->payOrder($component, $pickup->id);
        $this->assertDatabaseHas('orders', ['id' => $pickup->id, 'status' => 'pagada']);
        $this->assertDatabaseHas('order_payments', ['order_id' => $pickup->id, 'method' => 'efectivo', 'amount' => 100]);

        $component->call('reprintKitchenOrder', $delivery->id);
        $this->assertDatabaseHas('orders', ['id' => $delivery->id, 'status' => 'en_preparacion']);

        $component->call('markKitchenReady', $dineIn->id);
        $component->call('markKitchenReady', $dineIn->id);
        $this->assertDatabaseHas('orders', ['id' => $dineIn->id, 'status' => 'lista']);
    }

    public function test_paying_the_last_table_note_releases_the_table(): void
    {
        [$user, $register, $terminal, $mesa] = $this->posContext();
        $first = $this->kioskOrder($register->id, $user->id, $terminal->id, 'Primera nota', 'dine_in', $mesa->id, 'lista');
        $second = $this->kioskOrder($register->id, $user->id, $terminal->id, 'Última nota', 'dine_in', $mesa->id, 'lista');

        $this->actingAs($user);
        $component = Livewire::test(PointOfSale::class);

        $this->payOrder($component, $first->id);
        $this->assertDatabaseHas('mesas', ['id' => $mesa->id, 'status' => 'en_cuenta']);

        $this->payOrder($component, $second->id);
        $this->assertDatabaseHas('mesas', ['id' => $mesa->id, 'status' => 'disponible']);
    }

    public function test_kiosk_table_items_are_available_in_the_split_after_kitchen_marks_them_ready(): void
    {
        [$user, $register, $terminal, $mesa] = $this->posContext();
        $order = $this->kioskOrder($register->id, $user->id, $terminal->id, 'Cliente mesa', 'dine_in', $mesa->id, 'lista');
        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Hamburguesa kiosko',
            'product_price' => 120,
            'quantity' => 1,
            'subtotal' => 120,
        ]);

        Livewire::test(SplitCuenta::class, ['mesa' => $mesa])
            ->assertSee('Hamburguesa kiosko');
    }

    public function test_confirmed_split_is_persisted_and_exposed_in_pos_as_individual_accounts(): void
    {
        [$user, $register, $terminal, $mesa] = $this->posContext();
        $order = $this->kioskOrder($register->id, $user->id, $terminal->id, 'Cliente split', 'dine_in', $mesa->id, 'lista');
        $first = OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Pasta grande',
            'product_price' => 70,
            'quantity' => 1,
            'subtotal' => 70,
        ]);
        $second = OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Hamburguesa',
            'product_price' => 30,
            'quantity' => 1,
            'subtotal' => 30,
        ]);

        $this->actingAs($user);
        Livewire::test(SplitCuenta::class, ['mesa' => $mesa])
            ->call('assignItem', $first->id, 0)
            ->call('assignItem', $second->id, 1)
            ->call('confirm');

        $this->assertDatabaseHas('mesa_splits', ['mesa_id' => $mesa->id, 'status' => 'pendiente']);
        $split = MesaSplit::where('mesa_id', $mesa->id)->latest('id')->firstOrFail();
        $pos = Livewire::test(PointOfSale::class)
            ->assertSee('Cobrar cuenta dividida')
            ->assertSee('Cuenta 1')
            ->assertSee('Cuenta 2')
            ->assertSee('Cobrar')
            ->call('openMesaSplitPayModal', $split->id, 0)
            ->assertSet('showMesaPayModal', true)
            ->assertSet('mesaSplitId', $split->id)
            ->call('confirmMesaPayment')
            ->assertDispatched('pos-reprint-show', fn ($event, $params) =>
                str_contains($params['html_cliente'] ?? '', 'Pasta grande')
                && str_contains($params['html_cliente'] ?? '', 'Cuenta 1'));

        $this->assertDatabaseHas('mesa_splits', ['id' => $split->id, 'status' => 'parcial']);
        $this->assertDatabaseHas('order_payments', ['order_id' => $order->id, 'amount' => 70]);

        $pos->call('openMesaSplitPayModal', $split->id, 1)
            ->call('confirmMesaPayment')
            ->assertDispatched('pos-reprint-show', fn ($event, $params) =>
                str_contains($params['html_cliente'] ?? '', 'Hamburguesa')
                && str_contains($params['html_cliente'] ?? '', 'Cuenta 2'))
            ->assertDispatched('mesa-payment-completed', mesaId: $mesa->id, released: true)
            ->assertDontSee('Mesa '.$mesa->number);

        $this->assertDatabaseHas('mesa_splits', ['id' => $split->id, 'status' => 'completado']);
        $this->assertDatabaseHas('mesas', ['id' => $mesa->id, 'status' => 'disponible']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pagada']);
        $this->assertDatabaseMissing('mesa_splits', ['mesa_id' => $mesa->id, 'status' => 'pendiente']);
        $this->assertDatabaseMissing('mesa_splits', ['mesa_id' => $mesa->id, 'status' => 'parcial']);

        // Un split histórico inconsistente nunca debe volver a mostrar una
        // mesa que ya fue liberada y cuyas órdenes están pagadas.
        $split->update(['status' => 'pendiente']);
        Livewire::test(PointOfSale::class)
            ->assertDontSee('Mesa '.$mesa->number)
            ->assertSee('No hay mesas abiertas');
    }

    public function test_pos_layout_does_not_load_the_admin_menu_controller(): void
    {
        [$user] = $this->posContext();

        $this->actingAs($user)
            ->get(route('app.pos'))
            ->assertOk()
            ->assertDontSee('assets/vendor/js/menu.js', false);
    }

    public function test_pos_header_uses_the_configured_restaurant_logo(): void
    {
        [$user] = $this->posContext();
        BusinessSetting::current()->update([
            'business_name' => 'Restaurante Central',
            'logo_path' => 'business/restaurante-central.png',
        ]);

        $this->actingAs($user)
            ->get(route('app.pos'))
            ->assertOk()
            ->assertSee('class="pos-logo-img"', false)
            ->assertSee('/storage/business/restaurante-central.png', false)
            ->assertSee('Logo de Restaurante Central', false);
    }

    public function test_pos_only_exposes_orders_from_the_current_open_cash_register(): void
    {
        [$user, $currentRegister, $terminal, $mesa] = $this->posContext();
        $previousRegister = CashRegister::create([
            'name' => 'Caja anterior',
            'opened_by' => $user->id,
            'initial_amount' => 0,
            'opened_at' => now()->subHour(),
            'closed_at' => now()->subMinutes(30),
            'is_open' => false,
        ]);

        $this->kioskOrder($previousRegister->id, $user->id, $terminal->id, 'Pedido caja anterior', 'takeaway');
        $this->kioskOrder($currentRegister->id, $user->id, $terminal->id, 'Pedido caja vigente', 'takeaway');

        $oldMesa = Mesa::create([
            'area_id' => $mesa->area_id,
            'number' => 99,
            'capacity' => 2,
            'status' => 'en_cuenta',
        ]);
        $this->kioskOrder($previousRegister->id, $user->id, $terminal->id, 'Mesa de caja anterior', 'dine_in', $oldMesa->id, 'lista');

        $this->actingAs($user);
        Livewire::test(PointOfSale::class)
            ->assertSee('Pedido caja vigente')
            ->assertDontSee('Pedido caja anterior')
            ->assertDontSee('Mesa de caja anterior')
            ->assertDontSee('Mesa 99');
    }

    private function posContext(): array
    {
        $user = User::factory()->create();
        $register = CashRegister::create([
            'name' => 'Caja principal',
            'opened_by' => $user->id,
            'initial_amount' => 0,
            'opened_at' => now(),
            'is_open' => true,
        ]);
        $terminal = KioskTerminal::create([
            'name' => 'Kiosco principal',
            'token_hash' => hash('sha256', Str::random(64)),
            'user_id' => $user->id,
            'is_active' => true,
        ]);
        $area = Area::create(['name' => 'Salón']);
        $mesa = Mesa::create([
            'area_id' => $area->id,
            'number' => 7,
            'capacity' => 4,
            'status' => 'en_cuenta',
        ]);

        return [$user, $register, $terminal, $mesa];
    }

    private function kioskOrder(
        int $registerId,
        int $userId,
        int $terminalId,
        string $customerName,
        string $fulfillment,
        ?int $mesaId = null,
        string $status = 'pendiente',
    ): Order {
        return Order::create([
            'cash_register_id' => $registerId,
            'kiosk_terminal_id' => $terminalId,
            'public_token' => Str::random(64),
            'customer_name' => $customerName,
            'served_by' => $userId,
            'type' => $mesaId ? 'mesa' : ($fulfillment === 'delivery' ? 'delivery' : 'ventanilla'),
            'mesa_id' => $mesaId,
            'source' => 'kiosk',
            'fulfillment' => $fulfillment,
            'status' => $status,
            'subtotal' => 100,
            'total' => 100,
        ]);
    }

    private function payOrder($component, int $orderId): void
    {
        $component
            ->call('openPickupPayModal', $orderId)
            ->set('pickupPayAmount', '100')
            ->set('pickupPayReceived', '100')
            ->call('addPickupPayment')
            ->call('confirmPickupPayment');
    }
}
