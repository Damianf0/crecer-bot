<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pacientes de primera vez: por qué llegan y con qué médico se les da turno.
 * Reemplaza la planilla que recepción llevaba a mano. El médico se guarda por
 * nombre (no por id): la lista es propia de este registro, se edita desde el
 * panel, y sacar a un médico de la lista no tiene que tocar el historial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('primera_vez_medicos', function (Blueprint $t) {
            $t->id();
            $t->string('nombre', 80)->unique();
            $t->boolean('activo')->default(true);
            $t->timestamps();
        });

        Schema::create('primeras_veces', function (Blueprint $t) {
            $t->id();
            $t->date('fecha')->index();
            // Lo importado de la planilla solo trae el mes: la fecha es el día 1.
            $t->boolean('solo_mes')->default(false);
            $t->string('nombre', 160);
            $t->string('telefono', 30)->nullable();
            $t->string('dni', 15)->nullable()->index();
            $t->foreignId('contacto_id')->nullable()->constrained('contactos')->nullOnDelete();
            $t->string('motivo', 30);
            $t->string('medico', 80)->nullable();
            $t->string('derivante', 160)->nullable();
            $t->string('detalle', 500)->nullable();
            $t->string('origen', 20)->default('panel');
            $t->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('primeras_veces');
        Schema::dropIfExists('primera_vez_medicos');
    }
};
