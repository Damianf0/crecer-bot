<?php

namespace App\Services\Difusion;

use App\Jobs\SimularAcuseDifusion;

/**
 * No manda nada. Sirve para probar campañas, pausas y estadísticas de punta a
 * punta antes de tener número/proveedor: da "entregado" a los pocos segundos y
 * "leído" a una parte, con una tasa chica de números sin WhatsApp.
 */
class CanalSimulado implements Canal
{
    public function nombre(): string { return 'simulado'; }

    public function enviar(string $telefono, string $texto, ?array $adjunto): ResultadoEnvio
    {
        // Determinístico por número: la misma prueba da siempre lo mismo.
        $h = crc32($telefono) % 100;
        if ($h < 3) return ResultadoEnvio::omitido('No tiene WhatsApp (simulado)');

        return ResultadoEnvio::enviado('sim_' . $h . '_' . bin2hex(random_bytes(6)), $telefono . '@c.us');
    }

    /** Acuses ficticios: entregado a los pocos segundos, leído en ~65 % de los casos. */
    public function alRegistrar(string $mensajeId): void
    {
        $h = (int) explode('_', $mensajeId)[1];
        SimularAcuseDifusion::dispatch($mensajeId, 'entregado')->delay(now()->addSeconds(3 + $h % 5));
        if ($h < 65) {
            SimularAcuseDifusion::dispatch($mensajeId, 'leido')->delay(now()->addSeconds(15 + $h));
        }
    }

    public function estado(): array
    {
        return ['ok' => true, 'detalle' => 'Modo simulado: no se envía nada a nadie.'];
    }

    public function pausaSegundos(): int { return random_int(1, 3); }
}
