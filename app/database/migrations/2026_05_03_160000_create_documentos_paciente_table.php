<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('documentos_paciente', function (Blueprint $table) {
            $table->id();
            // Vínculo con el paciente (puede ser null para conv huérfanas indexadas).
            $table->foreignId('contacto_id')->nullable()->constrained('contactos')->nullOnDelete();
            // Trazabilidad al chat origen (si vino por WA).
            $table->foreignId('conversacion_id')->nullable()->constrained('conversaciones_wa')->nullOnDelete();
            $table->foreignId('mensaje_id')->nullable()->constrained('mensajes_wa')->nullOnDelete();
            // Quién originó: paciente (entrante), secretaria por WA (saliente), subida manual desde panel
            $table->enum('direccion', ['entrante', 'saliente', 'manual']);
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            // Tipo inferido del mime
            $table->enum('tipo', ['imagen', 'documento', 'audio', 'video']);
            $table->string('mime', 100);
            $table->string('nombre_original', 255);
            // Nombre real en disk (hash + ext) — separado para evitar colisiones y guessing
            $table->string('nombre_storage', 100);
            // Path relativo dentro del storage_path configurado: <contacto_id>/<año>/<hash>.ext
            $table->string('path', 300);
            $table->unsignedBigInteger('tamanio_bytes')->default(0);
            // Metadata opcional
            $table->boolean('destacado')->default(false);
            $table->text('notas')->nullable();
            // Texto extraído via OCR (PDFs e imágenes) — para búsqueda
            $table->longText('texto_ocr')->nullable();
            $table->timestamp('ocr_at')->nullable();
            $table->timestamps();

            $table->index(['contacto_id', 'created_at']);
            $table->index(['contacto_id', 'destacado']);
            $table->index(['tipo']);
            $table->index(['direccion']);
        });

        // Índice FULLTEXT para búsqueda en texto OCR (MySQL/InnoDB)
        DB::statement('ALTER TABLE documentos_paciente ADD FULLTEXT idx_ocr_search (texto_ocr, nombre_original, notas)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE documentos_paciente DROP INDEX idx_ocr_search');
        Schema::dropIfExists('documentos_paciente');
    }
};
