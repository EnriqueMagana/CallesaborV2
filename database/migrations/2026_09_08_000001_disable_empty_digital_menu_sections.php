<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $setting = DB::table('digital_menu_settings')->first();

        if (! $setting) {
            return;
        }

        $bannerPaths = $this->jsonArray($setting->banner_paths);
        $featuredProductIds = $this->jsonArray($setting->featured_product_ids);
        $galleryPaths = $this->jsonArray($setting->gallery_paths);
        $hasFeaturedProducts = $featuredProductIds !== []
            && DB::table('products')->whereIn('id', $featuredProductIds)->where('is_active', true)->exists();
        $hasCategories = DB::table('categories')
            ->join('products', 'products.category_id', '=', 'categories.id')
            ->where('categories.is_active', true)
            ->where('products.is_active', true)
            ->exists();

        DB::table('digital_menu_settings')->where('id', $setting->id)->update([
            'show_banners' => (bool) $setting->show_banners && $bannerPaths !== [],
            'show_featured' => (bool) $setting->show_featured && $hasFeaturedProducts,
            'show_categories' => (bool) $setting->show_categories && $hasCategories,
            'show_gallery' => (bool) $setting->show_gallery && $galleryPaths !== [],
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Content cannot determine the administrator's previous visibility preference.
    }

    private function jsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }
};
