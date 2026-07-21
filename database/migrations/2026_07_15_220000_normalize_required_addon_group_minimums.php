<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('addon_groups')
            ->where('is_required', true)
            ->where('min_selections', '<', 1)
            ->update(['min_selections' => 1]);
    }

    public function down(): void
    {
        // La normalización corrige datos inválidos y no debe revertirse.
    }
};
