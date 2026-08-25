<?php

use App\Models\AppNotification;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    if (! Schema::hasTable('notifications')) {
        return;
    }

    AppNotification::query()
        ->where('created_at', '<', now()->subDays(30))
        ->delete();
})->dailyAt('03:20')->name('notifications:prune')->withoutOverlapping();

Schedule::command('notifications:clear-realtime')
    ->dailyAt((string) config('firebase.realtime.cleanup_time', '23:59'))
    ->timezone((string) config('firebase.realtime.cleanup_timezone', 'America/Mexico_City'))
    ->name('notifications:clear-realtime')
    ->withoutOverlapping();
