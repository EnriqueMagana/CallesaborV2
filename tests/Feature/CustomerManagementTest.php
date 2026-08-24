<?php

namespace Tests\Feature;

use App\Livewire\Customers\CustomerManager;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SidebarMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_customer_route_requires_authentication_and_view_permission(): void
    {
        $this->get(route('app.clientes'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->get(route('app.clientes'))
            ->assertForbidden();

        $viewer = $this->employee(['ver clientes']);
        $this->actingAs($viewer)
            ->get(route('app.clientes'))
            ->assertOk()
            ->assertSee('Mis clientes')
            ->assertDontSee('Agregar cliente');
    }

    public function test_viewer_can_search_and_open_details_without_mutation_actions(): void
    {
        $viewer = $this->employee(['ver clientes']);
        $customer = Customer::create([
            'name' => 'Andrea Cliente',
            'phone' => '999 123 4567',
            'email' => 'andrea@example.com',
            'address' => 'Centro',
            'references' => 'Portón azul',
        ]);

        Livewire::actingAs($viewer)
            ->test(CustomerManager::class)
            ->set('search', '999 123')
            ->assertSee('Andrea Cliente')
            ->call('openDetails', $customer->id)
            ->assertSee('andrea@example.com')
            ->assertSee('Portón azul')
            ->assertDontSee('Editar cliente')
            ->assertDontSee('Eliminar');

        Livewire::actingAs($viewer)
            ->test(CustomerManager::class)
            ->call('openCreate')
            ->assertForbidden();
    }

    public function test_create_edit_and_delete_are_independent_permissions(): void
    {
        $creator = $this->employee(['ver clientes', 'crear clientes']);
        Livewire::actingAs($creator)
            ->test(CustomerManager::class)
            ->call('openCreate')
            ->assertSee('Colonia o zona')
            ->set('name', 'Cliente Nuevo')
            ->set('phone', '+52 999 555 1122')
            ->set('email', 'NUEVO@EXAMPLE.COM')
            ->set('address', 'Calle Principal 25')
            ->set('neighborhood', 'Centro')
            ->set('references', 'Frente al parque')
            ->call('save')
            ->assertHasNoErrors();

        $customer = Customer::where('phone', '+52 999 555 1122')->firstOrFail();
        $this->assertSame('nuevo@example.com', $customer->email);
        $this->assertSame('Centro', $customer->neighborhood);

        Livewire::actingAs($creator)
            ->test(CustomerManager::class)
            ->call('openEdit', $customer->id)
            ->assertForbidden();

        $editor = $this->employee(['ver clientes', 'editar clientes']);
        Livewire::actingAs($editor)
            ->test(CustomerManager::class)
            ->call('openEdit', $customer->id)
            ->set('name', 'Cliente Actualizado')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Cliente Actualizado', $customer->fresh()->name);

        Livewire::actingAs($editor)
            ->test(CustomerManager::class)
            ->call('deleteCustomer', $customer->id)
            ->assertForbidden();

        $deleter = $this->employee(['ver clientes', 'eliminar clientes']);
        Livewire::actingAs($deleter)
            ->test(CustomerManager::class)
            ->call('deleteCustomer', $customer->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    public function test_deleting_customer_preserves_order_history_and_unlinks_relation(): void
    {
        $manager = $this->employee(['ver clientes', 'eliminar clientes']);
        $customer = Customer::create([
            'name' => 'Cliente con historial',
            'phone' => '9990001122',
            'email' => 'historial@example.com',
        ]);
        $register = CashRegister::create([
            'name' => 'Caja',
            'opened_by' => $manager->id,
            'initial_amount' => 0,
            'opened_at' => now(),
            'is_open' => true,
        ]);
        $order = Order::create([
            'cash_register_id' => $register->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'served_by' => $manager->id,
            'type' => 'ventanilla',
            'status' => 'pagada',
            'subtotal' => 150,
            'total' => 150,
            'paid_at' => now(),
        ]);

        Livewire::actingAs($manager)
            ->test(CustomerManager::class)
            ->call('deleteCustomer', $customer->id);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'customer_id' => null,
            'customer_name' => 'Cliente con historial',
        ]);
    }

    public function test_financial_total_is_only_visible_with_financial_permission(): void
    {
        $customer = Customer::create(['name' => 'Cliente financiero', 'phone' => '9990003344']);
        $seller = User::factory()->create();
        $register = CashRegister::create([
            'name' => 'Caja',
            'opened_by' => $seller->id,
            'initial_amount' => 0,
            'opened_at' => now(),
            'is_open' => true,
        ]);
        Order::create([
            'cash_register_id' => $register->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'served_by' => $seller->id,
            'type' => 'ventanilla',
            'status' => 'pagada',
            'subtotal' => 432.10,
            'total' => 432.10,
            'paid_at' => now(),
        ]);

        $viewer = $this->employee(['ver clientes']);
        Livewire::actingAs($viewer)
            ->test(CustomerManager::class)
            ->call('openDetails', $customer->id)
            ->assertDontSee('Total comprado')
            ->assertDontSee('$432.10');

        $financial = $this->employee(['ver clientes', 'ver reportes financieros']);
        Livewire::actingAs($financial)
            ->test(CustomerManager::class)
            ->call('openDetails', $customer->id)
            ->assertSee('Total comprado')
            ->assertSee('$432.10');
    }

    public function test_customer_permissions_and_sidebar_entry_are_seeded(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SidebarMenuSeeder::class);

        foreach (['ver clientes', 'crear clientes', 'editar clientes', 'eliminar clientes'] as $permission) {
            $this->assertDatabaseHas('permissions', [
                'name' => $permission,
                'group' => 'clientes',
            ]);
        }

        $this->assertDatabaseHas('sidebar_menu_items', [
            'system_key' => 'operations.customers',
            'route_name' => 'app.clientes',
            'permission' => 'ver clientes',
            'is_active' => true,
        ]);
    }

    public function test_validation_messages_are_humanized(): void
    {
        $creator = $this->employee(['ver clientes', 'crear clientes']);

        Livewire::actingAs($creator)
            ->test(CustomerManager::class)
            ->call('openCreate')
            ->set('name', '')
            ->set('phone', 'abcdefgh')
            ->set('email', 'correo-invalido')
            ->call('save')
            ->assertHasErrors(['name', 'phone', 'email', 'neighborhood'])
            ->assertSee('Escribe el nombre del cliente.')
            ->assertSee('Escribe la colonia o zona del cliente.')
            ->assertSee('Usa únicamente números');
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
