<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->string('primary_color', 7)->default('#15803d')->after('banner_path');
            $table->string('instagram_url')->nullable()->after('website');
            $table->string('facebook_url')->nullable()->after('instagram_url');
            $table->string('tiktok_url')->nullable()->after('facebook_url');
            $table->json('gallery_paths')->nullable()->after('primary_color');
            $table->json('featured_product_ids')->nullable()->after('gallery_paths');
        });
    }

    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn([
                'primary_color',
                'instagram_url',
                'facebook_url',
                'tiktok_url',
                'gallery_paths',
                'featured_product_ids',
            ]);
        });
    }
};
