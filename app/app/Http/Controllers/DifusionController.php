<?php

namespace App\Http\Controllers;

use App\Jobs\DespacharDifusion;
use App\Models\Contacto;
use App\Models\DifusionBaja;
use App\Models\DifusionCampania;
use App\Models\DifusionDestinatario;
use App\Models\DifusionPlantilla;
use App\Services\Difusion\Difusiones;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Módulo de Difusiones (/v2/difusiones): plantillas, campañas con audiencia,
 * envío controlado por DespacharDifusion, bajas y estadísticas por campaña.
 */
class DifusionController extends Controller
{
    private const ADJ_MIMES = 'jpg,jpeg,png,webp,pdf,mp4';
    private const ADJ_MAX_KB = 15360;

    public function index()
    {
        return view('v2.difusiones', [
            'modulo'    => 'Difusiones',
            'title'     => 'Difusiones',
            'navActive' => 'difusiones',
            'areas'     => \App\Models\ConversacionWA::AREAS,
        ]);
    }

    /** Todo lo de la pantalla principal en una llamada. */
    public function data(): JsonResponse
    {
        $campanias = DifusionCampania::with('autor:id,nombre_completo')->orderByDesc('id')->limit(100)->get();
        $metricas = DifusionCampania::metricas($campanias->pluck('id')->all());

        // Enviados hoy por canal (el tope diario es por canal).
        $hoy = DifusionDestinatario::whereDate('difusion_destinatarios.enviado_at', today())
            ->join('difusion_campanias as c', 'c.id', '=', 'difusion_destinatarios.campania_id')
            ->groupBy('c.canal')->selectRaw('c.canal, COUNT(*) n')->pluck('n', 'canal');
        $canales = array_map(fn($c) => $c + ['enviados_hoy' => (int) ($hoy[$c['clave']] ?? 0)], Difusiones::canales());

        return response()->json([
            'ok'         => true,
            'campanias'  => $campanias->map(fn($c) => $this->serCampania($c, $metricas[$c->id] ?? []))->values(),
            'plantillas' => DifusionPlantilla::orderBy('nombre')->get(['id', 'nombre', 'texto', 'adjunto_nombre', 'adjunto_mime', 'updated_at']),
            'bajas'      => DifusionBaja::orderByDesc('id')->limit(500)->get(['id', 'telefono', 'origen', 'detalle', 'created_at']),
            'canales'    => $canales,
            'canal_por_defecto' => config('difusion.canal_por_defecto'),
            'cola_activa' => (bool) config('difusion.activa'),
            'limites'    => [
                'pausa'   => [config('difusion.pausa_min_seg'), config('difusion.pausa_max_seg')],
                'tope'    => config('difusion.tope_diario'),
                'horario' => config('difusion.horario'),
            ],
            'resumen'    => $this->resumenGeneral(),
        ]);
    }

    private function serCampania(DifusionCampania $c, array $m): array
    {
        return [
            'id'              => $c->id,
            'nombre'          => $c->nombre,
            'estado'          => $c->estado,
            'estado_label'    => DifusionCampania::ESTADOS[$c->estado] ?? $c->estado,
            'proveedor'       => $c->proveedor,
            'canal'           => $c->canal,
            'canal_nombre'    => Difusiones::nombreCanal($c->canal),
            'texto'           => $c->texto,
            'adjunto_nombre'  => $c->adjunto_nombre,
            'audiencia'       => $c->audiencia,
            'programada_para' => $c->programada_para?->format('Y-m-d H:i'),
            'iniciada_at'     => $c->iniciada_at?->format('d/m H:i'),
            'terminada_at'    => $c->terminada_at?->format('d/m H:i'),
            'creada'          => $c->created_at?->format('d/m/Y H:i'),
            'autor'           => $c->autor?->nombre_completo,
            'm'               => $m,
        ];
    }

