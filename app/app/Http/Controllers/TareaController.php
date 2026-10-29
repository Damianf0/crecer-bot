<?php

namespace App\Http\Controllers;

use App\Models\ConversacionEvento;
use App\Models\ConversacionWA;
use App\Models\MensajeWA;
use App\Models\Tarea;
use App\Models\TareaComentario;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Tareas: para una persona o, desde el 29/10, para un área (todo el equipo del
 * área la ve hasta que alguien la toma). Una tarea creada desde una
 * conversación lleva los mensajes marcados y permite contestarle al paciente
 * por el número de esa conversación, sin derivarla.
 */
class TareaController extends Controller
{
    private const RELACIONES = [
        'asignadaA:id,nombre_completo',
        'creadaPor:id,nombre_completo',
        'completadaPor:id,nombre_completo',
        'comentarios.user:id,nombre_completo',
        'procedimiento:id,titulo',
        'mensajes',
    ];

    public function data(Request $request): JsonResponse
    {
        $uid      = Auth::id();
        $filtro   = $request->input('filtro', 'mias');      // mias | area | asignadas | creadas | todas
        $estado   = $request->input('estado', 'activas');   // activas | completadas | todas
        $vencidas = (bool) $request->boolean('vencidas');   // si true, solo vencidas
        $misAreas = self::misAreas();

        $q = Tarea::with(self::RELACIONES)->visiblesPara(Auth::user());

        // Deep-link (?tarea_id=N, avisos): esa tarea sola, en el estado que esté.
        if ($request->filled('id')) {
            $q->where('id', (int) $request->input('id'));
        } else {
            match ($filtro) {
                // Lo mío + lo que espera a mis áreas sin que nadie lo haya tomado.
                'mias'      => $q->where(fn ($q) => $q->where('asignada_a', $uid)->orWhere('creada_por', $uid)
                                    ->orWhere(fn ($q) => $q->whereNull('asignada_a')->whereIn('area', $misAreas ?: ['-']))),
                'area'      => $q->whereIn('area', $misAreas ?: ['-']),
                'asignadas' => $q->where('asignada_a', $uid),
                'creadas'   => $q->where('creada_por', $uid),
                default     => null, // todas
            };

            match ($estado) {
                'activas'     => $q->where('estado', '!=', 'completada'),
                'completadas' => $q->where('estado', 'completada'),
                default       => null,
            };

            if ($vencidas) {
                $q->where('vence_at', '<', now())->where('estado', '!=', 'completada');
            }
        }

        $tareas = $q->orderByDesc('created_at')->limit(500)->get();
        $convs  = $this->conversacionesDe($tareas);

        return response()->json([
            'ok'        => true,
            'data'      => $tareas->map(fn ($t) => $this->mapTarea($t, $convs))->values(),
            'mis_areas' => $misAreas,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'titulo'      => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'asignada_a'  => 'nullable|exists:users,id',
            'area'        => ['nullable', Rule::in(self::areasDestino())],
            'vence_at'    => 'nullable|date',
            'prioridad'   => 'nullable|in:baja,normal,alta',
            'ref_tipo'    => 'nullable|in:wa,bot',
            'ref_id'      => 'nullable|integer',
            'procedimiento_id' => 'nullable|integer|exists:procedimientos,id',
            'mensaje_ids'   => 'nullable|array|max:50',
            'mensaje_ids.*' => 'integer',
        ]);

        $conv = ($data['ref_tipo'] ?? null) === 'wa' && !empty($data['ref_id'])
            ? ConversacionWA::find($data['ref_id']) : null;
        if (!empty($data['mensaje_ids']) && !$conv) {
            return response()->json(['ok' => false, 'error' => 'Los mensajes tienen que ser de una conversación.'], 422);
        }
        if (empty($data['area']) && empty($data['asignada_a'])) {
            $data['asignada_a'] = Auth::id();
        }

        $tarea = Tarea::create([
            ...collect($data)->except('mensaje_ids')->all(),
            'creada_por' => Auth::id(),
            'estado'     => 'pendiente',
            'prioridad'  => $data['prioridad'] ?? 'normal',
        ]);

        if ($conv && !empty($data['mensaje_ids'])) {
            $ids = MensajeWA::where('conversacion_id', $conv->id)->whereIn('id', $data['mensaje_ids'])->pluck('id');
            $tarea->mensajes()->sync($ids);
        }

        if ($conv && $tarea->area) {
            $this->eventoConv($conv->id, 'tarea_area', "{$tarea->area_label}: {$tarea->titulo}");
        }

        $tarea->load(self::RELACIONES);

        return response()->json(['ok' => true, 'data' => $this->mapTarea($tarea, $this->conversacionesDe(collect([$tarea])))]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tarea = Tarea::visiblesPara(Auth::user())->findOrFail($id);

        $data = $request->validate([
            'titulo'      => 'sometimes|required|string|max:255',
            'descripcion' => 'nullable|string',
            'asignada_a'  => 'nullable|exists:users,id',
            'area'        => ['nullable', Rule::in(self::areasDestino())],
            'vence_at'    => 'nullable|date',
            'prioridad'   => 'nullable|in:baja,normal,alta',
            'estado'      => 'nullable|in:pendiente,en_progreso,completada',
            'ref_tipo'    => 'nullable|in:wa,bot',
            'ref_id'      => 'nullable|integer',
            'procedimiento_id' => 'nullable|integer|exists:procedimientos,id',
            'resolucion'  => 'nullable|string|max:2000',
        ]);
        $resolucion = trim((string) ($data['resolucion'] ?? ''));
        unset($data['resolucion']);

        $seCompleta = ($data['estado'] ?? null) === 'completada' && $tarea->estado !== 'completada';
        $seReabre   = isset($data['estado']) && $data['estado'] !== 'completada' && $tarea->estado === 'completada';
        if ($seCompleta) {
            $data += ['completada_por' => Auth::id(), 'completada_at' => now()];
        } elseif ($seReabre) {
            $data += ['completada_por' => null, 'completada_at' => null];
        }

        $tarea->update($data);

        if ($resolucion !== '') {
            $tarea->comentarios()->create(['user_id' => Auth::id(), 'contenido' => $resolucion]);
        }

        // En el chat queda que el área la resolvió (y cómo, si lo escribieron).
        if (($seCompleta || $seReabre) && $tarea->area && $tarea->ref_tipo === 'wa' && $tarea->ref_id) {
            $detalle = $seCompleta
                ? "{$tarea->area_label}: {$tarea->titulo}" . ($resolucion !== '' ? " — {$resolucion}" : '')
                : "{$tarea->area_label}: {$tarea->titulo}";
            $this->eventoConv($tarea->ref_id, $seCompleta ? 'tarea_area_hecha' : 'tarea_area_reabierta', $detalle);
        }

        return response()->json(['ok' => true]);
    }

    public function destroy(int $id): JsonResponse
    {
        Tarea::visiblesPara(Auth::user())->findOrFail($id)->delete();
        return response()->json(['ok' => true]);
    }

    public function comentar(Request $request, int $id): JsonResponse
    {
        $data  = $request->validate(['contenido' => 'required|string|max:2000']);
        $tarea = Tarea::visiblesPara(Auth::user())->findOrFail($id);

        $comentario = $tarea->comentarios()->create([
            'user_id'   => Auth::id(),
            'contenido' => $data['contenido'],
        ]);
        $tarea->touch();   // los avisos de "comentaron tu tarea" miran updated_at

        return response()->json(['ok' => true, 'data' => [
            'id'        => $comentario->id,
            'contenido' => $comentario->contenido,
            'usuario'   => Auth::user()->nombre_completo,
            'user_id'   => Auth::id(),
            'hora'      => now()->format('H:i'),
            'hace'      => 'ahora',
        ]]);
    }

    /**
     * Contestarle al paciente desde la tarea. Sale por el número de la
     * conversación (no la deriva), queda en el chat marcado con la tarea y
     * como comentario de la tarea.
     */
    public function responder(Request $request, int $id): JsonResponse
    {
        $data  = $request->validate(['texto' => 'required|string|max:4000']);
        $tarea = Tarea::visiblesPara(Auth::user())->findOrFail($id);
        $conv  = $tarea->ref_tipo === 'wa' && $tarea->ref_id ? ConversacionWA::find($tarea->ref_id) : null;
        if (!$conv) {
            return response()->json(['ok' => false, 'error' => 'Esta tarea no tiene una conversación de WhatsApp.'], 422);
        }

        try {
            $resp = Http::timeout(10)->withToken(config('app.bot_ingress_token'))
                ->post($conv->botUrl() . '/enviar', ['contacto' => $conv->contacto, 'texto' => $data['texto']]);
            if (!$resp->ok() || $resp->json('ok') !== true) {
                return response()->json(['ok' => false, 'error' => 'El bot no pudo enviar el mensaje. Verificá que esté conectado a WhatsApp.'], 502);
            }
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'error' => 'No se pudo contactar al bot. Reintentá en unos segundos.'], 502);
        }

