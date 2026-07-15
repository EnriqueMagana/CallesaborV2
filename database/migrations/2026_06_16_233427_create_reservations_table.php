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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('customer_name', 100);
            $table->string('customer_phone', 30)->nullable();
            $table->unsignedSmallInteger('guests')->default(1);
            $table->dateTime('reserved_at');
            $table->text('notes')->nullable();
            $table->enum('status', ['pendiente', 'confirmada', 'completada', 'cancelada'])->default('pendiente');
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
