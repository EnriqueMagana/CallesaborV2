<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La migración 2026_06_16_110001 agregó `contra_entrega` al ENUM de métodos de
 * pago sólo en MySQL. En SQLite (entorno de pruebas) el método seguía
 * prohibido, así que ninguna prueba podía cubrir el cobro de un delivery
 * contra entrega en caja. MySQL no se toca.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        Schema::table('order_payments', function (Blueprint $table): void {
            $table->enum('method', ['efectivo', 'tarjeta', 'transferencia', 'contra_entrega'])->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        Schema::table('order_payments', function (Blueprint $table): void {
            $table->enum('method', ['efectivo', 'tarjeta', 'transferencia'])->change();
        });
    }
};
