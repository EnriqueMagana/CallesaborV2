<?php

namespace Tests\Feature;

use App\Livewire\Mesas\GestionMesas;
use App\Models\Area;
use App\Models\CashRegister;
use App\Models\Mesa;
use App\Models\MesaAssignment;
use App\Models\MesaSplit;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\MesaServiceManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MesaActiveAccountPrintingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_operator_can_preview_only_the_current_full_account_from_table_detail(): void
    {
        [$operator, $mesa] = $this->activeAccountContext();

        Livewire::actingAs($operator)
            ->test(GestionMesas::class)
            ->call('openDetail', $mesa->id)
            ->assertSee('Imprimir cuenta')
            ->assertSee('Cuenta completa')
            ->call('printActiveMesaAccount', $mesa->id)
            ->assertDispatched('mesa-account-ticket-preview', fn ($event, $params) => str_contains($params['html'] ?? '', 'Taco de prueba')
                && str_contains($params['html'] ?? '', '120.00'));
    }

    public function test_split_account_prints_each_pending_snapshot_and_rejects_paid_accounts(): void
    {
        [$operator, $mesa, $service] = $this->activeAccountContext();

        $split = MesaSplit::create([
            'mesa_id' => $mesa->id,
            'mesa_service_id' => $service->id,
            'created_by' => $operator->id,
            'status' => 'parcial',
            'total' => 120,
            'split_data' => [
                [
                    'label' => 'Cliente Ana',
                    'items' => [['id' => 1, 'name' => 'Taco de Ana', 'qty' => 1, 'subtotal' => 50]],
                    'total' => 50,
                    'paid' => false,
                ],
                [
                    'label' => 'Cliente Luis',
                    'items' => [['id' => 2, 'name' => 'Taco de Luis', 'qty' => 1, 'subtotal' => 70]],
                    'total' => 70,
                    'paid' => true,
                ],
            ],
        ]);

        Livewire::actingAs($operator)
            ->test(GestionMesas::class)
            ->call('openDetail', $mesa->id)
            ->assertSee('Cliente Ana')
            ->assertSee('Cliente Luis')
            ->call('printActiveMesaAccount', $mesa->id, $split->id, 0)
            ->assertDispatched('mesa-account-ticket-preview', fn ($event, $params) => str_contains($params['html'] ?? '', 'Taco de Ana')
                && ! str_contains($params['html'] ?? '', 'Taco de Luis'));

        Livewire::actingAs($operator)
            ->test(GestionMesas::class)
            ->call('printActiveMesaAccount', $mesa->id, $split->id, 1)
            ->assertNotDispatched('mesa-account-ticket-preview')
            ->assertDispatched('notify', fn ($event, $params) => str_contains($params['message'] ?? '', 'ya fue pagada'));
    }

    public function test_printing_requires_permission_and_an_in_account_active_service(): void
    {
        [$operator, $mesa, $service] = $this->activeAccountContext();
        $viewer = $this->employee(['ver mesas']);

        Livewire::actingAs($viewer)
            ->test(GestionMesas::class)
            ->call('printActiveMesaAccount', $mesa->id)
            ->assertForbidden();

        $mesa->update(['status' => 'ocupada']);
        $service->update(['status' => 'abierta']);

        Livewire::actingAs($operator)
            ->test(GestionMesas::class)
            ->call('printActiveMesaAccount', $mesa->id)
            ->assertNotDispatched('mesa-account-ticket-preview')
            ->assertDispatched('notify');
    }

    private function activeAccountContext(): array
    {
        $operator = $this->employee(['ver mesas', 'reimprimir tickets']);
        $register = CashRegister::create([
            'name' => 'Caja de pruebas',
            'opened_by' => $operator->id,
            'initial_amount' => 0,
            'opened_at' => now(),
            'is_open' => true,
        ]);
        $area = Area::create(['name' => 'Salón']);
        $mesa = Mesa::create([
            'area_id' => $area->id,
            'number' => 8,
            'capacity' => 4,
            'status' => 'ocupada',
        ]);
        $order = Order::create([
            'cash_register_id' => $register->id,
            'mesa_id' => $mesa->id,
            'served_by' => $operator->id,
            'type' => 'mesa',
            'status' => 'lista',
            'subtotal' => 120,
            'total' => 120,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Taco de prueba',
            'product_price' => 60,
            'quantity' => 2,
            'subtotal' => 120,
        ]);

        $service = app(MesaServiceManager::class)->resolveOrCreate($mesa, $register, $operator->id);
        app(MesaServiceManager::class)->markInAccount($mesa, $register->id);
        $mesa->update(['status' => 'en_cuenta']);
        MesaAssignment::create([
            'mesa_id' => $mesa->id,
            'mesa_service_id' => $service->id,
            'user_id' => $operator->id,
            'assigned_by' => $operator->id,
            'assigned_at' => now(),
        ]);

        return [$operator, $mesa->fresh(), $service->fresh()];
    }

    private function employee(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $user->givePermissionTo($permissions);

        return $user;
    }
}
