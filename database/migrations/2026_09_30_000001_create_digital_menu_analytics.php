<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digital_menu_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type', 32);
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->char('visitor_hash', 64);
            $table->char('session_hash', 64)->nullable();
            $table->date('occurred_on');
            $table->timestamps();

            $table->index(['event_type', 'occurred_on']);
            $table->index(['product_id', 'event_type', 'occurred_on'], 'digital_menu_product_event_date_idx');
            $table->index(['visitor_hash', 'occurred_on']);
        });

        DB::table('digital_menu_settings')->update(['show_featured' => true]);

        DB::table('permissions')->updateOrInsert(
            ['name' => 'ver analitica menu digital', 'guard_name' => 'web'],
            [
                'group' => 'reportes',
                'description' => 'Permite consultar vistas, visitantes y productos más consultados en el menú digital.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        $permissionId = DB::table('permissions')
            ->where('name', 'ver analitica menu digital')
            ->where('guard_name', 'web')
            ->value('id');

        $roleIds = DB::table('roles')->whereIn('name', ['admin', 'gerente'])->pluck('id');
        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }

        $restaurantId = DB::table('sidebar_menu_items')->where('system_key', 'section.restaurant')->value('id');
        if ($restaurantId) {
            DB::table('sidebar_menu_items')->insert([
                'system_key' => 'restaurant.digital-menu-analytics',
                'parent_id' => $restaurantId,
                'type' => 'link',
                'label' => 'Analítica del menú',
                'icon' => 'bx-bar-chart-alt-2',
                'route_name' => 'app.menu-analytics',
                'active_pattern' => 'app.menu-analytics*',
                'permission' => 'ver analitica menu digital',
                'sort_order' => 16,
                'is_active' => true,
                'is_system' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('sidebar_menu_items')->where('system_key', 'restaurant.digital-menu-analytics')->delete();
        $permissionId = DB::table('permissions')->where('name', 'ver analitica menu digital')->where('guard_name', 'web')->value('id');
        if ($permissionId) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('model_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
        Schema::dropIfExists('digital_menu_events');
    }
};
