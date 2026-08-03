<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionSeederCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_seeder_registers_every_granular_operational_action(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $expected = [
            'punto_venta' => ['usar punto de venta'],
            'mesas' => [
                'crear areas de mesas', 'editar areas de mesas', 'eliminar areas de mesas',
                'crear mesas', 'editar mesas', 'eliminar mesas', 'cambiar estado mesas',
                'cancelar divisiones mesas',
            ],
            'reservas' => [
                'ver reservas', 'crear reservas', 'editar reservas',
                'cambiar estado reservas', 'cancelar reservas',
            ],
            'inventario' => ['editar compras inventario', 'eliminar compras inventario'],
            'caja' => ['registrar gastos'],
            'ordenes' => ['reimprimir tickets'],
            'clientes' => ['ver clientes', 'crear clientes', 'editar clientes', 'eliminar clientes'],
        ];

        foreach ($expected as $group => $permissions) {
            foreach ($permissions as $permission) {
                $this->assertDatabaseHas('permissions', [
                    'name' => $permission,
                    'guard_name' => 'web',
                    'group' => $group,
                ]);
            }
        }
    }

    public function test_default_operational_roles_receive_only_their_expected_new_capabilities(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertTrue(Role::findByName('gerente')->hasPermissionTo('editar compras inventario'));
        $this->assertTrue(Role::findByName('cajero')->hasPermissionTo('registrar gastos'));
        $this->assertTrue(Role::findByName('cajero')->hasPermissionTo('editar clientes'));
        $this->assertFalse(Role::findByName('cajero')->hasPermissionTo('eliminar clientes'));
        $this->assertTrue(Role::findByName('cocinero')->hasPermissionTo('reimprimir tickets'));
        $this->assertTrue(Role::findByName('cajero')->hasPermissionTo('usar punto de venta'));
        $this->assertTrue(Role::findByName('mesero')->hasPermissionTo('reimprimir tickets'));
        $this->assertTrue(Role::findByName('mesero')->hasPermissionTo('dividir mesas'));
        $this->assertTrue(Role::findByName('mesero')->hasPermissionTo('cancelar divisiones mesas'));
        $this->assertTrue(Role::findByName('mesero')->hasPermissionTo('reasignar mesas'));
        $this->assertTrue(Role::findByName('mesero')->hasPermissionTo('gestionar grupos'));
        $this->assertFalse(Role::findByName('mesero')->hasPermissionTo('usar punto de venta'));
        $this->assertFalse(Role::findByName('mesero')->hasPermissionTo('registrar gastos'));
        $this->assertFalse(Role::findByName('repartidor')->hasPermissionTo('editar mesas'));
    }

    public function test_waiter_order_permissions_do_not_unlock_the_point_of_sale_module(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $waiter = User::factory()->create();
        $waiter->assignRole('mesero');
        $cashier = User::factory()->create();
        $cashier->assignRole('cajero');

        $this->actingAs($waiter)
            ->get(route('app.pos'))
            ->assertForbidden();

        $this->actingAs($cashier)
            ->get(route('app.pos'))
            ->assertOk();
    }
}
