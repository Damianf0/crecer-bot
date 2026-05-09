<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensajes_wa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversacion_id')->constrained('conversaciones_wa')->cascadeOnDelete();
            $table->string('direccion');            // entrante | saliente | nota_interna
            $table->string('tipo')->default('texto'); // texto | audio
            $table->text('contenido')->nullable();
            $table->string('archivo_url')->nullable(); // URL para audios/imágenes
            $table->string('wa_id')->nullable();    // ID de WhatsApp para deduplicar
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('leido')->default(false);
            $table->timestamps();
            $table->index(['conversacion_id', 'created_at']);
            $table->index('wa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajes_wa');
    }
};
