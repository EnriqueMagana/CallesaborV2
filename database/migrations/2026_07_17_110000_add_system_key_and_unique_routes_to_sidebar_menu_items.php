<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sidebar_menu_items', function (Blueprint $table): void {
            $table->string('system_key', 120)->nullable()->after('parent_id');
        });

        $this->removeDuplicateRoutes();
        $this->backfillSystemKeys();

        Schema::table('sidebar_menu_items', function (Blueprint $table): void {
            $table->unique('system_key', 'sidebar_menu_items_system_key_unique');
            $table->unique('route_name', 'sidebar_menu_items_route_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('sidebar_menu_items', function (Blueprint $table): void {
            $table->dropUnique('sidebar_menu_items_system_key_unique');
            $table->dropUnique('sidebar_menu_items_route_name_unique');
            $table->dropColumn('system_key');
        });
    }

    private function removeDuplicateRoutes(): void
    {
        $duplicateRoutes = DB::table('sidebar_menu_items')
            ->whereNotNull('route_name')
            ->select('route_name')
            ->groupBy('route_name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('route_name');

        foreach ($duplicateRoutes as $routeName) {
            $items = DB::table('sidebar_menu_items')
                ->where('route_name', $routeName)
                ->orderByRaw('CASE WHEN updated_by IS NULL THEN 1 ELSE 0 END')
                ->orderBy('id')
                ->get(['id']);

            $keeperId = $items->first()?->id;
            if ($keeperId) {
                DB::table('sidebar_menu_items')
                    ->where('route_name', $routeName)
                    ->where('id', '!=', $keeperId)
                    ->delete();
            }
        }
    }

    private function backfillSystemKeys(): void
    {
        $routeKeys = [
            'app.dashboard' => 'dashboard',
            'app.usuarios' => 'admin.users',
            'app.roles-permisos' => 'admin.roles',
            'app.kioscos' => 'configuration.kiosks',
            'app.configuracion-negocio' => 'configuration.business',
            'app.configuracion-negocio.menu' => 'configuration.sidebar',
            'app.constructor-menu' => 'restaurant.menu-builder',
            'app.pos' => 'restaurant.pos',
            'app.mesas' => 'operations.tables',
            'app.reservas' => 'operations.reservations',
            'app.ordenes' => 'operations.orders',
            'app.historial-ventas' => 'operations.sales-history',
            'app.delivery' => 'operations.delivery',
            'app.caja' => 'cash.dashboard',
            'app.caja.cortes' => 'cash.cuts',
            'profile' => 'account.profile',
        ];

        foreach ($routeKeys as $route => $key) {
            DB::table('sidebar_menu_items')->where('route_name', $route)->update(['system_key' => $key]);
        }

        $containerKeys = [
            ['type' => 'section', 'label' => 'Administración', 'key' => 'section.administration'],
            ['type' => 'group', 'label' => 'Configuración', 'key' => 'group.configuration'],
            ['type' => 'section', 'label' => 'Restaurante', 'key' => 'section.restaurant'],
            ['type' => 'group', 'label' => 'Operación', 'key' => 'group.operations'],
            ['type' => 'group', 'label' => 'Caja y cortes', 'key' => 'group.cash'],
            ['type' => 'section', 'label' => 'Mi cuenta', 'key' => 'section.account'],
        ];

        foreach ($containerKeys as $container) {
            DB::table('sidebar_menu_items')
                ->where('type', $container['type'])
                ->where('label', $container['label'])
                ->whereNull('system_key')
                ->limit(1)
                ->update(['system_key' => $container['key']]);
        }
    }
};
