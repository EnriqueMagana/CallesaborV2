<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('folio')->nullable()->after('cash_register_id');
            $table->unique(['cash_register_id', 'folio'], 'orders_register_folio_unique');
        });

        DB::table('orders')
            ->select('cash_register_id')
            ->distinct()
            ->orderBy('cash_register_id')
            ->pluck('cash_register_id')
            ->each(function ($registerId): void {
                $folio = 1;
                DB::table('orders')
                    ->where('cash_register_id', $registerId)
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->pluck('id')
                    ->each(function ($orderId) use (&$folio): void {
                        DB::table('orders')->where('id', $orderId)->update(['folio' => $folio++]);
                    });
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'folio')) {
            return;
        }

        // InnoDB can replace the implicit cash_register_id foreign-key index
        // with this composite unique index. Preserve a compatible index before
        // dropping the unique constraint during rollback.
        if (! Schema::hasIndex('orders', ['cash_register_id'])) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->index('cash_register_id', 'orders_cash_register_id_rollback_index');
            });
        }

        if (Schema::hasIndex('orders', 'orders_register_folio_unique')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropUnique('orders_register_folio_unique');
            });
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('folio');
        });
    }
};
