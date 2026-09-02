<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->string('icon', 50)->nullable()->after('guard_name');
        });

        $icons = [
            'super-admin' => 'bx-crown',
            'owner' => 'bx-crown',
            'admin' => 'bx-shield',
            'gerente' => 'bx-briefcase',
            'cajero' => 'bx-money',
            'mesero' => 'bx-dish',
            'cocinero' => 'bx-restaurant',
            'repartidor' => 'bx-cycling',
        ];

        foreach ($icons as $role => $icon) {
            DB::table('roles')->where('name', $role)->update(['icon' => $icon]);
        }
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn('icon');
        });
    }
};
