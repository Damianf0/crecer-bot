<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Registro de un paciente de primera vez (ver la migración). */
class PrimeraVez extends Model
{
    protected $table = 'primeras_veces';

    protected $fillable = [
        'fecha', 'solo_mes', 'nombre', 'telefono', 'dni', 'contacto_id',
        'motivo', 'medico', 'derivante', 'detalle', 'origen', 'registrado_por',
    ];

    protected $casts = [
        'fecha'    => 'date',
        'solo_mes' => 'boolean',
    ];

    /** Cómo llegó la paciente. Las claves son las mismas tres de la planilla. */
    public const MOTIVOS = [
        'turno'      => 'Toma turno por día/hora',
        'derivado'   => 'Derivado',
        'espontaneo' => 'Espontáneo',
    ];

    public function registradoPor()
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function contacto()
    {
        return $this->belongsTo(Contacto::class, 'contacto_id');
    }
}
