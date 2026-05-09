<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tareas', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->foreignId('asignada_a')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('creada_por')->constrained('users');
            $table->timestamp('vence_at')->nullable();
            $table->string('estado')->default('pendiente'); // pendiente | en_progreso | completada
            $table->string('prioridad')->default('normal'); // baja | normal | alta
            $table->string('ref_tipo')->nullable();         // wa | bot
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->timestamps();

            $table->index(['asignada_a', 'estado']);
            $table->index(['creada_por', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tareas');
    }
};
