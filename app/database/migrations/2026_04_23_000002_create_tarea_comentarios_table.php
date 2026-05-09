<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarea_comentarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarea_id')->constrained('tareas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->text('contenido');
            $table->timestamps();

            $table->index('tarea_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarea_comentarios');
    }
};
