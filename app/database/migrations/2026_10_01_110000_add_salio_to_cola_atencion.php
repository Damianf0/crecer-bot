<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pacientes "en la clínica": los que recepción liberó al consultorio siguen
 * listados unas horas por si vuelven al mostrador (pasa: ven al especialista y
 * vuelven por algo; antes tenían que anotarse otra vez en el tablet).
 *
 *  - salio_at: cuándo salió de esa lista (a mano, "se fue", o porque volvió al
 *    mostrador y se abrió una visita nueva).
 *  - vuelve_de_id: la visita de la que vuelve (para el reporte y para no perder
 *    el hilo del día).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cola_atencion', function (Blueprint $t) {
            $t->timestamp('salio_at')->nullable()->after('hora_liberado');
            $t->foreignId('vuelve_de_id')->nullable()->after('registrado_por')->constrained('cola_atencion')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cola_atencion', function (Blueprint $t) {
            $t->dropConstrainedForeignId('vuelve_de_id');
            $t->dropColumn('salio_at');
        });
    }
};
