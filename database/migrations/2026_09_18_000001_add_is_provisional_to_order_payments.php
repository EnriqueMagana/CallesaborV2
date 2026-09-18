<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un delivery contra entrega en modo manual registra su cobro al nacer la
 * orden, antes de que el repartidor salga. Ese registro no es dinero recibido:
 * es lo que el repartidor cobrará y entregará en el corte. Esta columna lo
 * distingue del dinero que ya entró a la caja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_payments', function (Blueprint $table): void {
            $table->boolean('is_provisional')->default(false)->after('amount')->index();
        });

        // Sólo lo que sigue en la calle: deliveries manuales contra entrega de
        // cajas abiertas. Las cajas cerradas ya hicieron corte y el repartidor
        // entregó el efectivo.
        DB::table('order_payments')
            ->whereIn('order_id', DB::table('orders')
                ->join('cash_registers', 'cash_registers.id', '=', 'orders.cash_register_id')
                ->where('cash_registers.is_open', true)
                ->where('orders.type', 'delivery')
                ->where('orders.delivery_method', 'contra_entrega')
                ->where('orders.delivery_flow_mode', 'manual')
                ->whereIn('orders.status', ['pendiente', 'en_preparacion', 'lista', 'en_reparto'])
                ->select('orders.id'))
            ->whereIn('method', ['efectivo', 'contra_entrega'])
            ->update(['is_provisional' => true]);
    }

    public function down(): void
    {
        Schema::table('order_payments', function (Blueprint $table): void {
            $table->dropIndex(['is_provisional']);
            $table->dropColumn('is_provisional');
        });
    }
};
