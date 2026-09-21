<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checklist de recepción configurable por obra social / plan / práctica.
 *
 *  - recepcion_requisitos: qué se le puede pedir a un paciente (orden médica,
 *    autorización previa, credencial, consentimiento…).
 *  - recepcion_reglas: cuándo se pide cada requisito. Campo vacío = "todas";
 *    la regla más específica gana (ver App\Services\ChecklistRecepcion).
 *  - omnia_catalogo: nombres de financiadores y prácticas tal como los escribe
 *    Omnia, para que las reglas se carguen eligiendo de una lista y matcheen.
 *  - cola_atencion: financiador completo (el campo obra_social guarda el
 *    nombre corto, "OSDE", que no matchea contra "Osde Binario"), todas las
 *    prácticas del turno y el presente dado por la recepcionista.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recepcion_requisitos', function (Blueprint $t) {
            $t->id();
            $t->string('nombre', 120);
            $t->text('instruccion')->nullable();
            $t->unsignedSmallInteger('vigencia_dias')->nullable();
            $t->boolean('activo')->default(true);
            $t->unsignedSmallInteger('orden')->default(0);
            $t->timestamps();
        });

        Schema::create('recepcion_reglas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('requisito_id')->constrained('recepcion_requisitos')->cascadeOnDelete();
            $t->string('financiador', 191)->nullable();
            $t->string('plan', 60)->nullable();
            $t->string('practica', 191)->nullable();
            $t->string('modo', 12)->default('obligatorio');   // obligatorio | opcional | no_pedir
            $t->string('nota', 255)->nullable();
            $t->boolean('activo')->default(true);
            $t->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['financiador', 'practica']);
        });

        Schema::create('omnia_catalogo', function (Blueprint $t) {
            $t->id();
            $t->string('tipo', 12);            // financiador | practica
            $t->string('nombre', 191);
            $t->unsignedInteger('turnos')->default(0);
            $t->timestamp('visto_at')->nullable();
            $t->timestamps();
            $t->unique(['tipo', 'nombre']);
        });

        Schema::table('cola_atencion', function (Blueprint $t) {
            $t->string('financiador', 191)->nullable()->after('plan');
            $t->json('practicas')->nullable()->after('practica');
            $t->timestamp('presente_at')->nullable();
            $t->foreignId('presente_por')->nullable()->constrained('users')->nullOnDelete();
            $t->json('presente_faltantes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cola_atencion', function (Blueprint $t) {
            $t->dropConstrainedForeignId('presente_por');
            $t->dropColumn(['financiador', 'practicas', 'presente_at', 'presente_faltantes']);
        });
        Schema::dropIfExists('omnia_catalogo');
        Schema::dropIfExists('recepcion_reglas');
        Schema::dropIfExists('recepcion_requisitos');
    }
};
