<?php

namespace App\Http\Controllers;

use App\Models\Tarea;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AgendaController extends Controller
{
    public function index()
    {
        $usuarios = User::where('activo', true)->orderBy('nombre_completo')->get(['id', 'nombre_completo']);
        return view('agenda.index', compact('usuarios'));
    }

    public function data(Request $request): JsonResponse
    {
        $estado   = $request->input('estado', 'pendiente');   // pendiente | completada | todas
        $asig     = $request->input('asig');                   // user id o 'mias'
        $desde    = $request->input('desde');
        $hasta    = $request->input('hasta');

        $q = Tarea::with(['asignadaA:id,nombre_completo', 'creadaPor:id,nombre_completo'])
            ->orderByRaw("FIELD(prioridad,'alta','normal','baja')")
            ->orderBy('vence_at')
            ->orderBy('created_at');

        if ($estado === 'pendiente') {
            $q->where('estado', '!=', 'completada');
        } elseif ($estado === 'completada') {
            $q->where('estado', 'completada');
        }

        if ($asig === 'mias') {
            $q->where('asignada_a', Auth::id());
        } elseif ($asig) {
            $q->where('asignada_a', $asig);
        }

        if ($desde) $q->where(fn($s) => $s->whereNull('vence_at')->orWhere('vence_at', '>=', $desde . ' 00:00:00'));
        if ($hasta) $q->where(fn($s) => $s->whereNull('vence_at')->orWhere('vence_at', '<=', $hasta . ' 23:59:59'));

        $tareas = $q->limit(200)->get()->map(fn($t) => $this->mapTarea($t));

        return response()->json(['ok' => true, 'data' => $tareas]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'titulo'      => 'required|string|max:200',
            'descripcion' => 'nullable|string|max:2000',
            'asignada_a'  => 'nullable|integer|exists:users,id',
            'vence_at'    => 'nullable|date',
            'prioridad'   => 'in:baja,normal,alta',
            'ref_tipo'    => 'nullable|in:bot,wa',
            'ref_id'      => 'nullable|integer',
        ]);

        $tarea = Tarea::create([
            ...$data,
            'creada_por' => Auth::id(),
            'estado'     => 'pendiente',
            'prioridad'  => $data['prioridad'] ?? 'normal',
        ]);

        $tarea->load(['asignadaA:id,nombre_completo', 'creadaPor:id,nombre_completo']);
        return response()->json(['ok' => true, 'tarea' => $this->mapTarea($tarea)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tarea = Tarea::findOrFail($id);

        $data = $request->validate([
            'titulo'      => 'sometimes|string|max:200',
            'descripcion' => 'nullable|string|max:2000',
            'asignada_a'  => 'nullable|integer|exists:users,id',
            'vence_at'    => 'nullable|date',
            'prioridad'   => 'sometimes|in:baja,normal,alta',
            'estado'      => 'sometimes|in:pendiente,en_progreso,completada',
        ]);

        $tarea->update($data);
        $tarea->load(['asignadaA:id,nombre_completo', 'creadaPor:id,nombre_completo']);
        return response()->json(['ok' => true, 'tarea' => $this->mapTarea($tarea)]);
    }

    public function destroy(int $id): JsonResponse
    {
        Tarea::findOrFail($id)->delete();
        return response()->json(['ok' => true]);
    }

    private function mapTarea(Tarea $t): array
    {
        $vence = $t->vence_at;
        $vencida = $vence && $vence->isPast() && $t->estado !== 'completada';
        return [
            'id'          => $t->id,
            'titulo'      => $t->titulo,
            'descripcion' => $t->descripcion,
            'estado'      => $t->estado,
            'prioridad'   => $t->prioridad,
            'asig_id'     => $t->asignada_a,
            'asig_name'   => $t->asignadaA?->nombre_completo,
            'creada_por'  => $t->creadaPor?->nombre_completo,
            'vence_at'    => $vence?->format('d/m/Y H:i'),
            'vence_iso'   => $vence?->format('Y-m-d\TH:i'),
            'vencida'     => $vencida,
            'ref_tipo'    => $t->ref_tipo,
            'ref_id'      => $t->ref_id,
            'hace'        => $t->created_at?->diffForHumans(),
        ];
    }
}
