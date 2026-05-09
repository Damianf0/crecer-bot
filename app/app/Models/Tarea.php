<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tarea extends Model
{
    protected $table = 'tareas';

    protected $fillable = [
        'titulo', 'descripcion', 'asignada_a', 'creada_por',
        'vence_at', 'estado', 'prioridad', 'ref_tipo', 'ref_id',
    ];

    protected $casts = [
        'vence_at' => 'datetime',
    ];

    public function asignadaA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignada_a');
    }

    public function creadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creada_por');
    }

    public function comentarios(): HasMany
    {
        return $this->hasMany(TareaComentario::class)->orderBy('created_at');
    }

    public function conversacionRef(): BelongsTo
    {
        return $this->belongsTo(ConversacionWA::class, 'ref_id');
    }

    public function getCompletadaAttribute(): bool
    {
        return $this->estado === 'completada';
    }

    public function getVencidaAttribute(): bool
    {
        return $this->vence_at && $this->vence_at->isPast() && $this->estado !== 'completada';
    }

    public function scopePendientes($q)    { return $q->where('estado', '!=', 'completada'); }
    public function scopeDeHoy($q)         { return $q->whereDate('vence_at', today()); }
    public function scopeVencidas($q)      { return $q->where('vence_at', '<', now())->where('estado', '!=', 'completada'); }
}
