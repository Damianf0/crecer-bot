<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dos protecciones sobre el contenido de la base de conocimiento:
 *
 * - softDeletes: esto lo escribe a mano gente no técnica. Un borrado accidental
 *   sin esto solo se recupera del backup de la noche anterior.
 * - revisado_at: en una clínica, un procedimiento desactualizado que alguien
 *   sigue al pie de la letra es peor que no tener nada escrito. La vista avisa
 *   cuando pasaron más de 6 meses sin revisión.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedimientos', function (Blueprint $table) {
            $table->timestamp('revisado_at')->nullable()->after('orden');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('procedimientos', function (Blueprint $table) {
            $table->dropColumn(['revisado_at', 'deleted_at']);
        });
    }
};
