<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una fila por cada vez que la IA clasifica una tanda de mensajes de un
 * paciente (el bot agrupa los mensajes seguidos antes de clasificar). Base de
 * las estadísticas por tipo de consulta.
 *
 *  - origen: 'bot' (en vivo) | 'retro' (clasificación retroactiva de
 *    conversaciones viejas a partir de su resumen, ver clasificaciones:retro).
 *  - sin_ia: FALLBACK porque Ollama no respondió, no porque el modelo no supo.
 *  - codigo_corregido: corrección de la supervisora; las estadísticas usan
 *    COALESCE(codigo_corregido, codigo) y la tasa de acierto sale de acá.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clasificaciones_wa', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversacion_id')->nullable()->constrained('conversaciones_wa')->nullOnDelete();
            $t->string('area', 30);
            $t->string('contacto', 100);
            $t->string('codigo', 40);
            $t->string('confianza', 10)->nullable();
            $t->string('resumen', 300)->nullable();
            $t->boolean('en_horario')->default(true);
            $t->boolean('sin_ia')->default(false);
            $t->string('origen', 10)->default('bot');
            $t->string('codigo_corregido', 40)->nullable();
            $t->foreignId('corregido_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('corregido_at')->nullable();
            $t->timestamps();
            $t->index(['created_at', 'area']);
            $t->index(['codigo', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clasificaciones_wa');
    }
};
