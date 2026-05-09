<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversaciones_wa', function (Blueprint $table) {
            $table->id();
            $table->string('contacto')->unique(); // ej: 5492235997247@c.us
            $table->string('nombre')->nullable();  // nombre asignado manualmente
            $table->string('estado')->default('activa'); // activa | archivada
            $table->unsignedInteger('no_leidos')->default(0);
            $table->timestamp('ultima_actividad')->useCurrent();
            $table->timestamps();
            $table->index(['estado', 'ultima_actividad']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversaciones_wa');
    }
};
