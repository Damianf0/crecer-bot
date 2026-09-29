<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada campaña elige su canal (número con QR o proveedor): clave de
 * config/difusion.php 'canales'. `proveedor` queda como el tipo del canal
 * (simulado | wwebjs | cloudapi) al momento de crearla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('difusion_campanias', function (Blueprint $t) {
            $t->string('canal', 30)->default('simulado')->after('proveedor');
            $t->index('canal');
        });
    }

    public function down(): void
    {
        Schema::table('difusion_campanias', function (Blueprint $t) {
            $t->dropIndex(['canal']);
            $t->dropColumn('canal');
        });
    }
};
