<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversaciones_wa', function (Blueprint $t) {
            // Marca el momento del último intento de generar resumen LLM. Permite distinguir
            // "nunca se intentó" de "se intentó y falló" o "no amerita resumen".
            $t->timestamp('resumen_intento_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('conversaciones_wa', function (Blueprint $t) {
            $t->dropColumn('resumen_intento_at');
        });
    }
};
