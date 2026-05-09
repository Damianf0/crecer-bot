<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Cantidad de intentos fallidos consecutivos (se resetea en login exitoso)
            $table->unsignedTinyInteger('intentos_fallidos')->default(0)->after('activo');
            // Si está seteado y > now(), el usuario está bloqueado hasta esa fecha
            $table->timestamp('bloqueado_hasta')->nullable()->after('intentos_fallidos');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['intentos_fallidos', 'bloqueado_hasta']);
        });
    }
};
