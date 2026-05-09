<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TareaWA extends Model
{
    protected $table = 'tareas_wa';

    protected $fillable = [
        'conversacion_id', 'titulo', 'descripcion', 'estado',
        'asignado_a', 'creado_por', 'vence_at', 'completado_at',
    ];

    protected $casts = [
        'vence_at'      => 'datetime',
        'completado_at' => 'datetime',
    ];

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(ConversacionWA::class, 'conversacion_id');
    }

    public function asignadoA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_a');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function getCompletadaAttribute(): bool
    {
        return $this->estado === 'completada';
    }

    public function getVencidaAttribute(): bool
    {
        return $this->vence_at && $this->vence_at->isPast() && !$this->completada;
    }
}
