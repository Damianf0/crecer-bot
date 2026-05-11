<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ColaAtencion extends Model
{
    protected $table = 'cola_atencion';

    protected $fillable = [
        'dni', 'nombre', 'apellido', 'obra_social', 'plan',
        'omnia_turno_id', 'profesional', 'practica', 'turno_hora',
        'planta', 'consultorio', 'motivo', 'primera_vez', 'sin_turno', 'derivado_bot',
        'estado', 'orden', 'alerta_espera', 'checklist', 'nota',
        'hora_llegada', 'hora_llamado', 'hora_liberado',
        'llamado_consultorio_at', 'atendido_at',
    ];

    protected $casts = [
        'primera_vez'   => 'boolean',
        'sin_turno'     => 'boolean',
        'derivado_bot'  => 'boolean',
        'alerta_espera' => 'boolean',
        'checklist'     => 'array',
        'hora_llegada'  => 'datetime',
        'hora_llamado'  => 'datetime',
        'hora_liberado' => 'datetime',
        'llamado_consultorio_at' => 'datetime',
        'atendido_at'   => 'datetime',
    ];

    // ── Helpers ────────────────────────────────────────────

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->apellido}, {$this->nombre}");
    }

    public function getMinutosEsperaAttribute(): int
    {
        return (int) $this->hora_llegada->diffInMinutes(now());
    }

    /** Flags para mostrar en la cola */
    public function getFlags(): array
    {
        $flags = [];
        if ($this->primera_vez)  $flags[] = ['icon' => '⭐', 'label' => 'Primera vez', 'color' => 'yellow'];
        if ($this->sin_turno)    $flags[] = ['icon' => '⚡', 'label' => 'Sin turno',    'color' => 'red'];
        if ($this->derivado_bot) $flags[] = ['icon' => '💬', 'label' => 'WhatsApp',     'color' => 'green'];
        if ($this->alerta_espera) $flags[] = ['icon' => '⚠️', 'label' => 'Espera larga', 'color' => 'orange'];
        return $flags;
    }

    /** Checklist por defecto al hacer check-in */
    public static function checklistDefault(): array
    {
        return [
            ['id' => 'obra_social', 'label' => 'Obra social verificada',  'obligatorio' => true,  'done' => false],
            ['id' => 'copago',      'label' => 'Copago gestionado',        'obligatorio' => true,  'done' => false],
            ['id' => 'orden',       'label' => 'Orden médica validada',    'obligatorio' => false, 'done' => false],
            ['id' => 'datos',       'label' => 'Datos del paciente completos', 'obligatorio' => false, 'done' => false],
        ];
    }

    public function checklistCompleto(): bool
    {
        foreach ($this->checklist ?? [] as $item) {
            if ($item['obligatorio'] && !$item['done']) return false;
        }
        return true;
    }

    // ── Scopes ─────────────────────────────────────────────

    public function scopeActivos($query)
    {
        return $query->whereIn('estado', ['esperando', 'en_atencion'])
            ->orderBy('orden')
            ->orderBy('hora_llegada');
    }

    public function scopeLiberades($query)
    {
        return $query->where('estado', 'liberado')
            ->orderBy('hora_liberado', 'desc');
    }
}
