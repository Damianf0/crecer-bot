<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Algo que recepción le puede pedir al paciente (orden, autorización, credencial…). */
class RecepcionRequisito extends Model
{
    protected $table = 'recepcion_requisitos';

    protected $fillable = ['nombre', 'instruccion', 'vigencia_dias', 'activo', 'orden'];

    protected $casts = [
        'activo'        => 'boolean',
        'vigencia_dias' => 'integer',
        'orden'         => 'integer',
    ];

    public function reglas(): HasMany
    {
        return $this->hasMany(RecepcionRegla::class, 'requisito_id');
    }
}
