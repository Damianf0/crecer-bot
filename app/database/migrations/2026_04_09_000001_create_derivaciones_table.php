<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('derivaciones', function (Blueprint $table) {
            $table->id();
            $table->string('contacto');          // número WA ej: 5492235000000@c.us
            $table->text('texto');               // mensaje original del paciente
            $table->string('codigo');            // código de acción del bot
            $table->boolean('en_horario');       // si era horario de atención
            $table->string('estado')->default('pendiente'); // pendiente | en_atencion | resuelto
            $table->text('nota')->nullable();    // nota interna de la secretaria
            $table->timestamp('bot_at');         // cuándo lo derivó el bot
            $table->timestamp('atendido_at')->nullable();
            $table->timestamps();

            $table->index(['estado', 'created_at']);
            $table->index('contacto');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('derivaciones');
    }
};
