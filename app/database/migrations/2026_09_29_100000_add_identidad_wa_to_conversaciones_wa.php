<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identidad de WhatsApp de cada conversación, según WhatsApp Web (no según el
 * directorio): el teléfono real detrás de un @lid y el nombre que la persona
 * tiene puesto en su perfil. Sin esto, 2.010 conversaciones @lid sin ficha se
 * veían como un código ("123456@lid") y no se encontraban por número; y cuando
 * el número de Omnia es el WhatsApp de otra persona (pareja, madre), el panel
 * mostraba el nombre de la paciente con la foto del otro sin avisar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversaciones_wa', function (Blueprint $t) {
            $t->string('telefono_wa', 20)->nullable()->after('nombre');
            $t->string('nombre_wa', 100)->nullable()->after('telefono_wa');
            $t->timestamp('wa_info_at')->nullable()->after('nombre_wa');
            $t->index('telefono_wa');
        });
    }

    public function down(): void
    {
        Schema::table('conversaciones_wa', function (Blueprint $t) {
            $t->dropIndex(['telefono_wa']);
            $t->dropColumn(['telefono_wa', 'nombre_wa', 'wa_info_at']);
        });
    }
};
