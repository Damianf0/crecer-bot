<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega soporte de "mensaje citado" (replies estilo WhatsApp) a mensajes_wa.
 * Solo lectura: el bot extrae el contexto de quoted al recibir y lo guardamos
 * para que el panel pueda renderear el bubble con la cita arriba.
 *
 *   quoted_wa_id   — el wa_id del mensaje original (puede no estar en BD si
 *                    es viejo o si el remitente respondió a un msg ajeno a
 *                    la conversación; aún así guardamos el preview).
 *   quoted_autor   — nombre/handle de quien envió el msg citado (cacheado
 *                    al momento de recibir; útil cuando el original es de
 *                    otra área o de un usuario que rotó).
 *   quoted_preview — texto/descripción corta del msg citado (max 300 chars,
 *                    truncado al guardar para no inflar la tabla).
 *
 * Mandar replies desde el panel hacia WA no está en este alcance; eso requiere
 * sumar el lado "send with quote" en el adapter + el form.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('mensajes_wa', function (Blueprint $table) {
            $table->string('quoted_wa_id', 150)->nullable()->after('wa_id');
            $table->string('quoted_autor', 80)->nullable()->after('quoted_wa_id');
            $table->string('quoted_preview', 300)->nullable()->after('quoted_autor');
        });
    }

    public function down(): void
    {
        Schema::table('mensajes_wa', function (Blueprint $table) {
            $table->dropColumn(['quoted_wa_id', 'quoted_autor', 'quoted_preview']);
        });
    }
};
