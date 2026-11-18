<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Cambio de tablero/etapa de una ficha del CRM de ovodonación. */
class OvoMovimiento extends Model
{
    protected $table = 'ovo_movimientos';

    public const UPDATED_AT = null;

    protected $fillable = [
        'ficha_id', 'proceso_desde', 'etapa_desde', 'proceso_hasta', 'etapa_hasta',
        'motivo', 'automatico', 'user_id',
    ];

    protected $casts = ['automatico' => 'boolean'];

    public function ficha()
    {
        return $this->belongsTo(OvoFicha::class, 'ficha_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
