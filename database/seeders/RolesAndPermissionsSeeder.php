<?php

namespace Database\Seeders;

use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::query()->whereIn('name', [
            'cancelar ordenes',
            'eliminar items de ordenes',
            'eliminar ordenes',
        ])->delete();

        $permissions = [
            'punto_venta' => [
                'usar punto de venta',
                'crear ventas en punto de venta',
                'gestionar borradores en punto de venta',
                'ver pedidos en punto de venta',
                'iniciar preparacion en punto de venta',
                'marcar pedidos listos en punto de venta',
                'cobrar pedidos en punto de venta',
                'convertir pedidos a delivery en punto de venta',
            ],
            'usuarios' => ['ver usuarios', 'crear usuarios', 'editar usuarios', 'eliminar usuarios', 'bloquear usuarios', 'gestionar roles', 'gestionar permisos', 'gestionar notificaciones por rol'],
            'clientes' => ['ver clientes', 'crear clientes', 'editar clientes', 'eliminar clientes'],
            'menu' => ['ver menu', 'crear platos', 'editar platos', 'eliminar platos', 'gestionar categorias', 'gestionar complementos', 'gestionar areas impresion', 'gestionar menu digital'],
            'promociones' => ['ver promociones', 'crear promociones', 'editar promociones', 'eliminar promociones'],
            'descuentos' => ['ver descuentos', 'crear descuentos', 'editar descuentos', 'eliminar descuentos'],
            'ordenes' => ['ver ordenes', 'crear ordenes', 'editar ordenes', 'solicitar cancelacion de ordenes', 'solicitar modificacion de ordenes', 'revisar solicitudes de ordenes', 'cerrar ordenes', 'reimprimir tickets'],
            'mesas' => [
                'ver mesas',
                'ver todas las mesas',
                'ver historial completo de asignaciones mesas',
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
            'caja' => ['ver caja', 'abrir caja', 'cerrar caja', 'aplicar descuentos', 'anular pagos', 'registrar gastos', 'registrar movimientos de caja'],
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
                'registrar salida de insumos',
            ],
            'desarrollo' => [
                'ver panel super admin',
                'ejecutar diagnosticos',
                'probar notificaciones',
                'gestionar variables de entorno',
            ],
        ];

        foreach ($permissions as $group => $perms) {
            foreach ($perms as $perm) {
                Permission::updateOrCreate(
                    ['name' => $perm, 'guard_name' => 'web'],
                    [
                        'group' => $group,
                        'description' => PermissionCatalog::description($perm),
                    ]
                );
            }
        }

        // Super Admin — bypasa todos los gates con Spatie
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

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
            'revisar solicitudes de ordenes',
            'ver panel super admin',
            'ejecutar diagnosticos',
            'probar notificaciones',
            'gestionar variables de entorno',
        ])->get());

        // Gerente
        $gerente = Role::firstOrCreate(['name' => 'gerente', 'guard_name' => 'web']);
        $gerente->syncPermissions([
            'usar punto de venta',
            'crear ventas en punto de venta', 'gestionar borradores en punto de venta', 'ver pedidos en punto de venta',
            'iniciar preparacion en punto de venta', 'marcar pedidos listos en punto de venta',
            'cobrar pedidos en punto de venta', 'convertir pedidos a delivery en punto de venta',
            'ver usuarios',
            'ver clientes', 'crear clientes', 'editar clientes', 'eliminar clientes',
            'ver menu', 'crear platos', 'editar platos', 'gestionar categorias', 'gestionar menu digital',
            'ver promociones', 'crear promociones', 'editar promociones', 'eliminar promociones',
            'ver descuentos', 'crear descuentos', 'editar descuentos', 'eliminar descuentos',
            'ver ordenes', 'crear ordenes', 'editar ordenes', 'solicitar cancelacion de ordenes', 'solicitar modificacion de ordenes', 'cerrar ordenes', 'reimprimir tickets',
            'ver mesas', 'ver todas las mesas', 'ver historial completo de asignaciones mesas', 'asignar mesas', 'ordenar mesas', 'cerrar mesas',
            'liberar mesas', 'reasignar mesas', 'cobrar mesas', 'dividir mesas',
            'gestionar mesas', 'gestionar grupos',
            'crear areas de mesas', 'editar areas de mesas', 'eliminar areas de mesas',
            'crear mesas', 'editar mesas', 'eliminar mesas', 'cambiar estado mesas',
            'cancelar divisiones mesas',
            'ver caja', 'abrir caja', 'cerrar caja', 'aplicar descuentos', 'registrar gastos', 'registrar movimientos de caja',
            'ver reportes', 'exportar reportes',
            'ver delivery', 'gestionar delivery',
            'ver reservas', 'crear reservas', 'editar reservas', 'cambiar estado reservas', 'cancelar reservas',
            'ver inventario', 'gestionar insumos', 'ajustar inventario',
            'generar compras inventario', 'recepcionar compras inventario',
            'editar compras inventario', 'eliminar compras inventario',
            'registrar salida de insumos',
        ]);

        // Cajero
        $cajero = Role::firstOrCreate(['name' => 'cajero', 'guard_name' => 'web']);
        $cajero->syncPermissions([
            'usar punto de venta',
            'crear ventas en punto de venta', 'gestionar borradores en punto de venta', 'ver pedidos en punto de venta',
            'iniciar preparacion en punto de venta', 'marcar pedidos listos en punto de venta',
            'cobrar pedidos en punto de venta', 'convertir pedidos a delivery en punto de venta',
            'ver clientes', 'crear clientes', 'editar clientes',
            'ver ordenes', 'crear ordenes', 'solicitar cancelacion de ordenes', 'solicitar modificacion de ordenes', 'cerrar ordenes', 'reimprimir tickets',
            'ver mesas', 'ver todas las mesas', 'asignar mesas', 'ordenar mesas', 'cerrar mesas',
            'liberar mesas', 'reasignar mesas', 'cobrar mesas', 'dividir mesas',
            'cancelar divisiones mesas',
            'ver caja', 'abrir caja', 'cerrar caja', 'aplicar descuentos', 'registrar gastos', 'registrar movimientos de caja',
            'registrar salida de insumos',
            'ver reservas', 'crear reservas', 'editar reservas', 'cambiar estado reservas', 'cancelar reservas',
        ]);

        // Mesero
        $mesero = Role::firstOrCreate(['name' => 'mesero', 'guard_name' => 'web']);
        $mesero->syncPermissions([
            'ver ordenes', 'crear ordenes', 'editar ordenes', 'solicitar cancelacion de ordenes', 'solicitar modificacion de ordenes', 'reimprimir tickets',
            'ver mesas', 'asignar mesas', 'ordenar mesas', 'cerrar mesas',
            'dividir mesas', 'cancelar divisiones mesas', 'reasignar mesas', 'gestionar grupos',
        ]);

        // Cocinero
        $cocinero = Role::firstOrCreate(['name' => 'cocinero', 'guard_name' => 'web']);
        $cocinero->syncPermissions([
            'usar punto de venta', 'ver pedidos en punto de venta',
            'iniciar preparacion en punto de venta', 'marcar pedidos listos en punto de venta',
            'ver ordenes', 'reimprimir tickets', 'ver menu',
        ]);

        // Repartidor — acceso operativo únicamente a sus entregas.
        $repartidor = Role::firstOrCreate(['name' => 'repartidor', 'guard_name' => 'web']);
        $repartidor->syncPermissions(['ver delivery', 'tomar delivery', 'entregar delivery']);

        if (Schema::hasColumn('roles', 'icon')) {
            foreach ([
                'super-admin' => 'bx-crown',
                'owner' => 'bx-crown',
                'admin' => 'bx-shield',
                'gerente' => 'bx-briefcase',
                'cajero' => 'bx-money',
                'mesero' => 'bx-dish',
                'cocinero' => 'bx-restaurant',
                'repartidor' => 'bx-cycling',
            ] as $roleName => $icon) {
                Role::query()->where('name', $roleName)->whereNull('icon')->update(['icon' => $icon]);
            }
        }

        $this->command->info('✅ Roles y permisos creados correctamente.');
    }
}
