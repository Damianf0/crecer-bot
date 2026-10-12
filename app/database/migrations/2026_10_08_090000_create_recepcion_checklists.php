<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Checklist de recepción, lógica nueva (08/10): cada obra social tiene su
 * propia lista de pasos, tal como la ve recepción. Reemplaza al motor de
 * reglas del 21/09 (recepcion_requisitos + recepcion_reglas: catálogo de
 * requisitos y reglas por obra social / plan / práctica que se pisaban por
 * peso), que nadie llegó a cargar: quedó con 2 requisitos y ninguna regla.
 *
 *  - recepcion_checklists: uno por obra social, con el nombre exacto con el
 *    que llega de Omnia. financiador NULL = el "General", que se usa cuando la
 *    obra social no tiene uno propio (particulares, sin turno, sin dato).
 *  - recepcion_checklist_items: los pasos, en orden.
 *
 * Las tablas viejas no se tocan (quedan sin uso).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recepcion_checklists', function (Blueprint $t) {
            $t->id();
            $t->string('financiador', 191)->nullable();
            // Nombre normalizado (minúsculas, espacios simples) para comparar; '' = General.
            $t->string('clave', 191)->unique();
            $t->boolean('activo')->default(true);
            $t->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('recepcion_checklist_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('checklist_id')->constrained('recepcion_checklists')->cascadeOnDelete();
            $t->string('texto', 160);
            $t->boolean('obligatorio')->default(true);
            $t->string('nota', 255)->nullable();
            $t->unsignedSmallInteger('orden')->default(0);
            $t->timestamps();
            $t->index(['checklist_id', 'orden']);
        });

        // El General arranca con la lista fija que recepción ve hoy.
        $ahora = now();
        $id = DB::table('recepcion_checklists')->insertGetId([
            'financiador' => null, 'clave' => '', 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora,
        ]);
        foreach ([
            ['Obra social verificada', true],
            ['Copago gestionado', true],
            ['Orden médica validada', false],
            ['Datos del paciente completos', false],
        ] as $i => [$texto, $obligatorio]) {
            DB::table('recepcion_checklist_items')->insert([
                'checklist_id' => $id, 'texto' => $texto, 'obligatorio' => $obligatorio, 'orden' => $i + 1,
                'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recepcion_checklist_items');
        Schema::dropIfExists('recepcion_checklists');
    }
};
