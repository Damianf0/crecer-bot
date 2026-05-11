<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Contacto;

class ConversacionWA extends Model
{
    protected $table = 'conversaciones_wa';

    protected $fillable = [
        'contacto', 'area', 'nombre', 'estado', 'no_leidos', 'ultima_actividad',
        'urgente', 'asignada_a', 'resumen_llm', 'historial_llm', 'resumen_intento_at',
    ];

    protected $casts = [
        'ultima_actividad'   => 'datetime',
        'resumen_intento_at' => 'datetime',
        'no_leidos'          => 'integer',
        'urgente'            => 'boolean',
    ];

    /** Áreas válidas (= números de WhatsApp). 'atencion' es el bot original. */
    public const AREAS = [
        'atencion'       => 'Atención',
        'administracion' => 'Administración',
        'ovodonacion'    => 'Ovodonación',
    ];

    /** URL interna del bot que corresponde al área de esta conversación. */
    public function botUrl(): string
    {
        $area = isset(self::AREAS[$this->area]) ? $this->area : 'atencion';
        return rtrim(config('app.bot_url_' . $area) ?: config('app.bot_url'), '/');
    }

    /** URL del bot para un área dada (helper estático, p/ casos sin instancia). */
    public static function botUrlPara(?string $area): string
    {
        $area = isset(self::AREAS[$area]) ? $area : 'atencion';
        return rtrim(config('app.bot_url_' . $area) ?: config('app.bot_url'), '/');
    }

    /**
     * Áreas que la secretaria declaró atender en esta sesión (las que marcó en
     * la pantalla de colas). Si no marcó ninguna → todas (compat + default).
     */
    public static function areasDeLaSesion(): array
    {
        $colas = (array) session('colas', []);
        $sel = array_values(array_intersect($colas, array_keys(self::AREAS)));
        return $sel ?: array_keys(self::AREAS);
    }

    /** Invalida el cache de la cola de /atencion (una clave por área). */
    public static function invalidarColaCache(): void
    {
        foreach (array_keys(self::AREAS) as $a) {
            \Illuminate\Support\Facades\Cache::forget("atencion.items.{$a}");
        }
    }

    /**
     * ¿Esta conversación amerita resumen LLM?
     * Filtramos "ruido" (saludos sueltos, "ok/gracias") para no desperdiciar Ollama:
     *   - Tiene ≥3 mensajes entrantes del paciente, O
     *   - Tiene un mensaje entrante con >80 caracteres (sustancioso), O
     *   - Está derivada a humano (asignada_a o no_leidos > 0), O
     *   - Tiene un mensaje entrante con audio/imagen/documento (siempre necesita contexto).
     *
     * 1 query barata. Devuelve false rápido si ya hay resumen.
     */
    public function ameritaResumen(): bool
    {
        if (!empty($this->resumen_llm)) return false;
        if ($this->asignada_a || $this->no_leidos > 0) return true;

        $stats = MensajeWA::where('conversacion_id', $this->id)
            ->where('direccion', 'entrante')
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN CHAR_LENGTH(contenido) > 80 THEN 1 ELSE 0 END) as largos,
                SUM(CASE WHEN tipo IN ("audio","imagen","documento","video") THEN 1 ELSE 0 END) as media
            ')
            ->first();

        return ($stats->total ?? 0) >= 3
            || ($stats->largos ?? 0) >= 1
            || ($stats->media  ?? 0) >= 1;
    }

    /**
     * Despacha el job de resumen si amerita y aún no se está procesando.
     * Idempotente: si ya hay resumen, o ya se intentó hace poco, o no amerita, no hace nada.
     * Diseñado para llamarse desde mensajeEntrante / abrir conversación sin penalizar latencia.
     */
    public function despacharResumenSiAmerita(): void
    {
        if (!empty($this->resumen_llm)) return;

        // Throttle: si ya se intentó en los últimos 10 min, no reintentar todavía
        if ($this->resumen_intento_at && $this->resumen_intento_at->gt(now()->subMinutes(10))) return;

        if (!$this->ameritaResumen()) {
            // No amerita: marcar para no recalcular cada vez que llegue un mensaje
            $this->forceFill(['resumen_intento_at' => now()])->saveQuietly();
            return;
        }

        \App\Jobs\GenerarResumenLLM::dispatch($this->id)->onQueue('resumen');
    }

    public function asignadaA()
    {
        return $this->belongsTo(\App\Models\User::class, 'asignada_a');
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(MensajeWA::class, 'conversacion_id');
    }

    public function tareas(): HasMany
    {
        return $this->hasMany(TareaWA::class, 'conversacion_id');
    }

    public function ultimoMensaje(): HasOne
    {
        return $this->hasOne(MensajeWA::class, 'conversacion_id')->latestOfMany();
    }

    /** Vínculo con el directorio de contactos vía wa_id (puede no existir). */
    public function contactoVinculado(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Contacto::class, 'wa_id', 'contacto');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(ConversacionEvento::class, 'conversacion_id')->orderBy('created_at');
    }

    public function getTelefonoAttribute(): string
    {
        return str_replace('@c.us', '', $this->contacto);
    }

    public function getNombreOTelefonoAttribute(): string
    {
        if ($this->nombre) return $this->nombre;
        $contacto = Contacto::buscarPorContacto($this->contacto);
        return $contacto?->nombre ?? $this->telefono;
    }

    public function scopeActivas($query)
    {
        return $query->where('estado', 'activa');
    }

    public function scopeArchivadas($query)
    {
        return $query->where('estado', 'archivada');
    }
}
