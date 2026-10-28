<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Derivar de área: lo que el paciente siga mandando al número viejo durante
 * unos días se pasa a la conversación del área destino (antes abría una
 * conversación nueva en el área de origen y el destino nunca lo veía).
 *
 *  - conversaciones_wa.derivada_desde / derivada_at: de qué área vino y cuándo.
 *  - mensajes_wa.via_area: el mensaje entró por el número de otra área.
 *  - conversacion_eventos.detalle: texto libre del evento (p. ej. "atencion→administracion").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversaciones_wa', function (Blueprint $table) {
            $table->string('derivada_desde', 30)->nullable()->after('area');
            $table->timestamp('derivada_at')->nullable()->after('derivada_desde');
        });
        Schema::table('mensajes_wa', function (Blueprint $table) {
            $table->string('via_area', 30)->nullable()->after('direccion');
        });
        Schema::table('conversacion_eventos', function (Blueprint $table) {
            $table->string('detalle', 120)->nullable()->after('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('conversaciones_wa', function (Blueprint $table) {
            $table->dropColumn(['derivada_desde', 'derivada_at']);
        });
        Schema::table('mensajes_wa', function (Blueprint $table) {
            $table->dropColumn('via_area');
        });
        Schema::table('conversacion_eventos', function (Blueprint $table) {
            $table->dropColumn('detalle');
        });
    }
};
