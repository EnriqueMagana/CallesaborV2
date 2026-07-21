<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('banned_at')->nullable()->after('deleted_at')->index();
            $table->foreignId('banned_by')->nullable()->after('banned_at')->constrained('users')->nullOnDelete();
            $table->string('ban_reason', 500)->nullable()->after('banned_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['banned_by']);
            $table->dropColumn(['banned_at', 'banned_by', 'ban_reason']);
        });
    }
};
