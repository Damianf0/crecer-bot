<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Médicos entre los que se reparten las primeras veces (lista editable). */
class PrimeraVezMedico extends Model
{
    protected $table = 'primera_vez_medicos';

    protected $fillable = ['nombre', 'activo'];

    protected $casts = ['activo' => 'boolean'];
}
