<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un procedimiento cubre VARIOS casos del clasificador, no uno solo:
 * RESULTADO_BETA y RESULTADO_OTROS se resuelven igual, y lo mismo pasa con las
 * dos variantes de turno de ecografía. La columna `codigo_bot` forzaba un 1:1
 * que se rompía el primer día.
 *
 * Migra lo que haya en `codigo_bot` a la pivote y después elimina la columna,
 * para no quedar con dos fuentes de verdad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedimiento_codigos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedimiento_id')->constrained('procedimientos')->cascadeOnDelete();
            $table->string('codigo', 40)->index();

            $table->unique(['procedimiento_id', 'codigo']);
        });

        // Traspaso de los procedimientos ya cargados
        DB::table('procedimientos')
            ->whereNotNull('codigo_bot')
            ->where('codigo_bot', '!=', '')
            ->orderBy('id')
            ->each(function ($p) {
                DB::table('procedimiento_codigos')->insertOrIgnore([
                    'procedimiento_id' => $p->id,
                    'codigo'           => $p->codigo_bot,
                ]);
            });

        // El índice primero: MySQL lo arrastra solo al borrar la columna, SQLite
        // (tests) no y rechaza el drop.
        Schema::table('procedimientos', function (Blueprint $table) {
            $table->dropIndex(['codigo_bot']);
            $table->dropColumn('codigo_bot');
        });
    }

    public function down(): void
    {
        Schema::table('procedimientos', function (Blueprint $table) {
            $table->string('codigo_bot', 40)->nullable()->index()->after('area');
        });

        // Devuelve el primer código de cada procedimiento (la columna solo admite uno)
        foreach (DB::table('procedimiento_codigos')->orderBy('id')->get() as $c) {
            DB::table('procedimientos')
                ->where('id', $c->procedimiento_id)
                ->whereNull('codigo_bot')
                ->update(['codigo_bot' => $c->codigo]);
        }

        Schema::dropIfExists('procedimiento_codigos');
    }
};
