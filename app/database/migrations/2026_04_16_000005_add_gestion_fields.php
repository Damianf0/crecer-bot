<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('derivaciones', function (Blueprint $table) {
            $table->boolean('urgente')->default(false)->after('es_prueba');
            $table->foreignId('asignada_a')->nullable()->constrained('users')->nullOnDelete()->after('urgente');
            $table->text('resumen_llm')->nullable()->after('asignada_a');
        });

        Schema::table('conversaciones_wa', function (Blueprint $table) {
            $table->boolean('urgente')->default(false)->after('no_leidos');
            $table->foreignId('asignada_a')->nullable()->constrained('users')->nullOnDelete()->after('urgente');
            $table->text('resumen_llm')->nullable()->after('asignada_a');
        });
    }

    public function down(): void
    {
        Schema::table('derivaciones', function (Blueprint $table) {
            $table->dropForeign(['asignada_a']);
            $table->dropColumn(['urgente', 'asignada_a', 'resumen_llm']);
        });
        Schema::table('conversaciones_wa', function (Blueprint $table) {
            $table->dropForeign(['asignada_a']);
            $table->dropColumn(['urgente', 'asignada_a', 'resumen_llm']);
        });
    }
};
