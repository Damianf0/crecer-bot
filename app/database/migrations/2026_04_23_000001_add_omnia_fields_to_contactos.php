<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contactos', function (Blueprint $table) {
            $table->string('dni', 20)->nullable()->unique()->after('telefono');
            $table->string('email', 150)->nullable()->after('dni');
            $table->date('fecha_nacimiento')->nullable()->after('email');
            $table->unsignedBigInteger('omnia_patient_id')->nullable()->unique()->after('fecha_nacimiento');
        });
    }

    public function down(): void
    {
        Schema::table('contactos', function (Blueprint $table) {
            $table->dropColumn(['dni', 'email', 'fecha_nacimiento', 'omnia_patient_id']);
        });
    }
};
