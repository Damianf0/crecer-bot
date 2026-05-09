<?php

namespace App\Http\Controllers;

use App\Models\ConversacionWA;
use App\Models\Tarea;
use App\Models\TareaComentario;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TareaController extends Controller
{
    public function data(Request $request): JsonResponse
    {
        $uid    = Auth::id();
        $filtro = $request->input('filtro', 'mias');   // mias | todas
        $estado = $request->input('estado', 'activas'); // activas | completadas | todas

        $q = Tarea::with([
            'asignadaA:id,nombre_completo',
            'creadaPor:id,nombre_completo',
            'comentarios.user:id,nombre_completo',
        ]);

        if ($filtro === 'mias') {
            $q->where(fn($q) => $q->where('asignada_a', $uid)->orWhere('creada_por', $uid));
        }

        match ($estado) {
            'activas'     => $q->where('estado', '!=', 'completada'),
            'completadas' => $q->where('estado', 'completada'),
            default       => null,
        };

        $tareas = $q->orderByDesc('created_at')->get()->map(fn($t) => $this->mapTarea($t));

        return response()->json(['ok' => true, 'data' => $tareas]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'titulo'      => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'asignada_a'  => 'nullable|exists:users,id',
            'vence_at'    => 'nullable|date',
            'prioridad'   => 'nullable|in:baja,normal,alta',
            'ref_tipo'    => 'nullable|in:wa,bot',
            'ref_id'      => 'nullable|integer',
        ]);

        $tarea = Tarea::create([
            ...$data,
            'creada_por' => Auth::id(),
            'estado'     => 'pendiente',
            'prioridad'  => $data['prioridad'] ?? 'normal',
        ]);

        $tarea->load(['asignadaA:id,nombre_completo', 'creadaPor:id,nombre_completo', 'comentarios']);

        return response()->json(['ok' => true, 'data' => $this->mapTarea($tarea)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tarea = Tarea::findOrFail($id);

        $data = $request->validate([
            'titulo'      => 'sometimes|required|string|max:255',
            'descripcion' => 'nullable|string',
            'asignada_a'  => 'nullable|exists:users,id',
            'vence_at'    => 'nullable|date',
            'prioridad'   => 'nullable|in:baja,normal,alta',
            'estado'      => 'nullable|in:pendiente,en_progreso,completada',
            'ref_tipo'    => 'nullable|in:wa,bot',
            'ref_id'      => 'nullable|integer',
        ]);

        $tarea->update($data);

        return response()->json(['ok' => true]);
    }

    public function destroy(int $id): JsonResponse
    {
        Tarea::findOrFail($id)->delete();
        return response()->json(['ok' => true]);
    }

    public function comentar(Request $request, int $id): JsonResponse
    {
        $data  = $request->validate(['contenido' => 'required|string|max:2000']);
        $tarea = Tarea::findOrFail($id);

        $comentario = $tarea->comentarios()->create([
            'user_id'   => Auth::id(),
            'contenido' => $data['contenido'],
        ]);

        return response()->json(['ok' => true, 'data' => [
            'id'        => $comentario->id,
            'contenido' => $comentario->contenido,
            'usuario'   => Auth::user()->nombre_completo,
            'user_id'   => Auth::id(),
            'hora'      => now()->format('H:i'),
            'hace'      => 'ahora',
        ]]);
    }

    public function eliminarComentario(int $id): JsonResponse
    {
        TareaComentario::where('id', $id)->where('user_id', Auth::id())->firstOrFail()->delete();
        return response()->json(['ok' => true]);
    }

    private function mapTarea(Tarea $t): array
    {
        return [
            'id'              => $t->id,
            'titulo'          => $t->titulo,
            'descripcion'     => $t->descripcion,
            'asignada_a'      => $t->asignada_a,
            'asignado_nombre' => $t->asignadaA?->nombre_completo,
            'creada_por'      => $t->creada_por,
            'creado_nombre'   => $t->creadaPor?->nombre_completo,
            'vence_at'        => $t->vence_at?->format('Y-m-d\TH:i'),
            'vence_fmt'       => $t->vence_at?->format('d/m/Y H:i'),
            'vencida'         => $t->vencida,
            'estado'          => $t->estado,
            'prioridad'       => $t->prioridad ?? 'normal',
            'ref_tipo'        => $t->ref_tipo,
            'ref_id'          => $t->ref_id,
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
