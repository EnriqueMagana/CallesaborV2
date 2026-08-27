<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->string('presentation_type', 20)->default('promotion')->after('description');
            $table->string('short_description', 160)->nullable()->after('presentation_type');
            $table->unsignedTinyInteger('discount_percentage')->nullable()->after('price');

            $table->index(['presentation_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropIndex(['presentation_type', 'is_active']);
            $table->dropColumn(['presentation_type', 'short_description', 'discount_percentage']);
        });
    }
};
