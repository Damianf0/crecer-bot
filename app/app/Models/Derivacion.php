<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Derivacion extends Model
{
    protected $table = 'derivaciones';

    protected $fillable = [
        'contacto',
        'texto',
        'codigo',
        'en_horario',
        'es_prueba',
        'urgente',
        'asignada_a',
        'resumen_llm',
        'estado',
        'nota',
        'bot_at',
        'atendido_at',
    ];

    protected $casts = [
        'en_horario'  => 'boolean',
        'es_prueba'   => 'boolean',
        'urgente'     => 'boolean',
        'bot_at'      => 'datetime',
        'atendido_at' => 'datetime',
    ];

    public function asignadaA()
    {
        return $this->belongsTo(\App\Models\User::class, 'asignada_a');
    }

    // Etiquetas legibles por código de acción
    public static array $etiquetas = [
        'TURNO_DGP'          => 'Turno DGP',
        'TURNO_PRESERVACION' => 'Preservación de fertilidad',
        'TURNO_PRESUPUESTO'  => 'Presupuesto',
        'RESULTADO_BETA'     => 'Resultado beta hCG',
        'CONSULTA_CLINICA'   => 'Consulta clínica',
        'DERIVAR_SECRETARIA' => 'Derivación directa',
        'FALLBACK'           => 'Sin clasificar',
    ];

    public function getEtiquetaAttribute(): string
    {
        return static::$etiquetas[$this->codigo] ?? $this->codigo;
    }

    // Número de teléfono limpio (sin @c.us)
    public function getTelefonoAttribute(): string
    {
        return str_replace('@c.us', '', $this->contacto);
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente')->orderBy('created_at');
    }
}
