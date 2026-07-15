<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->decimal('declared_amount', 10, 2)->nullable()->after('final_amount');
            $table->decimal('difference_amount', 10, 2)->nullable()->after('declared_amount');
            $table->text('closing_notes')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->dropColumn(['declared_amount', 'difference_amount', 'closing_notes']);
        });
    }
};
