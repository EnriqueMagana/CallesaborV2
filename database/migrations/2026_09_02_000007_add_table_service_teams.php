<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mesa_assignments', function (Blueprint $table): void {
            $table->string('assignment_type', 20)->default('primary')->after('user_id');
            $table->index(['mesa_id', 'assignment_type', 'released_at'], 'mesa_assignment_active_type_idx');
        });

        Schema::create('mesa_help_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mesa_id')->constrained('mesas')->cascadeOnDelete();
            $table->foreignId('mesa_group_id')->nullable()->constrained('mesa_groups')->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('requested_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('scope', 20)->default('table');
            $table->string('status', 20)->default('pending');
            $table->string('message', 255)->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['requested_user_id', 'status'], 'mesa_help_recipient_status_idx');
            $table->index(['mesa_id', 'status'], 'mesa_help_table_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mesa_help_requests');

        Schema::table('mesa_assignments', function (Blueprint $table): void {
            $table->dropIndex('mesa_assignment_active_type_idx');
            $table->dropColumn('assignment_type');
        });
    }
};
