<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resumen por consulta (23/10). La conversación es una sola por paciente y
 * número, para siempre: el resumen de mayo seguía mostrándose en octubre.
 *  - episodio_desde: desde cuándo cuenta la consulta actual (vuelve a escribir
 *    tras archivar o tras 3 días sin movimiento). El resumen solo mira desde acá.
 *  - resumen_msg_id: último mensaje entrante que entró en el resumen; si hay
 *    entrantes más nuevos, el resumen se regenera.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversaciones_wa', function (Blueprint $t) {
            $t->timestamp('episodio_desde')->nullable()->after('resumen_intento_at');
            $t->unsignedBigInteger('resumen_msg_id')->nullable()->after('episodio_desde');
        });
    }

    public function down(): void
    {
        Schema::table('conversaciones_wa', function (Blueprint $t) {
            $t->dropColumn(['episodio_desde', 'resumen_msg_id']);
        });
    }
};
