<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $permissions = [
        'ver inventario',
        'gestionar insumos',
        'ajustar inventario',
        'generar compras inventario',
        'recepcionar compras inventario',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $now = now();
        foreach ($this->permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission, 'guard_name' => 'web'],
                ['group' => 'inventario', 'updated_at' => $now, 'created_at' => $now]
            );
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', $this->permissions)
            ->where('guard_name', 'web')
            ->pluck('id');
        $roleIds = DB::table('roles')
            ->whereIn('name', ['admin', 'gerente'])
            ->where('guard_name', 'web')
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        if (! Schema::hasTable('sidebar_menu_items')) {
            return;
        }

        $parentId = DB::table('sidebar_menu_items')->where('system_key', 'group.operations')->value('id');
        if ($parentId && ! DB::table('sidebar_menu_items')->where('system_key', 'operations.inventory')->exists()) {
            DB::table('sidebar_menu_items')->insert([
                'parent_id' => $parentId,
                'system_key' => 'operations.inventory',
                'label' => 'Inventario',
                'type' => 'link',
                'icon' => 'bx-package',
                'route_name' => 'app.inventario',
                'active_pattern' => 'app.inventario*',
                'permission' => 'ver inventario',
                'sort_order' => 60,
                'is_active' => true,
                'is_system' => false,
                'requires_open_register' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sidebar_menu_items')) {
            DB::table('sidebar_menu_items')->where('system_key', 'operations.inventory')->delete();
        }

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')
                ->whereIn('name', $this->permissions)
                ->where('guard_name', 'web')
                ->delete();
        }
    }
};
