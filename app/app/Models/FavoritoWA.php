<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Contacto de WhatsApp favorito (compartido por el equipo, por JID). */
class FavoritoWA extends Model
{
    protected $table = 'favoritos_wa';

    protected $fillable = ['contacto', 'creado_por'];

    /**
     * JIDs favoritos como set (jid => true). Lo consulta la cola en cada
     * refresco: caché de 60 s, que se borra al marcar o desmarcar.
     */
    public static function set(): array
    {
        return Cache::remember('favoritos.wa', 60, fn() => array_fill_keys(self::pluck('contacto')->all(), true));
    }

    public static function limpiarCache(): void
    {
        Cache::forget('favoritos.wa');
        ConversacionWA::invalidarColaCache();
    }
}
