<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['asignado', 'entregado'])->default('asignado');
            $table->timestamp('assigned_at');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'status']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pendiente','en_preparacion','lista','en_reparto','entregada','pagada','cancelada') NOT NULL DEFAULT 'pendiente'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::table('orders')->where('status', 'en_reparto')->update(['status' => 'lista']);
            DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pendiente','en_preparacion','lista','entregada','pagada','cancelada') NOT NULL DEFAULT 'pendiente'");
        }

        Schema::dropIfExists('delivery_assignments');
    }
};
