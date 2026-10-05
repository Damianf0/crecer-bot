<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué avisos del panel quiere recibir cada persona (chat interno, conversación
 * delegada, tarea asignada, favorito, urgente) y si suenan. Sin fila = todo
 * prendido (ver PreferenciaAviso::DEFAULTS). Tabla propia para no tocar users.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preferencias_avisos', function (Blueprint $t) {
            $t->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $t->json('prefs');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preferencias_avisos');
    }
};
