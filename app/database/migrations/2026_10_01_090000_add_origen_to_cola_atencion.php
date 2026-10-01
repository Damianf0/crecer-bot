<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De dónde vino cada llegada a recepción: el tablet de la entrada o la
 * recepcionista en el mostrador. Hasta el 01/10 solo existía el tablet y los
 * pacientes atendidos en el mostrador sin anotarse no quedaban registrados (en
 * los 30 días previos la cola tenía 2 llegadas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cola_atencion', function (Blueprint $t) {
            $t->string('origen', 12)->default('tablet')->after('motivo');   // tablet | mostrador
            $t->foreignId('registrado_por')->nullable()->after('origen')->constrained('users')->nullOnDelete();
            $t->index(['hora_llegada', 'origen']);
        });
    }

    public function down(): void
    {
        Schema::table('cola_atencion', function (Blueprint $t) {
            $t->dropIndex(['hora_llegada', 'origen']);
            $t->dropConstrainedForeignId('registrado_por');
            $t->dropColumn('origen');
        });
    }
};
