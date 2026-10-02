<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->boolean('online_sales_enabled')->default(false)->after('delivery_management_enabled');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('preferred_payment_method', 30)->nullable()->after('fulfillment');
            $table->decimal('cash_tendered', 10, 2)->nullable()->after('preferred_payment_method');
        });

        Schema::create('online_orders', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 32)->unique();
            $table->string('public_token', 64)->unique();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('awaiting_whatsapp')->index();
            $table->string('fulfillment', 20)->index();
            $table->string('customer_name', 160);
            $table->string('customer_phone', 20);
            $table->string('customer_address')->nullable();
            $table->string('customer_neighborhood', 120)->nullable();
            $table->text('customer_references')->nullable();
            $table->string('payment_method', 30);
            $table->decimal('cash_tendered', 10, 2)->nullable();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('total', 10, 2);
            $table->json('cart_snapshot');
            $table->text('customer_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('whatsapp_opened_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_orders');
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['preferred_payment_method', 'cash_tendered']));
        Schema::table('business_settings', fn (Blueprint $table) => $table->dropColumn('online_sales_enabled'));
    }
};
