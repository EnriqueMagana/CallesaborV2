<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_register_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('completed_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('orders_count');
            $table->decimal('sales_total', 10, 2)->default(0);
            $table->decimal('expected_cash', 10, 2)->default(0);
            $table->decimal('declared_cash', 10, 2);
            $table->decimal('difference', 10, 2);
            $table->decimal('transfer_total', 10, 2)->default(0);
            $table->decimal('card_total', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->index(['cash_register_id', 'driver_id']);
        });

        Schema::table('delivery_assignments', function (Blueprint $table) {
            $table->foreignId('delivery_settlement_id')
                ->nullable()
                ->after('delivered_by')
                ->constrained('delivery_settlements')
                ->nullOnDelete();
            $table->index(['driver_id', 'status', 'delivery_settlement_id'], 'delivery_assignment_settlement_index');
        });

        // Legacy deliveries were completed before the workflow recorded cash.
        // Backfill only COD orders with no payment so their original sale becomes reconcilable.
        DB::table('orders')
            ->where('type', 'delivery')
            ->where('delivery_method', 'contra_entrega')
            ->where('status', 'entregada')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('delivery_assignments')
                    ->whereColumn('delivery_assignments.order_id', 'orders.id')
                    ->where('delivery_assignments.status', 'entregado');
            })
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('order_payments')
                    ->whereColumn('order_payments.order_id', 'orders.id');
            })
            ->orderBy('id')
            ->get(['id', 'total', 'updated_at'])
            ->each(function ($order): void {
                DB::table('order_payments')->insert([
                    'order_id' => $order->id,
                    'method' => 'efectivo',
                    'amount' => $order->total,
                    'received_amount' => $order->total,
                    'change_amount' => 0,
                    'created_at' => $order->updated_at ?? now(),
                    'updated_at' => $order->updated_at ?? now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('delivery_assignments', function (Blueprint $table) {
            $table->dropIndex('delivery_assignment_settlement_index');
            $table->dropConstrainedForeignId('delivery_settlement_id');
        });

        Schema::dropIfExists('delivery_settlements');
    }
};
