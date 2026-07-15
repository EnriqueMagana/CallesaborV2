<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'usuarios'      => ['ver usuarios','crear usuarios','editar usuarios','eliminar usuarios','gestionar roles','gestionar permisos'],
            'menu'          => ['ver menu','crear platos','editar platos','eliminar platos','gestionar categorias','gestionar complementos','gestionar areas impresion'],
            'ordenes'       => ['ver ordenes','crear ordenes','editar ordenes','cancelar ordenes','eliminar items de ordenes','eliminar ordenes','cerrar ordenes'],
            'mesas'         => [
                'ver mesas',
                'asignar mesas',       // asignarse a una mesa disponible
                'ordenar mesas',       // crear órdenes desde una mesa (mesero)
                'cerrar mesas',        // cerrar cuenta (mesero solicita cobro)
                'liberar mesas',       // liberar / desasignar una mesa
                'reasignar mesas',     // reasignar mesa a otro mesero
                'cobrar mesas',        // marcar cuenta como cobrada (cajero/gerente)
                'dividir mesas',       // dividir cuenta en partes
                'gestionar mesas',     // crear/editar/eliminar mesas y áreas (admin)
                'gestionar grupos',    // agrupar/desagrupar mesas
            ],
            'caja'          => ['ver caja','abrir caja','cerrar caja','aplicar descuentos','anular pagos'],
            'reportes'      => ['ver reportes','exportar reportes','ver reportes financieros'],
            'configuracion' => ['ver configuracion','editar configuracion'],
        ];

        foreach ($permissions as $group => $perms) {
            foreach ($perms as $perm) {
                Permission::updateOrCreate(
                    ['name' => $perm, 'guard_name' => 'web'],
                    ['group' => $group]
                );
            }
        }

        // Super Admin — bypasa todos los gates con Spatie
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        // Admin — todo excepto permisos críticos
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::whereNotIn('name', ['gestionar permisos', 'editar configuracion'])->get());

        // Gerente
        $gerente = Role::firstOrCreate(['name' => 'gerente', 'guard_name' => 'web']);
        $gerente->syncPermissions([
            'ver usuarios',
            'ver menu','crear platos','editar platos','gestionar categorias',
            'ver ordenes','crear ordenes','editar ordenes','cancelar ordenes','cerrar ordenes',
            'ver mesas','asignar mesas','ordenar mesas','cerrar mesas',
            'liberar mesas','reasignar mesas','cobrar mesas','dividir mesas',
            'gestionar mesas','gestionar grupos',
            'ver caja','abrir caja','cerrar caja','aplicar descuentos',
            'ver reportes','exportar reportes',
        ]);

        // Cajero
        $cajero = Role::firstOrCreate(['name' => 'cajero', 'guard_name' => 'web']);
        $cajero->syncPermissions([
            'ver ordenes','crear ordenes','cerrar ordenes',
            'ver mesas','asignar mesas','ordenar mesas','cerrar mesas',
            'liberar mesas','reasignar mesas','cobrar mesas','dividir mesas',
            'ver caja','abrir caja','cerrar caja','aplicar descuentos',
        ]);

        // Mesero
        $mesero = Role::firstOrCreate(['name' => 'mesero', 'guard_name' => 'web']);
        $mesero->syncPermissions([
            'ver ordenes','crear ordenes','editar ordenes',
            'ver mesas','asignar mesas','ordenar mesas','cerrar mesas',
        ]);

        // Cocinero
        $cocinero = Role::firstOrCreate(['name' => 'cocinero', 'guard_name' => 'web']);
        $cocinero->syncPermissions(['ver ordenes','editar ordenes','ver menu']);

        $this->command->info('✅ Roles y permisos creados correctamente.');
    }
}
