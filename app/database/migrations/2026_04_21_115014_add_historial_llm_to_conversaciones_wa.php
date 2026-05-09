<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('conversaciones_wa', function (Blueprint $table) {
            $table->text('historial_llm')->nullable()->after('resumen_llm');
        });
    }

    public function down(): void
    {
        Schema::table('conversaciones_wa', function (Blueprint $table) {
            $table->dropColumn('historial_llm');
        });
    }
};
