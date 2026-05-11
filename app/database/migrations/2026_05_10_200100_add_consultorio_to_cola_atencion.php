<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cola_atencion', function (Blueprint $t) {
            // Consultorio numerado al que se llama al paciente. Se setea cuando
            // el médico hace click en "Llamar" — el llamador de sala de espera
            // muestra: "Pase Juan Pérez, consultorio 3 (planta alta)".
            $t->unsignedSmallInteger('consultorio')->nullable()->after('planta');
            // `hora_llamado` ya la usa la secretaria al abrir la ficha (= "lo
            // estoy atendiendo en recepción"). Usamos un campo aparte para el
            // momento en que el médico llama al paciente al consultorio.
            $t->timestamp('llamado_consultorio_at')->nullable()->after('hora_llamado');
            $t->timestamp('atendido_at')->nullable()->after('llamado_consultorio_at');
        });
    }

    public function down(): void
    {
        Schema::table('cola_atencion', function (Blueprint $t) {
            $t->dropColumn(['consultorio', 'llamado_consultorio_at', 'atendido_at']);
        });
    }
};
