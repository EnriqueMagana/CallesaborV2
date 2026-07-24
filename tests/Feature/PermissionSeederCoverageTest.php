<?php

namespace Tests\Feature;

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
        $this->assertFalse(Role::findByName('mesero')->hasPermissionTo('registrar gastos'));
        $this->assertFalse(Role::findByName('repartidor')->hasPermissionTo('editar mesas'));
    }
}
