<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConversacionEvento extends Model
{
    protected $table = 'conversacion_eventos';

    protected $fillable = [
        'conversacion_id',
        'tipo',
        'usuario_id',
        'usuario_destino_id',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function usuarioDestino()
    {
        return $this->belongsTo(User::class, 'usuario_destino_id');
    }
}
