<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DifusionPlantilla extends Model
{
    protected $table = 'difusion_plantillas';

    protected $fillable = ['nombre', 'texto', 'adjunto_path', 'adjunto_nombre', 'adjunto_mime', 'creado_por'];
}
