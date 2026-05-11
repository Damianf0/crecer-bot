<?php

namespace App\Http\Controllers;

use App\Models\ColaAtencion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Panel público (sin auth Laravel) para mostrar llamados en la sala de espera.
 *
 * Acceso: /llamador?token=<LLAMADOR_TOKEN>. Token configurable en app/.env.
 * Privacidad: solo se muestra primer nombre + inicial de apellido + consultorio,
 * sin DNI, obra social, motivo de consulta ni nombre del profesional.
 */
class LlamadorController extends Controller
{
    /** Ventana de tiempo en la que un llamado se considera "vigente" (cartel grande + TTS). */
    private const VENTANA_LLAMADO_SEGS = 45;

    private function validarToken(Request $r): void
    {
        $esperado = (string) config('app.llamador_token');
        $recibido = (string) ($r->input('token') ?? $r->bearerToken() ?? '');
        if (!$esperado || !hash_equals($esperado, $recibido)) {
            abort(403, 'Token inválido o ausente. Pedile el link al administrador.');
        }
    }

    /** Reduce el nombre a "Carolina G." — preserva privacidad en una TV pública. */
    private function nombreDiscreto(?string $nombre, ?string $apellido): string
    {
        $n = trim((string) $nombre);
        $a = trim((string) $apellido);
        $primerNombre = $n !== '' ? explode(' ', $n)[0] : '';
        $iniApellido  = $a !== '' ? mb_strtoupper(mb_substr($a, 0, 1)) . '.' : '';
        return trim("$primerNombre $iniApellido");
    }

    /** Nombre completo para TTS (queremos que la persona se reconozca). */
    private function nombreParaAnuncio(?string $nombre, ?string $apellido): string
    {
        return trim(((string) $nombre) . ' ' . ((string) $apellido));
    }

    public function index(Request $r)
    {
        $this->validarToken($r);
        $planta = $this->plantaValida($r->input('planta'));
        return view('llamador.index', [
            'token'              => $r->input('token'),
            'planta'             => $planta,   // 'baja' | 'alta' | null
            'ventana_segs'       => self::VENTANA_LLAMADO_SEGS,
            'nombre_clinica'     => 'Crecer Reproducción',
        ]);
    }

    public function data(Request $r): JsonResponse
    {
        $this->validarToken($r);
        $planta = $this->plantaValida($r->input('planta'));

        $vigentesQ = ColaAtencion::query()
            ->whereNotNull('llamado_consultorio_at')
            ->where('llamado_consultorio_at', '>', now()->subSeconds(self::VENTANA_LLAMADO_SEGS))
            ->whereNull('atendido_at')
            ->orderByDesc('llamado_consultorio_at')
            ->limit(5);
        if ($planta) $vigentesQ->where('planta', $planta);
        $vigentes = $vigentesQ->get();

        $llamadoActual = $vigentes->first();

        $esperandoQ = ColaAtencion::query()
            ->where('estado', 'liberado')
            ->whereNull('llamado_consultorio_at')
            ->orderBy('hora_liberado')
            ->limit(12);
        if ($planta) $esperandoQ->where('planta', $planta);
        $esperando = $esperandoQ->get();

        return response()->json([
            'ok'      => true,
            'planta'  => $planta,
            'now_ts'  => now()->timestamp,
            'actual'  => $llamadoActual ? $this->mapLlamado($llamadoActual) : null,
            'previos' => $vigentes->slice(1)->values()->map(fn ($p) => $this->mapLlamado($p)),
            'esperando' => $esperando->map(fn ($p) => [
                'id'       => $p->id,
                'nombre'   => $this->nombreDiscreto($p->nombre, $p->apellido),
                'planta'   => $p->planta,
            ])->values(),
        ]);
    }

    private function plantaValida($v): ?string
    {
        $v = is_string($v) ? mb_strtolower(trim($v)) : null;
        return in_array($v, ['baja', 'alta'], true) ? $v : null;
    }

    private function mapLlamado(ColaAtencion $p): array
    {
        return [
            'id'             => $p->id,
            'nombre_display' => $this->nombreDiscreto($p->nombre, $p->apellido),
            'nombre_anuncio' => $this->nombreParaAnuncio($p->nombre, $p->apellido),
            'consultorio'    => $p->consultorio,
            'planta'         => $p->planta,
            'llamado_ts'     => $p->llamado_consultorio_at?->timestamp,
        ];
    }
}
