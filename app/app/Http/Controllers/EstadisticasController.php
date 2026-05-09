<?php

namespace App\Http\Controllers;

use App\Models\ColaAtencion;
use App\Models\ConversacionEvento;
use App\Models\ConversacionWA;
use App\Models\DocumentoPaciente;
use App\Models\MensajeWA;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EstadisticasController extends Controller
{
    private const TZ = 'America/Argentina/Buenos_Aires';

    public function index()
    {
        return view('admin.estadisticas');
    }

    // ── Tab Hoy ────────────────────────────────────────────

    public function hoy(): JsonResponse
    {
        $data = Cache::remember('stats.hoy', 60, function () {
            $hoyStart = now(self::TZ)->startOfDay();
            $hoyEnd   = now(self::TZ)->endOfDay();

            // En vivo
            $backlog   = ConversacionWA::activas()->whereNull('asignada_a')->where('no_leidos', '>', 0)->count();
            $enProceso = ConversacionWA::activas()->whereNotNull('asignada_a')->count();
            $urgentes  = ConversacionWA::activas()->where('urgente', true)->count();
            $enSala    = ColaAtencion::activos()->count();

            // Volumen del día
            $msgIn  = MensajeWA::whereBetween('created_at', [$hoyStart, $hoyEnd])->where('direccion', 'entrante')->count();
            $msgOut = MensajeWA::whereBetween('created_at', [$hoyStart, $hoyEnd])->where('direccion', 'saliente')->count();

            $convsNuevas = ConversacionWA::whereBetween('created_at', [$hoyStart, $hoyEnd])->count();
            $convsCerradas = ConversacionEvento::where('tipo', 'resuelta')
                ->whereBetween('created_at', [$hoyStart, $hoyEnd])->count();

            $tabletPorMotivo = ColaAtencion::whereBetween('hora_llegada', [$hoyStart, $hoyEnd])
                ->selectRaw('motivo, COUNT(*) as c')->groupBy('motivo')->pluck('c', 'motivo')->toArray();

            $docsHoy = DocumentoPaciente::whereBetween('created_at', [$hoyStart, $hoyEnd])
                ->selectRaw('direccion, COUNT(*) as c')->groupBy('direccion')->pluck('c', 'direccion')->toArray();

            // SLA: tiempo desde creación de conversación hasta primera "tomada".
            // Solo conversaciones creadas hoy (proxy razonable; reaperturas no se cuentan acá).
            $slaSamples = ConversacionWA::whereBetween('created_at', [$hoyStart, $hoyEnd])
                ->select('id', 'created_at')
                ->get()
                ->map(function ($c) {
                    $primera = ConversacionEvento::where('conversacion_id', $c->id)
                        ->where('tipo', 'tomada')
                        ->orderBy('created_at')->value('created_at');
                    return $primera ? (int) Carbon::parse($primera)->diffInSeconds($c->created_at) : null;
                })
                ->filter()
                ->values()
                ->toArray();

            $sla = [
                'tomadas_total' => count($slaSamples),
                'pct_5min'  => $this->pctEnMenos($slaSamples, 5 * 60),
                'pct_15min' => $this->pctEnMenos($slaSamples, 15 * 60),
                'pct_30min' => $this->pctEnMenos($slaSamples, 30 * 60),
                'mediana_seg' => $this->mediana($slaSamples),
            ];

            // Cobertura LLM (resumen): solo cuenta convs que ameritan resumen.
            // Una conv sin resumen y nunca intentada se asume "pendiente" hasta que el job
            // (que corre asincrónico) la procese o marque como no-amerita.
            $sinResumen = ConversacionWA::whereNull('resumen_llm')
                ->whereNotNull('resumen_intento_at')   // ya se evaluó al menos una vez
                ->count();
            $conResumen = ConversacionWA::whereNotNull('resumen_llm')->count();
            $pendientes = ConversacionWA::whereNull('resumen_llm')
                ->whereNull('resumen_intento_at')      // nunca evaluadas
                ->count();
            $totalEvaluadas = $sinResumen + $conResumen;
            $coberturaPct = $totalEvaluadas > 0 ? round($conResumen / $totalEvaluadas * 100, 1) : 0;

            // Jobs en cola 'resumen' (peek a la tabla jobs)
            $jobsPendientes = \DB::table('jobs')->where('queue', 'resumen')->count();

            return [
                'vivo' => [
                    'backlog' => $backlog, 'en_proceso' => $enProceso,
                    'urgentes' => $urgentes, 'en_sala' => $enSala,
                ],
                'volumen' => [
                    'msg_in' => $msgIn, 'msg_out' => $msgOut,
                    'convs_nuevas' => $convsNuevas, 'convs_cerradas' => $convsCerradas,
                    'tablet_por_motivo' => $tabletPorMotivo,
                    'docs' => $docsHoy,
                ],
                'sla' => $sla,
                'llm' => [
                    'con_resumen'      => $conResumen,
                    'sin_resumen'      => $sinResumen,
                    'pendientes_eval'  => $pendientes,
                    'cobertura_pct'    => $coberturaPct,
                    'jobs_en_cola'     => $jobsPendientes,
                ],
                'updated_at' => now()->toIso8601String(),
            ];
        });

        return response()->json($data);
    }

    // ── Tab Por secretaria ─────────────────────────────────

    public function secretarias(Request $request): JsonResponse
    {
        [$from, $to] = $this->rango($request, 7);
        $cacheKey = "stats.sec.{$from->timestamp}.{$to->timestamp}";

        $data = Cache::remember($cacheKey, 300, function () use ($from, $to) {
            $usuarios = User::where('activo', true)
                ->orderBy('nombre_completo')
                ->get(['id', 'nombre_completo', 'rol']);

            $rows = [];
            foreach ($usuarios as $u) {
                $eventos = ConversacionEvento::where('usuario_id', $u->id)
                    ->whereBetween('created_at', [$from, $to])
                    ->selectRaw('tipo, COUNT(*) as c')
                    ->groupBy('tipo')
                    ->pluck('c', 'tipo')
                    ->toArray();

                $msgEnv = MensajeWA::where('usuario_id', $u->id)
                    ->where('direccion', 'saliente')
                    ->whereBetween('created_at', [$from, $to])
                    ->count();

                // Tiempo medio respuesta: por cada "tomada" del usuario, delta vs created_at de la conv
                $tomadas = ConversacionEvento::where('usuario_id', $u->id)
                    ->where('tipo', 'tomada')
                    ->whereBetween('created_at', [$from, $to])
                    ->pluck('conversacion_id', 'created_at');
                $deltasResp = [];
                foreach ($tomadas as $tomadaAt => $convId) {
                    $convCreated = ConversacionWA::where('id', $convId)->value('created_at');
                    if ($convCreated) {
                        $deltasResp[] = (int) Carbon::parse($tomadaAt)->diffInSeconds($convCreated);
                    }
                }

                // Tiempo medio resolución: para cada "resuelta" del usuario, delta vs primera "tomada" de la misma conv
                $resueltas = ConversacionEvento::where('usuario_id', $u->id)
                    ->where('tipo', 'resuelta')
                    ->whereBetween('created_at', [$from, $to])
                    ->pluck('conversacion_id', 'created_at');
                $deltasResol = [];
                foreach ($resueltas as $resueltaAt => $convId) {
                    $tomadaAt = ConversacionEvento::where('conversacion_id', $convId)
                        ->where('tipo', 'tomada')
                        ->where('created_at', '<=', $resueltaAt)
                        ->orderByDesc('created_at')
                        ->value('created_at');
                    if ($tomadaAt) {
                        $deltasResol[] = (int) Carbon::parse($resueltaAt)->diffInSeconds($tomadaAt);
                    }
                }

                $rows[] = [
                    'id'              => $u->id,
                    'nombre'          => $u->nombre_completo,
                    'rol'             => $u->rol,
                    'tomadas'         => (int) ($eventos['tomada'] ?? 0),
                    'resueltas'       => (int) ($eventos['resuelta'] ?? 0),
                    'delegadas'       => (int) ($eventos['delegada'] ?? 0),
                    'reabiertas'      => (int) ($eventos['reabierta'] ?? 0),
                    'msj_enviados'    => $msgEnv,
                    't_resp_medio_seg'  => $this->mediana($deltasResp),
                    't_resol_medio_seg' => $this->mediana($deltasResol),
                ];
            }

            // Solo mostrar usuarios con alguna actividad
            $rows = array_values(array_filter($rows, fn($r) =>
                $r['tomadas'] || $r['resueltas'] || $r['msj_enviados']
            ));

            return [
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
                'rows' => $rows,
            ];
        });

        return response()->json($data);
    }

    // ── Tab Tendencias ─────────────────────────────────────

    public function tendencias(Request $request): JsonResponse
    {
        [$from, $to] = $this->rango($request, 30);
        $cacheKey = "stats.tend.{$from->timestamp}.{$to->timestamp}";

        $data = Cache::remember($cacheKey, 300, function () use ($from, $to) {
            // Conversaciones nuevas por día
            $convsPorDia = ConversacionWA::whereBetween('created_at', [$from, $to])
                ->selectRaw('DATE(created_at) as dia, COUNT(*) as c')
                ->groupBy('dia')->orderBy('dia')->pluck('c', 'dia')->toArray();

            // Mensajes in/out por día
            $msjPorDia = MensajeWA::whereBetween('created_at', [$from, $to])
                ->selectRaw('DATE(created_at) as dia, direccion, COUNT(*) as c')
                ->whereIn('direccion', ['entrante', 'saliente'])
                ->groupBy('dia', 'direccion')->orderBy('dia')->get();

            $msjIn = []; $msjOut = [];
            foreach ($msjPorDia as $r) {
                if ($r->direccion === 'entrante') $msjIn[$r->dia] = (int) $r->c;
                else                              $msjOut[$r->dia] = (int) $r->c;
            }

            // Llegadas Tablet por motivo
            $tablet = ColaAtencion::whereBetween('hora_llegada', [$from, $to])
                ->selectRaw('motivo, COUNT(*) as c')->groupBy('motivo')->pluck('c', 'motivo')->toArray();

            // Heatmap día-de-semana × hora — mensajes entrantes
            $heatRaw = MensajeWA::whereBetween('created_at', [$from, $to])
                ->where('direccion', 'entrante')
                ->selectRaw('DAYOFWEEK(created_at) as dow, HOUR(created_at) as hora, COUNT(*) as c')
                ->groupBy('dow', 'hora')->get();
            // MySQL DAYOFWEEK: 1=domingo..7=sábado. Convertimos a 0..6 con lunes=0
            $heat = array_fill(0, 7, array_fill(0, 24, 0));
            foreach ($heatRaw as $r) {
                $idx = ((int) $r->dow + 5) % 7;   // 1(dom)→6, 2(lun)→0, ..., 7(sab)→5
                $heat[$idx][(int) $r->hora] = (int) $r->c;
            }

            return [
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
                'convs_por_dia'  => $convsPorDia,
                'msj_in_por_dia'  => $msjIn,
                'msj_out_por_dia' => $msjOut,
                'tablet_por_motivo' => $tablet,
                'heatmap' => $heat,
            ];
        });

        return response()->json($data);
    }

    // ── Helpers ────────────────────────────────────────────

    private function rango(Request $r, int $diasDefault): array
    {
        $tz = self::TZ;
        $to   = $r->filled('to')   ? Carbon::parse($r->input('to'),   $tz)->endOfDay()
                                   : now($tz)->endOfDay();
        $from = $r->filled('from') ? Carbon::parse($r->input('from'), $tz)->startOfDay()
                                   : now($tz)->subDays($diasDefault)->startOfDay();
        return [$from, $to];
    }

    private function pctEnMenos(array $samples, int $maxSeg): float
    {
        if (empty($samples)) return 0.0;
        $ok = count(array_filter($samples, fn($s) => $s !== null && $s <= $maxSeg));
        return round(($ok / count($samples)) * 100, 1);
    }

    private function mediana(array $samples): ?int
    {
        $samples = array_values(array_filter($samples, fn($s) => $s !== null));
        if (empty($samples)) return null;
        sort($samples);
        $n = count($samples);
        $mid = (int) floor($n / 2);
        return $n % 2 ? $samples[$mid] : (int) (($samples[$mid - 1] + $samples[$mid]) / 2);
    }
}
