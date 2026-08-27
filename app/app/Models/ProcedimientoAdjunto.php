<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcedimientoAdjunto extends Model
{
    protected $table = 'procedimiento_adjuntos';

    protected $fillable = [
        'procedimiento_id', 'paso_id', 'tipo', 'path',
        'nombre_original', 'mime', 'tamano', 'orden',
    ];

    /** Tipos MIME aceptados: solo capturas e instructivos. */
    public const MIMES_PERMITIDOS = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
    ];

    public function procedimiento()
    {
        return $this->belongsTo(Procedimiento::class, 'procedimiento_id');
    }

    public function paso()
    {
        return $this->belongsTo(ProcedimientoPaso::class, 'paso_id');
    }

    public function esImagen(): bool
    {
        return $this->tipo === 'imagen' || str_starts_with((string) $this->mime, 'image/');
    }
}
