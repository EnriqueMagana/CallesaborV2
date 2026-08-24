<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digital_menu_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('primary_color', 7)->default('#15803d');
            $table->boolean('show_banners')->default(true);
            $table->boolean('autoplay_banners')->default(true);
            $table->unsignedTinyInteger('banner_interval_seconds')->default(5);
            $table->json('banner_paths')->nullable();
            $table->boolean('show_featured')->default(true);
            $table->json('featured_product_ids')->nullable();
            $table->boolean('show_categories')->default(true);
            $table->string('category_style', 20)->default('cards');
            $table->boolean('show_gallery')->default(true);
            $table->json('gallery_paths')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $business = DB::table('business_settings')->first();
        $bannerPaths = filled($business?->banner_path)
            ? [['path' => $business->banner_path, 'alt' => '']]
            : [];

        DB::table('digital_menu_settings')->insert([
            'primary_color' => $business?->primary_color ?: '#15803d',
            'banner_paths' => json_encode($bannerPaths, JSON_UNESCAPED_UNICODE),
            'featured_product_ids' => $business?->featured_product_ids ?: '[]',
            'gallery_paths' => $business?->gallery_paths ?: '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionId = DB::table('permissions')
            ->where('name', 'gestionar menu digital')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'gestionar menu digital',
                'guard_name' => 'web',
                'group' => 'menu',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $roleIds = DB::table('roles')->whereIn('name', ['admin', 'gerente'])->pluck('id');
        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }

        $restaurantId = DB::table('sidebar_menu_items')
            ->where('system_key', 'section.restaurant')
            ->value('id');

        if ($restaurantId) {
            DB::table('sidebar_menu_items')->updateOrInsert(
                ['system_key' => 'restaurant.digital-menu'],
                [
                    'parent_id' => $restaurantId,
                    'type' => 'link',
                    'label' => 'Menú digital',
                    'icon' => 'bx-mobile-alt',
                    'route_name' => 'app.menu-digital',
                    'active_pattern' => 'app.menu-digital*',
                    'permission' => 'gestionar menu digital',
                    'sort_order' => 15,
                    'is_active' => true,
                    'is_system' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('sidebar_menu_items')->where('system_key', 'restaurant.digital-menu')->delete();

        $permissionId = DB::table('permissions')
            ->where('name', 'gestionar menu digital')
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('model_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        Schema::dropIfExists('digital_menu_settings');
    }
};
