<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cuándo se pide un requisito. financiador / plan / práctica en null = "todas".
 * modo: obligatorio | opcional | no_pedir (no_pedir sirve para exceptuar un caso
 * puntual de una regla general). Resolución en App\Services\ChecklistRecepcion.
 */
class RecepcionRegla extends Model
{
    protected $table = 'recepcion_reglas';

    public const MODOS = [
        'obligatorio' => 'Obligatorio',
        'opcional'    => 'Opcional',
        'no_pedir'    => 'No pedir',
    ];

    protected $fillable = ['requisito_id', 'financiador', 'plan', 'practica', 'modo', 'nota', 'activo', 'actualizado_por'];

    protected $casts = ['activo' => 'boolean'];

    public function requisito(): BelongsTo
    {
        return $this->belongsTo(RecepcionRequisito::class, 'requisito_id');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }
}
