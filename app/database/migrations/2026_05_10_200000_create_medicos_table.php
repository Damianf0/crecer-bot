<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('medicos', function (Blueprint $t) {
            $t->id();
            $t->string('nombre_completo', 150);
            $t->string('especialidad', 100)->nullable();
            // Default cuando el médico tiene su consultorio fijo. Se puede
            // sobrescribir por sesión si lo necesita.
            $t->string('planta', 10)->nullable();
            $t->unsignedSmallInteger('consultorio')->nullable();
            // ID en Omnia, si lo tenemos. Por ahora vinculamos por nombre
            // (cola_atencion.profesional viene como string desde Omnia).
            $t->string('omnia_id', 50)->nullable()->index();
            $t->boolean('activo')->default(true);
            $t->timestamps();
            $t->index('nombre_completo');
        });

        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('medico_id')->nullable()->after('rol')
                ->constrained('medicos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('medico_id');
        });
        Schema::dropIfExists('medicos');
    }
};
