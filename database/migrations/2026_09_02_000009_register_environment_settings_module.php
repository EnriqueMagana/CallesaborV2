<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSION = 'gestionar variables de entorno';

    public function up(): void
    {
        if (! Schema::hasTable('environment_change_audits')) {
            Schema::create('environment_change_audits', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->json('changed_keys');
                $table->string('backup_file')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->char('user_agent_hash', 64)->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable(config('permission.table_names.permissions', 'permissions'))) {
            $permission = Permission::query()->updateOrCreate(
                ['name' => self::PERMISSION, 'guard_name' => 'web'],
                ['group' => 'desarrollo', 'description' => PermissionCatalog::description(self::PERMISSION)],
            );

            Role::query()
                ->whereIn('name', ['owner', 'super-admin'])
                ->where('guard_name', 'web')
                ->get()
                ->each(fn (Role $role) => $role->givePermissionTo($permission));

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        if (Schema::hasTable('sidebar_menu_items')) {
            $parentId = DB::table('sidebar_menu_items')->where('system_key', 'section.development')->value('id');

            if ($parentId) {
                $attributes = [
                    'parent_id' => $parentId,
                    'label' => 'Variables de entorno',
                    'type' => 'link',
                    'icon' => 'bx-slider-alt',
                    'route_name' => 'app.super-admin.environment',
                    'active_pattern' => 'app.super-admin.environment',
                    'permission' => self::PERMISSION,
                    'sort_order' => 20,
                    'is_active' => true,
                    'is_system' => true,
                    'updated_at' => now(),
                ];

                if (Schema::hasColumn('sidebar_menu_items', 'requires_open_register')) {
                    $attributes['requires_open_register'] = false;
                }

                DB::table('sidebar_menu_items')->updateOrInsert(
                    ['system_key' => 'development.environment'],
                    $attributes + ['created_at' => now()],
                );

                DB::table('sidebar_menu_items')
                    ->where('system_key', 'development.pulse')
                    ->update(['sort_order' => 30, 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sidebar_menu_items')) {
            DB::table('sidebar_menu_items')->where('system_key', 'development.environment')->delete();
            DB::table('sidebar_menu_items')->where('system_key', 'development.pulse')->update(['sort_order' => 20]);
        }

        if (Schema::hasTable(config('permission.table_names.permissions', 'permissions'))) {
            Permission::query()->where('name', self::PERMISSION)->where('guard_name', 'web')->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        Schema::dropIfExists('environment_change_audits');
    }
};
