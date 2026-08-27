<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Texto plano derivado del HTML del paso, solo para buscar.
 *
 * Sin esto, el LIKE del buscador corre contra el HTML y devuelve basura: quien
 * busque "li" matchea todas las listas, quien busque "strong" todas las
 * negritas. Se llena al guardar, a partir del HTML ya sanitizado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedimiento_pasos', function (Blueprint $table) {
            $table->text('contenido_texto')->nullable()->after('contenido');
        });
    }

    public function down(): void
    {
        Schema::table('procedimiento_pasos', function (Blueprint $table) {
            $table->dropColumn('contenido_texto');
        });
    }
};