    /** Totales de los últimos 90 días (tarjetas de arriba). */
    private function resumenGeneral(): array
    {
        $desde = now()->subDays(90);
        $r = DifusionDestinatario::where('enviado_at', '>=', $desde)->selectRaw("
                COUNT(*) enviados, SUM(entregado_at IS NOT NULL) entregados, SUM(leido_at IS NOT NULL) leidos,
                SUM(respondio_at IS NOT NULL) respondieron, SUM(baja_at IS NOT NULL) bajas")->first();
        $env = (int) ($r->enviados ?? 0);
        $pct = fn($n) => $env ? round(100 * (int) $n / $env, 1) : null;
        return [
            'campanias'      => DifusionCampania::where('created_at', '>=', $desde)->whereNotIn('estado', ['borrador', 'cancelada'])->count(),
            'enviados'       => $env,
            'tasa_entrega'   => $pct($r->entregados ?? 0),
            'tasa_lectura'   => $pct($r->leidos ?? 0),
            'tasa_respuesta' => $pct($r->respondieron ?? 0),
            'bajas'          => (int) ($r->bajas ?? 0),
        ];
    }

    public function campania(int $id, Request $request): JsonResponse
    {
        $c = DifusionCampania::with('autor:id,nombre_completo')->findOrFail($id);
        $m = DifusionCampania::metricas([$c->id])[$c->id];

        $q = $c->destinatarios()->orderBy('id');
        $filtro = $request->query('estado');
        if ($filtro === 'respondieron') $q->whereNotNull('respondio_at');
        elseif ($filtro === 'bajas')    $q->whereNotNull('baja_at');
        elseif ($filtro)                $q->where('estado', $filtro);
        $dest = $q->paginate(50, ['id', 'telefono', 'nombre', 'estado', 'error', 'enviado_at', 'entregado_at', 'leido_at', 'respondio_at', 'baja_at']);

        // Lecturas y respuestas por hora desde el inicio (curva de la campaña).
        $curva = [];
        if ($c->iniciada_at) {
            $curva = DifusionDestinatario::where('campania_id', $c->id)->whereNotNull('enviado_at')
                ->get(['enviado_at', 'leido_at', 'respondio_at'])
                ->reduce(function ($acc, $d) use ($c) {
                    foreach (['enviados' => $d->enviado_at, 'leidos' => $d->leido_at, 'respuestas' => $d->respondio_at] as $k => $t) {
                        if (!$t) continue;
                        $h = max(0, (int) floor($c->iniciada_at->diffInMinutes($t) / 60));
                        $acc[$h][$k] = ($acc[$h][$k] ?? 0) + 1;
                    }
                    return $acc;
                }, []);
            ksort($curva);
        }

        return response()->json([
            'ok'       => true,
            'campania' => $this->serCampania($c, $m),
            'destinatarios' => $dest,
            'curva'    => $curva,
        ]);
    }

    // ── Audiencia ─────────────────────────────────────────────────────

    private function validarAudiencia(Request $r): array
    {
        return $r->validate([
            'audiencia.origen'                 => 'required|in:contactos,lista',
            'audiencia.lista'                  => 'nullable|string|max:200000',
            'audiencia.area'                   => 'nullable|in:' . implode(',', array_keys(\App\Models\ConversacionWA::AREAS)),
            'audiencia.solo_con_conversacion'  => 'nullable|boolean',
            'audiencia.activos_dias'           => 'nullable|integer|min:1|max:3650',
            'audiencia.inactivos_dias'         => 'nullable|integer|min:1|max:3650',
            'audiencia.excluir_recientes_dias' => 'nullable|integer|min:1|max:365',
        ])['audiencia'];
    }

    public function previewAudiencia(Request $request): JsonResponse
    {
        $f = $this->validarAudiencia($request);
        $a = Difusiones::armarAudiencia($f);
        $muestra = array_map(fn($d) => [
            'nombre'   => $d['nombre'],
            'telefono' => substr($d['telefono'], 0, -4) . '····',
        ], array_slice($a['destinatarios'], 0, 8));
        return response()->json(['ok' => true, 'total' => count($a['destinatarios']), 'descartados' => $a['descartados'], 'muestra' => $muestra]);
    }

    // ── Campañas ──────────────────────────────────────────────────────

    public function crearCampania(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre'          => 'required|string|max:160',
            'texto'           => 'required|string|max:4000',
            'plantilla_id'    => 'nullable|integer|exists:difusion_plantillas,id',
            'usar_adjunto_plantilla' => 'nullable|boolean',
            'adjunto'         => 'nullable|file|mimes:' . self::ADJ_MIMES . '|max:' . self::ADJ_MAX_KB,
            'programada_para' => 'nullable|date|after:now',
            'accion'          => 'required|in:borrador,enviar',
            'canal'           => 'required|in:' . implode(',', config('difusion.canales_habilitados')),
        ]);
        $inmediata = $data['accion'] === 'enviar' && empty($data['programada_para']);
        if ($inmediata && ($err = $this->canalNoListo($data['canal']))) {
            return response()->json(['ok' => false, 'error' => $err], 422);
        }
        // El formulario viaja como multipart (por el adjunto): la audiencia va en JSON.
        $request->merge(['audiencia' => json_decode((string) $request->input('audiencia_json'), true) ?: []]);
        $filtros = $this->validarAudiencia($request);
        $aud = Difusiones::armarAudiencia($filtros);
        if (!$aud['destinatarios']) {
            return response()->json(['ok' => false, 'error' => 'La audiencia quedó vacía.'], 422);
        }

