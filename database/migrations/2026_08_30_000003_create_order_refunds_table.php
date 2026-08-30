<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_change_request_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('cash_register_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('processed_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('cash_movement_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->string('type', 20);
            $table->decimal('amount', 10, 2);
            $table->json('allocations');
            $table->string('external_reference', 120)->nullable();
            $table->string('inventory_disposition', 30)->default('not_applicable');
            $table->string('status', 20)->default('recorded');
            $table->text('reason');
            $table->timestamp('processed_at');
            $table->timestamps();

            $table->index(['cash_register_id', 'processed_at']);
            $table->index(['status', 'processed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_refunds');
    }
};
