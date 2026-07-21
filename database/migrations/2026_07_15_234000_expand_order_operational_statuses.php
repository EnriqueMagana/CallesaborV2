<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pendiente','en_preparacion','lista','entregada','pagada','cancelada') NOT NULL DEFAULT 'pendiente'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::table('orders')
            ->whereIn('status', ['lista', 'entregada'])
            ->update(['status' => 'en_preparacion']);

        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pendiente','en_preparacion','pagada','cancelada') NOT NULL DEFAULT 'pendiente'");
    }
};
