<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sidebar_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('sidebar_menu_items')->cascadeOnDelete();
            $table->string('type', 20)->default('link');
            $table->string('label', 80);
            $table->string('icon', 80)->nullable();
            $table->string('route_name', 120)->nullable();
            $table->string('url', 255)->nullable();
            $table->string('active_pattern', 160)->nullable();
            $table->string('permission', 160)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
            $table->index(['is_active', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sidebar_menu_items');
    }
};
