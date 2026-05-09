<?php

namespace App\Jobs;

use App\Models\ConversacionWA;
use App\Models\MensajeWA;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Genera el resumen LLM de una conversación llamando al endpoint /resumir del bot.
 * Asincrónico vía queue 'resumen' (worker dedicado, no bloquea el path crítico).
 *
 * Reglas:
 *  - WithoutOverlapping por conversation_id: si ya hay otro job para la misma conv corriendo
 *    o pendiente, no se procesa en paralelo (evita doble llamada a Ollama y race conditions).
 *  - tries=2: un reintento con 60s de delay si Ollama falla.
 *  - Marca resumen_intento_at siempre (éxito o fallo) para diagnóstico.
 */
class GenerarResumenLLM implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $backoff = 60;
    public int $timeout = 90;

    public function __construct(public int $conversacionId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->conversacionId))->expireAfter(180)];
    }

    public function handle(): void
    {
        $conv = ConversacionWA::find($this->conversacionId);
        if (!$conv) return;
        if (!empty($conv->resumen_llm)) return;

        // Construir el texto a resumir: últimos 25 mensajes en orden cronológico
        $msgs = MensajeWA::where('conversacion_id', $conv->id)
            ->whereIn('direccion', ['entrante', 'saliente'])
            ->orderBy('created_at')
            ->take(25)
            ->get(['direccion', 'tipo', 'contenido']);

        if ($msgs->isEmpty()) {
            $conv->forceFill(['resumen_intento_at' => now()])->saveQuietly();
            return;
        }

        $texto = $msgs->map(function ($m) {
            $prefix = $m->direccion === 'entrante' ? 'Paciente: ' : 'Bot: ';
            $cuerpo = $m->contenido;
            if (!$cuerpo) {
                $cuerpo = match ($m->tipo) {
                    'audio'     => '[mensaje de audio]',
                    'imagen'    => '[imagen adjunta]',
                    'video'     => '[video adjunto]',
                    'documento' => '[documento adjunto]',
                    default     => '[sin texto]',
                };
            }
            return $prefix . $cuerpo;
        })->implode("\n");

        try {
            $resp = Http::timeout($this->timeout)
                ->withToken(config('app.bot_ingress_token'))
                ->post(rtrim(config('app.bot_url'), '/') . '/resumir', ['texto' => $texto]);

            $resumen = $resp->ok() ? $resp->json('resumen') : null;

            // Marcar el intento siempre (éxito o fallo) para que el throttle de 10min funcione.
            $conv->forceFill([
                'resumen_llm'        => $resumen ?: $conv->resumen_llm,
                'resumen_intento_at' => now(),
            ])->saveQuietly();

            if (!$resumen) {
                Log::warning('[ResumenLLM] Bot devolvió sin resumen', [
                    'conv'   => $conv->id,
                    'status' => $resp->status(),
                ]);
            }
        } catch (\Throwable $e) {
            $conv->forceFill(['resumen_intento_at' => now()])->saveQuietly();
            Log::warning('[ResumenLLM] Excepción', ['conv' => $conv->id, 'msg' => $e->getMessage()]);
            throw $e;   // permite el retry de tries=2
        }
    }
}
