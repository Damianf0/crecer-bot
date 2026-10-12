<?php

namespace App\Http\Controllers;

use App\Models\OmniaCatalogo;
use App\Models\RecepcionChecklist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Checklist de recepción por obra social (/v2/recepcion/reglas): cada obra
 * social tiene su lista de pasos y hay una General para las que no tienen la
 * suya. Lo cargan supervisora y admin (permiso 'admin'); las recepcionistas
 * ven el resultado en la ficha del paciente. Qué lista le toca a cada paciente
 * lo decide App\Services\ChecklistRecepcion.
 */
class RecepcionReglasController extends Controller
{
    public function index()
    {
        return view('v2.recepcion-reglas', [
            'modulo'    => 'Recepción',
            'title'     => 'Checklist por obra social',
            'navActive' => 'recepcion-reglas',
        ]);
    }

    public function data(): JsonResponse
    {
        $checklists = RecepcionChecklist::with(['items', 'actualizadoPor:id,nombre_completo'])
            ->orderByRaw("clave = '' DESC")->orderBy('financiador')
            ->get()
            ->map(fn (RecepcionChecklist $c) => self::mapear($c));

        // Nombres de obras sociales tal como los manda Omnia, de más a menos turnos.
        $financiadores = OmniaCatalogo::where('tipo', 'financiador')->orderByDesc('turnos')->orderBy('nombre')
            ->get(['nombre', 'turnos']);

        return response()->json(['ok' => true, 'checklists' => $checklists, 'financiadores' => $financiadores]);
    }

    /**
     * POST /v2/recepcion/reglas/checklist — crea o guarda un checklist entero
     * (nombre de la obra social + sus pasos en orden). Sin `id` crea uno nuevo.
     */
    public function guardarChecklist(Request $r): JsonResponse
    {
        $d = $r->validate([
            'id'                  => 'nullable|integer|exists:recepcion_checklists,id',
            'financiador'         => 'nullable|string|max:191',
            'activo'              => 'nullable|boolean',
            'items'               => 'present|array|max:40',
            'items.*.texto'       => 'required|string|max:160',
            'items.*.obligatorio' => 'nullable|boolean',
            'items.*.nota'        => 'nullable|string|max:255',
        ]);

        $c = !empty($d['id']) ? RecepcionChecklist::findOrFail($d['id']) : new RecepcionChecklist();

        if (!$c->exists || !$c->esGeneral()) {
            $nombre = trim(preg_replace('/\s+/', ' ', (string) ($d['financiador'] ?? '')));
            $clave  = RecepcionChecklist::clave($nombre);
            if ($clave === '') {
                return response()->json(['ok' => false, 'error' => 'Falta el nombre de la obra social.'], 422);
            }
            if (RecepcionChecklist::where('clave', $clave)->where('id', '!=', $c->id ?? 0)->exists()) {
                return response()->json(['ok' => false, 'error' => "Ya hay un checklist para {$nombre}."], 422);
            }
            $c->financiador = $nombre;
            $c->clave       = $clave;
        }

        DB::transaction(function () use ($c, $d) {
            $c->activo          = $c->exists && $c->esGeneral() ? true : (bool) ($d['activo'] ?? true);
            $c->actualizado_por = auth()->id();
            $c->save();
            $c->touch();   // aunque solo hayan cambiado los pasos, queda quién y cuándo
            $c->items()->delete();
            foreach (array_values($d['items']) as $i => $item) {
                $c->items()->create([
                    'texto'       => trim($item['texto']),
                    'obligatorio' => (bool) ($item['obligatorio'] ?? false),
                    'nota'        => trim((string) ($item['nota'] ?? '')) ?: null,
                    'orden'       => $i + 1,
                ]);
            }
        });

        return response()->json(['ok' => true, 'checklist' => self::mapear($c->fresh(['items', 'actualizadoPor:id,nombre_completo']))]);
    }

    /** POST /v2/recepcion/reglas/checklist/{id}/borrar — el General no se borra. */
    public function borrarChecklist(int $id): JsonResponse
    {
        $c = RecepcionChecklist::findOrFail($id);
        if ($c->esGeneral()) {
            return response()->json(['ok' => false, 'error' => 'El checklist General no se puede eliminar: es el que se usa cuando la obra social no tiene uno propio.'], 422);
        }
        $c->delete();
        return response()->json(['ok' => true]);
    }

    private static function mapear(RecepcionChecklist $c): array
    {
        return [
            'id'          => $c->id,
            'general'     => $c->esGeneral(),
            'financiador' => $c->financiador,
            'activo'      => (bool) $c->activo,
            'items'       => $c->items->map(fn ($i) => [
                'texto' => $i->texto, 'obligatorio' => (bool) $i->obligatorio, 'nota' => $i->nota,
            ])->values(),
            'editado'     => $c->updated_at?->format('d/m/Y H:i'),
            'editado_por' => $c->actualizadoPor?->nombre_completo,
        ];
    }
}
