<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cutover Fase 3 — V2 pasa a ser la interfaz por defecto (2026-06-30).
 *
 * 1) Default de `users.ui_pref` a 'v2' → todo usuario nuevo arranca en V2.
 * 2) Migra a TODOS los usuarios existentes de 'v1' a 'v2'.
 *
 * V1 NO se borra: queda como legacy/escape hatch, accesible con el toggle
 * "UI clásica" del navbar V2 (/cambiar-ui/v1). Reversible por usuario.
 * El down() restaura el default a 'v1' (no revierte la preferencia ya elegida
 * por cada usuario, que es dato propio de cada uno).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users ALTER COLUMN ui_pref SET DEFAULT 'v2'");
        DB::table('users')->where('ui_pref', 'v1')->update(['ui_pref' => 'v2']);
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users ALTER COLUMN ui_pref SET DEFAULT 'v1'");
    }
};
