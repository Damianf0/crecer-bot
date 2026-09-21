<?php

namespace App\Http\Controllers;

use App\Models\OmniaCatalogo;
use App\Models\RecepcionRegla;
use App\Models\RecepcionRequisito;
use App\Services\ChecklistRecepcion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Gestión del checklist de recepción (/v2/recepcion/reglas): catálogo de
 * requisitos y reglas por obra social / plan / práctica. Lo usan supervisora y
 * admin (permiso 'admin'); las recepcionistas solo ven el resultado en la ficha.
 */
class RecepcionReglasController extends Controller
{
    public function index()
    {
        return view('v2.recepcion-reglas', [
            'modulo'    => 'Recepción',
            'title'     => 'Checklist de recepción',
            'navActive' => 'recepcion-reglas',
        ]);
    }

    public function data(): JsonResponse
    {
        $requisitos = RecepcionRequisito::withCount('reglas')->orderBy('orden')->orderBy('nombre')->get();
        $reglas = RecepcionRegla::with(['requisito:id,nombre', 'actualizadoPor:id,nombre_completo'])
            ->orderByRaw('financiador IS NULL, financiador')->orderByRaw('practica IS NULL, practica')->orderBy('plan')
            ->get()
            ->map(fn (RecepcionRegla $r) => [
                'id'           => $r->id,
                'requisito_id' => $r->requisito_id,
                'requisito'    => $r->requisito?->nombre,
                'financiador'  => $r->financiador,
                'plan'         => $r->plan,
                'practica'     => $r->practica,
                'modo'         => $r->modo,
                'nota'         => $r->nota,
                'activo'       => $r->activo,
                'editado'      => $r->updated_at?->format('d/m/Y H:i'),
                'editado_por'  => $r->actualizadoPor?->nombre_completo,
            ]);

        $catalogo = OmniaCatalogo::orderByDesc('turnos')->get(['tipo', 'nombre', 'turnos'])->groupBy('tipo');

        return response()->json([
            'ok'           => true,
            'requisitos'   => $requisitos,
            'reglas'       => $reglas,
            'financiadores'=> $catalogo->get('financiador', collect())->values(),
            'practicas'    => $catalogo->get('practica', collect())->values(),
            'modos'        => RecepcionRegla::MODOS,
        ]);
    }

    public function guardarRequisito(Request $r): JsonResponse
    {
        $d = $r->validate([
            'id'            => 'nullable|integer|exists:recepcion_requisitos,id',
            'nombre'        => 'required|string|max:120',
            'instruccion'   => 'nullable|string|max:2000',
            'vigencia_dias' => 'nullable|integer|min:1|max:3650',
            'orden'         => 'nullable|integer|min:0|max:999',
            'activo'        => 'boolean',
        ]);
        $req = RecepcionRequisito::updateOrCreate(['id' => $d['id'] ?? null], [
            'nombre'        => trim($d['nombre']),
            'instruccion'   => $d['instruccion'] ?? null,
            'vigencia_dias' => $d['vigencia_dias'] ?? null,
            'orden'         => $d['orden'] ?? 0,
            'activo'        => $d['activo'] ?? true,
        ]);
        return response()->json(['ok' => true, 'requisito' => $req]);
    }

    /** Solo si no tiene reglas: con reglas se desactiva, así no se pierde el historial. */
    public function borrarRequisito(int $id): JsonResponse
    {
        $req = RecepcionRequisito::withCount('reglas')->findOrFail($id);
        if ($req->reglas_count > 0) {
            return response()->json(['ok' => false, 'error' => 'Tiene reglas asociadas: desactivalo en vez de borrarlo.'], 422);
        }
        $req->delete();
        return response()->json(['ok' => true]);
    }

    public function guardarRegla(Request $r): JsonResponse
    {
        $d = $r->validate([
            'id'           => 'nullable|integer|exists:recepcion_reglas,id',
            'requisito_id' => 'required|integer|exists:recepcion_requisitos,id',
            'financiador'  => 'nullable|string|max:191',
            'plan'         => 'nullable|string|max:60',
            'practica'     => 'nullable|string|max:191',
            'modo'         => ['required', Rule::in(array_keys(RecepcionRegla::MODOS))],
            'nota'         => 'nullable|string|max:255',
            'activo'       => 'boolean',
        ]);
        $vacio = fn ($v) => ($v = trim((string) $v)) === '' ? null : $v;
        $datos = [
            'requisito_id'    => $d['requisito_id'],
            'financiador'     => $vacio($d['financiador'] ?? null),
            'plan'            => $vacio($d['plan'] ?? null),
            'practica'        => $vacio($d['practica'] ?? null),
            'modo'            => $d['modo'],
            'nota'            => $vacio($d['nota'] ?? null),
            'activo'          => $d['activo'] ?? true,
            'actualizado_por' => auth()->id(),
        ];
        if ($datos['plan'] && !$datos['financiador']) {
            return response()->json(['ok' => false, 'error' => 'El plan solo tiene sentido junto con una obra social.'], 422);
        }

        // Dos reglas para el mismo requisito y el mismo caso se pisarían en
        // silencio (gana la más reciente): mejor avisar y editar la existente.
        $dup = RecepcionRegla::where('requisito_id', $datos['requisito_id'])
            ->where(fn ($q) => $datos['financiador'] === null ? $q->whereNull('financiador') : $q->where('financiador', $datos['financiador']))
            ->where(fn ($q) => $datos['plan'] === null ? $q->whereNull('plan') : $q->where('plan', $datos['plan']))
            ->where(fn ($q) => $datos['practica'] === null ? $q->whereNull('practica') : $q->where('practica', $datos['practica']))
            ->when($d['id'] ?? null, fn ($q, $id) => $q->where('id', '!=', $id))
            ->exists();
        if ($dup) {
            return response()->json(['ok' => false, 'error' => 'Ya hay una regla para ese requisito y ese mismo caso: editá esa.'], 422);
        }

        $regla = RecepcionRegla::updateOrCreate(['id' => $d['id'] ?? null], $datos);
        return response()->json(['ok' => true, 'regla' => $regla]);
    }

    public function borrarRegla(int $id): JsonResponse
    {
        RecepcionRegla::findOrFail($id)->delete();
        return response()->json(['ok' => true]);
    }

    /** "¿Qué le pedimos a este paciente?" — el mismo cálculo que usa la ficha de recepción. */
    public function probar(Request $r): JsonResponse
    {
        $d = $r->validate([
            'financiador' => 'nullable|string|max:191',
            'plan'        => 'nullable|string|max:60',
            'practica'    => 'nullable|string|max:191',
        ]);
        $practicas = array_filter([trim((string) ($d['practica'] ?? ''))]);
        return response()->json([
            'ok'        => true,
            'checklist' => ChecklistRecepcion::para($d['financiador'] ?? null, $d['plan'] ?? null, $practicas),
        ]);
    }
}
