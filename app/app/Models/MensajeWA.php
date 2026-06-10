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

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(ConversacionWA::class, 'conversacion_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
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
