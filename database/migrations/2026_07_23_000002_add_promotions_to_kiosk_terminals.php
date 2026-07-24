<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kiosk_terminals', function (Blueprint $table) {
            $table->boolean('promotion_enabled')->default(false)->after('success_message');
            $table->string('promotion_badge', 60)->default('Especiales de la casa')->after('promotion_enabled');
            $table->string('promotion_title', 120)->default('Descubre algo delicioso')->after('promotion_badge');
            $table->string('promotion_message', 240)->default('Conoce nuestras recomendaciones y encuentra tu próximo favorito.')->after('promotion_title');
        });

        Schema::create('kiosk_product_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kiosk_terminal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('promotional_price', 10, 2)->nullable();
            $table->string('label', 40)->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['kiosk_terminal_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_product_promotions');

        Schema::table('kiosk_terminals', function (Blueprint $table) {
            $table->dropColumn([
                'promotion_enabled',
                'promotion_badge',
                'promotion_title',
                'promotion_message',
            ]);
        });
    }
};