        MensajeWA::create([
            'conversacion_id' => $conv->id,
            'direccion'       => 'saliente',
            'tipo'            => 'texto',
            'contenido'       => $data['texto'],
            'wa_id'           => $resp->json('wa_id'),
            'usuario_id'      => Auth::id(),
            'leido'           => true,
            'tarea_id'        => $tarea->id,
        ]);
        $conv->update(['ultima_actividad' => now()]);
        $tarea->comentarios()->create(['user_id' => Auth::id(), 'contenido' => "📲 Le respondió al paciente: {$data['texto']}"]);
        $tarea->touch();

        $numero = ConversacionWA::areas()[$conv->area] ?? $conv->area;
        return response()->json(['ok' => true, 'numero' => $numero]);
    }

    public function eliminarComentario(int $id): JsonResponse
    {
        TareaComentario::where('id', $id)->where('user_id', Auth::id())->firstOrFail()->delete();
        return response()->json(['ok' => true]);
    }

    /** Áreas a las que se le puede pedir una tarea: las que la persona ve, sin Difusiones. */
    public static function areasDestino(): array
    {
        return array_values(array_diff(array_keys(ConversacionWA::areasPara(Auth::user())), [ConversacionWA::AREA_DIFUSION]));
    }

    /** Las áreas que la persona atiende hoy (colas elegidas al entrar). */
    public static function misAreas(): array
    {
        return array_values(array_diff(ConversacionWA::areasDeLaSesion(), [ConversacionWA::AREA_DIFUSION]));
    }

    private function eventoConv(int $convId, string $tipo, string $detalle): void
    {
        ConversacionEvento::create([
            'conversacion_id' => $convId,
            'tipo'            => $tipo,
            'detalle'         => Str::limit($detalle, 117),
            'usuario_id'      => Auth::id(),
        ]);
        ConversacionWA::invalidarColaCache();
    }

    /** Conversaciones vinculadas, en una sola consulta. */
    private function conversacionesDe($tareas)
    {
        $ids = $tareas->where('ref_tipo', 'wa')->pluck('ref_id')->filter()->unique();
        return $ids->isEmpty() ? collect() : ConversacionWA::whereIn('id', $ids)->get()->keyBy('id');
    }

    private function mapTarea(Tarea $t, $convs): array
    {
        $user = Auth::user();
        $conv = $t->ref_tipo === 'wa' ? ($convs[$t->ref_id] ?? null) : null;

        return [
            'id'              => $t->id,
            'titulo'          => $t->titulo,
            'descripcion'     => $t->descripcion,
            'asignada_a'      => $t->asignada_a,
            'asignado_nombre' => $t->asignadaA?->nombre_completo,
            'area'            => $t->area,
            'area_label'      => $t->area_label,
            'creada_por'      => $t->creada_por,
            'creado_nombre'   => $t->creadaPor?->nombre_completo,
            'completado_nombre' => $t->completadaPor?->nombre_completo,
            'completada_fmt'  => $t->completada_at?->format('d/m/Y H:i'),
            'vence_at'        => $t->vence_at?->format('Y-m-d\TH:i'),
            'vence_fmt'       => $t->vence_at?->format('d/m/Y H:i'),
            'vencida'         => $t->vencida,
            'estado'          => $t->estado,
            'prioridad'       => $t->prioridad ?? 'normal',
            'ref_tipo'        => $t->ref_tipo,
            'ref_id'          => $t->ref_id,
            'conversacion'    => $conv ? [
                'id'         => $conv->id,
                'area'       => $conv->area,
                'area_label' => ConversacionWA::areas()[$conv->area] ?? $conv->area,
                'contacto'   => $conv->nombreOTelefono,
                'puede_ver'  => ConversacionWA::puedeVer($user, $conv->area),
            ] : null,
            'mensajes'        => $t->mensajes->map(fn ($m) => [
                'id'          => $m->id,
                'direccion'   => $m->direccion,
                'tipo'        => $m->tipo,
                'contenido'   => $m->contenido,
                'archivo_url' => $m->archivo_url,
                'fecha'       => $m->created_at->format('d/m H:i'),
            ])->values(),
            // Si el procedimiento fue eliminado, la tarea no ofrece un link muerto.
            'procedimiento_id'     => $t->procedimiento ? $t->procedimiento_id : null,
            'procedimiento_titulo' => $t->procedimiento?->titulo,
            'hace'            => $t->created_at->diffForHumans(),
            'comentarios'     => $t->comentarios->map(fn($c) => [
                'id'        => $c->id,
                'contenido' => $c->contenido,
                'usuario'   => $c->user?->nombre_completo,
                'user_id'   => $c->user_id,
                'hora'      => $c->created_at->format('H:i d/m'),
                'hace'      => $c->created_at->diffForHumans(),
            ])->toArray(),
        ];
    }
}
