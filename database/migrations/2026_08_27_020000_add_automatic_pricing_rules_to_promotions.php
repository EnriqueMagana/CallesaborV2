<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table): void {
            $table->string('pricing_rule_type', 60)->nullable()->after('discount_percentage');
            $table->json('pricing_rule_config')->nullable()->after('pricing_rule_type');
            $table->boolean('auto_apply')->default(false)->after('pricing_rule_config');
            $table->index(['auto_apply', 'pricing_rule_type'], 'promotions_automatic_rule_index');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->decimal('promotion_discount', 10, 2)->default(0)->after('subtotal');
            $table->json('promotion_rule_snapshot')->nullable()->after('promotion_selections');
        });

        Schema::table('quotation_items', function (Blueprint $table): void {
            $table->decimal('promotion_discount', 10, 2)->default(0)->after('subtotal');
            $table->json('promotion_rule_snapshot')->nullable()->after('promotion_selections');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table): void {
            $table->dropColumn(['promotion_discount', 'promotion_rule_snapshot']);
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn(['promotion_discount', 'promotion_rule_snapshot']);
        });

        Schema::table('promotions', function (Blueprint $table): void {
            $table->dropIndex('promotions_automatic_rule_index');
            $table->dropColumn(['pricing_rule_type', 'pricing_rule_config', 'auto_apply']);
        });
    }
};
