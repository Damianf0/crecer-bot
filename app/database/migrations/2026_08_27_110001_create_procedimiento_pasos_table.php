<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pasos de un procedimiento. El contenido es HTML, pero SIEMPRE pasado por
 * App\Services\HtmlSeguro::limpiar() antes de guardarse — nunca confiar en lo
 * que manda el editor del navegador.
 *
 * `respuesta_wa` es el texto listo para copiar/enviar al paciente en ese paso
 * (mismo uso que las respuestas rápidas, pero en el contexto del procedimiento).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedimiento_pasos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedimiento_id')->constrained('procedimientos')->cascadeOnDelete();
            $table->unsignedInteger('orden')->default(0);
            $table->string('titulo', 160)->nullable();
            $table->text('contenido')->nullable();
            $table->text('respuesta_wa')->nullable();
            $table->timestamps();

            $table->index(['procedimiento_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedimiento_pasos');
    }
};
