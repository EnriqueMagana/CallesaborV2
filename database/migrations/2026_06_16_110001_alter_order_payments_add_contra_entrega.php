<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE order_payments MODIFY COLUMN method ENUM('efectivo','tarjeta','transferencia','contra_entrega') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE order_payments MODIFY COLUMN method ENUM('efectivo','tarjeta','transferencia') NOT NULL");
        }
    }
};
