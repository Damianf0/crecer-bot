<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('derivaciones', function (Blueprint $table) {
            $table->boolean('es_prueba')->default(false)->after('bot_at');
        });
    }

    public function down(): void
    {
        Schema::table('derivaciones', function (Blueprint $table) {
            $table->dropColumn('es_prueba');
        });
    }
};
