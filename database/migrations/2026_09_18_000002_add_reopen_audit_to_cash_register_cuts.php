<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reabrir una caja no borra su corte: lo deja anulado con quién, cuándo y por
 * qué, para que el historial conserve cada cierre que existió.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_register_cuts', function (Blueprint $table): void {
            $table->timestamp('reopened_at')->nullable()->after('generated_at')->index();
            $table->foreignId('reopened_by')->nullable()->after('reopened_at')->constrained('users')->nullOnDelete();
            $table->text('reopen_reason')->nullable()->after('reopened_by');
        });
    }

    public function down(): void
    {
        Schema::table('cash_register_cuts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reopened_by');
            $table->dropIndex(['reopened_at']);
            $table->dropColumn(['reopened_at', 'reopen_reason']);
        });
    }
};
