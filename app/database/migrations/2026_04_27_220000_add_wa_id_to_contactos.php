<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contactos', function (Blueprint $table) {
            // JID real de WhatsApp del contacto (ej: 549...@c.us o XXX@lid).
            // Permite vincular conversaciones cuando WA usa LinkedDevice ID en vez del número.
            $table->string('wa_id', 80)->nullable()->after('telefono');
            $table->unique('wa_id');
        });
    }

    public function down(): void
    {
        Schema::table('contactos', function (Blueprint $table) {
            $table->dropUnique(['wa_id']);
            $table->dropColumn('wa_id');
        });
    }
};
