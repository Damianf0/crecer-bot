<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cutover gradual a la interfaz V2 (Fase 3 de la migración).
 * `ui_pref` = 'v1' (clásica, default) | 'v2' (nueva). El route `/` y los
 * redirects post-login honran este flag para mandar a cada usuario a su home.
 * Reversible por usuario con el toggle del navbar (/cambiar-ui/{pref}).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('ui_pref', 2)->default('v1')->after('medico_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ui_pref');
        });
    }
};
