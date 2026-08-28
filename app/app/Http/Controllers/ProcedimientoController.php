<?php

namespace App\Http\Controllers;

use App\Models\Procedimiento;
use App\Models\ProcedimientoAdjunto;
use App\Models\ProcedimientoCodigo;
use App\Models\ProcedimientoPaso;
use App\Services\HtmlSeguro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Base de conocimiento: consulta de procedimientos de atención.
 *
 * La lectura la tiene cualquier usuario del panel; el alta y la edición viven
 * en AdminController bajo permiso:admin (M3 del brief — ver el plan). Los
 * borradores solo los ven quienes pueden editarlos.
 */
class ProcedimientoController extends Controller
{
    /** ¿El usuario actual puede ver y tocar borradores? */
    private function puedeEditar(): bool
    {
        return (bool) Auth::user()?->hasPermiso('admin');
    }

    /**
     * GET /procedimientos/data — listado con búsqueda y filtro por área.
     *
     * Sin paginación a propósito: son decenas de procedimientos, no miles como
     * contactos. Si algún día crecen, se copia el offset/limit de
     * ContactoController::data().
     */
    public function data(Request $request): JsonResponse
    {
        $q    = trim($request->input('q', ''));
        $area = trim($request->input('area', ''));

        $base = Procedimiento::query();

        if (!$this->puedeEditar()) {
            $base->publicados();
        }

        if ($area !== '' && array_key_exists($area, Procedimiento::AREAS)) {
            $base->area($area);
        }

        if ($q !== '') {
            $base->where(function ($w) use ($q) {
                $w->where('titulo', 'like', "%{$q}%")
                  ->orWhere('resumen', 'like', "%{$q}%")
                  // Contra contenido_texto, no contra el HTML: buscar "li" o
                  // "strong" sobre el markup matchearía todas las listas y
                  // todas las negritas.
                  ->orWhereHas('pasos', function ($p) use ($q) {
                      $p->where('titulo', 'like', "%{$q}%")
                        ->orWhere('contenido_texto', 'like', "%{$q}%");
                  });
            });
        }

        $procs = $base->ordenados()
            ->withCount('pasos')
            ->with('codigos:id,procedimiento_id,codigo')
            ->get(['id', 'titulo', 'slug', 'area', 'resumen', 'estado', 'orden', 'revisado_at', 'updated_at'])
            ->map(fn($p) => [
                'id'          => $p->id,
                'titulo'      => $p->titulo,
                'area'        => $p->area,
                'resumen'     => $p->resumen,
                'estado'      => $p->estado,
                'pasos_count' => $p->pasos_count,
                'casos'       => $p->codigos->map(fn($c) => Procedimiento::CODIGOS_BOT[$c->codigo] ?? $c->codigo)->all(),
                'sin_revisar' => $p->necesitaRevision(),
            ]);

        return response()->json([
            'ok'          => true,
            'data'        => $procs,
            'total'       => $procs->count(),
            'areas'       => Procedimiento::AREAS,
            'codigos_bot' => Procedimiento::CODIGOS_BOT,
            'puede_editar' => $this->puedeEditar(),
        ]);
    }

    /**
     * GET /procedimientos/opciones — lista mínima para poblar un <select>.
     * La usa el formulario de tareas. Solo publicados: no tiene sentido
     * vincular una tarea a un borrador que el resto del equipo no puede ver.
     */
    public function opciones(): JsonResponse
    {
        $data = Cache::remember('procedimientos.opciones', 300, function () {
            return Procedimiento::publicados()
                ->orderBy('area')->orderBy('orden')->orderBy('titulo')
                ->get(['id', 'titulo', 'area'])
                ->map(fn($p) => [
                    'id'         => $p->id,
                    'titulo'     => $p->titulo,
                    'area'       => $p->area,
                    'area_label' => Procedimiento::AREAS[$p->area] ?? $p->area,
                ]);
        });

        return response()->json(['ok' => true, 'data' => $data]);
    }

