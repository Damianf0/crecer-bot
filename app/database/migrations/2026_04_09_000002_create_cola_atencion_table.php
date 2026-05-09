<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cola_atencion', function (Blueprint $table) {
            $table->id();

            // Datos del paciente (copiados desde Omnia en el momento del check-in)
            $table->string('dni', 20);
            $table->string('nombre', 100);
            $table->string('apellido', 100);
            $table->string('obra_social', 150)->nullable();
            $table->string('plan', 100)->nullable();

            // Datos del turno Omnia
            $table->string('omnia_turno_id', 50)->nullable();
            $table->string('profesional', 150)->nullable();
            $table->string('practica', 150)->nullable();
            $table->time('turno_hora')->nullable();

            // Clasificación
            $table->string('planta', 10)->nullable();     // alta | baja
            $table->string('motivo', 50)->default('turno'); // turno | quiere_turno | gestion
            $table->boolean('primera_vez')->default(false);
            $table->boolean('sin_turno')->default(false);
            $table->boolean('derivado_bot')->default(false);

            // Estado del flujo
            $table->string('estado', 20)->default('esperando'); // esperando | en_atencion | liberado | resuelto
            $table->unsignedInteger('orden')->default(0);       // para reordenar manualmente
            $table->boolean('alerta_espera')->default(false);

            // Checklist (JSON de ítems con done: bool)
            $table->json('checklist')->nullable();

            // Atención
            $table->text('nota')->nullable();
            $table->timestamp('hora_llegada');
            $table->timestamp('hora_llamado')->nullable();
            $table->timestamp('hora_liberado')->nullable();

            $table->timestamps();

            $table->index(['estado', 'orden', 'hora_llegada']);
            $table->index('dni');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cola_atencion');
    }
};
