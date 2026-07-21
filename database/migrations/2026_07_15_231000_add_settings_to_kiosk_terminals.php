<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kiosk_terminals', function (Blueprint $table) {
            $table->string('token_hint', 12)->nullable()->after('token_hash');
            $table->boolean('allow_dine_in')->default(true)->after('is_active');
            $table->boolean('allow_takeaway')->default(true)->after('allow_dine_in');
            $table->boolean('require_customer_phone')->default(false)->after('allow_takeaway');
            $table->unsignedTinyInteger('orders_per_minute')->default(8)->after('require_customer_phone');
            $table->unsignedSmallInteger('auto_reset_seconds')->default(45)->after('orders_per_minute');
            $table->string('welcome_title', 100)->default('¿Cómo quieres disfrutar tu pedido?')->after('auto_reset_seconds');
            $table->string('welcome_message', 240)->default('Elige una opción para comenzar. Podrás personalizar cada producto antes de confirmar.')->after('welcome_title');
            $table->string('payment_instructions', 180)->default('Paga en caja mostrando tu número de pedido.')->after('welcome_message');
            $table->string('success_message', 180)->default('Tu pedido fue recibido y pronto comenzaremos a prepararlo.')->after('payment_instructions');
        });
    }

    public function down(): void
    {
        Schema::table('kiosk_terminals', function (Blueprint $table) {
            $table->dropColumn([
                'token_hint', 'allow_dine_in', 'allow_takeaway', 'require_customer_phone',
                'orders_per_minute', 'auto_reset_seconds', 'welcome_title', 'welcome_message',
                'payment_instructions', 'success_message',
            ]);
        });
    }
};
