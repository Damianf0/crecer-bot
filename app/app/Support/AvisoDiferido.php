<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Avisos de tiempo real por Reverb, diferidos al final del request.
 *
 * Varias escrituras del mismo request (un entrante toca mensaje + conversación,
 * un check-in crea y actualiza) se deduplican por clave y salen una sola vez,
 * después de enviada la respuesta: quien escribe (el bot, el tablet) no espera
 * a Reverb. En consola y queue worker no hay "fin de request" útil, así que se
 * avisa en el momento.
 *
 * Regla para todo el que lo use: si el broadcast falla se loguea y listo. La
 * escritura ya está en la BD y los paneles tienen polling de respaldo.
 */
class AvisoDiferido
{
    /** @var array<string, \Closure> */
    private static array $pendientes = [];
    private static bool $registrado = false;

    public static function encolar(string $clave, \Closure $avisar): void
    {
        if (app()->runningInConsole()) {
            self::ejecutar($clave, $avisar);
            return;
        }
        self::$pendientes[$clave] = $avisar;
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
        foreach ($pendientes as $clave => $avisar) self::ejecutar($clave, $avisar);
    }

    private static function ejecutar(string $clave, \Closure $avisar): void
    {
        try {
            $avisar();
        } catch (\Throwable $e) {
            Log::warning("[AvisoDiferido] {$clave}: {$e->getMessage()}");
        }
    }
}
