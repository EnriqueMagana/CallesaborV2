<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->foreignId('promotion_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->json('promotion_selections')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotion_id');
            $table->dropColumn('promotion_selections');
        });
    }
};
