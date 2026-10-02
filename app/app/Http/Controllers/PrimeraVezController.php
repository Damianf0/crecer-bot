<?php

namespace App\Http\Controllers;

use App\Models\ColaAtencion;
use App\Models\Contacto;
use App\Models\PrimeraVez;
use App\Models\PrimeraVezMedico;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Pacientes de primera vez (/v2/primera-vez): por qué llegan y con qué médico
 * se les da turno. Lo carga cualquier persona del panel, normalmente mientras
 * atiende el teléfono; el reporte y la lista de médicos son de supervisión.
 *
 * Módulo aislado a propósito: tablas propias y ninguna escritura fuera de
 * ellas (ni contactos ni la cola de recepción).
 */
class PrimeraVezController extends Controller
{
    private function esAdmin(): bool
    {
        return (bool) Auth::user()?->hasPermiso('admin');
    }

    public function index()
    {
        return view('v2.primera-vez', [
            'modulo'    => 'Recepción',
            'title'     => 'Primera vez',
            'navActive' => 'primera-vez',
        ]);
    }

    /** GET /primera-vez/data?mes=YYYY-MM&q= — registros del mes + listas para el formulario. */
    public function data(Request $request): JsonResponse
    {
        $mes = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('mes')) ? $request->query('mes') : now()->format('Y-m');
        $desde = Carbon::createFromFormat('Y-m-d', $mes . '-01')->startOfDay();
        $q = trim((string) $request->query('q', ''));

