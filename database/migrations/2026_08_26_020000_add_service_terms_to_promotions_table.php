<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->json('fulfillment_modes')->nullable()->after('weekdays');
            $table->string('terms_and_conditions', 1000)->nullable()->after('fulfillment_modes');
            $table->boolean('show_on_kiosk')->default(true)->after('show_on_digital_menu');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn(['fulfillment_modes', 'terms_and_conditions', 'show_on_kiosk']);
        });
    }
};
