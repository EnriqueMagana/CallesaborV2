<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->string('category', 30)->default('occasional');
            $table->string('value_type', 20)->default('percentage');
            $table->decimal('value', 10, 2);
            $table->string('scope', 20)->default('order');
            $table->string('audience', 30)->default('everyone');
            $table->decimal('minimum_purchase', 10, 2)->default(0);
            $table->decimal('maximum_discount', 10, 2)->nullable();
            $table->json('fulfillment_modes')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('combine_with_promotions')->default(false);
            $table->boolean('auto_apply')->default(true);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'auto_apply', 'starts_at', 'ends_at'], 'discounts_availability_index');
        });

        Schema::create('discount_product', function (Blueprint $table): void {
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['discount_id', 'product_id']);
        });

        Schema::create('customer_discount', function (Blueprint $table): void {
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->primary(['discount_id', 'customer_id']);
        });

        Schema::create('discount_user', function (Blueprint $table): void {
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['discount_id', 'user_id']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('discount_beneficiary_user_id')->nullable()->after('customer_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreignId('discount_id')->nullable()->after('promotion_id')->constrained()->nullOnDelete();
            $table->decimal('discount_amount', 10, 2)->default(0)->after('promotion_discount');
            $table->json('discount_snapshot')->nullable()->after('promotion_rule_snapshot');
        });

        Schema::table('quotation_items', function (Blueprint $table): void {
            $table->foreignId('discount_id')->nullable()->after('promotion_id')->constrained()->nullOnDelete();
            $table->decimal('discount_amount', 10, 2)->default(0)->after('promotion_discount');
            $table->json('discount_snapshot')->nullable()->after('promotion_rule_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('discount_id');
            $table->dropColumn(['discount_amount', 'discount_snapshot']);
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('discount_id');
            $table->dropColumn(['discount_amount', 'discount_snapshot']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('discount_beneficiary_user_id');
        });

        Schema::dropIfExists('discount_user');
        Schema::dropIfExists('customer_discount');
        Schema::dropIfExists('discount_product');
        Schema::dropIfExists('discounts');
    }
};
