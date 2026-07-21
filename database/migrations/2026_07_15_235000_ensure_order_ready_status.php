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

        $column = DB::selectOne("SHOW COLUMNS FROM orders LIKE 'status'");
        $type = (string) ($column->Type ?? '');

        // Some installations were created before the operational-status migration
        // was applied. Keep this migration idempotent so the POS can persist `lista`.
        if (! str_contains($type, "'lista'")) {
            DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pendiente','en_preparacion','lista','entregada','pagada','cancelada') NOT NULL DEFAULT 'pendiente'");
        }
    }

    public function down(): void
    {
        // Do not remove `lista`: existing orders and the POS workflow depend on it.
    }
};
