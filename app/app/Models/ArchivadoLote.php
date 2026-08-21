<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchivadoLote extends Model
{
    protected $table = 'archivados_lote';

    protected $fillable = [
        'usuario_id', 'origen', 'criterio', 'total', 'snapshot',
        'revertido_at', 'revertido_por', 'revertidas',
    ];

    protected $casts = [
        'criterio'     => 'array',
        'snapshot'     => 'array',
        'revertido_at' => 'datetime',
        'total'        => 'integer',
        'revertidas'   => 'integer',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function revertidoPor()
    {
        return $this->belongsTo(User::class, 'revertido_por');
    }

    public function getRevertidoAttribute(): bool
    {
        return $this->revertido_at !== null;
    }
}
