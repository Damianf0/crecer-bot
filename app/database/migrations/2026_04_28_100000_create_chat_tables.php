<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_canales', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['equipo', 'dm']);
            $table->string('nombre', 80)->nullable();   // 'Equipo' para grupales; null para DMs
            $table->timestamps();
            $table->index('tipo');
        });

        Schema::create('chat_canal_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canal_id')->constrained('chat_canales')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('ultimo_leido_id')->nullable();
            $table->timestamps();
            $table->unique(['canal_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('chat_mensajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canal_id')->constrained('chat_canales')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->text('texto');
            $table->timestamps();
            $table->index(['canal_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_mensajes');
        Schema::dropIfExists('chat_canal_user');
        Schema::dropIfExists('chat_canales');
    }
};
