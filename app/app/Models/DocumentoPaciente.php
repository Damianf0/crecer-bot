<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoPaciente extends Model
{
    protected $table = 'documentos_paciente';

    protected $fillable = [
        'contacto_id', 'conversacion_id', 'mensaje_id',
        'direccion', 'usuario_id',
        'tipo', 'mime', 'nombre_original', 'nombre_storage',
        'path', 'tamanio_bytes',
        'destacado', 'notas', 'texto_ocr', 'ocr_at',
    ];

    protected $casts = [
        'destacado' => 'boolean',
        'ocr_at'    => 'datetime',
    ];

    public function contacto(): BelongsTo
    {
        return $this->belongsTo(Contacto::class);
    }

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(ConversacionWA::class, 'conversacion_id');
    }

    public function mensaje(): BelongsTo
    {
        return $this->belongsTo(MensajeWA::class, 'mensaje_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /** Tamaño legible en KB/MB */
    public function getTamanioHumanAttribute(): string
    {
        $b = $this->tamanio_bytes;
        if ($b < 1024) return $b . ' B';
        if ($b < 1024 * 1024) return round($b / 1024, 1) . ' KB';
        return round($b / 1024 / 1024, 1) . ' MB';
    }

    /** Path absoluto al archivo en el filesystem. */
    public function pathAbsoluto(): string
    {
        return rtrim(\App\Services\LegajoStorage::basePath(), '/') . '/' . $this->path;
    }

    /** Helper de inferencia de tipo desde mime. */
    public static function tipoDesdeMime(string $mime): string
    {
        if (str_starts_with($mime, 'image/'))   return 'imagen';
        if (str_starts_with($mime, 'audio/'))   return 'audio';
        if (str_starts_with($mime, 'video/'))   return 'video';
        return 'documento';
    }
}
