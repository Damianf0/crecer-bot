<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SesionSecretaria extends Model
{
    protected $table = 'sesiones_secretaria';

    protected $fillable = [
        'user_id', 'colas', 'inicio_sesion', 'fin_sesion',
        'casos_atendidos', 'casos_resueltos',
    ];

    protected $casts = [
        'colas'         => 'array',
        'inicio_sesion' => 'datetime',
        'fin_sesion'    => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getDuracionAttribute(): string
    {
        $fin = $this->fin_sesion ?? now();
        $mins = $this->inicio_sesion->diffInMinutes($fin);
        return sprintf('%dh %02dm', intdiv($mins, 60), $mins % 60);
    }
}
