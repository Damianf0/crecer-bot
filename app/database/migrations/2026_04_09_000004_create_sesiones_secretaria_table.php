<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesiones_secretaria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->json('colas');                          // colas declaradas en este turno
            $table->timestamp('inicio_sesion');
            $table->timestamp('fin_sesion')->nullable();
            $table->unsignedInteger('casos_atendidos')->default(0);
            $table->unsignedInteger('casos_resueltos')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'inicio_sesion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesiones_secretaria');
    }
};
