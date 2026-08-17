<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('business_settings', 'home_badge')) {
            Schema::table('business_settings', function (Blueprint $table) {
                $table->string('home_badge')->nullable();
            });
        }

        if (!Schema::hasColumn('business_settings', 'home_headline')) {
            Schema::table('business_settings', function (Blueprint $table) {
                $table->string('home_headline')->nullable();
            });
        }

        if (!Schema::hasColumn('business_settings', 'home_description')) {
            Schema::table('business_settings', function (Blueprint $table) {
                $table->text('home_description')->nullable();
            });
        }

        if (!Schema::hasColumn('business_settings', 'home_intro_kicker')) {
            Schema::table('business_settings', function (Blueprint $table) {
                $table->string('home_intro_kicker')->nullable();
            });
        }

        if (!Schema::hasColumn('business_settings', 'home_intro_title')) {
            Schema::table('business_settings', function (Blueprint $table) {
                $table->string('home_intro_title')->nullable();
            });
        }

        if (!Schema::hasColumn('business_settings', 'home_intro_description')) {
            Schema::table('business_settings', function (Blueprint $table) {
                $table->text('home_intro_description')->nullable();
            });
        }
    }

    public function down(): void
    {
        //
    }
};