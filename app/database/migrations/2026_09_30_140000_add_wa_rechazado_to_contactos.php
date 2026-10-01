<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp que NO es de la persona de la ficha: el teléfono cargado en Omnia es
 * de otra persona (pareja, madre, un médico) y la clínica lo tiene agendado con
 * otro nombre. Caso testigo 30/09: la ficha de una paciente tenía el celular
 * del Dr. Elena → 628 mensajes del doctor figuraban como de ella y 3 audios suyos
 * estaban en el legajo de la paciente.
 *
 *  - wa_id_rechazado: el JID que no se le debe volver a vincular (el proceso
 *    nocturno re-vincula por teléfono si no se lo impide).
 *  - wa_rechazo_nombre: cómo lo tiene agendado la clínica (para mostrarlo y
 *    para el listado de teléfonos a corregir en Omnia).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contactos', function (Blueprint $t) {
            $t->string('wa_id_rechazado', 100)->nullable()->after('wa_id');
            $t->string('wa_rechazo_nombre', 100)->nullable()->after('wa_id_rechazado');
            $t->timestamp('wa_rechazado_at')->nullable()->after('wa_rechazo_nombre');
            $t->index('wa_id_rechazado');
        });
    }

    public function down(): void
    {
        Schema::table('contactos', function (Blueprint $t) {
            $t->dropIndex(['wa_id_rechazado']);
            $t->dropColumn(['wa_id_rechazado', 'wa_rechazo_nombre', 'wa_rechazado_at']);
        });
    }
};
