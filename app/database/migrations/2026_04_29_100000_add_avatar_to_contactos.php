<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contactos', function (Blueprint $table) {
            // Path relativo dentro de storage/app/public/ — ej: 'wa-avatars/abc123.jpg'
            $table->string('avatar_path', 200)->nullable()->after('wa_id');
            // Última vez que se intentó sincronizar (sea OK o falle por privacidad)
            $table->timestamp('avatar_actualizado_at')->nullable()->after('avatar_path');
            $table->index('avatar_actualizado_at');
        });
    }

    public function down(): void
    {
        Schema::table('contactos', function (Blueprint $table) {
            $table->dropIndex(['avatar_actualizado_at']);
            $table->dropColumn(['avatar_path', 'avatar_actualizado_at']);
        });
    }
};