    /** GET /procedimientos/{id} — detalle con pasos y adjuntos. */
    public function show(int $id): JsonResponse
    {
        $proc = Procedimiento::with(['pasos', 'adjuntos', 'codigos', 'actualizadoPor:id,nombre_completo'])
            ->findOrFail($id);

        if ($proc->estado !== 'publicado' && !$this->puedeEditar()) {
            abort(404);
        }

        return response()->json([
            'ok'            => true,
            'procedimiento' => $this->mapProcedimiento($proc),
            'puede_editar'  => $this->puedeEditar(),
            'areas'         => Procedimiento::AREAS,
            'codigos_bot'   => Procedimiento::CODIGOS_BOT,
        ]);
    }

    /**
     * POST /procedimientos — crea uno vacío y devuelve su id.
     *
     * El editor necesita un id existente antes de poder subir adjuntos, así
     * que "Nuevo procedimiento" crea primero y edita después. Nace en borrador,
     * o sea que nadie más lo ve hasta que se publique.
     */
    public function store(Request $request): JsonResponse
    {
        $titulo = trim($request->input('titulo', '')) ?: 'Procedimiento sin título';

        $proc = Procedimiento::create([
            'titulo'          => mb_substr($titulo, 0, 160),
            'slug'            => Procedimiento::generarSlug($titulo),
            'area'            => 'general',
            'estado'          => 'borrador',
            'creado_por'      => Auth::id(),
            'actualizado_por' => Auth::id(),
        ]);

        return response()->json(['ok' => true, 'id' => $proc->id], 201);
    }

    /**
     * POST /procedimientos/{id} — guarda el documento completo.
     *
     * Se manda todo junto (metadata + pasos) en vez de un endpoint por paso:
     * con este volumen es mucho más simple que un CRUD por paso más un
     * endpoint de reordenamiento. El `orden` sale del índice del array.
     */
    public function save(Request $request, int $id): JsonResponse
    {
        $proc = Procedimiento::findOrFail($id);

        $datos = $request->validate([
            'updated_at'            => 'nullable|string',
            'titulo'                => 'required|string|max:160',
            'resumen'               => 'nullable|string|max:300',
            'area'                  => 'required|string|in:' . implode(',', array_keys(Procedimiento::AREAS)),
            'estado'                => 'required|string|in:borrador,publicado',
            'revisar'               => 'nullable|boolean',
            'codigos'               => 'nullable|array',
            'codigos.*'             => 'string|in:' . implode(',', array_keys(Procedimiento::CODIGOS_BOT)),
            'pasos'                 => 'nullable|array',
            'pasos.*.id'            => 'nullable|integer',
            'pasos.*.titulo'        => 'nullable|string|max:160',
            'pasos.*.contenido'     => 'nullable|string',
            'pasos.*.respuesta_wa'  => 'nullable|string|max:4000',
        ]);

        // Chequeo optimista: si el procedimiento cambió desde que el editor lo
        // cargó, otra persona lo guardó mientras tanto. Sin esto el último en
        // apretar Guardar pisa el trabajo del otro sin que nadie se entere.
        $marcaEnviada = $datos['updated_at'] ?? null;
        if ($marcaEnviada && $proc->updated_at
            && $proc->updated_at->toIso8601String() !== $marcaEnviada) {
            return response()->json([
                'ok'    => false,
                'error' => 'Alguien más editó este procedimiento mientras lo tenías abierto. '
                         . 'Recargá la página para ver los cambios; si guardás ahora vas a pisar su trabajo.',
            ], 409);
        }

        DB::transaction(function () use ($proc, $datos, $request) {

            $proc->update([
                'titulo'          => $datos['titulo'],
                'resumen'         => $datos['resumen'] ?? null,
                'area'            => $datos['area'],
                'estado'          => $datos['estado'],
                'actualizado_por' => Auth::id(),
                'revisado_at'     => $request->boolean('revisar') ? now() : $proc->revisado_at,
            ]);

            // ── Casos del clasificador ──
            $codigos = array_values(array_unique($datos['codigos'] ?? []));
            $proc->codigos()->whereNotIn('codigo', $codigos ?: ['__ninguno__'])->delete();
            foreach ($codigos as $c) {
                ProcedimientoCodigo::firstOrCreate(['procedimiento_id' => $proc->id, 'codigo' => $c]);
            }

            // ── Pasos ──
            $vistos = [];
            foreach (array_values($datos['pasos'] ?? []) as $i => $p) {
                // El HTML del navegador NO es confiable: se limpia acá, siempre.
                $html  = HtmlSeguro::limpiar($p['contenido'] ?? '');
                $campos = [
                    'orden'           => $i + 1,
                    'titulo'          => $p['titulo'] ?? null,
                    'contenido'       => $html,
                    'contenido_texto' => HtmlSeguro::aTexto($html),
                    'respuesta_wa'    => $p['respuesta_wa'] ?? null,
                ];

                $paso = !empty($p['id'])
                    ? $proc->pasos()->whereKey($p['id'])->first()
                    : null;

                if ($paso) {
                    $paso->update($campos);
                } else {
                    $paso = $proc->pasos()->create($campos);
                }

                $vistos[] = $paso->id;
            }

            // Los pasos que el editor no mandó fueron borrados por el usuario.
            // Los adjuntos que colgaban de ellos sobreviven a nivel procedimiento.
            $aBorrar = $proc->pasos()->whereNotIn('id', $vistos ?: [0])->pluck('id');
            if ($aBorrar->isNotEmpty()) {
                $proc->adjuntos()->whereIn('paso_id', $aBorrar)->update(['paso_id' => null]);
                ProcedimientoPaso::whereIn('id', $aBorrar)->delete();
            }
        });

        // El <select> de tareas cachea la lista 5 min; si cambió el título o el
        // estado hay que refrescarla.
        Cache::forget('procedimientos.opciones');

        return response()->json(['ok' => true, 'procedimiento' => $this->mapProcedimiento(
            $proc->fresh(['pasos', 'adjuntos', 'codigos', 'actualizadoPor'])
        )]);
    }

