<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MensajeWA extends Model
{
    protected $table = 'mensajes_wa';

    protected $fillable = [
        'conversacion_id', 'direccion', 'tipo', 'contenido',
        'archivo_url', 'wa_id', 'usuario_id', 'leido',
        'quoted_wa_id', 'quoted_autor', 'quoted_preview',
    ];

    protected $casts = [
        'leido' => 'boolean',
    ];

    // Mensaje nuevo (entrante, respuesta del panel o saliente del celular):
    // aviso por Reverb a la cola del área. Ver App\Support\AvisoColaWA.
    protected static function booted(): void
    {
        static::created(function (self $m) {
            $area = ConversacionWA::whereKey($m->conversacion_id)->value('area');
            \App\Support\AvisoColaWA::marcar($m->conversacion_id, $area);
        });
    }

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(ConversacionWA::class, 'conversacion_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Sin id de WhatsApp = NULL, nunca ''. El bot devuelve '' cuando sendMessage
     * no trae id, y 12 salientes quedaron con wa_id vacío: para el dedup y para
     * responder citando, '' parecía un id compartido por mensajes distintos.
     */
    public function setWaIdAttribute(?string $value): void
    {
        $this->attributes['wa_id'] = ($value === null || trim($value) === '') ? null : $value;
    }

    /**
     * Reescribe archivo_url al leer: las URLs históricas apuntan al /media
     * público del bot (http://IP:300X/media/...) o al storage público de
     * Laravel (asset('storage/wa-media/...')). Ambas pasan a servirse con auth
     * de sesión via /wa-media/{filename} — sin migrar datos. El valor crudo en
     * BD no cambia; URLs que no matchean (o null) se devuelven tal cual.
     */
    public function getArchivoUrlAttribute(?string $value): ?string
    {
        if (!$value) return $value;
        if (preg_match('#^https?://[^/]+/(?:media|storage/wa-media)/([A-Za-z0-9._@-]+)$#', $value, $m)) {
            return '/wa-media/' . $m[1];
        }
        return $value;
    }

    public function getEsEntranteAttribute(): bool
    {
        return $this->direccion === 'entrante';
    }

    public function getEsSalienteAttribute(): bool
    {
        return $this->direccion === 'saliente';
    }

    public function getEsNotaAttribute(): bool
    {
        return $this->direccion === 'nota_interna';
    }

    public function getSnippetAttribute(): string
    {
        if ($this->tipo === 'audio') return '🎤 Audio';
        return \Str::limit($this->contenido ?? '', 60);
    }
}
