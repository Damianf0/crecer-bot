<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Medico extends Model
{
    protected $fillable = [
        'nombre_completo',
        'especialidad',
        'planta',
        'consultorio',
        'omnia_id',
        'activo',
    ];

    protected $casts = [
        'activo'      => 'boolean',
        'consultorio' => 'integer',
    ];

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function scopeActivos($q)
    {
        return $q->where('activo', true);
    }
}
