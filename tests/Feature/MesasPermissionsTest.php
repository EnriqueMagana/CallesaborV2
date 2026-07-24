<?php

namespace Tests\Feature;

use App\Livewire\Mesas\GestionMesas;
use App\Livewire\Mesas\MesaOrden;
use App\Livewire\Mesas\SplitCuenta;
use App\Models\Area;
use App\Models\CashRegister;
use App\Models\Mesa;
use App\Models\MesaAssignment;
use App\Models\MesaSplit;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MesasPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['mesero', 'cajero', 'gerente', 'admin', 'super-admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_table_routes_require_the_permission_for_each_flow(): void
    {
        $employee = $this->employee([]);
        $area = $this->area();
        $mesa = $this->mesa($area, status: 'ocupada');
        $this->openRegister($employee);

        $this->actingAs($employee)
            ->get(route('app.mesas'))
            ->assertForbidden();

        $employee->givePermissionTo($this->permission('ver mesas'));
        $this->actingAs($employee)
            ->get(route('app.mesas'))
            ->assertOk()
            ->assertSee('mesas-initial-skeleton', false)
            ->assertSee('wire:submit="applySearch"', false)
            ->assertDontSee('wire:model.live.debounce.300ms="search"', false);

        $this->actingAs($employee)
            ->get(route('app.mesas.ordenar', $mesa))
            ->assertForbidden();

        $this->actingAs($employee)
            ->get(route('app.mesas.split', $mesa))
            ->assertForbidden();
    }

    public function test_area_and_table_crud_honor_granular_permissions(): void
    {
        $creator = $this->employee(['ver mesas', 'crear areas de mesas', 'crear mesas']);

        $component = Livewire::actingAs($creator)
            ->test(GestionMesas::class)
            ->call('openAreaModal')
            ->set('areaName', 'Patio')
            ->set('areaColor', '#123456')
            ->set('areaIcon', 'bx-sun')
            ->call('saveArea')
            ->assertHasNoErrors();

        $area = Area::where('name', 'Patio')->firstOrFail();

        $component
            ->call('openMesaModal')
            ->set('mesaNumber', '17')
            ->set('mesaName', 'Ventana')
            ->set('mesaCapacity', 6)
            ->set('mesaAreaId', $area->id)
            ->call('saveMesa')
            ->assertHasNoErrors();

        $mesa = Mesa::where('area_id', $area->id)->where('number', 17)->firstOrFail();

        Livewire::actingAs($creator)
            ->test(GestionMesas::class)
            ->call('openMesaModal', $mesa->id)
            ->assertForbidden();

        $editor = $this->employee(['ver mesas', 'editar areas de mesas', 'editar mesas']);
        Livewire::actingAs($editor)
            ->test(GestionMesas::class)
            ->call('openAreaModal', $area->id)
            ->set('areaName', 'Patio central')
            ->call('saveArea')
            ->call('openMesaModal', $mesa->id)
            ->set('mesaName', 'Ventanal')
            ->call('saveMesa')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('areas', ['id' => $area->id, 'name' => 'Patio central']);
        $this->assertDatabaseHas('mesas', ['id' => $mesa->id, 'name' => 'Ventanal']);

        $deleter = $this->employee(['ver mesas', 'eliminar areas de mesas', 'eliminar mesas']);
        Livewire::actingAs($deleter)
            ->test(GestionMesas::class)
            ->call('openDeleteMesa', $mesa->id)
            ->call('confirmDeleteMesa')
            ->call('deleteArea', $area->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('mesas', ['id' => $mesa->id]);
        $this->assertDatabaseMissing('areas', ['id' => $area->id]);
    }

    public function test_assignment_reassignment_release_close_and_status_changes_are_isolated(): void
    {
        $area = $this->area();
        $mesa = $this->mesa($area);
        $viewer = $this->employee(['ver mesas']);

        Livewire::actingAs($viewer)
            ->test(GestionMesas::class)
            ->call('openAssign', $mesa->id)
            ->assertForbidden();

        $waiter = $this->employee(['ver mesas', 'asignar mesas']);
        Livewire::actingAs($waiter)
            ->test(GestionMesas::class)
            ->call('openAssign', $mesa->id)
            ->call('confirmAssign')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('mesa_assignments', [
            'mesa_id' => $mesa->id,
            'user_id' => $waiter->id,
            'released_at' => null,
        ]);

        Livewire::actingAs($viewer)
            ->test(GestionMesas::class)
            ->call('openReassign', $mesa->id)
            ->assertForbidden();

        $newWaiter = $this->employee(['ver mesas']);
        $supervisor = $this->employee(['ver mesas', 'reasignar mesas']);
        Livewire::actingAs($supervisor)
            ->test(GestionMesas::class)
            ->call('openReassign', $mesa->id)
            ->set('reassignUserId', $newWaiter->id)
            ->call('confirmReassign')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('mesa_assignments', [
            'mesa_id' => $mesa->id,
            'user_id' => $newWaiter->id,
            'released_at' => null,
        ]);

        Livewire::actingAs($viewer)
            ->test(GestionMesas::class)
            ->call('openRelease', $mesa->id)
            ->assertForbidden();

        $releaser = $this->employee(['ver mesas', 'liberar mesas']);
        Livewire::actingAs($releaser)
            ->test(GestionMesas::class)
            ->call('openRelease', $mesa->id)
            ->call('confirmRelease')
            ->assertHasNoErrors();

        $this->assertSame('disponible', $mesa->fresh()->status);
        $this->assertNull(MesaAssignment::where('mesa_id', $mesa->id)->whereNull('released_at')->first());

        $statusOperator = $this->employee(['ver mesas', 'cambiar estado mesas']);
        Livewire::actingAs($statusOperator)
            ->test(GestionMesas::class)
            ->call('openStatusChange', $mesa->id, 'bloqueada')
            ->call('confirmStatusChange')
            ->assertHasNoErrors();

        $this->assertSame('bloqueada', $mesa->fresh()->status);

        $mesa->update(['status' => 'ocupada']);
        Livewire::actingAs($viewer)
            ->test(GestionMesas::class)
            ->call('closeMesa', $mesa->id)
            ->assertForbidden();

        $closer = $this->employee(['ver mesas', 'cerrar mesas']);
        Livewire::actingAs($closer)
            ->test(GestionMesas::class)
            ->call('closeMesa', $mesa->id);

        $this->assertSame('en_cuenta', $mesa->fresh()->status);
    }

    public function test_only_ordering_permission_can_create_a_table_order(): void
    {
        $area = $this->area();
        $operator = $this->employee(['ver mesas', 'ordenar mesas']);
        $mesa = $this->mesa($area, status: 'ocupada');
        $this->assign($mesa, $operator);
        $this->openRegister($operator);
        $product = Product::create(['name' => 'Taco de prueba', 'price' => 25, 'is_active' => true]);

        Livewire::actingAs($operator)
            ->test(MesaOrden::class, ['mesa' => $mesa])
            ->set('cart', [[
                'cart_id' => 'test-line',
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => 25,
                'unit_total' => 25,
                'qty' => 2,
                'notes' => '',
                'addons' => [],
                'ingredients' => [],
            ]])
            ->call('placeOrder')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('orders', [
            'mesa_id' => $mesa->id,
            'served_by' => $operator->id,
            'type' => 'mesa',
            'total' => 50,
        ]);

        $viewer = $this->employee(['ver mesas']);
        $this->assign($mesa, $viewer);

        Livewire::actingAs($viewer)
            ->test(MesaOrden::class, ['mesa' => $mesa])
            ->assertForbidden();
    }

    public function test_split_creation_and_cancellation_use_separate_permissions(): void
    {
        $area = $this->area();
        $divider = $this->employee(['ver mesas', 'dividir mesas']);
        $mesa = $this->mesa($area, status: 'ocupada');
        $register = $this->openRegister($divider);
        $order = Order::create([
            'cash_register_id' => $register->id,
            'mesa_id' => $mesa->id,
            'served_by' => $divider->id,
            'type' => 'mesa',
            'status' => 'pendiente',
            'subtotal' => 80,
            'total' => 80,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Consumo de prueba',
            'product_price' => 80,
            'quantity' => 1,
            'subtotal' => 80,
        ]);

        Livewire::actingAs($divider)
            ->test(SplitCuenta::class, ['mesa' => $mesa])
            ->set('mode', 'igual')
            ->set('equalParts', 2)
            ->call('confirm')
            ->assertHasNoErrors()
            ->call('requestCancelConfirm')
            ->assertForbidden();

        $split = MesaSplit::where('mesa_id', $mesa->id)->firstOrFail();

        $canceller = $this->employee([
            'ver mesas',
            'dividir mesas',
            'cancelar divisiones mesas',
        ]);
        Livewire::actingAs($canceller)
            ->test(SplitCuenta::class, ['mesa' => $mesa])
            ->call('requestCancelConfirm')
            ->call('cancelConfirm')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('mesa_splits', ['id' => $split->id]);
        $this->assertSame('ocupada', $mesa->fresh()->status);
    }

    private function employee(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            $this->permission($permission);
        }
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function permission(string $name): Permission
    {
        return Permission::findOrCreate($name, 'web');
    }

    private function area(): Area
    {
        return Area::create(['name' => 'Salón']);
    }

    private function mesa(Area $area, string $status = 'disponible'): Mesa
    {
        return Mesa::create([
            'area_id' => $area->id,
            'number' => ((int) Mesa::max('number')) + 1,
            'capacity' => 4,
            'status' => $status,
        ]);
    }

    private function assign(Mesa $mesa, User $user): MesaAssignment
    {
        return MesaAssignment::create([
            'mesa_id' => $mesa->id,
            'user_id' => $user->id,
            'assigned_by' => $user->id,
            'assigned_at' => now(),
        ]);
    }

    private function openRegister(User $user): CashRegister
    {
        return CashRegister::create([
            'name' => 'Caja de pruebas',
            'opened_by' => $user->id,
            'initial_amount' => 0,
            'opened_at' => now(),
            'is_open' => true,
        ]);
    }
}
