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
        'procedimiento_id', 'area', 'completada_por', 'completada_at',
    ];

    protected $casts = [
        'vence_at'      => 'datetime',
        'completada_at' => 'datetime',
    ];

    /** Mensajes de la conversación que se marcaron al crear la tarea. */
    public function mensajes(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(MensajeWA::class, 'tarea_mensajes', 'tarea_id', 'mensaje_id')->orderBy('mensajes_wa.id');
    }

    public function completadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completada_por');
    }

    /**
     * Tareas que la persona puede ver: las de personas, y las de áreas que
     * puede ver (las áreas restringidas no existen para el resto del equipo).
     */
    public function scopeVisiblesPara($q, ?User $user)
    {
        $areas = array_keys(ConversacionWA::areasPara($user));
        return $q->where(fn ($q) => $q->whereNull('area')->orWhereIn('area', $areas));
    }

    /** Pendientes de un área que nadie tomó todavía. */
    public function scopeSinTomarEn($q, array $areas)
    {
        return $q->where('estado', '!=', 'completada')->whereNull('asignada_a')->whereIn('area', $areas ?: ['-']);
    }

    public function getAreaLabelAttribute(): ?string
    {
        return $this->area ? (ConversacionWA::areas()[$this->area] ?? $this->area) : null;
    }

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

    /** Procedimiento que explica cómo resolver esta tarea (opcional). */
    public function procedimiento(): BelongsTo
    {
        return $this->belongsTo(Procedimiento::class, 'procedimiento_id');
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
