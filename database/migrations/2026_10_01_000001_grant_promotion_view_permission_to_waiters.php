<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')
            || ! Schema::hasTable('permissions')
            || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $roleId = DB::table('roles')
            ->where('name', 'mesero')
            ->where('guard_name', 'web')
            ->value('id');
        $permissionId = DB::table('permissions')
            ->where('name', 'ver promociones')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $roleId || ! $permissionId) {
            return;
        }

        DB::table('role_has_permissions')->insertOrIgnore([
            'permission_id' => $permissionId,
            'role_id' => $roleId,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles')
            || ! Schema::hasTable('permissions')
            || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $roleId = DB::table('roles')
            ->where('name', 'mesero')
            ->where('guard_name', 'web')
            ->value('id');
        $permissionId = DB::table('permissions')
            ->where('name', 'ver promociones')
            ->where('guard_name', 'web')
            ->value('id');

        if ($roleId && $permissionId) {
            DB::table('role_has_permissions')
                ->where('permission_id', $permissionId)
                ->where('role_id', $roleId)
                ->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
