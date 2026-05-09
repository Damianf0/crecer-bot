<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nombre_completo', 100)->after('name');
            $table->string('rol', 20)->default('secretaria')->after('nombre_completo'); // secretaria | supervisora | admin | tecnico
            $table->boolean('activo')->default(true)->after('rol');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nombre_completo', 'rol', 'activo']);
        });
    }
};
