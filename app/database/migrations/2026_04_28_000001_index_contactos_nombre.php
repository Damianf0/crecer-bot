<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contactos', function (Blueprint $table) {
            // Acelera ORDER BY nombre y prefix-search "nombre LIKE 'foo%'"
            $table->index('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('contactos', function (Blueprint $table) {
            $table->dropIndex(['nombre']);
        });
    }
};
