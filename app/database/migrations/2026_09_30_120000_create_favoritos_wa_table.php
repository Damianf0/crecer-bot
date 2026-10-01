<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contactos de WhatsApp favoritos, compartidos por todo el equipo (médicos,
 * laboratorios, proveedores, pacientes a seguir). Van por JID (el contacto de
 * WhatsApp, no la conversación): si ese número escribe a cualquier cola, se
 * destaca en todas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favoritos_wa', function (Blueprint $t) {
            $t->id();
            $t->string('contacto', 100)->unique();
            $t->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favoritos_wa');
    }
};