    /** DELETE /procedimientos/{id} — borrado reversible (softDeletes). */
    public function destroy(int $id): JsonResponse
    {
        Procedimiento::findOrFail($id)->delete();
        Cache::forget('procedimientos.opciones');

        return response()->json(['ok' => true]);
    }

    // ── Adjuntos ──────────────────────────────────────────────

    /**
     * POST /procedimientos/{id}/adjuntos — sube una captura o un instructivo.
     *
     * Los archivos van al disk `local` (storage/app/private), NO a
     * storage/app/public: ese último cuelga del symlink public/storage y nginx
     * lo serviría sin pedir sesión, y una captura de un procedimiento puede
     * tener datos de una paciente.
     */
    public function subirAdjunto(Request $request, int $id): JsonResponse
    {
        $proc = Procedimiento::findOrFail($id);

        // 8 MB y no los 25 de DocumentoController: el php.ini del container
        // tiene upload_max_filesize=20M, así que un cap mayor sería letra
        // muerta y el usuario vería un error críptico de PHP en vez del nuestro.
        $request->validate([
            'archivo' => [
                'required', 'file', 'max:8192',
                'mimetypes:' . implode(',', ProcedimientoAdjunto::MIMES_PERMITIDOS),
            ],
            'paso_id' => 'nullable|integer',
        ]);

        $archivo = $request->file('archivo');
        $nombre  = $archivo->getClientOriginalName();

        if ($ext = AtencionController::extensionBloqueada($nombre)) {
            return response()->json(['ok' => false, 'error' => "Extensión .{$ext} no permitida"], 422);
        }

        // El paso tiene que ser de ESTE procedimiento; si no, se guarda suelto.
        $pasoId = $request->input('paso_id');
        if ($pasoId && !$proc->pasos()->whereKey($pasoId)->exists()) {
            $pasoId = null;
        }

        $mime      = $archivo->getMimeType();
        $extension = strtolower($archivo->getClientOriginalExtension() ?: 'bin');

        // Nombre generado, nunca el del cliente: evita traversal y colisiones.
        $ruta = $archivo->storeAs(
            'procedimientos/' . $proc->id,
            (string) Str::ulid() . '.' . $extension,
            'local'
        );

        if (!$ruta) {
            return response()->json(['ok' => false, 'error' => 'No se pudo guardar el archivo'], 500);
        }

        $adj = ProcedimientoAdjunto::create([
            'procedimiento_id' => $proc->id,
            'paso_id'          => $pasoId,
            'tipo'             => str_starts_with((string) $mime, 'image/') ? 'imagen' : 'archivo',
            'path'             => $ruta,
            'nombre_original'  => mb_substr($nombre, 0, 255),
            'mime'             => $mime,
            'tamano'           => $archivo->getSize(),
        ]);

        return response()->json(['ok' => true, 'adjunto' => [
            'id'     => $adj->id,
            'tipo'   => $adj->tipo,
            'nombre' => $adj->nombre_original,
            'url'    => "/procedimientos/adjunto/{$adj->id}",
            'imagen' => $adj->esImagen(),
        ]], 201);
    }

