<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adjuntos de un procedimiento: capturas de pantalla e instructivos PDF.
 *
 * Ojo: los archivos NO van a storage/app/public. Ese directorio está expuesto
 * por el symlink public/storage y se serviría sin autenticación; una captura de
 * un procedimiento puede tener datos de pacientes. Se guardan en
 * storage/app/procedimientos y se sirven por ProcedimientoAdjuntoController,
 * que exige sesión (mismo criterio que WaMediaController con la media de WA).
 *
 * `paso_id` nullable: un adjunto puede colgar del procedimiento entero o de un
 * paso puntual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedimiento_adjuntos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedimiento_id')->constrained('procedimientos')->cascadeOnDelete();
            $table->foreignId('paso_id')->nullable()->constrained('procedimiento_pasos')->cascadeOnDelete();
            $table->string('tipo', 20)->default('archivo');   // imagen | archivo
            $table->string('path', 400);
            $table->string('nombre_original', 255);
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('tamano')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['procedimiento_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedimiento_adjuntos');
    }
};
