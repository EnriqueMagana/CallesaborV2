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
        Schema::create('cash_register_cuts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_register_id')->constrained()->cascadeOnDelete();
            $table->foreignId('generated_by')->constrained('users');
            $table->string('folio', 20);

            // Efectivo por área (entra a caja física)
            $table->decimal('v_efectivo', 10, 2)->default(0);
            $table->decimal('m_efectivo', 10, 2)->default(0);
            $table->decimal('d_efectivo', 10, 2)->default(0); // contra_entrega cobrado en caja

            // Digital por área (informativo, NO entra a caja)
            $table->decimal('v_tarjeta', 10, 2)->default(0);
            $table->decimal('v_transfer', 10, 2)->default(0);
            $table->decimal('m_tarjeta', 10, 2)->default(0);
            $table->decimal('m_transfer', 10, 2)->default(0);
            $table->decimal('d_tarjeta', 10, 2)->default(0);
            $table->decimal('d_transfer', 10, 2)->default(0);

            // Movimientos de caja
            $table->decimal('initial_amount', 10, 2)->default(0);
            $table->decimal('total_cash_in', 10, 2)->default(0);
            $table->decimal('total_expenses_cash', 10, 2)->default(0);
            $table->decimal('expected_cash', 10, 2)->default(0);
            $table->decimal('declared_cash', 10, 2)->nullable();
            $table->decimal('difference', 10, 2)->nullable();

            $table->json('cut_data')->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_register_cuts');
    }
};
