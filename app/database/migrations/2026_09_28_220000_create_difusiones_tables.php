<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Difusiones (envíos masivos desde el 4º número), independientes del proveedor.
 *
 *  - difusion_plantillas: mensajes reutilizables ({nombre} se reemplaza).
 *  - difusion_campanias: un envío. Copia texto y adjunto de la plantilla al
 *    crearse (editar la plantilla no cambia lo ya enviado). `audiencia` guarda
 *    los filtros con los que se armó la lista, para mostrarlos después.
 *  - difusion_destinatarios: una fila por persona y campaña; su estado y las
 *    marcas de tiempo son las estadísticas. `mensaje_id` es el id del
 *    proveedor (para los acuses) y `chat_id` el chat real al que llegó (en
 *    WhatsApp puede ser un @lid distinto del número: con él se reconocen las
 *    respuestas).
 *  - difusion_bajas: quien pidió no recibir más. Se excluye siempre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('difusion_plantillas', function (Blueprint $t) {
            $t->id();
            $t->string('nombre', 120);
            $t->text('texto');
            $t->string('adjunto_path', 255)->nullable();
            $t->string('adjunto_nombre', 191)->nullable();
            $t->string('adjunto_mime', 100)->nullable();
            $t->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('difusion_campanias', function (Blueprint $t) {
            $t->id();
            $t->string('nombre', 160);
            $t->foreignId('plantilla_id')->nullable()->constrained('difusion_plantillas')->nullOnDelete();
            $t->text('texto');
            $t->string('adjunto_path', 255)->nullable();
            $t->string('adjunto_nombre', 191)->nullable();
            $t->string('adjunto_mime', 100)->nullable();
            $t->json('audiencia')->nullable();
            $t->string('proveedor', 20);
            // borrador | programada | enviando | pausada | terminada | cancelada
            $t->string('estado', 12)->default('borrador');
            $t->timestamp('programada_para')->nullable();
            $t->timestamp('iniciada_at')->nullable();
            $t->timestamp('terminada_at')->nullable();
            $t->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index('estado');
        });

        Schema::create('difusion_destinatarios', function (Blueprint $t) {
            $t->id();
            $t->foreignId('campania_id')->constrained('difusion_campanias')->cascadeOnDelete();
            $t->foreignId('contacto_id')->nullable()->constrained('contactos')->nullOnDelete();
            $t->string('telefono', 30);
            $t->string('nombre', 150)->nullable();
            // pendiente | enviado | entregado | leido | fallido | omitido
            $t->string('estado', 10)->default('pendiente');
            $t->string('error', 255)->nullable();
            $t->string('mensaje_id', 150)->nullable();
            $t->string('chat_id', 100)->nullable();
            $t->timestamp('enviado_at')->nullable();
            $t->timestamp('entregado_at')->nullable();
            $t->timestamp('leido_at')->nullable();
            $t->timestamp('respondio_at')->nullable();
            $t->timestamp('baja_at')->nullable();
            $t->timestamps();
            $t->unique(['campania_id', 'telefono']);
            $t->index(['campania_id', 'estado']);
            $t->index('mensaje_id');
            $t->index('chat_id');
        });

        Schema::create('difusion_bajas', function (Blueprint $t) {
            $t->id();
            $t->string('telefono', 30)->unique();
            $t->foreignId('contacto_id')->nullable()->constrained('contactos')->nullOnDelete();
            $t->string('origen', 12);           // respuesta | manual
            $t->string('detalle', 255)->nullable();
            $t->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('difusion_bajas');
        Schema::dropIfExists('difusion_destinatarios');
        Schema::dropIfExists('difusion_campanias');
        Schema::dropIfExists('difusion_plantillas');
    }
};
