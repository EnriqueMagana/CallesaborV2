<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();

            // Cliente propietario de la dirección
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            // Nombre identificador: Casa, Trabajo, etc.
            $table->string('label', 100)->nullable();

            // Dirección
            $table->string('street', 255);
            $table->string('exterior_number', 50)->nullable();
            $table->string('interior_number', 50)->nullable();
            $table->string('neighborhood', 150)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('city', 150)->nullable();
            $table->string('state', 150)->nullable();

            // Indicaciones para el repartidor
            $table->text('references')->nullable();

            // Coordenadas para delivery
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Dirección predeterminada del cliente
            $table->boolean('is_default')->default(false);

            $table->timestamps();

            // Facilita búsquedas de direcciones por cliente
            $table->index(['customer_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};