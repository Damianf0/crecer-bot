<?php

namespace App\Http\Controllers;

use App\Models\ColaAtencion;
use App\Models\Medico;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class MedicoController extends Controller
{
    /** Resuelve el médico actual desde el user logueado. Null si no está vinculado. */
    private function medicoActual(): ?Medico
    {
        $u = Auth::user();
        if (!$u || !$u->medico_id) return null;
        return Medico::find($u->medico_id);
    }

    /** Pacientes que el médico ve en sala. Comparte la lógica entre /medico (vista) y /medico/data (polling). */
    private function pacientesEnSala(Medico $m)
    {
        // El médico ve los pacientes que:
        //  - tienen `cola_atencion.profesional` == nombre del médico (case-insensitive)
        //  - están en estado `liberado` (la secretaria ya hizo check-in) o `en_atencion`
        //    si el médico quiere ver los que todavía no terminó la secretaria.
        // Por ahora solo `liberado` (que está literalmente "en sala esperándolo").
        return ColaAtencion::query()
            ->where('estado', 'liberado')
            ->whereNull('llamado_consultorio_at')
            ->whereRaw('LOWER(profesional) = ?', [mb_strtolower($m->nombre_completo)])
            ->orderBy('hora_liberado')
            ->get()
            ->map(fn ($p) => $this->mapPaciente($p));
    }

    /** Pacientes que YA llamó el médico al consultorio (en consulta o atendidos hoy). */
    private function pacientesLlamados(Medico $m)
    {
        return ColaAtencion::query()
            ->whereNotNull('llamado_consultorio_at')
            ->whereDate('llamado_consultorio_at', today())
            ->whereRaw('LOWER(profesional) = ?', [mb_strtolower($m->nombre_completo)])
            ->orderByDesc('llamado_consultorio_at')
            ->limit(10)
            ->get()
            ->map(fn ($p) => $this->mapPaciente($p));
    }

    private function mapPaciente(ColaAtencion $p): array
    {
        return [
            'id'            => $p->id,
            'dni'           => $p->dni,
            'nombre'        => $p->nombre_completo,
            'obra_social'   => $p->obra_social,
            'plan'          => $p->plan,
            'practica'      => $p->practica,
            'turno_hora'    => $p->turno_hora ? substr($p->turno_hora, 0, 5) : null,
            'planta'        => $p->planta,
            'consultorio'   => $p->consultorio,
            'motivo'        => $p->motivo,
            'primera_vez'   => $p->primera_vez,
            'sin_turno'     => $p->sin_turno,
            'derivado_bot'  => $p->derivado_bot,
            'estado'        => $p->estado,
            'hora_llegada'  => $p->hora_llegada?->format('H:i'),
            'hora_liberado' => $p->hora_liberado?->format('H:i'),
            'minutos_espera'=> $p->hora_llegada ? (int) $p->hora_llegada->diffInMinutes(now()) : null,
            'llamado_at'    => $p->llamado_consultorio_at?->format('H:i'),
            'atendido_at'   => $p->atendido_at?->format('H:i'),
            'nota'          => $p->nota,
            'checklist'     => $p->checklist,
        ];
    }

    // ── Vista ─────────────────────────────────────────────────────────

    public function index()
    {
        $m = $this->medicoActual();
        if (!$m) {
            abort(403, 'Tu usuario no está vinculado a un médico. Pedile a un administrador que te asocie uno.');
        }

        return view('medico.index', [
            'medico'   => $m,
            'enSala'   => $this->pacientesEnSala($m),
            'llamados' => $this->pacientesLlamados($m),
            'destinatarios' => $this->destinatariosTareas(),
            'tareasDelegadas' => $this->tareasDelegadas(),
        ]);
    }

    /** Usuarios a los que el médico puede delegar tareas (secretarias / supervisoras / admin). */
    private function destinatariosTareas()
    {
        return User::where('activo', true)
            ->where('id', '!=', Auth::id())
            ->where(function ($q) {
                $q->where('rol', '!=', 'medico')->orWhereNull('rol');
            })
            ->orderBy('nombre_completo')
            ->get(['id', 'nombre_completo', 'rol'])
            ->map(fn ($u) => [
                'id'     => $u->id,
                'nombre' => $u->nombre_completo,
                'rol'    => User::ROLES[$u->rol] ?? $u->rol,
            ]);
    }

    /** Últimas tareas que este médico creó. */
    private function tareasDelegadas()
    {
        return Tarea::where('creada_por', Auth::id())
            ->with('asignadaA:id,nombre_completo')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn ($t) => [
                'id'         => $t->id,
                'titulo'     => $t->titulo,
                'descripcion' => $t->descripcion ? Str::limit($t->descripcion, 100) : null,
                'asignada_a_nombre' => $t->asignadaA?->nombre_completo,
                'estado'     => $t->estado,
                'prioridad'  => $t->prioridad,
                'vence_at'   => $t->vence_at?->format('d/m H:i'),
                'creada'     => $t->created_at?->diffForHumans(),
                'completada' => $t->estado === 'completada',
                'vencida'    => $t->vencida,
            ]);
    }

    // ── Endpoints de polling ──────────────────────────────────────────

    public function data(): JsonResponse
    {
        $m = $this->medicoActual();
        if (!$m) return response()->json(['ok' => false, 'error' => 'no-vinculado'], 403);

        return response()->json([
            'ok'       => true,
            'en_sala'  => $this->pacientesEnSala($m),
            'llamados' => $this->pacientesLlamados($m),
            'tareas_delegadas' => $this->tareasDelegadas(),
        ]);
    }

    // ── Tareas que el médico delega a una secretaria ─────────────────

    public function crearTarea(Request $r): JsonResponse
    {
        $m = $this->medicoActual();
        if (!$m) return response()->json(['ok' => false, 'error' => 'no-vinculado'], 403);

        $data = $r->validate([
            'titulo'      => 'required|string|max:200',
            'descripcion' => 'nullable|string|max:2000',
            'asignada_a'  => 'required|integer|exists:users,id',
            'prioridad'   => 'required|in:baja,normal,alta',
            'vence_at'    => 'nullable|date',
        ]);

        // No permitir asignarse a sí mismo ni a otros médicos.
        $destinatario = User::find($data['asignada_a']);
        if (!$destinatario || !$destinatario->activo || $destinatario->id === Auth::id() || $destinatario->rol === 'medico') {
            return response()->json(['ok' => false, 'error' => 'destinatario-invalido'], 422);
        }

        $tarea = Tarea::create([
            'titulo'      => $data['titulo'],
            'descripcion' => $data['descripcion'] ?? null,
            'asignada_a'  => $data['asignada_a'],
            'creada_por'  => Auth::id(),
            'estado'      => 'pendiente',
            'prioridad'   => $data['prioridad'],
            'vence_at'    => $data['vence_at'] ?? null,
        ]);

        return response()->json(['ok' => true, 'tarea_id' => $tarea->id]);
    }

    // ── Acciones ──────────────────────────────────────────────────────

    public function llamar(Request $r, int $id): JsonResponse
    {
        $m = $this->medicoActual();
        if (!$m) return response()->json(['ok' => false], 403);

        $data = $r->validate([
            'consultorio' => 'nullable|integer|min:1|max:99',
        ]);

        $p = ColaAtencion::findOrFail($id);
        if (mb_strtolower($p->profesional) !== mb_strtolower($m->nombre_completo)) {
            return response()->json(['ok' => false, 'error' => 'no-asignado-a-vos'], 403);
        }

        $p->update([
            'consultorio'             => $data['consultorio'] ?? $m->consultorio ?? null,
            'llamado_consultorio_at'  => now(),
        ]);

        return response()->json(['ok' => true, 'paciente' => $this->mapPaciente($p)]);
    }

    public function rellamar(int $id): JsonResponse
    {
        $m = $this->medicoActual();
        if (!$m) return response()->json(['ok' => false], 403);

        $p = ColaAtencion::findOrFail($id);
        if (mb_strtolower($p->profesional) !== mb_strtolower($m->nombre_completo)) {
            return response()->json(['ok' => false, 'error' => 'no-asignado-a-vos'], 403);
        }

        $p->update(['llamado_consultorio_at' => now()]);
        return response()->json(['ok' => true, 'paciente' => $this->mapPaciente($p)]);
    }

    public function atendido(int $id): JsonResponse
    {
        $m = $this->medicoActual();
        if (!$m) return response()->json(['ok' => false], 403);

        $p = ColaAtencion::findOrFail($id);
        if (mb_strtolower($p->profesional) !== mb_strtolower($m->nombre_completo)) {
            return response()->json(['ok' => false, 'error' => 'no-asignado-a-vos'], 403);
        }

        $p->update([
            'estado'      => 'resuelto',
            'atendido_at' => now(),
        ]);
        return response()->json(['ok' => true]);
    }
}
