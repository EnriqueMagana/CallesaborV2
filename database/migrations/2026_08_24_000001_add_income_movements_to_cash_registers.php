<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('expenses') && ! Schema::hasColumn('expenses', 'type')) {
            Schema::table('expenses', function (Blueprint $table): void {
                $table->string('type', 20)->default('expense')->after('created_by');
                $table->index(['cash_register_id', 'type', 'created_at'], 'cash_movement_register_type_created_index');
            });
        }

        if (Schema::hasTable('cash_register_cuts') && ! Schema::hasColumn('cash_register_cuts', 'total_cash_income')) {
            Schema::table('cash_register_cuts', function (Blueprint $table): void {
                $table->decimal('total_cash_income', 10, 2)->default(0)->after('total_cash_in');
            });
        }

        if (! Schema::hasTable('permissions')) {
            return;
        }

        $now = now();
        $permissions = [
            'registrar movimientos de caja' => 'caja',
            'registrar salida de insumos' => 'inventario',
        ];

        foreach ($permissions as $name => $group) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name, 'guard_name' => 'web'],
                ['group' => $group, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', array_keys($permissions))
            ->where('guard_name', 'web')
            ->pluck('id', 'name');

        $roleIds = DB::table('roles')
            ->whereIn('name', ['admin', 'gerente', 'cajero'])
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

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')
                ->whereIn('name', ['registrar movimientos de caja', 'registrar salida de insumos'])
                ->where('guard_name', 'web')
                ->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        if (Schema::hasTable('cash_register_cuts') && Schema::hasColumn('cash_register_cuts', 'total_cash_income')) {
            Schema::table('cash_register_cuts', fn (Blueprint $table) => $table->dropColumn('total_cash_income'));
        }

        if (Schema::hasTable('expenses') && Schema::hasColumn('expenses', 'type')) {
            Schema::table('expenses', function (Blueprint $table): void {
                $table->dropIndex('cash_movement_register_type_created_index');
                $table->dropColumn('type');
            });
        }
    }
};
