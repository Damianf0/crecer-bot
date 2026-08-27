<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Caso del clasificador del bot que cubre un procedimiento. Un procedimiento
 * puede cubrir varios (ej: RESULTADO_BETA y RESULTADO_OTROS se resuelven igual).
 *
 * Las etiquetas legibles están en Procedimiento::CODIGOS_BOT.
 */
class ProcedimientoCodigo extends Model
{
    protected $table = 'procedimiento_codigos';

    public $timestamps = false;

    protected $fillable = ['procedimiento_id', 'codigo'];

    public function procedimiento()
    {
        return $this->belongsTo(Procedimiento::class, 'procedimiento_id');
    }

    public function getLabelAttribute(): string
    {
        return Procedimiento::CODIGOS_BOT[$this->codigo] ?? $this->codigo;
    }
}
