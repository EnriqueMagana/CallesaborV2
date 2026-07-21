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
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_register_folio_unique');
            $table->dropColumn('folio');
        });
    }
};
