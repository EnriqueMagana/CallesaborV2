<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indices para los paneles del punto de venta.
 *
 * Todos los paneles filtran `orders` por la caja abierta y despues acotan por
 * estado o por tipo. `orders` solo tenia el indice de la clave foranea de
 * `cash_register_id`, asi que cada panel resolvia el resto con un barrido.
 *
 * `mesa_services (cash_register_id, status)` ya existia desde su migracion
 * original, por eso aqui no se repite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(
                ['cash_register_id', 'status', 'type'],
                'orders_register_status_type_index'
            );

            // El contador de mesas heredadas y el panel de mesas filtran por
            // este par antes de agrupar por mesa.
            $table->index(
                ['cash_register_id', 'mesa_service_id', 'mesa_id'],
                'orders_register_mesa_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_register_status_type_index');
            $table->dropIndex('orders_register_mesa_index');
        });
    }
};
