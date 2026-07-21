<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosk_terminals', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('token_hash', 64)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('kiosk_terminal_id')->nullable()->after('cash_register_id')
                ->constrained('kiosk_terminals')->nullOnDelete();
            $table->string('public_token', 64)->nullable()->unique()->after('kiosk_terminal_id');
            $table->string('source', 30)->default('pos')->after('type');
            $table->string('fulfillment', 30)->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['kiosk_terminal_id']);
            $table->dropColumn(['kiosk_terminal_id', 'public_token', 'source', 'fulfillment']);
        });

        Schema::dropIfExists('kiosk_terminals');
    }
};
