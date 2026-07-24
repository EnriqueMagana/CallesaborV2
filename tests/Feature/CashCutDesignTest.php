<?php

namespace Tests\Feature;

use App\Livewire\Caja\CorteDeCaja;
use App\Models\Area;
use App\Models\CashRegister;
use App\Models\Mesa;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CashCutDesignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_authenticated_user_can_see_the_redesigned_cash_cut(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('cerrar caja');
        CashRegister::create([
            'name' => 'Caja principal',
            'opened_by' => $user->id,
            'initial_amount' => 500,
            'opened_at' => now(),
            'is_open' => true,
        ]);

        $this->actingAs($user)
            ->get(route('app.caja.corte'))
            ->assertOk()
            ->assertSee('Conciliación de efectivo')
            ->assertSee('Ventas por área')
            ->assertSee('Efectivo esperado');
    }

    public function test_cash_cut_confirmation_keeps_the_expected_summary(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('cerrar caja');
        CashRegister::create([
            'name' => 'Caja principal',
            'opened_by' => $user->id,
            'initial_amount' => 500,
            'opened_at' => now(),
            'is_open' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(CorteDeCaja::class)
            ->set('declaredCash', '500.00')
            ->call('confirmCut')
            ->assertHasNoErrors()
            ->assertSet('showConfirm', true)
            ->assertSee('Confirmación final')
            ->assertSee('Cuadra exacto');
    }

    public function test_unpaid_pos_and_kiosk_orders_are_listed_and_block_every_cut_action(): void
    {
        [$user, $register] = $this->cashContext();

        $counter = $this->createPendingOrder($register, $user, [
            'customer_name' => 'Cliente ventanilla',
            'source' => 'pos',
            'type' => 'ventanilla',
            'status' => 'lista',
            'total' => 185.50,
        ]);
        $kiosk = $this->createPendingOrder($register, $user, [
            'customer_name' => 'Cliente kiosco',
            'source' => 'kiosk',
            'type' => 'ventanilla',
            'status' => 'pendiente',
            'total' => 92,
        ]);

        $this->actingAs($user);

        Livewire::test(CorteDeCaja::class)
            ->set('declaredCash', '500.00')
            ->assertSee("Orden #{$counter->display_folio}")
            ->assertSee("Orden #{$kiosk->display_folio}")
            ->assertSee('Pedidos de kiosco')
            ->assertSee('$277.50 pendientes de conciliación')
            ->call('confirmCut')
            ->assertHasErrors(['cutBlockers'])
            ->assertSet('showConfirm', false)
            ->call('generateCut')
            ->assertHasErrors(['cutBlockers']);

        $this->assertTrue($register->fresh()->is_open);
        $this->assertDatabaseCount('cash_register_cuts', 0);
    }

    public function test_occupied_table_blocks_the_cut_until_it_is_released(): void
    {
        [$user, $register] = $this->cashContext();
        $area = Area::create(['name' => 'Terraza']);
        $mesa = Mesa::create([
            'area_id' => $area->id,
            'number' => 7,
            'capacity' => 4,
            'status' => 'ocupada',
        ]);
        $tableOrder = $this->createPendingOrder($register, $user, [
            'customer_name' => 'Cuenta de terraza',
            'type' => 'mesa',
            'mesa_id' => $mesa->id,
            'status' => 'entregada',
            'total' => 240,
        ]);

        $this->actingAs($user);

        $component = Livewire::test(CorteDeCaja::class)
            ->set('declaredCash', '500.00')
            ->assertSee('Mesa 7')
            ->assertSee("Orden #{$tableOrder->display_folio}")
            ->assertSee('Pedidos de mesa')
            ->assertSee('Mesas pendientes de liberar')
            ->call('confirmCut')
            ->assertHasErrors(['cutBlockers']);

        $mesa->update(['status' => 'disponible']);
        $tableOrder->update(['status' => 'pagada', 'paid_at' => now()]);

        $component
            ->call('$refresh')
            ->assertSee('Operación lista para conciliar')
            ->call('confirmCut')
            ->assertHasNoErrors()
            ->assertSet('showConfirm', true)
            ->call('generateCut')
            ->assertHasNoErrors()
            ->assertSet('cutDone', true);

        $this->assertFalse($register->fresh()->is_open);
        $this->assertDatabaseCount('cash_register_cuts', 1);
    }

    public function test_a_new_pending_order_between_review_and_confirmation_prevents_the_cut(): void
    {
        [$user, $register] = $this->cashContext();
        $this->actingAs($user);

        $component = Livewire::test(CorteDeCaja::class)
            ->set('declaredCash', '500.00')
            ->call('confirmCut')
            ->assertHasNoErrors()
            ->assertSet('showConfirm', true);

        $this->createPendingOrder($register, $user, [
            'customer_name' => 'Pedido de último minuto',
            'source' => 'kiosk',
            'status' => 'pendiente',
        ]);

        $component
            ->call('generateCut')
            ->assertHasErrors(['cutBlockers'])
            ->assertSet('showConfirm', false);

        $this->assertTrue($register->fresh()->is_open);
        $this->assertDatabaseMissing('cash_register_cuts', ['cash_register_id' => $register->id]);
    }

    private function cashContext(): array
    {
        $user = User::factory()->create();
        $user->givePermissionTo('cerrar caja');
        $register = CashRegister::create([
            'name' => 'Caja principal',
            'opened_by' => $user->id,
            'initial_amount' => 500,
            'opened_at' => now(),
            'is_open' => true,
        ]);

        return [$user, $register];
    }

    private function createPendingOrder(CashRegister $register, User $user, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'cash_register_id' => $register->id,
            'customer_name' => 'Cliente pendiente',
            'served_by' => $user->id,
            'type' => 'ventanilla',
            'source' => 'pos',
            'status' => 'pendiente',
            'subtotal' => 100,
            'total' => 100,
        ], $overrides));
    }
}
