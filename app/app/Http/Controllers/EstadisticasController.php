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

            // Cobertura LLM (resumen). OJO semántica (corregida 07/07):
            // despacharResumenSiAmerita() marca resumen_intento_at TAMBIÉN cuando
            // la conv no amerita resumen (charla corta) — antes eso se contaba
            // como "sin resumen/falló" e inflaba el número (365 de 743 eran
            // saltos deliberados). Acá se separa re-evaluando ameritaResumen()
            // en SQL sobre las no-resueltas — tiene que ser LA MISMA regla que
            // ConversacionWA::ameritaResumen() (desde el 23/09 sin el atajo de
            // asignada/no_leidos, que la volvía verdadera para todas).
            $split = \DB::selectOne("
                SELECT
                  SUM(CASE WHEN amerita THEN 1 ELSE 0 END) AS fallaron,
                  SUM(CASE WHEN amerita THEN 0 ELSE 1 END) AS no_ameritan
                FROM (
                  SELECT c.id,
                    ((SELECT COUNT(*) FROM mensajes_wa m WHERE m.conversacion_id = c.id AND m.direccion = 'entrante') >= 3
                     OR EXISTS (SELECT 1 FROM mensajes_wa m WHERE m.conversacion_id = c.id AND m.direccion = 'entrante' AND CHAR_LENGTH(m.contenido) > 80)
                     OR EXISTS (SELECT 1 FROM mensajes_wa m WHERE m.conversacion_id = c.id AND m.direccion = 'entrante' AND m.tipo IN ('audio','imagen','documento','video'))
                    ) AS amerita
                  FROM conversaciones_wa c
                  WHERE c.resumen_llm IS NULL AND c.resumen_intento_at IS NOT NULL
                ) t
            ");
            $sinResumen = (int) ($split->fallaron ?? 0);
            $noAmeritan = (int) ($split->no_ameritan ?? 0);
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
                    'sin_resumen'      => $sinResumen,   // ameritan y fallaron de verdad
                    'no_ameritan'      => $noAmeritan,   // charlas cortas: salto deliberado
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

    // ── Tab Tipos de consulta ──────────────────────────────
    // Fuente: clasificaciones_wa (la etiqueta de la IA de cada tanda de mensajes;
    // historia importada de los logs desde el 26/06). Ver App\Support\TiposConsulta.

    public function tiposConsulta(Request $request): JsonResponse
    {
        [$from, $to] = $this->rango($request, 30);
        // Semanas enteras: una primera semana cortada se lee como una caída que no existió.
        $from = $from->copy()->startOfWeek();
        $area = array_key_exists((string) $request->input('area'), ConversacionWA::AREAS) ? $request->input('area') : null;
        $cacheKey = 'stats.tipos2.v' . Cache::get('stats.tipos.ver', 1) . ".{$from->timestamp}.{$to->timestamp}." . ($area ?? 'todas');

        $data = Cache::remember($cacheKey, 300, function () use ($from, $to, $area) {
            $T = \App\Support\TiposConsulta::class;
            $base = fn() => DB::table('clasificaciones_wa')->whereBetween('created_at', [$from, $to])
                ->when($area, fn($q) => $q->where('area', $area));
            $codigoSql = 'COALESCE(codigo_corregido, codigo)';

            // Por día × código (se agrupa en semanas en PHP: portable y liviano).
            $porDia = $base()->selectRaw("DATE(created_at) dia, {$codigoSql} cod, COUNT(*) n, SUM(en_horario = 0) fuera")
                ->groupBy('dia', 'cod')->get();

            $porCodigo = []; $fueraPorCodigo = []; $semanas = [];
            foreach ($porDia as $r) {
                $porCodigo[$r->cod] = ($porCodigo[$r->cod] ?? 0) + (int) $r->n;
                $fueraPorCodigo[$r->cod] = ($fueraPorCodigo[$r->cod] ?? 0) + (int) $r->fuera;
                $sem = Carbon::parse($r->dia)->startOfWeek()->toDateString();
                $fam = $T::familia($r->cod);
                $semanas[$sem][$fam] = ($semanas[$sem][$fam] ?? 0) + (int) $r->n;
            }
            ksort($semanas);

            $consultas = array_sum($porCodigo) - ($porCodigo['IGNORAR'] ?? 0);
            $codigos = [];
            foreach ($porCodigo as $cod => $n) {
                $codigos[] = [
                    'codigo'   => $cod,
                    'etiqueta' => $T::etiqueta($cod),
                    'familia'  => $T::familia($cod),
                    'n'        => $n,
                    'pct'      => $cod === 'IGNORAR' || !$consultas ? null : round(100 * $n / $consultas, 1),
                    'pct_fuera'=> $n ? round(100 * ($fueraPorCodigo[$cod] ?? 0) / $n, 1) : 0,
                ];
            }
            usort($codigos, fn($a, $b) => $b['n'] <=> $a['n']);

            $familias = [];
            foreach ($codigos as $c) {
                $f = &$familias[$c['familia']];
                $f['n'] = ($f['n'] ?? 0) + $c['n'];
                $f['fuera'] = ($f['fuera'] ?? 0) + (int) round($c['n'] * $c['pct_fuera'] / 100);
                unset($f);
            }
            $famOut = [];
            foreach ($T::FAMILIAS as $k => [$label, $claro, $oscuro]) {
                if (!isset($familias[$k])) continue;
                $n = $familias[$k]['n'];
                $famOut[] = ['familia' => $k, 'label' => $label, 'color' => $claro, 'color_oscuro' => $oscuro, 'n' => $n,
                    'pct' => $k === 'ruido' || !$consultas ? null : round(100 * $n / $consultas, 1),
                    'pct_fuera' => $n ? round(100 * $familias[$k]['fuera'] / $n, 1) : 0];
            }
            // Más frecuente primero; los saludos siempre al final.
            usort($famOut, fn($a, $b) => [$a['familia'] === 'ruido', -$a['n']] <=> [$b['familia'] === 'ruido', -$b['n']]);

            // Por área × familia.
            $porArea = $base()->selectRaw("area, {$codigoSql} cod, COUNT(*) n")->groupBy('area', 'cod')->get();
            $areas = [];
            foreach ($porArea as $r) {
                $fam = $T::familia($r->cod);
                $areas[$r->area][$fam] = ($areas[$r->area][$fam] ?? 0) + (int) $r->n;
            }

            // Tiempo hasta la primera respuesta humana, por familia (solo las
            // clasificaciones asociadas a su conversación: las derivadas en la
            // historia importada, todas desde el 28/09). El bot está en modo
            // prueba: todo saliente es de una persona.
            $conResp = $base()->whereNotNull('conversacion_id')->where('codigo', '!=', 'IGNORAR')
                ->selectRaw("{$codigoSql} cod, created_at,
                    (SELECT MIN(m.created_at) FROM mensajes_wa m WHERE m.conversacion_id = clasificaciones_wa.conversacion_id
                        AND m.direccion = 'saliente' AND m.created_at > clasificaciones_wa.created_at) primera")
                ->limit(20000)->get();
            $esperas = [];
            foreach ($conResp as $r) {
                $fam = $T::familia($r->cod);
                $seg = $r->primera ? Carbon::parse($r->created_at)->diffInSeconds(Carbon::parse($r->primera)) : null;
                $esperas[$fam][] = ($seg !== null && $seg <= 48 * 3600) ? (int) $seg : null;
            }
            $tiempos = [];
            foreach ($esperas as $fam => $s) {
                $validos = array_filter($s, fn($x) => $x !== null);
                $tiempos[] = [
                    'familia'    => $fam,
                    'label'      => $T::FAMILIAS[$fam][0] ?? $fam,
                    'casos'      => count($s),
                    'mediana_min'=> ($m = $this->mediana($s)) !== null ? round($m / 60) : null,
                    'pct_1h'     => $this->pctEnMenos($s, 3600),
                    'sin_resp_48h' => count($s) ? round(100 * (count($s) - count($validos)) / count($s), 1) : 0,
                ];
            }
            usort($tiempos, fn($a, $b) => $b['casos'] <=> $a['casos']);

            // Calidad del clasificador.
            $q = $base()->selectRaw("COUNT(*) total, SUM(confianza = 'baja') baja, SUM(sin_ia = 1) sin_ia,
                SUM(codigo = 'FALLBACK' AND sin_ia = 0) fallback,
                SUM(codigo_corregido IS NOT NULL) revisadas, SUM(codigo_corregido IS NOT NULL AND codigo_corregido = codigo) acertadas")->first();
            $total = (int) $q->total;

            return [
                'from' => $from->toDateString(), 'to' => $to->toDateString(),
                'total' => $total, 'consultas' => $consultas, 'ruido' => $porCodigo['IGNORAR'] ?? 0,
                'familias' => $famOut, 'codigos' => $codigos,
                'semanas' => $semanas, 'areas' => $areas, 'tiempos' => $tiempos,
                'area_labels' => ConversacionWA::AREAS,
                'fam_labels' => collect($T::FAMILIAS)->map(fn($v) => ['label' => $v[0], 'color' => $v[1], 'color_oscuro' => $v[2]]),
                'calidad' => [
                    'pct_baja'     => $total ? round(100 * $q->baja / $total, 1) : 0,
                    'sin_ia'       => (int) $q->sin_ia,
                    'fallback'     => (int) $q->fallback,
                    'revisadas'    => (int) $q->revisadas,
                    'pct_acierto'  => $q->revisadas ? round(100 * $q->acertadas / $q->revisadas, 1) : null,
                ],
            ];
        });

        return response()->json(['ok' => true] + $data);
    }

    /** Clasificaciones recientes de un código o familia, para revisar y corregir. */
    public function tiposDetalle(Request $request): JsonResponse
    {
        [$from, $to] = $this->rango($request, 30);
        $T = \App\Support\TiposConsulta::class;
        $codigos = $request->filled('codigo') ? [$request->input('codigo')]
            : array_keys(array_filter($T::CODIGOS, fn($v) => $v[1] === $request->input('familia')));

        $filas = \App\Models\ClasificacionWA::with('conversacion:id,nombre,contacto,area,resumen_llm')
            ->whereBetween('created_at', [$from, $to])
            ->when($request->filled('area'), fn($q) => $q->where('area', $request->input('area')))
            ->whereIn(DB::raw('COALESCE(codigo_corregido, codigo)'), $codigos)
            ->whereNotNull('conversacion_id')
            ->when($request->boolean('sin_revisar'), fn($q) => $q->whereNull('codigo_corregido'))
            ->orderByDesc('created_at')->limit(40)->get();

        return response()->json(['ok' => true, 'filas' => $filas->map(fn($c) => [
            'id'        => $c->id,
            'fecha'     => $c->created_at->timezone(self::TZ)->format('d/m H:i'),
            'area'      => $c->area,
            'codigo'    => $c->codigo,
            'corregido' => $c->codigo_corregido,
            'confianza' => $c->confianza,
            'conv_id'   => $c->conversacion_id,
            'nombre'    => $c->conversacion?->nombre ?: $c->conversacion?->contacto,
            'resumen'   => $c->resumen ?: $c->conversacion?->resumen_llm,
            'origen'    => $c->origen,
        ]), 'codigos' => collect($T::CODIGOS)->map(fn($v) => $v[0])]);
    }

    /** La supervisora confirma (mismo código) o corrige la etiqueta de la IA. */
    public function corregirTipo(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['codigo' => 'required|string|in:' . implode(',', array_keys(\App\Support\TiposConsulta::CODIGOS))]);
        \App\Models\ClasificacionWA::findOrFail($id)->update([
            'codigo_corregido' => $data['codigo'],
            'corregido_por'    => auth()->id(),
            'corregido_at'     => now(),
        ]);
        // Los reportes cachean 5 min: cambiar la versión de la clave hace que la
        // corrección se vea en el acto (flush no: borraría toda la caché, incluido
        // el token de la cadena de una difusión en curso).
        Cache::forever('stats.tipos.ver', (int) Cache::get('stats.tipos.ver', 1) + 1);
        return response()->json(['ok' => true]);
    }

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
