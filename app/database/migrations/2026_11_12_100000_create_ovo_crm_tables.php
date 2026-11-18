<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM de ovodonación (12/11): reemplaza el seguimiento que el equipo de ovo
 * llevaba en Zoho Bigin. Una ficha por persona (donante o receptora) que vive
 * en un tablero (proceso) y una etapa; cada cambio de etapa queda en
 * ovo_movimientos. Módulo aislado: no escribe en contactos ni en las colas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ovo_fichas', function (Blueprint $t) {
            $t->id();
            $t->string('tipo', 12);                       // donante | receptora
            $t->string('proceso', 20)->index();           // tablero: donantes, ya_donaron, tatuaje, receptoras
            $t->string('etapa', 30);
            $t->timestamp('etapa_desde')->nullable();

            $t->string('nombre', 80);
            $t->string('apellido', 80)->nullable();
            $t->string('dni', 15)->nullable()->index();
            $t->string('email', 120)->nullable();
            $t->string('telefono', 30)->nullable();
            $t->foreignId('contacto_id')->nullable()->constrained('contactos')->nullOnDelete();
            $t->date('fecha_nacimiento')->nullable();
            $t->unsignedTinyInteger('edad')->nullable();   // si no hay fecha de nacimiento

            // Cuestionario de ingreso de donante
            $t->decimal('peso', 5, 1)->nullable();         // kg
            $t->unsignedSmallInteger('altura')->nullable(); // cm
            $t->boolean('tiene_hijos')->nullable();
            $t->boolean('amamantando')->nullable();
            $t->boolean('tatuaje_reciente')->nullable();
            $t->date('fecha_tatuaje')->nullable();
            $t->boolean('planea_tatuaje')->nullable();
            $t->string('tatuaje_detalle', 160)->nullable();
            $t->string('medicacion', 255)->nullable();
            $t->string('fuente', 60)->nullable();
            $t->string('codigo_donante', 30)->nullable()->index();

            // Seguimiento
            $t->dateTime('proximo_turno')->nullable();
            $t->date('fecha_puncion')->nullable();
            $t->text('estudios')->nullable();
            $t->string('motivo_cancelacion', 255)->nullable();
            $t->string('motivo_no_cumple', 255)->nullable();
            $t->date('recontactar_desde')->nullable();

            // Receptoras
            $t->string('medico', 80)->nullable();
            $t->string('cobertura', 80)->nullable();
            $t->date('fecha_ingreso')->nullable();
            $t->date('fecha_orden_entregada')->nullable();
            $t->date('vence_autorizacion')->nullable();
            $t->date('fecha_transferencia')->nullable();

            $t->text('descripcion')->nullable();
            $t->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('origen', 20)->default('panel');    // panel | bigin
            $t->string('externo_id', 40)->nullable()->index(); // id del registro en Bigin (importación)
            $t->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();

            $t->index(['proceso', 'etapa']);
        });

        Schema::create('ovo_movimientos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ficha_id')->constrained('ovo_fichas')->cascadeOnDelete();
            $t->string('proceso_desde', 20)->nullable();
            $t->string('etapa_desde', 30)->nullable();
            $t->string('proceso_hasta', 20);
            $t->string('etapa_hasta', 30);
            $t->string('motivo', 255)->nullable();
            $t->boolean('automatico')->default(false);
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->nullable()->index();
        });

        Schema::create('ovo_notas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ficha_id')->constrained('ovo_fichas')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('texto');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ovo_notas');
        Schema::dropIfExists('ovo_movimientos');
        Schema::dropIfExists('ovo_fichas');
    }
};