        [$adjPath, $adjNombre, $adjMime] = [null, null, null];
        if ($request->hasFile('adjunto')) {
            $f = $request->file('adjunto');
            $adjPath = $f->store('difusion');
            [$adjNombre, $adjMime] = [$f->getClientOriginalName(), $f->getMimeType()];
        } elseif (!empty($data['plantilla_id']) && $request->boolean('usar_adjunto_plantilla')) {
            $p = DifusionPlantilla::find($data['plantilla_id']);
            if ($p?->adjunto_path) {
                // Copia: borrar o cambiar la plantilla no rompe la campaña.
                $adjPath = 'difusion/' . uniqid('c', true) . '.' . pathinfo($p->adjunto_path, PATHINFO_EXTENSION);
                Storage::copy($p->adjunto_path, $adjPath);
                [$adjNombre, $adjMime] = [$p->adjunto_nombre, $p->adjunto_mime];
            }
        }

        $c = DB::transaction(function () use ($data, $filtros, $aud, $adjPath, $adjNombre, $adjMime) {
            $c = DifusionCampania::create([
                'nombre'         => $data['nombre'],
                'plantilla_id'   => $data['plantilla_id'] ?? null,
                'texto'          => $data['texto'],
                'adjunto_path'   => $adjPath,
                'adjunto_nombre' => $adjNombre,
                'adjunto_mime'   => $adjMime,
                'audiencia'      => array_merge($filtros, ['lista' => isset($filtros['lista']) ? '(' . count($aud['destinatarios']) . ' números)' : null]),
                'proveedor'      => config("difusion.canales.{$data['canal']}.tipo"),
                'canal'          => $data['canal'],
                'estado'         => 'borrador',
                'programada_para'=> $data['programada_para'] ?? null,
                'creado_por'     => Auth::id(),
            ]);
            $ahora = now();
            foreach (array_chunk($aud['destinatarios'], 500) as $lote) {
                DifusionDestinatario::insert(array_map(fn($d) => $d + [
                    'campania_id' => $c->id, 'estado' => 'pendiente', 'created_at' => $ahora, 'updated_at' => $ahora,
                ], $lote));
            }
            return $c;
        });

        if ($data['accion'] === 'enviar') $this->lanzar($c);

