<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Clasificación de la IA de una tanda de mensajes (ver migración y App\Support\TiposConsulta). */
class ClasificacionWA extends Model
{
    protected $table = 'clasificaciones_wa';

    protected $fillable = [
        'conversacion_id', 'area', 'contacto', 'codigo', 'confianza', 'resumen',
        'en_horario', 'sin_ia', 'origen', 'codigo_corregido', 'corregido_por', 'corregido_at',
    ];

    protected $casts = [
        'en_horario'   => 'boolean',
        'sin_ia'       => 'boolean',
        'corregido_at' => 'datetime',
    ];

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(ConversacionWA::class, 'conversacion_id');
    }

    /** Código efectivo: la corrección de la supervisora gana sobre la IA. */
    public function getCodigoFinalAttribute(): string
    {
        return $this->codigo_corregido ?: $this->codigo;
    }
}
