<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tareas_wa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversacion_id')->constrained('conversaciones_wa')->cascadeOnDelete();
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->string('estado')->default('pendiente'); // pendiente | completada
            $table->foreignId('asignado_a')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('creado_por')->constrained('users');
            $table->timestamp('vence_at')->nullable();
            $table->timestamp('completado_at')->nullable();
            $table->timestamps();
            $table->index(['conversacion_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tareas_wa');
    }
};
