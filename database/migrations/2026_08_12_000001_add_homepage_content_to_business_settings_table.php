<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->string('home_badge', 120)->nullable()->after('primary_color');
            $table->string('home_headline', 180)->nullable()->after('home_badge');
            $table->text('home_description')->nullable()->after('home_headline');
            $table->string('home_intro_kicker', 80)->nullable()->after('home_description');
            $table->string('home_intro_title', 180)->nullable()->after('home_intro_kicker');
            $table->text('home_intro_description')->nullable()->after('home_intro_title');
        });
    }

    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn(['home_badge', 'home_headline', 'home_description', 'home_intro_kicker', 'home_intro_title', 'home_intro_description']);
        });
    }
};