        $items = PrimeraVez::with('registradoPor:id,nombre_completo')
            ->whereBetween('fecha', [$desde->toDateString(), $desde->copy()->endOfMonth()->toDateString()])
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('nombre', 'like', "%{$q}%")
                ->orWhere('derivante', 'like', "%{$q}%")->orWhere('dni', 'like', "%{$q}%")))
            ->orderByDesc('fecha')->orderByDesc('id')
            ->limit(500)->get();

        return response()->json([
            'ok'        => true,
            'mes'       => $mes,
            'items'     => $items->map(fn ($p) => $this->map($p))->values(),
            'motivos'   => PrimeraVez::MOTIVOS,
            'medicos'   => PrimeraVezMedico::orderBy('nombre')->get(['id', 'nombre', 'activo']),
            // Derivantes ya usados, los más frecuentes primero: el autocompletado
            // es lo que evita que el mismo médico quede escrito de diez formas.
            'derivantes' => PrimeraVez::whereNotNull('derivante')->where('derivante', '!=', '')
                ->selectRaw('derivante, COUNT(*) as n')->groupBy('derivante')->orderByDesc('n')->limit(300)->pluck('derivante'),
            'es_admin'  => $this->esAdmin(),
            'yo'        => Auth::id(),
        ]);
    }

    private function validar(Request $request): array
    {
        $d = $request->validate([
            'fecha'     => 'nullable|date|before_or_equal:today',
            'nombre'    => 'required|string|max:160',
            'telefono'  => 'nullable|string|max:30',
            'dni'       => 'nullable|string|max:15',
            'motivo'    => ['required', Rule::in(array_keys(PrimeraVez::MOTIVOS))],
            'medico'    => 'nullable|string|max:80',
            'derivante' => 'nullable|string|max:160',
            'detalle'   => 'nullable|string|max:500',
        ]);

        $d['nombre']    = trim($d['nombre']);
        $d['dni']       = preg_replace('/\D/', '', (string) ($d['dni'] ?? '')) ?: null;
        $d['telefono']  = trim((string) ($d['telefono'] ?? '')) ?: null;
        $d['medico']    = trim((string) ($d['medico'] ?? '')) ?: null;
        $d['derivante'] = trim((string) ($d['derivante'] ?? '')) ?: null;
        $d['detalle']   = trim((string) ($d['detalle'] ?? '')) ?: null;
        $d['fecha']     = !empty($d['fecha']) ? Carbon::parse($d['fecha'])->toDateString() : now()->toDateString();

        // Vínculo con la ficha si ya existe (solo lectura: acá no se crean contactos).
        $contacto = null;
        if ($d['dni']) $contacto = Contacto::where('dni', $d['dni'])->first();
        if (!$contacto && $d['telefono'] && ($tel = Contacto::normalizarTelefono($d['telefono']))) {
            $contacto = Contacto::where('telefono', $tel)->first();
        }
        $d['contacto_id'] = $contacto?->id;

        return $d;
    }

    /** POST /primera-vez */
    public function store(Request $request): JsonResponse
    {
        $p = PrimeraVez::create($this->validar($request) + ['origen' => 'panel', 'registrado_por' => Auth::id()]);

        return response()->json(['ok' => true, 'item' => $this->map($p->load('registradoPor:id,nombre_completo'))], 201);
    }

    /** Lo corrige quien lo cargó, o supervisión. */
    private function propio(int $id): PrimeraVez
    {
        $p = PrimeraVez::findOrFail($id);
        abort_unless($this->esAdmin() || $p->registrado_por === Auth::id(), 403, 'Solo puede modificarlo quien lo cargó o supervisión.');
        return $p;
    }

    /** POST /primera-vez/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $p = $this->propio($id);
        $d = $this->validar($request);
        // Corregir un registro importado de la planilla no le inventa un día.
        if ($p->solo_mes && !$request->filled('fecha')) unset($d['fecha']);
        elseif ($request->filled('fecha')) $d['solo_mes'] = false;
        $p->update($d);

        return response()->json(['ok' => true, 'item' => $this->map($p->load('registradoPor:id,nombre_completo'))]);
    }

    /** POST /primera-vez/{id}/borrar */
    public function destroy(int $id): JsonResponse
    {
        $this->propio($id)->delete();

        return response()->json(['ok' => true]);
    }

    /** POST /primera-vez/medicos — alta, cambio de nombre o baja de la lista (supervisión). */
    public function guardarMedico(Request $request): JsonResponse
    {
        $d = $request->validate([
            'id'     => 'nullable|integer',
            'nombre' => 'required|string|max:80',
            'activo' => 'nullable|boolean',
        ]);
        $nombre = trim($d['nombre']);

        $m = !empty($d['id']) ? PrimeraVezMedico::findOrFail($d['id']) : new PrimeraVezMedico();
        if (PrimeraVezMedico::where('nombre', $nombre)->when($m->exists, fn ($q) => $q->where('id', '!=', $m->id))->exists()) {
            return response()->json(['ok' => false, 'error' => 'Ya hay un médico con ese nombre.'], 422);
        }

        // Cambio de nombre (un error de tipeo): se corrige también en lo ya cargado.
        if ($m->exists && $m->nombre !== $nombre) {
            PrimeraVez::where('medico', $m->nombre)->update(['medico' => $nombre]);
        }
        $m->fill(['nombre' => $nombre, 'activo' => $request->boolean('activo', true)])->save();

        return response()->json(['ok' => true, 'medicos' => PrimeraVezMedico::orderBy('nombre')->get(['id', 'nombre', 'activo'])]);
    }

    /**
     * GET /primera-vez/reporte?desde=YYYY-MM&hasta=YYYY-MM — lo que salía de las
     * tablas dinámicas de la planilla (mes × médico, médico × motivo) más el
     * ranking de derivantes y cuántas de las registradas después vinieron.
     *
     * Se agrupa en PHP: son cientos de filas por año y así no depende de las
     * funciones de fecha del motor.
     */
    public function reporte(Request $request): JsonResponse
    {
        $mes = fn ($v, $def) => preg_match('/^\d{4}-\d{2}$/', (string) $v) ? $v : $def;
        $desde = Carbon::createFromFormat('Y-m-d', $mes($request->query('desde'), now()->subMonths(11)->format('Y-m')) . '-01')->startOfDay();
        $hasta = Carbon::createFromFormat('Y-m-d', $mes($request->query('hasta'), now()->format('Y-m')) . '-01')->endOfMonth();

        $filas = PrimeraVez::whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->get(['fecha', 'motivo', 'medico', 'derivante', 'dni']);

        $sinMedico = '(sin médico)';
        $medicos = $filas->map(fn ($f) => $f->medico ?: $sinMedico)->unique()->sort()->values();

        $porMes = $filas->groupBy(fn ($f) => $f->fecha->format('Y-m'))->sortKeys()->map(fn ($g, $m) => [
            'mes'      => $m,
            'total'    => $g->count(),
            'medicos'  => $g->countBy(fn ($f) => $f->medico ?: $sinMedico),
            'motivos'  => $g->countBy('motivo'),
        ])->values();

        $porMedico = $filas->groupBy(fn ($f) => $f->medico ?: $sinMedico)->sortKeys()->map(fn ($g, $m) => [
            'medico'  => $m,
            'total'   => $g->count(),
            'motivos' => $g->countBy('motivo'),
        ])->values();

        $derivantes = $filas->filter(fn ($f) => $f->derivante)->countBy(fn ($f) => mb_strtoupper(trim($f->derivante)))
            ->sortDesc()->take(30)->map(fn ($n, $d) => ['derivante' => $d, 'n' => $n])->values();

        // ¿Vinieron? Solo se puede saber de las que tienen DNI: se busca una
        // llegada a recepción desde el día en que se registraron.
        $conDni = $filas->filter(fn ($f) => $f->dni);
        $llegadas = $conDni->isEmpty() ? collect()
            : ColaAtencion::whereIn('dni', $conDni->pluck('dni')->unique()->all())
                ->selectRaw('dni, MAX(hora_llegada) as ultima')->groupBy('dni')->pluck('ultima', 'dni');
        $vinieron = $conDni->filter(fn ($f) => isset($llegadas[$f->dni])
            && Carbon::parse($llegadas[$f->dni])->gte($f->fecha->copy()->startOfDay()))->count();

        return response()->json([
            'ok'         => true,
            'desde'      => $desde->format('Y-m'),
            'hasta'      => $hasta->format('Y-m'),
            'total'      => $filas->count(),
            'motivos'    => PrimeraVez::MOTIVOS,
            'por_motivo' => $filas->countBy('motivo'),
            'medicos'    => $medicos,
            'por_mes'    => $porMes,
            'por_medico' => $porMedico,
            'derivantes' => $derivantes,
            'asistencia' => ['con_dni' => $conDni->count(), 'vinieron' => $vinieron],
        ]);
    }

    private function map(PrimeraVez $p): array
    {
        return [
            'id'          => $p->id,
            'fecha'       => $p->fecha->toDateString(),
            'fecha_txt'   => $p->solo_mes ? ucfirst($p->fecha->translatedFormat('M Y')) : $p->fecha->format('d/m/Y'),
            'solo_mes'    => $p->solo_mes,
            'nombre'      => $p->nombre,
            'telefono'    => $p->telefono,
            'dni'         => $p->dni,
            'contacto_id' => $p->contacto_id,
            'motivo'      => $p->motivo,
            'medico'      => $p->medico,
            'derivante'   => $p->derivante,
            'detalle'     => $p->detalle,
            'origen'      => $p->origen,
            'por'         => $p->registradoPor?->nombre_completo,
            'por_id'      => $p->registrado_por,
        ];
    }
}
