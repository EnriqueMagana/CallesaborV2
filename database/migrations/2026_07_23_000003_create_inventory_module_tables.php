<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 80)->nullable()->unique();
            $table->string('name', 160);
            $table->string('category', 100)->nullable();
            $table->string('unit', 30)->default('piece');
            $table->decimal('current_stock', 14, 3)->default(0);
            $table->decimal('minimum_stock', 14, 3)->default(0);
            $table->decimal('estimated_unit_cost', 14, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'name']);
            $table->index(['unit', 'current_stock']);
        });

        Schema::create('inventory_purchases', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 40)->nullable()->unique();
            $table->string('status', 30)->default('pending');
            $table->text('notes')->nullable();
            $table->text('reception_notes')->nullable();
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'issued_at']);
        });

        Schema::create('inventory_purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->string('item_name', 160);
            $table->string('unit', 30);
            $table->decimal('requested_quantity', 14, 3);
            $table->decimal('received_quantity', 14, 3)->nullable();
            $table->decimal('estimated_unit_cost', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->text('reception_note')->nullable();
            $table->timestamps();

            $table->index(['inventory_purchase_id', 'inventory_item_id'], 'inventory_purchase_item_lookup');
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inventory_purchase_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('type', 40);
            $table->decimal('quantity', 14, 3);
            $table->decimal('stock_before', 14, 3);
            $table->decimal('stock_after', 14, 3);
            $table->string('reason', 255);
            $table->timestamps();

            $table->index(['inventory_item_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_purchase_items');
        Schema::dropIfExists('inventory_purchases');
        Schema::dropIfExists('inventory_items');
    }
};
