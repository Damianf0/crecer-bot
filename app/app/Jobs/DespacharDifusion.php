<?php

namespace App\Jobs;

use App\Models\DifusionBaja;
use App\Models\DifusionCampania;
use App\Services\Difusion\Difusiones;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Manda UN mensaje de una campaña y se vuelve a encolar con la pausa del
 * canal. Así el ritmo lo marca la cola (sin procesos colgados esperando) y
 * pausar/cancelar corta la cadena en el próximo paso.
 *
 * Cadena única: cada arranque (iniciar/reanudar) guarda un token en cache y
 * solo sigue el job que lo tenga. Sin esto, reanudar con un job viejo todavía
 * en la cola duplicaba el ritmo de envío.
 */
class DespacharDifusion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries   = 1;
    public int $timeout = 120;

    private const MAX_TRANSITORIOS = 3;

    public function __construct(public int $campaniaId, public string $token)
    {
        $this->onQueue('difusion');
    }

    public static function claveToken(int $id): string { return "difusion:{$id}:token"; }

    /** Arranca (o re-arranca) la cadena de una campaña, invalidando la anterior. */
    public static function arrancar(DifusionCampania $c, ?\DateTimeInterface $cuando = null): void
    {
        $token = bin2hex(random_bytes(8));
        Cache::forever(self::claveToken($c->id), $token);
        $job = new self($c->id, $token);
        if ($cuando) $job->delay($cuando);
        dispatch($job);
    }

    private function seguir(int $segundos): void
    {
        dispatch((new self($this->campaniaId, $this->token))->delay(now()->addSeconds($segundos)));
    }

    public function handle(): void
    {
        if (Cache::get(self::claveToken($this->campaniaId)) !== $this->token) return;   // cadena vieja

        $c = DifusionCampania::find($this->campaniaId);
        if (!$c) return;

        // Programada: al llegar la hora pasa a "enviando".
        if ($c->estado === 'programada') {
            if ($c->programada_para && $c->programada_para->isFuture()) {
                $this->seguir(max(5, (int) now()->diffInSeconds($c->programada_para)));
                return;
            }
            $c->update(['estado' => 'enviando', 'iniciada_at' => $c->iniciada_at ?? now()]);
        }
        if ($c->estado !== 'enviando') return;   // pausada, cancelada o terminada

        if ($ventana = Difusiones::proximaVentana($c->canal)) {
            $this->seguir(max(60, (int) now()->diffInSeconds($ventana)));
            return;
        }

        $dest = $c->destinatarios()->where('estado', 'pendiente')->orderBy('id')->first();
        if (!$dest) {
            $c->update(['estado' => 'terminada', 'terminada_at' => now()]);
            Cache::forget(self::claveToken($c->id));
            return;
        }

        // Pudo pedir la baja después de armada la lista.
        if (DifusionBaja::where('telefono', $dest->telefono)->exists()) {
            $dest->update(['estado' => 'omitido', 'error' => 'Dado de baja']);
            $this->seguir(1);
            return;
        }

        $canal = Difusiones::canal($c->canal);
        $adjunto = $c->adjunto_path ? [
            'path'   => Storage::disk('local')->path($c->adjunto_path),
            'nombre' => $c->adjunto_nombre,
            'mime'   => $c->adjunto_mime,
        ] : null;
        $r = $canal->enviar($dest->telefono, $c->textoPara($dest->nombre), $adjunto);

        $claveFallos = "difusion:{$c->id}:transitorios";
        switch ($r->tipo) {
            case 'enviado':
                $dest->update(['estado' => 'enviado', 'enviado_at' => now(), 'mensaje_id' => $r->mensajeId,
                               'chat_id' => $r->chatId, 'error' => null]);
                Cache::forget($claveFallos);
                $canal->alRegistrar($r->mensajeId);
                break;
            case 'omitido':
            case 'fallido':
                $dest->update(['estado' => $r->tipo, 'error' => mb_substr((string) $r->error, 0, 255)]);
                break;
            default:   // transitorio: el proveedor no responde
                Cache::add($claveFallos, 0, 3600);   // increment no crea la clave en el store database
                $n = (int) Cache::increment($claveFallos);
                $dest->update(['error' => mb_substr((string) $r->error, 0, 255)]);
                if ($n >= self::MAX_TRANSITORIOS) {
                    // No insistir contra un bot caído: queda pausada y se ve en la pantalla.
                    $c->update(['estado' => 'pausada']);
                    Cache::forget($claveFallos);
                    Log::warning('Difusión pausada: el proveedor no responde', ['campania' => $c->id, 'error' => $r->error]);
                    return;
                }
                $this->seguir(120);
                return;
        }

        $this->seguir($canal->pausaSegundos());
    }
}