        return response()->json(['ok' => true, 'id' => $c->id]);
    }

    /** null si el canal puede mandar ahora; si no, el motivo para mostrar. */
    private function canalNoListo(string $canal): ?string
    {
        $e = Difusiones::canal($canal)->estado();
        return $e['ok'] ? null : Difusiones::nombreCanal($canal) . ' no está listo: ' . $e['detalle'];
    }

    /**
     * Cambiar el canal de un borrador o de una campaña pausada (p. ej. el
     * número se desconectó a mitad de envío: los pendientes salen por otro).
     */
    public function cambiarCanal(int $id, Request $request): JsonResponse
    {
        $c = DifusionCampania::findOrFail($id);
        $data = $request->validate(['canal' => 'required|in:' . implode(',', config('difusion.canales_habilitados'))]);
        if (!in_array($c->estado, ['borrador', 'pausada', 'programada'], true)) {
            return response()->json(['ok' => false, 'error' => 'Solo se puede cambiar el canal de un borrador, una campaña programada o una pausada.'], 422);
        }
        $c->update(['canal' => $data['canal'], 'proveedor' => config("difusion.canales.{$data['canal']}.tipo")]);
        return response()->json(['ok' => true]);
    }

    private function lanzar(DifusionCampania $c): void
    {
        if ($c->programada_para && $c->programada_para->isFuture()) {
            $c->update(['estado' => 'programada']);
            DespacharDifusion::arrancar($c, $c->programada_para);
        } else {
            $c->update(['estado' => 'enviando', 'iniciada_at' => $c->iniciada_at ?? now()]);
            DespacharDifusion::arrancar($c);
        }
    }

    public function accion(int $id, string $accion): JsonResponse
    {
        $c = DifusionCampania::findOrFail($id);
        $permitidas = [
            'iniciar'  => ['borrador'],
            'pausar'   => ['enviando', 'programada'],
            'reanudar' => ['pausada'],
            'cancelar' => ['borrador', 'programada', 'enviando', 'pausada'],
        ];
        abort_unless(isset($permitidas[$accion]), 404);
        if (!in_array($c->estado, $permitidas[$accion], true)) {
            return response()->json(['ok' => false, 'error' => "No se puede {$accion} una campaña en estado " . mb_strtolower(DifusionCampania::ESTADOS[$c->estado] ?? $c->estado) . '.'], 422);
        }

        $programadaFutura = $c->programada_para && $c->programada_para->isFuture();
        if (in_array($accion, ['iniciar', 'reanudar'], true) && !$programadaFutura && ($err = $this->canalNoListo($c->canal))) {
            return response()->json(['ok' => false, 'error' => $err], 422);
        }

        match ($accion) {
            'iniciar', 'reanudar' => $this->lanzar($c),
            'pausar'   => $c->update(['estado' => 'pausada']),
            'cancelar' => (function () use ($c) {
                $c->update(['estado' => 'cancelada', 'terminada_at' => now()]);
                $c->destinatarios()->where('estado', 'pendiente')->update(['estado' => 'omitido', 'error' => 'Campaña cancelada']);
            })(),
        };
        return response()->json(['ok' => true, 'estado' => $c->fresh()->estado]);
    }

    public function eliminar(int $id): JsonResponse
    {
        $c = DifusionCampania::findOrFail($id);
        if ($c->estado !== 'borrador') {
            return response()->json(['ok' => false, 'error' => 'Solo se pueden borrar borradores (el resto queda como registro).'], 422);
        }
        if ($c->adjunto_path) Storage::delete($c->adjunto_path);
        $c->delete();
        return response()->json(['ok' => true]);
    }

    /** Manda la campaña a UN número (el propio) para ver cómo llega. */
    public function prueba(int $id, Request $request): JsonResponse
    {
        $c = DifusionCampania::findOrFail($id);
        $tel = Contacto::normalizarTelefono((string) $request->input('telefono'));
        if ($tel === '') return response()->json(['ok' => false, 'error' => 'Número inválido.'], 422);

        $adjunto = $c->adjunto_path ? ['path' => Storage::path($c->adjunto_path), 'nombre' => $c->adjunto_nombre, 'mime' => $c->adjunto_mime] : null;
        $r = Difusiones::canal($c->canal)->enviar($tel, $c->textoPara(Auth::user()->nombre_completo), $adjunto);
        return $r->tipo === 'enviado'
            ? response()->json(['ok' => true])
            : response()->json(['ok' => false, 'error' => $r->error], 422);
    }

    // ── Plantillas ────────────────────────────────────────────────────

    public function guardarPlantilla(Request $request, ?int $id = null): JsonResponse
    {
        $data = $request->validate([
            'nombre'        => 'required|string|max:120',
            'texto'         => 'required|string|max:4000',
            'adjunto'       => 'nullable|file|mimes:' . self::ADJ_MIMES . '|max:' . self::ADJ_MAX_KB,
            'quitar_adjunto'=> 'nullable|boolean',
        ]);
        $p = $id ? DifusionPlantilla::findOrFail($id) : new DifusionPlantilla(['creado_por' => Auth::id()]);
        $p->fill(['nombre' => $data['nombre'], 'texto' => $data['texto']]);

        if ($request->hasFile('adjunto') || $request->boolean('quitar_adjunto')) {
            if ($p->adjunto_path) Storage::delete($p->adjunto_path);
            $p->fill(['adjunto_path' => null, 'adjunto_nombre' => null, 'adjunto_mime' => null]);
        }
        if ($request->hasFile('adjunto')) {
            $f = $request->file('adjunto');
            $p->fill(['adjunto_path' => $f->store('difusion'), 'adjunto_nombre' => $f->getClientOriginalName(), 'adjunto_mime' => $f->getMimeType()]);
        }
        $p->save();
        return response()->json(['ok' => true, 'id' => $p->id]);
    }

    public function eliminarPlantilla(int $id): JsonResponse
    {
        $p = DifusionPlantilla::findOrFail($id);
        if ($p->adjunto_path) Storage::delete($p->adjunto_path);
        $p->delete();
        return response()->json(['ok' => true]);
    }

    public function adjunto(string $tipo, int $id)
    {
        $m = $tipo === 'plantilla' ? DifusionPlantilla::findOrFail($id) : DifusionCampania::findOrFail($id);
        abort_unless($m->adjunto_path && Storage::exists($m->adjunto_path), 404);
        return Storage::response($m->adjunto_path, $m->adjunto_nombre);
    }

    // ── Bajas ─────────────────────────────────────────────────────────

    public function agregarBaja(Request $request): JsonResponse
    {
        $data = $request->validate(['telefono' => 'required|string|max:40', 'detalle' => 'nullable|string|max:255']);
        $tel = Contacto::normalizarTelefono($data['telefono']);
        if ($tel === '') return response()->json(['ok' => false, 'error' => 'Número inválido.'], 422);
        DifusionBaja::firstOrCreate(['telefono' => $tel], [
            'contacto_id' => Contacto::where('telefono', $tel)->value('id'),
            'origen'      => 'manual',
            'detalle'     => $data['detalle'] ?? null,
            'creado_por'  => Auth::id(),
        ]);
        return response()->json(['ok' => true]);
    }

    public function quitarBaja(int $id): JsonResponse
    {
        DifusionBaja::findOrFail($id)->delete();
        return response()->json(['ok' => true]);
    }
}
