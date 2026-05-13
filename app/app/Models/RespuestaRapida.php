<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RespuestaRapida extends Model
{
    protected $table = 'respuestas_rapidas';

    protected $fillable = ['area', 'titulo', 'texto', 'orden'];

    public const AREAS = ConversacionWA::AREAS;

    public function scopeArea($query, string $area)
    {
        return $query->where('area', $area);
    }

    public function scopeOrdenadas($query)
    {
        return $query->orderBy('orden')->orderBy('id');
    }
}
