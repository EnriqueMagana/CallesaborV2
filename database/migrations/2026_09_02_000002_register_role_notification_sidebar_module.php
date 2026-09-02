<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sidebar_menu_items')) {
            return;
        }

        $parentId = DB::table('sidebar_menu_items')
            ->where('system_key', 'section.administration')
            ->value('id');

        if (! $parentId) {
            return;
        }

        DB::table('sidebar_menu_items')->updateOrInsert(
            ['system_key' => 'admin.role-notifications'],
            [
                'parent_id' => $parentId,
                'label' => 'Notificaciones por rol',
                'type' => 'link',
                'icon' => 'bx-bell',
                'route_name' => 'app.notificaciones-roles',
                'url' => null,
                'active_pattern' => 'app.notificaciones-roles',
                'permission' => 'gestionar roles',
                'sort_order' => 25,
                'is_active' => true,
                'is_system' => false,
                'requires_open_register' => false,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('sidebar_menu_items')) {
            return;
        }

        DB::table('sidebar_menu_items')
            ->where('system_key', 'admin.role-notifications')
            ->delete();
    }
};
