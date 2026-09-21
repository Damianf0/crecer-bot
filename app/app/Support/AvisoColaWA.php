<?php

namespace App\Support;

use App\Events\ConversacionWAActualizada;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Tiempo real de las colas de WhatsApp (/v2/atencion, /v2/mis-conversaciones).
 *
 * Los hooks de ConversacionWA y MensajeWA marcan qué conversaciones cambiaron;
 * al terminar el request (ya enviada la respuesta, así el bot no espera a
 * Reverb) se invalida el cache de la cola del área y se avisa por el canal
 * privado wa.area.{area}. Un mensaje entrante toca mensaje + conversación en
 * el mismo request: la dedup lo deja en un solo aviso.
 *
 * Regla: si Reverb está caído se loguea y listo. El mensaje del paciente ya
 * está en la BD y el panel lo levanta con el polling de respaldo.
 */
class AvisoColaWA
{
    /** @var array<string, array{0:int,1:string}> "id|area" → [id, area] */
    private static array $pendientes = [];
    private static bool $registrado = false;

    public static function marcar(?int $convId, ?string $area): void
    {
        if (!$convId || !$area) return;

        // Consola y queue worker no tienen "fin de request" útil (el worker
        // termina recién al apagarse): avisar en el momento.
        if (app()->runningInConsole()) {
            self::avisar($convId, $area);
            return;
        }

        self::$pendientes["{$convId}|{$area}"] = [$convId, $area];
        if (!self::$registrado) {
            self::$registrado = true;
            app()->terminating(fn () => self::vaciar());
        }
    }

    public static function vaciar(): void
    {
        $pendientes = self::$pendientes;
        self::$pendientes = [];
        self::$registrado = false;
        foreach ($pendientes as [$id, $area]) self::avisar($id, $area);
    }

    private static function avisar(int $id, string $area): void
    {
        // La cola tiene cache de 3 s: sin esto, el panel que reacciona al
        // aviso podría recibir la foto de antes del cambio.
        Cache::forget("atencion.items.{$area}");
        try {
            broadcast(new ConversacionWAActualizada($id, $area));
        } catch (\Throwable $e) {
            Log::warning("[AvisoColaWA] broadcast falló (conv {$id}, {$area}): {$e->getMessage()}");
        }
    }
}
