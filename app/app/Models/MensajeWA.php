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
