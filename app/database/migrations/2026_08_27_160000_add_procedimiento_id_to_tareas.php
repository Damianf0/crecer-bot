<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vincula una tarea con el procedimiento que explica cómo resolverla.
 *
 * `tareas` no tiene campo de tipo, así que el vínculo tiene que ser explícito.
 * nullOnDelete: si se borra el procedimiento, la tarea sobrevive sin él.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            $table->foreignId('procedimiento_id')->nullable()->after('ref_id')
                  ->constrained('procedimientos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            $table->dropForeign(['procedimiento_id']);
            $table->dropColumn('procedimiento_id');
        });
    }
};
