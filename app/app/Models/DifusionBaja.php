<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Quien pidió no recibir más difusiones (se excluye siempre de las audiencias). */
class DifusionBaja extends Model
{
    protected $table = 'difusion_bajas';

    protected $fillable = ['telefono', 'contacto_id', 'origen', 'detalle', 'creado_por'];
}
