<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('respuestas_rapidas', function (Blueprint $table) {
            $table->id();
            $table->string('area', 30)->index();       // atencion | administracion | ovodonacion
            $table->string('titulo', 80);
            $table->text('texto');
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['area', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('respuestas_rapidas');
    }
};
