<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_registers', function (Blueprint $table): void {
            $table->unsignedInteger('next_order_folio')->default(1)->after('is_open');
        });

        DB::table('cash_registers')->orderBy('id')->each(function (object $register): void {
            $next = ((int) DB::table('orders')->where('cash_register_id', $register->id)->max('folio')) + 1;
            DB::table('cash_registers')->where('id', $register->id)->update([
                'next_order_folio' => max(1, $next),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('cash_registers', function (Blueprint $table): void {
            $table->dropColumn('next_order_folio');
        });
    }
};
