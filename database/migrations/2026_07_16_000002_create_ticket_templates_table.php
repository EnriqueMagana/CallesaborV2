<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->unsignedSmallInteger('paper_width_mm')->default(80);
            $table->unsignedTinyInteger('font_size')->default(12);
            $table->unsignedTinyInteger('margin_mm')->default(4);
            $table->boolean('show_logo')->default(false);
            $table->boolean('show_qr')->default(false);
            $table->string('qr_label')->nullable();
            $table->text('footer_text')->nullable();
            $table->json('blocks');
            $table->json('options')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_templates');
    }
};
