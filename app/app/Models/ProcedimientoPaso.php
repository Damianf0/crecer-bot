<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcedimientoPaso extends Model
{
    protected $table = 'procedimiento_pasos';

    protected $fillable = [
        'procedimiento_id', 'orden', 'titulo', 'contenido', 'contenido_texto', 'respuesta_wa',
    ];

    public function procedimiento()
    {
        return $this->belongsTo(Procedimiento::class, 'procedimiento_id');
    }

    public function adjuntos()
    {
        return $this->hasMany(ProcedimientoAdjunto::class, 'paso_id')
            ->orderBy('orden')->orderBy('id');
    }
}
