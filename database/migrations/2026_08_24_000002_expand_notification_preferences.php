<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->boolean('notifications_enabled')->default(true)->after('user_id');
            $table->boolean('quiet_hours_enabled')->default(false)->after('volume');
            $table->time('quiet_hours_start')->default('22:00')->after('quiet_hours_enabled');
            $table->time('quiet_hours_end')->default('07:00')->after('quiet_hours_start');
            $table->json('event_preferences')->nullable()->after('quiet_hours_end');
        });

        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn('notification_settings');
        });
    }

    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->json('notification_settings')->nullable();
        });

        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn([
                'notifications_enabled',
                'quiet_hours_enabled',
                'quiet_hours_start',
                'quiet_hours_end',
                'event_preferences',
            ]);
        });
    }
};
