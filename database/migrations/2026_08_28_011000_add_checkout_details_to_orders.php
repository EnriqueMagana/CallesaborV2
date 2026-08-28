<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('customer_neighborhood', 120)->nullable()->after('customer_address');
        });

        Schema::table('order_payments', function (Blueprint $table) {
            $table->string('card_last4', 4)->nullable()->after('change_amount');
            $table->string('transfer_reference', 120)->nullable()->after('card_last4');
        });
    }

    public function down(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->dropColumn(['card_last4', 'transfer_reference']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('customer_neighborhood');
        });
    }
};
