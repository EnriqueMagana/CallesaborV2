<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_assignment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('delivery_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_driver_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 30)->default('reassigned');
            $table->string('reason', 500);
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
            $table->index(['to_driver_id', 'created_at']);
        });

        if (Schema::hasTable('role_notification_settings')) {
            DB::table('role_notification_settings')->orderBy('id')->each(function (object $setting): void {
                $events = json_decode($setting->event_keys ?: '[]', true) ?: [];
                if (! in_array('delivery.assigned', $events, true) || in_array('delivery.reassigned', $events, true)) {
                    return;
                }

                $events[] = 'delivery.reassigned';
                DB::table('role_notification_settings')->where('id', $setting->id)->update([
                    'event_keys' => json_encode(array_values(array_unique($events)), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('role_notification_settings')) {
            DB::table('role_notification_settings')->orderBy('id')->each(function (object $setting): void {
                $events = collect(json_decode($setting->event_keys ?: '[]', true) ?: [])
                    ->reject(fn (string $event): bool => $event === 'delivery.reassigned')
                    ->values()
                    ->all();

                DB::table('role_notification_settings')->where('id', $setting->id)->update([
                    'event_keys' => json_encode($events, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
            });
        }

        Schema::dropIfExists('delivery_assignment_events');
    }
};
