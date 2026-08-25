<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->string('event_key', 80)->index();
            $table->string('category', 30)->index();
            $table->string('priority', 20)->default('normal')->index();
            $table->nullableMorphs('subject');
            $table->string('dedupe_key', 160);
            $table->json('data');
            $table->timestamp('announced_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['notifiable_type', 'notifiable_id', 'dedupe_key'], 'notifications_recipient_dedupe_unique');
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_recipient_read_index');
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('sound_enabled')->default(true);
            $table->unsignedTinyInteger('volume')->default(65);
            $table->timestamps();
        });

        Schema::table('business_settings', function (Blueprint $table) {
            $table->json('notification_settings')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn('notification_settings');
        });

        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
    }
};
