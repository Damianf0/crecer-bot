<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Financiadores y prácticas tal como los nombra Omnia (se llena con omnia:catalogo). */
class OmniaCatalogo extends Model
{
    protected $table = 'omnia_catalogo';

    protected $fillable = ['tipo', 'nombre', 'turnos', 'visto_at'];

    protected $casts = ['visto_at' => 'datetime', 'turnos' => 'integer'];
}
