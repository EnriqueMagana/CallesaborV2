<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->string('image')->nullable();
            $table->decimal('price', 10, 2);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->json('weekdays')->nullable();
            $table->boolean('show_on_pos')->default(true);
            $table->boolean('show_on_digital_menu')->default(true);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'starts_on', 'ends_on']);
        });

        Schema::create('promotion_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('min_selections')->default(1);
            $table->unsignedSmallInteger('max_selections')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('promotion_group_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['promotion_group_id', 'product_id']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('promotion_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->json('promotion_selections')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotion_id');
            $table->dropColumn('promotion_selections');
        });

        Schema::dropIfExists('promotion_group_product');
        Schema::dropIfExists('promotion_groups');
        Schema::dropIfExists('promotions');
    }
};
