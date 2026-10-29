<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tareas para un área (29/10). Antes una tarea solo se asignaba a una persona;
 * para pasarle algo a otra área (ej. un comprobante a Administración) había
 * que derivar la conversación entera y quien la tenía perdía el hilo.
 *
 *  - tareas.area: el área destino. Sin persona asignada, la ve todo el equipo
 *    del área; quien la toma queda en asignada_a.
 *  - tareas.completada_por / completada_at: quién la cerró, para avisarle a
 *    quien la pidió.
 *  - tarea_mensajes: los mensajes de la conversación que se marcaron al crearla
 *    (textos y adjuntos). Viajan con la tarea: el área destino los ve aunque no
 *    atienda la cola de esa conversación.
 *  - mensajes_wa.tarea_id: respuesta al paciente mandada desde una tarea (queda
 *    en el chat, marcada).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            $table->string('area', 30)->nullable()->after('asignada_a');
            $table->foreignId('completada_por')->nullable()->after('estado')->constrained('users')->nullOnDelete();
            $table->timestamp('completada_at')->nullable()->after('completada_por');
            $table->index(['area', 'estado']);
        });

        Schema::create('tarea_mensajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarea_id')->constrained('tareas')->cascadeOnDelete();
            $table->foreignId('mensaje_id')->constrained('mensajes_wa')->cascadeOnDelete();
            $table->unique(['tarea_id', 'mensaje_id']);
        });

        Schema::table('mensajes_wa', function (Blueprint $table) {
            $table->unsignedBigInteger('tarea_id')->nullable()->after('via_area');
        });
    }

    public function down(): void
    {
        Schema::table('mensajes_wa', fn (Blueprint $t) => $t->dropColumn('tarea_id'));
        Schema::dropIfExists('tarea_mensajes');
        Schema::table('tareas', function (Blueprint $table) {
            $table->dropIndex(['area', 'estado']);
            $table->dropForeign(['completada_por']);
            $table->dropColumn(['area', 'completada_por', 'completada_at']);
        });
    }
};
