<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Área (= número de WhatsApp) a la que pertenece la conversación.
        // 'atencion' es el bot original; 'administracion' y 'ovodonacion' son los nuevos.
        Schema::table('conversaciones_wa', function (Blueprint $table) {
            $table->string('area', 30)->default('atencion')->after('contacto');
            $table->index('area');
        });

        // El unique era sobre `contacto` solo. Ahora un mismo paciente puede tener
        // una conversación separada con cada número, así que la unicidad pasa a ser
        // por (contacto, area).
        Schema::table('conversaciones_wa', function (Blueprint $table) {
            $table->dropUnique('conversaciones_wa_contacto_unique');
            $table->unique(['contacto', 'area']);
        });
    }

    public function down(): void
    {
        Schema::table('conversaciones_wa', function (Blueprint $table) {
            $table->dropUnique(['contacto', 'area']);
            $table->unique('contacto');
        });
        Schema::table('conversaciones_wa', function (Blueprint $table) {
            $table->dropIndex(['area']);
            $table->dropColumn('area');
        });
    }
};
