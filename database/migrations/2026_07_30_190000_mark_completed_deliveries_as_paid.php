<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $deliveries = DB::table('orders')
            ->join('delivery_assignments', 'delivery_assignments.order_id', '=', 'orders.id')
            ->where('orders.type', 'delivery')
            ->where('orders.status', 'entregada')
            ->where('delivery_assignments.status', 'entregado')
            ->select([
                'orders.id',
                'orders.total',
                'orders.paid_at',
                'delivery_assignments.delivered_at',
            ])
            ->get();

        foreach ($deliveries as $delivery) {
            $paid = (float) DB::table('order_payments')
                ->where('order_id', $delivery->id)
                ->sum('amount');

            if ($paid + 0.01 < (float) $delivery->total) {
                continue;
            }

            DB::table('orders')
                ->where('id', $delivery->id)
                ->update([
                    'status' => 'pagada',
                    'paid_at' => $delivery->paid_at ?? $delivery->delivered_at ?? now(),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // La normalización representa pagos reales y no debe revertirse.
    }
};
