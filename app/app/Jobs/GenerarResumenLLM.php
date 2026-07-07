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

        // Construir el texto a resumir: SOLO los mensajes entrantes del paciente
        // (últimos 25, en orden cronológico). No incluimos salientes: ni las
        // autorrespuestas del bot ni las respuestas manuales de la secretaria —
        // el resumen es del motivo de consulta del paciente, no del ida y vuelta.
        $msgs = MensajeWA::where('conversacion_id', $conv->id)
            ->where('direccion', 'entrante')
            ->orderBy('created_at')
            ->take(25)
            ->get(['tipo', 'contenido']);

        if ($msgs->isEmpty()) {
            $conv->forceFill(['resumen_intento_at' => now()])->saveQuietly();
            return;
        }

        $texto = $msgs->map(function ($m) {
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
            return 'Paciente: ' . $cuerpo;
        })->implode("\n");

        // Bot del área de la conversación (07/07): antes TODOS los resúmenes
        // pasaban por el bot de atención — cada caída suya (48h acumuladas en
        // 2 semanas) tiraba los resúmenes de las 3 áreas. Fallback al alias
        // viejo si el área no tiene URL propia.
        $botUrl = config('app.bot_url_' . $conv->area) ?: config('app.bot_url');

        try {
            $resp = Http::timeout($this->timeout)
                ->withToken(config('app.bot_ingress_token'))
                ->post(rtrim($botUrl, '/') . '/resumir', ['texto' => $texto]);

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
