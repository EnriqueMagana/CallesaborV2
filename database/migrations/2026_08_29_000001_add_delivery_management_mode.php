<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table): void {
            $table->boolean('delivery_management_enabled')->default(true);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('delivery_flow_mode', 20)->default('managed')->index();
            $table->timestamp('accounted_at')->nullable()->index();
        });

        Schema::create('delivery_module_audits', function (Blueprint $table): void {
            $table->id();
            $table->boolean('previous_enabled');
            $table->boolean('new_enabled');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cash_register_id')->nullable()->constrained('cash_registers')->nullOnDelete();
            $table->unsignedInteger('converted_orders')->default(0);
            $table->json('impact')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_module_audits');

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['delivery_flow_mode']);
            $table->dropIndex(['accounted_at']);
            $table->dropColumn(['delivery_flow_mode', 'accounted_at']);
        });

        Schema::table('business_settings', function (Blueprint $table): void {
            $table->dropColumn('delivery_management_enabled');
        });
    }
};
