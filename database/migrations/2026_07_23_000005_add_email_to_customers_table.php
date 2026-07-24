<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customers', 'email')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->string('email', 160)->nullable()->after('phone')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customers', 'email')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->dropIndex(['email']);
                $table->dropColumn('email');
            });
        }
    }
};
