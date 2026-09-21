<?php

namespace App\Support;

use App\Events\ConversacionWAActualizada;
use Illuminate\Support\Facades\Cache;

/**
 * Tiempo real de las colas de WhatsApp (/v2/atencion, /v2/mis-conversaciones).
 *
 * Los hooks de ConversacionWA y MensajeWA marcan qué conversaciones cambiaron;
 * al terminar el request se invalida el cache de la cola del área y se avisa
 * por el canal privado wa.area.{area}. Dedup, diferido y tolerancia a Reverb
 * caído: ver AvisoDiferido.
 */
class AvisoColaWA
{
    public static function marcar(?int $convId, ?string $area): void
    {
        if (!$convId || !$area) return;

        AvisoDiferido::encolar("wa:{$convId}|{$area}", function () use ($convId, $area) {
            // La cola tiene cache de 3 s: sin esto, el panel que reacciona al
            // aviso podría recibir la foto de antes del cambio.
            Cache::forget("atencion.items.{$area}");
            broadcast(new ConversacionWAActualizada($convId, $area));
        });
    }
}
