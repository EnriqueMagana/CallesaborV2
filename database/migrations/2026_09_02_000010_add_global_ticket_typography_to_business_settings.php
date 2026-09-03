<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table): void {
            $table->string('ticket_font_family', 20)->default('arial')->after('ticket_logo_path');
            $table->unsignedTinyInteger('ticket_font_size')->default(12)->after('ticket_font_family');
        });
    }

    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $table): void {
            $table->dropColumn(['ticket_font_family', 'ticket_font_size']);
        });
    }
};
