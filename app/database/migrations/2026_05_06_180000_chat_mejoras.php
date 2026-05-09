<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('chat_canal_user', 'oculto')) {
            Schema::table('chat_canal_user', function (Blueprint $t) {
                $t->boolean('oculto')->default(false)->after('ultimo_leido_id');
            });
        }

        if (!Schema::hasColumn('chat_mensajes', 'deleted_at')) {
            Schema::table('chat_mensajes', function (Blueprint $t) {
                $t->softDeletes()->after('updated_at');
            });
        }
        $idxExiste = (bool) \Illuminate\Support\Facades\DB::selectOne(
            "SHOW INDEX FROM chat_mensajes WHERE Key_name = 'chat_mensajes_texto_ft'"
        );
        if (!$idxExiste) {
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE chat_mensajes ADD FULLTEXT chat_mensajes_texto_ft (texto)');
        }

        if (!Schema::hasColumn('users', 'last_seen_at')) {
            Schema::table('users', function (Blueprint $t) {
                $t->timestamp('last_seen_at')->nullable()->after('updated_at');
            });
        }
    }

    public function down(): void
    {
        Schema::table('chat_canal_user', fn (Blueprint $t) => $t->dropColumn('oculto'));
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE chat_mensajes DROP INDEX chat_mensajes_texto_ft');
        Schema::table('chat_mensajes', fn (Blueprint $t) => $t->dropSoftDeletes());
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('last_seen_at'));
    }
};
