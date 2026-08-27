<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Procedimiento extends Model
{
    use SoftDeletes;

    protected $table = 'procedimientos';

    protected $fillable = [
        'titulo', 'slug', 'area', 'resumen',
        'estado', 'orden', 'revisado_at', 'creado_por', 'actualizado_por',
    ];

    protected $casts = [
        'revisado_at' => 'datetime',
    ];

    /** Meses sin revisión a partir de los cuales el panel muestra el aviso. */
    public const MESES_REVISION = 6;

    /** Las 3 áreas de WhatsApp + 'general' para lo que aplica a todas. */
    public const AREAS = ConversacionWA::AREAS + ['general' => 'General'];

    public const ESTADOS = [
        'borrador'  => 'Borrador',
        'publicado' => 'Publicado',
    ];

    /**
     * Códigos del clasificador del bot (bot/ollama.js). Vincular un
     * procedimiento a uno de estos permite, más adelante, sugerirlo según cómo
     * se clasificó la conversación. Se omiten FALLBACK e IGNORAR: no son casos
     * de atención sino salidas del clasificador cuando no entendió.
     */
    public const CODIGOS_BOT = [
        'PRIMERA_CONSULTA'      => 'Primera consulta',
        'TURNO_PORTAL'          => 'Turno por el portal',
        'TURNO_ECO_CON_CUENTA'  => 'Turno de ecografía (con cuenta)',
        'TURNO_ECO_SIN_CUENTA'  => 'Turno de ecografía (sin cuenta)',
        'TURNO_DGP'             => 'Turno DGP',
        'TURNO_PRESERVACION'    => 'Turno de preservación',
        'TURNO_PRESUPUESTO'     => 'Presupuesto',
        'RESULTADO_BETA'        => 'Resultado de beta',
        'RESULTADO_OTROS'       => 'Otros resultados',
        'MEDICACION_INSTRUCTIVO' => 'Instructivo de medicación',
        'ORDEN_MDP'             => 'Orden (Mar del Plata)',
        'ORDEN_OTRA_CIUDAD'     => 'Orden (otra ciudad)',
        'CONSULTA_CLINICA'      => 'Consulta clínica',
        'DERIVAR_SECRETARIA'    => 'Derivar a secretaría',
    ];

    public function pasos()
    {
        return $this->hasMany(ProcedimientoPaso::class, 'procedimiento_id')
            ->orderBy('orden')->orderBy('id');
    }

    public function adjuntos()
    {
        return $this->hasMany(ProcedimientoAdjunto::class, 'procedimiento_id')
            ->orderBy('orden')->orderBy('id');
    }

    /** Casos del clasificador que cubre este procedimiento. */
    public function codigos()
    {
        return $this->hasMany(ProcedimientoCodigo::class, 'procedimiento_id');
    }

    public function creadoPor()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function actualizadoPor()
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }

    public function scopePublicados($query)
    {
        return $query->where('estado', 'publicado');
    }

    public function scopeArea($query, string $area)
    {
        return $query->where('area', $area);
    }

    public function scopeOrdenados($query)
    {
        return $query->orderBy('orden')->orderBy('titulo');
    }

    /** ¿Hace cuántos meses que nadie lo revisa? null si nunca se revisó. */
    public function mesesSinRevisar(): ?int
    {
        return $this->revisado_at?->diffInMonths(now());
    }

    /** True si nunca se revisó o si pasó el umbral. */
    public function necesitaRevision(): bool
    {
        return $this->revisado_at === null
            || $this->revisado_at->lt(now()->subMonths(self::MESES_REVISION));
    }

    /**
     * Slug único a partir del título. Si ya existe, le agrega -2, -3, etc.
     * $ignorarId permite reusar el propio slug al editar un procedimiento.
     */
    public static function generarSlug(string $titulo, ?int $ignorarId = null): string
    {
        $base = Str::slug($titulo) ?: 'procedimiento';
        $slug = $base;
        $n    = 1;

        while (static::where('slug', $slug)
            ->when($ignorarId, fn($q) => $q->where('id', '!=', $ignorarId))
            ->exists()
        ) {
            $slug = $base . '-' . (++$n);
        }

        return $slug;
    }
}
