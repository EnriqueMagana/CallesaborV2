<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kiosk_terminals', function (Blueprint $table) {
            $table->boolean('allow_delivery')->default(false)->after('allow_takeaway');
        });
    }

    public function down(): void
    {
        Schema::table('kiosk_terminals', function (Blueprint $table) {
            $table->dropColumn('allow_delivery');
        });
    }
};
