<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->change();
            $table->string('customer_email', 160)->nullable()->after('customer_phone');
            $table->string('occasion', 80)->nullable()->after('guests');
            $table->string('source', 30)->default('admin')->after('status');
            $table->uuid('public_token')->nullable()->unique()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropUnique(['public_token']);
            $table->dropColumn(['customer_email', 'occasion', 'source', 'public_token']);
            $table->foreignId('created_by')->nullable(false)->change();
        });
    }
};
