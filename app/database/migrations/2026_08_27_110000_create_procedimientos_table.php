<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base de conocimiento: procedimientos de atención.
 *
 * Milestone M3 del brief original (CLAUDE-clinica.md) — repositorio interno de
 * protocolos e instructivos, editable desde el panel por supervisión.
 *
 * `codigo_bot` engancha el procedimiento con el código que devuelve el
 * clasificador del bot (bot/ollama.js), para poder sugerir el procedimiento
 * correcto según cómo se clasificó una conversación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedimientos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 160);
            $table->string('slug', 180)->unique();
            $table->string('area', 30)->index();        // atencion | administracion | ovodonacion | general
            $table->string('codigo_bot', 40)->nullable()->index();
            $table->string('resumen', 300)->nullable();
            $table->string('estado', 20)->default('borrador');   // borrador | publicado
            $table->unsignedInteger('orden')->default(0);
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['area', 'orden']);
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedimientos');
    }
};