    /** GET /procedimientos/adjunto/{id} — lo sirve con sesión, nunca por URL pública. */
    public function verAdjunto(int $id, bool $descargar = false)
    {
        $adj = ProcedimientoAdjunto::findOrFail($id);
        $abs = Storage::disk('local')->path($adj->path);

        if (!is_file($abs)) abort(404);

        $disposicion = $descargar ? 'attachment' : 'inline';

        return response()->file($abs, [
            'Content-Type'        => $adj->mime ?: 'application/octet-stream',
            'Content-Disposition' => $disposicion . '; filename="' . addslashes($adj->nombre_original) . '"',
            // El navegador no debe adivinar el tipo: un .pdf que en realidad es
            // HTML se ejecutaría en el origen del panel.
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** GET /procedimientos/adjunto/{id}/descargar */
    public function descargarAdjunto(int $id)
    {
        return $this->verAdjunto($id, true);
    }

    /** DELETE /procedimientos/adjunto/{id} */
    public function borrarAdjunto(int $id): JsonResponse
    {
        $adj = ProcedimientoAdjunto::findOrFail($id);

        Storage::disk('local')->delete($adj->path);
        $adj->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Serializa el procedimiento para el front. Mismo criterio que el
     * mapTarea() de TareaController: un array plano y explícito, sin depender
     * de lo que Eloquent decida serializar.
     */
    private function mapProcedimiento(Procedimiento $p): array
    {
        return [
            'id'          => $p->id,
            'titulo'      => $p->titulo,
            'slug'        => $p->slug,
            'area'        => $p->area,
            'area_label'  => Procedimiento::AREAS[$p->area] ?? $p->area,
            'casos'       => $p->codigos->map(fn($c) => Procedimiento::CODIGOS_BOT[$c->codigo] ?? $c->codigo)->all(),
            'codigos'     => $p->codigos->pluck('codigo')->all(),   // crudos, para el editor
            'resumen'     => $p->resumen,
            'estado'      => $p->estado,
            'actualizado' => $p->updated_at?->format('d/m/Y H:i'),
            // Marca para el chequeo optimista al guardar (ver save())
            'updated_at'  => $p->updated_at?->toIso8601String(),
            'actualizado_por' => $p->actualizadoPor?->nombre_completo,
            'revisado'    => $p->revisado_at?->format('d/m/Y'),
            'sin_revisar' => $p->necesitaRevision(),
            'meses_sin_revisar' => $p->mesesSinRevisar(),
            'pasos'       => $p->pasos->map(fn($paso) => [
                'id'           => $paso->id,
                'orden'        => $paso->orden,
                'titulo'       => $paso->titulo,
                'contenido'    => $paso->contenido,      // HTML ya sanitizado al guardar
                'respuesta_wa' => $paso->respuesta_wa,
                'adjuntos'     => $p->adjuntos->where('paso_id', $paso->id)->values()->map(fn($a) => [
                    'id'      => $a->id,
                    'tipo'    => $a->tipo,
                    'nombre'  => $a->nombre_original,
                    'url'     => "/procedimientos/adjunto/{$a->id}",
                    'imagen'  => $a->esImagen(),
                ])->all(),
            ])->all(),
            // Adjuntos del procedimiento en sí, los que no cuelgan de un paso
            'adjuntos' => $p->adjuntos->whereNull('paso_id')->values()->map(fn($a) => [
                'id'     => $a->id,
                'tipo'   => $a->tipo,
                'nombre' => $a->nombre_original,
                'url'    => "/procedimientos/adjunto/{$a->id}",
                'imagen' => $a->esImagen(),
            ])->all(),
        ];
    }
}
