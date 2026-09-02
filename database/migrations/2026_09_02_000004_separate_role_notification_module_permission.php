<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSION = 'gestionar notificaciones por rol';

    public function up(): void
    {
        if (Schema::hasTable(config('permission.table_names.permissions', 'permissions'))) {
            $permission = Permission::query()->updateOrCreate(
                ['name' => self::PERMISSION, 'guard_name' => 'web'],
                [
                    'group' => 'usuarios',
                    'description' => PermissionCatalog::description(self::PERMISSION),
                ]
            );

            Role::query()
                ->whereHas('permissions', fn ($query) => $query->where('name', 'gestionar roles'))
                ->get()
                ->each(fn (Role $role) => $role->givePermissionTo($permission));

            Role::query()->whereIn('name', ['owner', 'super-admin'])->get()
                ->each(fn (Role $role) => $role->givePermissionTo($permission));

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        if (Schema::hasTable('sidebar_menu_items')) {
            DB::table('sidebar_menu_items')
                ->where('system_key', 'admin.role-notifications')
                ->update([
                    'permission' => self::PERMISSION,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sidebar_menu_items')) {
            DB::table('sidebar_menu_items')
                ->where('system_key', 'admin.role-notifications')
                ->update([
                    'permission' => 'gestionar roles',
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable(config('permission.table_names.permissions', 'permissions'))) {
            Permission::query()->where('name', self::PERMISSION)->where('guard_name', 'web')->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
