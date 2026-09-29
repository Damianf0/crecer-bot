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
        'telefono_wa', 'nombre_wa', 'wa_info_at',
    ];

    protected $casts = [
        'ultima_actividad'   => 'datetime',
        'resumen_intento_at' => 'datetime',
        'no_leidos'          => 'integer',
        'urgente'            => 'boolean',
        'wa_info_at'         => 'datetime',
    ];

    /** Áreas válidas (= números de WhatsApp). 'atencion' es el bot original. */
    public const AREAS = [
        'atencion'       => 'Atención',
        'administracion' => 'Administración',
        'ovodonacion'    => 'Ovodonación',
    ];

    /** Cola de las respuestas a las difusiones (4º número, ver config/difusion.php). */
    public const AREA_DIFUSION = 'difusion';

    /**
     * Áreas con cola en el panel: las de AREAS más la de difusiones cuando está
     * activa. Usar esto (no AREAS) en validaciones, rutas y vistas: con
     * DIFUSION_ACTIVA=false no aparece una cola vacía ni se busca un bot que
     * todavía no existe.
     */
    public static function areas(): array
    {
        return config('difusion.activa')
            ? self::AREAS + [self::AREA_DIFUSION => 'Difusiones']
            : self::AREAS;
    }

    /**
     * Campos que se ven en la cola: si cambia alguno, se avisa por Reverb.
     * historial_llm y resumen_intento_at quedan afuera — el bot los reescribe
     * en cada mensaje y no cambian nada de lo que muestra el panel.
     */
    private const CAMPOS_COLA = ['estado', 'no_leidos', 'ultima_actividad', 'urgente',
                                 'asignada_a', 'area', 'nombre', 'resumen_llm', 'telefono_wa', 'nombre_wa'];

    protected static function booted(): void
    {
        static::saved(function (self $c) {
            if (!$c->wasRecentlyCreated && !$c->wasChanged(self::CAMPOS_COLA)) return;
            \App\Support\AvisoColaWA::marcar($c->id, $c->area);
            // Derivada a otra área: también tiene que desaparecer de la cola vieja.
            if ($c->wasChanged('area') && $c->getOriginal('area')) {
                \App\Support\AvisoColaWA::marcar($c->id, $c->getOriginal('area'));
            }
        });
    }

    /** URL interna del bot que corresponde al área de esta conversación. */
    public function botUrl(): string
    {
        $area = array_key_exists($this->area, self::areas()) ? $this->area : 'atencion';
        return rtrim(config('app.bot_url_' . $area) ?: config('app.bot_url'), '/');
    }

    /** URL del bot para un área dada (helper estático, p/ casos sin instancia). */
    public static function botUrlPara(?string $area): string
    {
        $area = array_key_exists($area, self::areas()) ? $area : 'atencion';
        return rtrim(config('app.bot_url_' . $area) ?: config('app.bot_url'), '/');
    }

    /**
     * Áreas que la secretaria declaró atender en esta sesión (las que marcó en
     * la pantalla de colas). Si no marcó ninguna → todas (compat + default).
     */
    public static function areasDeLaSesion(): array
    {
        $colas = (array) session('colas', []);
        $sel = array_values(array_intersect($colas, array_keys(self::areas())));
        return $sel ?: array_keys(self::areas());
    }

    /** Invalida el cache de la cola de /atencion (una clave por área). */
    public static function invalidarColaCache(): void
    {
        foreach (array_keys(self::areas()) as $a) {
            \Illuminate\Support\Facades\Cache::forget("atencion.items.{$a}");
        }
    }

    /**
     * ¿Esta conversación amerita resumen LLM?
     * Filtramos "ruido" (saludos sueltos, "ok/gracias") para no desperdiciar Ollama:
     *   - Tiene ≥3 mensajes entrantes del paciente, O
     *   - Tiene un mensaje entrante con >80 caracteres (sustancioso), O
     *   - Tiene un mensaje entrante con audio/imagen/documento (siempre necesita contexto).
     *
     * Hasta el 23/09 también alcanzaba con estar en la cola (asignada_a o
     * no_leidos > 0), pero mensajeEntrante suma no_leidos justo antes de
     * preguntar: el filtro no filtraba nada. El 96% de los "fallos" eran chats de
     * 1-2 mensajes cortos ("¿Me enviarían más detalles?") donde el modelo
     * devuelve {} porque no hay qué resumir; la cola ya muestra ese mensaje tal cual.
     *
     * 1 query barata. Devuelve false rápido si ya hay resumen.
     */
    public function ameritaResumen(): bool
    {
        if (!empty($this->resumen_llm)) return false;

        // CHAR_LENGTH cuenta caracteres en MySQL (LENGTH, bytes); SQLite (tests) solo tiene LENGTH, que ahí cuenta caracteres.
        $largo = $this->getConnection()->getDriverName() === 'sqlite' ? 'LENGTH' : 'CHAR_LENGTH';
        $stats = MensajeWA::where('conversacion_id', $this->id)
            ->where('direccion', 'entrante')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN {$largo}(contenido) > 80 THEN 1 ELSE 0 END) as largos,
                SUM(CASE WHEN tipo IN ('audio','imagen','documento','video') THEN 1 ELSE 0 END) as media
            ")
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

    /**
     * Teléfono para mostrar: el que WhatsApp asocia al chat (telefono_wa) o el
     * del JID @c.us. Un @lid sin resolver devuelve '' (antes devolvía el código
     * "123456@lid", que no le sirve a nadie).
     */
    public function getTelefonoAttribute(): string
    {
        if ($this->telefono_wa) return $this->telefono_wa;
        return str_ends_with((string) $this->contacto, '@c.us') ? str_replace('@c.us', '', $this->contacto) : '';
    }

    /** Nombre para mostrar: ficha del directorio → nombre de WhatsApp → teléfono → el JID como último recurso. */
    public function getNombreOTelefonoAttribute(): string
    {
        if ($this->nombre) return $this->nombre;
        $contacto = Contacto::buscarPorContacto($this->contacto);
        if ($contacto?->nombre) return $contacto->nombre;
        if ($this->nombre_wa) return $this->nombre_wa;
        return $this->telefono ? '+' . $this->telefono : (string) $this->contacto;
    }

    /**
     * ¿El WhatsApp de este chat está a nombre de otra persona que la ficha?
     * Pasa cuando el número de Omnia es de la pareja, la madre o un número
     * viejo: el vínculo es correcto (es ese número), pero la foto y el nombre
     * de WhatsApp son del otro. Sin coincidencia ni en la inicial de ningún
     * nombre (los apodos y abreviaturas no cuentan como distinto).
     */
    public static function nombresDistintos(?string $ficha, ?string $wa): bool
    {
        $tok = fn($s) => array_values(array_filter(
            preg_split('/\s+/', preg_replace('/[^a-z ]/', ' ', strtolower(\Illuminate\Support\Str::ascii((string) $s)))),
            fn($w) => strlen($w) >= 3
        ));
        $a = $tok($ficha);
        $b = $tok($wa);
        if (!$a || !$b) return false;
        foreach ($a as $x) foreach ($b as $y) if ($x[0] === $y[0]) return false;
        return true;
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
