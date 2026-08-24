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
            'punto_venta' => ['usar punto de venta'],
            'usuarios' => ['ver usuarios', 'crear usuarios', 'editar usuarios', 'eliminar usuarios', 'bloquear usuarios', 'gestionar roles', 'gestionar permisos'],
            'clientes' => ['ver clientes', 'crear clientes', 'editar clientes', 'eliminar clientes'],
            'menu' => ['ver menu', 'crear platos', 'editar platos', 'eliminar platos', 'gestionar categorias', 'gestionar complementos', 'gestionar areas impresion', 'gestionar menu digital'],
            'ordenes' => ['ver ordenes', 'crear ordenes', 'editar ordenes', 'cancelar ordenes', 'eliminar items de ordenes', 'eliminar ordenes', 'cerrar ordenes', 'reimprimir tickets'],
            'mesas' => [
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
                'crear areas de mesas',
                'editar areas de mesas',
                'eliminar areas de mesas',
                'crear mesas',
                'editar mesas',
                'eliminar mesas',
                'cambiar estado mesas',
                'cancelar divisiones mesas',
            ],
            'caja' => ['ver caja', 'abrir caja', 'cerrar caja', 'aplicar descuentos', 'anular pagos', 'registrar gastos'],
            'reportes' => ['ver reportes', 'exportar reportes', 'ver reportes financieros'],
            'configuracion' => [
                'ver configuracion', 'editar configuracion', 'gestionar configuracion negocio',
                'ver menu sidebar', 'crear menu sidebar', 'editar menu sidebar', 'eliminar menu sidebar',
                'gestionar bloqueos por caja',
            ],
            'kiosco' => ['gestionar kioscos'],
            'delivery' => ['ver delivery', 'tomar delivery', 'entregar delivery', 'gestionar delivery'],
            'reservas' => ['ver reservas', 'crear reservas', 'editar reservas', 'cambiar estado reservas', 'cancelar reservas'],
            'inventario' => [
                'ver inventario',
                'gestionar insumos',
                'ajustar inventario',
                'generar compras inventario',
                'editar compras inventario',
                'eliminar compras inventario',
                'recepcionar compras inventario',
            ],
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

        // Owner — propietario del negocio, con bypass global desde AppServiceProvider.
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);

        // Admin — todo excepto permisos críticos
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::whereNotIn('name', [
            'gestionar permisos',
            'editar configuracion',
            'gestionar configuracion negocio',
            'crear menu sidebar',
            'editar menu sidebar',
            'eliminar menu sidebar',
            'gestionar bloqueos por caja',
        ])->get());

        // Gerente
        $gerente = Role::firstOrCreate(['name' => 'gerente', 'guard_name' => 'web']);
        $gerente->syncPermissions([
            'usar punto de venta',
            'ver usuarios',
            'ver clientes', 'crear clientes', 'editar clientes', 'eliminar clientes',
            'ver menu', 'crear platos', 'editar platos', 'gestionar categorias', 'gestionar menu digital',
            'ver ordenes', 'crear ordenes', 'editar ordenes', 'cancelar ordenes', 'cerrar ordenes', 'reimprimir tickets',
            'ver mesas', 'asignar mesas', 'ordenar mesas', 'cerrar mesas',
            'liberar mesas', 'reasignar mesas', 'cobrar mesas', 'dividir mesas',
            'gestionar mesas', 'gestionar grupos',
            'crear areas de mesas', 'editar areas de mesas', 'eliminar areas de mesas',
            'crear mesas', 'editar mesas', 'eliminar mesas', 'cambiar estado mesas',
            'cancelar divisiones mesas',
            'ver caja', 'abrir caja', 'cerrar caja', 'aplicar descuentos', 'registrar gastos',
            'ver reportes', 'exportar reportes',
            'ver delivery', 'gestionar delivery',
            'ver reservas', 'crear reservas', 'editar reservas', 'cambiar estado reservas', 'cancelar reservas',
            'ver inventario', 'gestionar insumos', 'ajustar inventario',
            'generar compras inventario', 'recepcionar compras inventario',
            'editar compras inventario', 'eliminar compras inventario',
        ]);

        // Cajero
        $cajero = Role::firstOrCreate(['name' => 'cajero', 'guard_name' => 'web']);
        $cajero->syncPermissions([
            'usar punto de venta',
            'ver clientes', 'crear clientes', 'editar clientes',
            'ver ordenes', 'crear ordenes', 'cerrar ordenes', 'reimprimir tickets',
            'ver mesas', 'asignar mesas', 'ordenar mesas', 'cerrar mesas',
            'liberar mesas', 'reasignar mesas', 'cobrar mesas', 'dividir mesas',
            'cancelar divisiones mesas',
            'ver caja', 'abrir caja', 'cerrar caja', 'aplicar descuentos', 'registrar gastos',
            'ver reservas', 'crear reservas', 'editar reservas', 'cambiar estado reservas', 'cancelar reservas',
        ]);

        // Mesero
        $mesero = Role::firstOrCreate(['name' => 'mesero', 'guard_name' => 'web']);
        $mesero->syncPermissions([
            'ver ordenes', 'crear ordenes', 'editar ordenes', 'reimprimir tickets',
            'ver mesas', 'asignar mesas', 'ordenar mesas', 'cerrar mesas',
            'dividir mesas', 'cancelar divisiones mesas', 'reasignar mesas', 'gestionar grupos',
        ]);

        // Cocinero
        $cocinero = Role::firstOrCreate(['name' => 'cocinero', 'guard_name' => 'web']);
        $cocinero->syncPermissions(['ver ordenes', 'editar ordenes', 'reimprimir tickets', 'ver menu']);

        // Repartidor — acceso operativo únicamente a sus entregas.
        $repartidor = Role::firstOrCreate(['name' => 'repartidor', 'guard_name' => 'web']);
        $repartidor->syncPermissions(['ver delivery', 'tomar delivery', 'entregar delivery']);

        $this->command->info('✅ Roles y permisos creados correctamente.');
    }
}
