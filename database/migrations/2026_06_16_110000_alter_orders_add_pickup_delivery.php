<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE orders MODIFY COLUMN type ENUM('mesa','ventanilla','delivery','pick_up') NOT NULL DEFAULT 'ventanilla'");
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->enum('delivery_method', ['contra_entrega', 'tarjeta', 'transferencia'])
                ->nullable()
                ->after('table_identifier');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('delivery_method');
        });
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE orders MODIFY COLUMN type ENUM('mesa','ventanilla','delivery') NOT NULL DEFAULT 'ventanilla'");
        }
    }
};
