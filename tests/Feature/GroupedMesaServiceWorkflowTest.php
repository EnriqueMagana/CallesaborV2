<?php

namespace Tests\Feature;

use App\Livewire\Pos\PointOfSale;
use App\Models\Area;
use App\Models\CashRegister;
use App\Models\Mesa;
use App\Models\MesaGroup;
use App\Models\Order;
use App\Models\User;
use App\Services\MesaServiceManager;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GroupedMesaServiceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_members_resolve_to_one_operational_service(): void
    {
        [$user, $register, $group, $first, $second] = $this->context();
        $manager = app(MesaServiceManager::class);

        $serviceFromFirst = $manager->resolveOrCreate($first, $register, $user->id);
        $serviceFromSecond = $manager->resolveOrCreate($second, $register, $user->id);

        $this->assertSame($serviceFromFirst->id, $serviceFromSecond->id);
        $this->assertSame('Grupo Terraza', $serviceFromFirst->service_label);
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $serviceFromFirst->mesas()->pluck('mesas.id')->all()
        );
    }

    public function test_group_is_tracked_and_paid_as_one_service_then_moves_to_history(): void
    {
        [$user, $register, $group, $first, $second] = $this->context();
        $service = app(MesaServiceManager::class)->resolveOrCreate($first, $register, $user->id);
        $service->update([
            'status' => 'en_cuenta',
            'in_account_at' => now(),
            'opened_at' => now()->subMinutes(18)->startOfMinute(),
        ]);
        $service->refresh();

        foreach ([101, 104, 106] as $folio) {
            Order::create([
                'cash_register_id' => $register->id,
                'mesa_id' => $first->id,
                'mesa_service_id' => $service->id,
                'served_by' => $user->id,
                'folio' => $folio,
                'type' => 'mesa',
                'status' => 'lista',
                'subtotal' => 50,
                'total' => 50,
            ]);
        }

        Livewire::actingAs($user)
            ->test(PointOfSale::class)
            ->call('openTableTracking')
            ->assertSee('Grupo Terraza')
            ->assertSee('Mesa 1')
            ->assertSee('Mesa 2')
            ->assertSee('Orden #101')
            ->assertSee($service->opened_at->format('g:i A'))
            ->assertSee('18min activa')
            ->assertSee('Reimprimir cocina')
            ->assertDontSee('Lista para cobrar')
            ->call('openMesaPayModal', $first->id)
            ->set('mesaPayAmount', '150')
            ->set('mesaPayReceived', '150')
            ->call('addMesaPayment')
            ->call('confirmMesaPayment')
            ->set('reprintType', 'mesas')
            ->assertSee('Servicio pagado')
            ->assertSee('Grupo Terraza');

        $this->assertDatabaseHas('mesa_services', [
            'id' => $service->id,
            'status' => 'pagada',
            'group_name_snapshot' => 'Grupo Terraza',
        ]);
        $this->assertDatabaseHas('mesas', ['id' => $first->id, 'status' => 'disponible']);
        $this->assertDatabaseHas('mesas', ['id' => $second->id, 'status' => 'disponible']);
        $this->assertDatabaseMissing('mesa_groups', ['id' => $group->id]);
    }

    public function test_tracking_accordion_keeps_many_table_services_reachable(): void
    {
        [$user, $register, , $first] = $this->context();
        $manager = app(MesaServiceManager::class);
        $manager->resolveOrCreate($first, $register, $user->id);
        $area = $first->area;

        foreach (range(10, 21) as $number) {
            $mesa = Mesa::create([
                'area_id' => $area->id,
                'number' => $number,
                'capacity' => 4,
                'status' => 'en_cuenta',
            ]);
            $manager->resolveOrCreate($mesa, $register, $user->id);
        }

        $component = Livewire::actingAs($user)
            ->test(PointOfSale::class)
            ->call('openTableTracking')
            ->assertSeeHtml('class="panel-body pos-area-panel__body pos-tracking-accordion"')
            ->assertSeeHtml('class="pos-tracking-service__toggle"')
            ->assertSeeHtml('wire:loading.flex wire:target="openTableTracking"')
            ->assertDontSeeHtml('wire:target="openTableTracking,refreshTableTracking,markKitchenReady"')
            ->assertSeeHtml('aria-controls="tracking-service-content-')
            ->assertSeeHtml('x-show="openService ===');

        foreach (range(10, 21) as $number) {
            $component->assertSee('Mesa '.$number);
        }
    }

    public function test_grouped_table_payment_uses_the_complete_service_total(): void
    {
        [$user, $register, , $first, $second] = $this->context();
        $service = app(MesaServiceManager::class)->resolveOrCreate($first, $register, $user->id);
        $service->update(['status' => 'en_cuenta', 'in_account_at' => now()]);

        $firstOrder = Order::create([
            'cash_register_id' => $register->id,
            'mesa_id' => $first->id,
            'mesa_service_id' => $service->id,
            'served_by' => $user->id,
            'type' => 'mesa',
            'status' => 'lista',
            'subtotal' => 125,
            'total' => 125,
        ]);
        $secondOrder = Order::create([
            'cash_register_id' => $register->id,
            'mesa_id' => $second->id,
            'mesa_service_id' => $service->id,
            'served_by' => $user->id,
            'type' => 'mesa',
            'status' => 'lista',
            'subtotal' => 75,
            'total' => 75,
        ]);

        Livewire::actingAs($user)
            ->test(PointOfSale::class)
            ->call('openTablesBilling')
            ->assertSeeHtml('class="pos-table-billing-toggle"')
            ->call('openMesaPayModal', $first->id)
            ->assertSee('$200.00')
            ->set('mesaPayAmount', '200')
            ->set('mesaPayReceived', '200')
            ->call('addMesaPayment')
            ->call('confirmMesaPayment')
            ->assertDispatched('notify');

        $this->assertDatabaseHas('orders', ['id' => $firstOrder->id, 'status' => 'pagada']);
        $this->assertDatabaseHas('orders', ['id' => $secondOrder->id, 'status' => 'pagada']);
        $this->assertDatabaseHas('order_payments', ['order_id' => $firstOrder->id, 'amount' => 125]);
        $this->assertDatabaseHas('order_payments', ['order_id' => $secondOrder->id, 'amount' => 75]);
    }

    public function test_empty_grouped_service_is_visible_and_cancellation_releases_every_member(): void
    {
        [$user, $register, $group, $first, $second] = $this->context();
        $service = app(MesaServiceManager::class)->resolveOrCreate($first, $register, $user->id);
        $service->update(['status' => 'en_cuenta', 'in_account_at' => now()]);

        Livewire::actingAs($user)
            ->test(PointOfSale::class)
            ->call('openTablesBilling')
            ->assertSee('Grupo Terraza')
            ->assertSee('Servicio sin consumo')
            ->call('discardEmptyMesaAccount', $first->id)
            ->assertDispatched('notify');

        $this->assertDatabaseHas('mesa_services', ['id' => $service->id, 'status' => 'liberada']);
        $this->assertDatabaseHas('mesas', ['id' => $first->id, 'status' => 'disponible', 'mesa_group_id' => null]);
        $this->assertDatabaseHas('mesas', ['id' => $second->id, 'status' => 'disponible', 'mesa_group_id' => null]);
        $this->assertDatabaseMissing('mesa_groups', ['id' => $group->id]);
    }

    public function test_grouped_service_cannot_be_discarded_when_a_secondary_table_has_an_active_order(): void
    {
        [$user, $register, $group, $first, $second] = $this->context();
        $service = app(MesaServiceManager::class)->resolveOrCreate($first, $register, $user->id);
        $service->update(['status' => 'en_cuenta', 'in_account_at' => now()]);
        $order = Order::create([
            'cash_register_id' => $register->id,
            'mesa_id' => $second->id,
            'mesa_service_id' => $service->id,
            'served_by' => $user->id,
            'type' => 'mesa',
            'status' => 'lista',
            'subtotal' => 80,
            'total' => 80,
        ]);

        Livewire::actingAs($user)
            ->test(PointOfSale::class)
            ->call('discardEmptyMesaAccount', $first->id)
            ->assertDispatched('notify');

        $this->assertDatabaseHas('mesa_services', ['id' => $service->id, 'status' => 'en_cuenta']);
        $this->assertDatabaseHas('mesas', ['id' => $first->id, 'status' => 'en_cuenta', 'mesa_group_id' => $group->id]);
        $this->assertDatabaseHas('mesas', ['id' => $second->id, 'status' => 'en_cuenta', 'mesa_group_id' => $group->id]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'lista']);
    }

    private function context(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('owner');
        $register = CashRegister::create([
            'name' => 'Caja servicio',
            'opened_by' => $user->id,
            'initial_amount' => 0,
            'opened_at' => now(),
            'is_open' => true,
        ]);
        $area = Area::create(['name' => 'Terraza']);
        $group = MesaGroup::create(['area_id' => $area->id, 'name' => 'Grupo Terraza']);
        $first = Mesa::create([
            'area_id' => $area->id,
            'mesa_group_id' => $group->id,
            'number' => 1,
            'capacity' => 4,
            'status' => 'en_cuenta',
        ]);
        $second = Mesa::create([
            'area_id' => $area->id,
            'mesa_group_id' => $group->id,
            'number' => 2,
            'capacity' => 4,
            'status' => 'en_cuenta',
        ]);

        return [$user, $register, $group, $first, $second];
    }
}
