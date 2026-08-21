<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de archivados masivos de conversaciones (por inactividad o por rango
 * de fechas). Guarda el estado previo de cada conversación del lote para poder
 * deshacer la corrida completa desde el panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archivados_lote', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('origen', 20)->default('panel');   // panel | consola
            $table->json('criterio');                          // modo, dias/desde/hasta, area, excluir_asignadas
            $table->unsignedInteger('total')->default(0);
            $table->json('snapshot');                          // [{id, estado, asignada_a, urgente}, ...]
            $table->timestamp('revertido_at')->nullable();
            $table->foreignId('revertido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('revertidas')->nullable(); // cuántas se pudieron revertir
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archivados_lote');
    }
};
