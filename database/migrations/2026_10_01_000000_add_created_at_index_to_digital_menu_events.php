<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('digital_menu_events', function (Blueprint $table): void {
            $table->index('created_at', 'digital_menu_events_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('digital_menu_events', function (Blueprint $table): void {
            $table->dropIndex('digital_menu_events_created_at_index');
        });
    }
};
