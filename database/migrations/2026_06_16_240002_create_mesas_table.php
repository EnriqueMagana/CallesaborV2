<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mesas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('areas')->restrictOnDelete();
            $table->foreignId('mesa_group_id')->nullable()->constrained('mesa_groups')->nullOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('name', 80)->nullable();
            $table->unsignedSmallInteger('capacity')->default(4);
            $table->enum('status', ['disponible', 'ocupada', 'reservada', 'en_cuenta', 'bloqueada'])->default('disponible');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['area_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mesas');
    }
};
