<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mesa_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_register_id')->constrained()->restrictOnDelete();
            $table->foreignId('primary_mesa_id')->nullable()->constrained('mesas')->nullOnDelete();
            $table->foreignId('mesa_group_id')->nullable()->constrained('mesa_groups')->nullOnDelete();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('kiosk_terminal_id')->nullable()->constrained('kiosk_terminals')->nullOnDelete();
            $table->string('source', 20)->default('waiter');
            $table->string('status', 30)->default('abierta');
            $table->string('service_label', 160);
            $table->string('opener_name_snapshot', 160)->nullable();
            $table->string('group_name_snapshot', 120)->nullable();
            $table->decimal('total_snapshot', 10, 2)->default(0);
            $table->text('close_reason')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('in_account_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['cash_register_id', 'status']);
            $table->index(['primary_mesa_id', 'status']);
            $table->index(['mesa_group_id', 'status']);
        });

        Schema::create('mesa_service_mesa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mesa_service_id')->constrained('mesa_services')->cascadeOnDelete();
            $table->foreignId('mesa_id')->constrained('mesas')->restrictOnDelete();
            $table->string('mesa_label_snapshot', 120);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['mesa_service_id', 'mesa_id']);
            $table->index(['mesa_id', 'mesa_service_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('mesa_service_id')
                ->nullable()
                ->after('mesa_id')
                ->constrained('mesa_services')
                ->nullOnDelete();
            $table->index(['mesa_service_id', 'status']);
        });

        Schema::table('mesa_assignments', function (Blueprint $table) {
            $table->foreignId('mesa_service_id')
                ->nullable()
                ->after('mesa_id')
                ->constrained('mesa_services')
                ->nullOnDelete();
        });

        Schema::table('mesa_splits', function (Blueprint $table) {
            $table->foreignId('mesa_service_id')
                ->nullable()
                ->after('mesa_id')
                ->constrained('mesa_services')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mesa_splits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mesa_service_id');
        });

        Schema::table('mesa_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mesa_service_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['mesa_service_id', 'status']);
            $table->dropConstrainedForeignId('mesa_service_id');
        });

        Schema::dropIfExists('mesa_service_mesa');
        Schema::dropIfExists('mesa_services');
    }
};
