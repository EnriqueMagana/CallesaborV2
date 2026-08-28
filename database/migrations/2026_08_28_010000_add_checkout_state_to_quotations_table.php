<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->string('order_type', 20)->nullable()->after('customer_phone')->index();
            $table->unsignedSmallInteger('draft_version')->default(1)->after('order_type');
            $table->json('checkout_state')->nullable()->after('draft_version');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropIndex(['order_type']);
            $table->dropColumn(['order_type', 'draft_version', 'checkout_state']);
        });
    }
};
