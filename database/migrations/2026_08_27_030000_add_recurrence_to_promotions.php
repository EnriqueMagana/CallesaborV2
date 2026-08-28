<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table): void {
            $table->string('recurrence_type', 30)->default('date_range')->after('ends_on');
            $table->unsignedTinyInteger('monthly_day')->nullable()->after('weekdays');
            $table->index(['recurrence_type', 'monthly_day'], 'promotions_recurrence_index');
        });

        DB::table('promotions')->select(['id', 'weekdays'])->orderBy('id')->each(function ($promotion): void {
            $weekdays = is_string($promotion->weekdays) ? json_decode($promotion->weekdays, true) : $promotion->weekdays;
            if (is_array($weekdays) && $weekdays !== []) {
                DB::table('promotions')->where('id', $promotion->id)->update(['recurrence_type' => 'weekdays']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table): void {
            $table->dropIndex('promotions_recurrence_index');
            $table->dropColumn(['recurrence_type', 'monthly_day']);
        });
    }
};
