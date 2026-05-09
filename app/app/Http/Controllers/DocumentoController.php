<?php

namespace App\Http\Controllers;

use App\Models\Contacto;
use App\Models\ConversacionWA;
use App\Models\DocumentoPaciente;
use App\Services\LegajoStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class DocumentoController extends Controller
{
    /** Pantalla del legajo de un paciente. */
    public function indexPaciente(int $contactoId)
    {
        $contacto = Contacto::findOrFail($contactoId);
        return response()
            ->view('contactos.documentos', ['contacto' => $contacto])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    /** GET /pacientes/{id}/documentos/data — listado paginado con filtros. */
    public function dataPaciente(int $contactoId, Request $request): JsonResponse
    {
        Contacto::findOrFail($contactoId);

        $q          = trim($request->input('q', ''));
        $tipo       = $request->input('tipo');         // imagen|documento|audio|video|null
        $direccion  = $request->input('direccion');    // entrante|saliente|manual|null
        $destacados = $request->boolean('destacados');
        $desde      = $request->input('desde');
        $hasta      = $request->input('hasta');
        $page       = max(1, (int) $request->input('page', 1));
        $perPage    = 50;

        $base = DocumentoPaciente::where('contacto_id', $contactoId);

        if ($tipo)      $base->where('tipo', $tipo);
        if ($direccion) $base->where('direccion', $direccion);
        if ($destacados) $base->where('destacado', true);
        if ($desde) $base->where('created_at', '>=', \Carbon\Carbon::parse($desde)->startOfDay());
        if ($hasta) $base->where('created_at', '<=', \Carbon\Carbon::parse($hasta)->endOfDay());

        if ($q !== '') {
            $base->where(function ($w) use ($q) {
                $w->where('nombre_original', 'like', "%{$q}%")
                  ->orWhere('notas', 'like', "%{$q}%")
                  ->orWhere('texto_ocr', 'like', "%{$q}%");
            });
        }

        $total = $base->count();

        $docs = $base
            ->with('usuario:id,nombre_completo')
            ->orderByDesc('created_at')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(fn(DocumentoPaciente $d) => $this->mapDoc($d));

        $stats = [
            'total_docs'    => DocumentoPaciente::where('contacto_id', $contactoId)->count(),
            'tamanio_total' => (int) DocumentoPaciente::where('contacto_id', $contactoId)->sum('tamanio_bytes'),
            'destacados'    => DocumentoPaciente::where('contacto_id', $contactoId)->where('destacado', true)->count(),
        ];

        return response()->json([
            'ok'       => true,
            'data'     => $docs,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'has_more' => ($page * $perPage) < $total,
            'stats'    => $stats,
        ]);
    }

    /** GET /documentos/{id}/preview — sirve el archivo inline (con auth). */
    public function preview(int $id)
    {
        $doc = DocumentoPaciente::findOrFail($id);
        $abs = $doc->pathAbsoluto();
        if (!file_exists($abs)) abort(404);

        return response()->file($abs, [
            'Content-Type'        => $doc->mime,
            'Content-Disposition' => 'inline; filename="' . addslashes($doc->nombre_original) . '"',
            'Cache-Control'       => 'private, max-age=3600',
        ]);
    }

    /** GET /documentos/{id}/descargar — fuerza descarga. */
    public function descargar(int $id)
    {
        $doc = DocumentoPaciente::findOrFail($id);
        $abs = $doc->pathAbsoluto();
        if (!file_exists($abs)) abort(404);

        return response()->download($abs, $doc->nombre_original, [
            'Content-Type' => $doc->mime,
        ]);
    }

    /** POST /documentos/{id}/destacar — toggle destacado. */
    public function destacar(int $id): JsonResponse
    {
        $doc = DocumentoPaciente::findOrFail($id);
        $doc->update(['destacado' => !$doc->destacado]);
        return response()->json(['ok' => true, 'destacado' => $doc->destacado]);
    }

    /** PATCH /documentos/{id}/notas — actualiza notas. */
    public function notas(int $id, Request $request): JsonResponse
    {
        $data = $request->validate(['notas' => 'nullable|string|max:2000']);
        $doc  = DocumentoPaciente::findOrFail($id);
        $doc->update(['notas' => $data['notas']]);
        return response()->json(['ok' => true]);
    }

    /** DELETE /documentos/{id} — elimina archivo + registro. */
    public function eliminar(int $id): JsonResponse
    {
        $doc = DocumentoPaciente::findOrFail($id);
        LegajoStorage::eliminar($doc);
        return response()->json(['ok' => true]);
    }

    /** POST /pacientes/{id}/documentos/upload — subida manual. */
    public function uploadManual(int $contactoId, Request $request): JsonResponse
    {
        $contacto = Contacto::findOrFail($contactoId);
        $request->validate([
            'archivo' => [
                'required', 'file', 'max:25600',  // 25 MB
                'mimetypes:' . implode(',', AtencionController::MIMETYPES_PERMITIDOS),
            ],
            'notas'     => 'nullable|string|max:2000',
            'destacado' => 'boolean',
        ]);

        $archivo = $request->file('archivo');
        $nombre  = $archivo->getClientOriginalName();
        if ($ext = AtencionController::extensionBloqueada($nombre)) {
            return response()->json(['ok' => false, 'error' => "Extensión .{$ext} no permitida"], 422);
        }

        $doc = LegajoStorage::indexar($archivo->getRealPath(), [
            'contacto_id'     => $contacto->id,
            'direccion'       => 'manual',
            'usuario_id'      => Auth::id(),
            'mime'            => $archivo->getMimeType(),
            'nombre_original' => $nombre,
            'destacado'       => (bool) $request->input('destacado'),
            'notas'           => $request->input('notas'),
        ], movePreserveSource: false);

        if (!$doc) {
            return response()->json(['ok' => false, 'error' => 'No se pudo guardar el documento'], 500);
        }

        return response()->json(['ok' => true, 'doc' => $this->mapDoc($doc)]);
    }

    /** POST /documentos/{id}/reenviar — re-envía el doc al paciente por WA. */
    public function reenviar(int $id): JsonResponse
    {
        $doc = DocumentoPaciente::findOrFail($id);
        if (!$doc->contacto_id) {
            return response()->json(['ok' => false, 'error' => 'Doc sin contacto vinculado'], 422);
        }
        $contacto = Contacto::find($doc->contacto_id);
        if (!$contacto || !$contacto->wa_id) {
            return response()->json(['ok' => false, 'error' => 'Contacto sin WhatsApp resuelto'], 422);
        }

        $abs = $doc->pathAbsoluto();
        if (!file_exists($abs)) {
            return response()->json(['ok' => false, 'error' => 'El archivo no existe en disk'], 404);
        }

        $base64 = base64_encode(file_get_contents($abs));
        $botUrl = config('app.bot_url');
        $botTok = config('app.bot_ingress_token');

        try {
            $r = Http::timeout(30)->withToken($botTok)->post("{$botUrl}/enviar-archivo", [
                'contacto' => $contacto->wa_id,
                'base64'   => $base64,
                'mimetype' => $doc->mime,
                'filename' => $doc->nombre_original,
            ]);
            if (!$r->ok() || $r->json('ok') !== true) {
                return response()->json(['ok' => false, 'error' => 'El bot no pudo enviar el archivo'], 502);
            }
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => 'No se pudo contactar al bot'], 502);
        }

        // Indexar el envío también para que quede en el historial como saliente.
        $conv = ConversacionWA::where('contacto', $contacto->wa_id)->first();
        if ($conv) {
            \App\Models\MensajeWA::create([
                'conversacion_id' => $conv->id,
                'direccion'       => 'saliente',
                'tipo'            => $doc->tipo,
                'contenido'       => '[Re-enviado del legajo] ' . $doc->nombre_original,
                'archivo_url'     => null,
                'usuario_id'      => Auth::id(),
                'leido'           => true,
            ]);
        }

        return response()->json(['ok' => true]);
    }

    /** POST /pacientes/{id}/documentos/zip — descarga seleccionados como ZIP. */
    public function descargarZip(int $contactoId, Request $request)
    {
        $contacto = Contacto::findOrFail($contactoId);
        $ids = $request->input('ids', []);
        if (!is_array($ids) || empty($ids)) abort(400, 'Faltan IDs');

        $docs = DocumentoPaciente::where('contacto_id', $contactoId)->whereIn('id', $ids)->get();
        if ($docs->isEmpty()) abort(404);

        $tmp = tempnam(sys_get_temp_dir(), 'legajo_') . '.zip';
        $zip = new \ZipArchive;
        if ($zip->open($tmp, \ZipArchive::CREATE) !== true) abort(500, 'No se pudo crear ZIP');

        $usados = [];
        foreach ($docs as $d) {
            $abs = $d->pathAbsoluto();
            if (!file_exists($abs)) continue;
            // Evitar nombres duplicados en el ZIP
            $entry = $d->nombre_original;
            $i = 1;
            while (isset($usados[$entry])) {
                $info = pathinfo($d->nombre_original);
                $entry = ($info['filename'] ?? 'doc') . " ({$i})." . ($info['extension'] ?? '');
                $i++;
            }
            $usados[$entry] = true;
            $zip->addFile($abs, $entry);
        }
        $zip->close();

        $filenameZip = 'legajo_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $contacto->nombre ?? 'paciente') . '_' . now()->format('Ymd_His') . '.zip';

        return response()->download($tmp, $filenameZip)->deleteFileAfterSend();
    }

    private function mapDoc(DocumentoPaciente $d): array
    {
        return [
            'id'              => $d->id,
            'tipo'            => $d->tipo,
            'mime'            => $d->mime,
            'nombre'          => $d->nombre_original,
            'tamanio'         => $d->tamanio_bytes,
            'tamanio_human'   => $d->tamanio_human,
            'direccion'       => $d->direccion,
            'usuario'         => $d->usuario?->nombre_completo,
            'destacado'       => (bool) $d->destacado,
            'notas'           => $d->notas,
            'tiene_ocr'       => !empty($d->texto_ocr),
            'fecha'           => $d->created_at->format('d/m/Y'),
            'hora'            => $d->created_at->format('H:i'),
            'fecha_iso'       => $d->created_at->toIso8601String(),
            'preview_url'     => route('docs.preview', $d->id),
            'descarga_url'    => route('docs.descargar', $d->id),
        ];
    }
}
