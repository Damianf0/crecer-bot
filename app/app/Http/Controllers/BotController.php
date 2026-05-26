<?php

namespace App\Http\Controllers;

use App\Models\Contacto;
use App\Models\ConversacionWA;
use App\Models\Derivacion;
use App\Models\MensajeWA;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BotController extends Controller
{
    // ── Derivaciones ────────────────────────────────────────────────

    public function recibirDerivacion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contacto'   => 'required|string|max:100',
            'texto'      => 'required|string|max:5000',
            'codigo'     => 'required|string|max:50',
            'en_horario' => 'required|boolean',
            'es_prueba'  => 'boolean',
            'resumen_llm'=> 'nullable|string|max:1000',
            'timestamp'  => 'required|string',
        ]);

        $derivacion = Derivacion::create([
            'contacto'    => $data['contacto'],
            'texto'       => $data['texto'],
            'codigo'      => $data['codigo'],
            'en_horario'  => $data['en_horario'],
            'es_prueba'   => $data['es_prueba'] ?? false,
            'resumen_llm' => $data['resumen_llm'] ?? null,
            'estado'      => 'pendiente',
            'bot_at'      => now(),
        ]);

        // Agregar nota interna a la conversación WA para que la secretaria vea qué hizo el bot
        $conv = ConversacionWA::where('contacto', $data['contacto'])->first();
        if ($conv) {
            $etiqueta = Derivacion::$etiquetas[$data['codigo']] ?? $data['codigo'];
            $nota     = "🤖 Bot derivó como: {$etiqueta}";
            if ($data['resumen_llm'] ?? null) {
                $nota .= "\n{$data['resumen_llm']}";
            }
            MensajeWA::create([
                'conversacion_id' => $conv->id,
                'direccion'       => 'nota_interna',
                'tipo'            => 'texto',
                'contenido'       => $nota,
                'leido'           => false,
            ]);
        }

        return response()->json(['ok' => true, 'id' => $derivacion->id], 201);
    }

    public function listarDerivaciones(): JsonResponse
    {
        $derivaciones = Derivacion::pendientes()
            ->select('id', 'contacto', 'texto', 'codigo', 'en_horario', 'estado', 'bot_at', 'created_at')
            ->get()
            ->map(fn($d) => [
                'id'         => $d->id,
                'telefono'   => $d->telefono,
                'texto'      => $d->texto,
                'codigo'     => $d->codigo,
                'etiqueta'   => $d->etiqueta,
                'en_horario' => $d->en_horario,
                'bot_at'     => $d->bot_at?->toIso8601String(),
                'hace'       => $d->bot_at?->diffForHumans(),
            ]);

        return response()->json(['ok' => true, 'data' => $derivaciones]);
    }

    public function actualizarResumen(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['resumen_llm' => 'required|string|max:1000']);
        Derivacion::findOrFail($id)->update(['resumen_llm' => $data['resumen_llm']]);
        return response()->json(['ok' => true]);
    }

    public function actualizarDerivacion(Request $request, int $id): JsonResponse
    {
        $derivacion = Derivacion::findOrFail($id);

        $data = $request->validate([
            'estado' => 'required|in:pendiente,en_atencion,resuelto',
            'nota'   => 'nullable|string|max:1000',
        ]);

        $derivacion->update($data);

        if ($data['estado'] === 'resuelto') {
            $derivacion->update(['atendido_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }

    // ── Derivar conversación WA directamente ────────────────────────

    /**
     * El bot detectó que el mensaje requiere atención humana.
     * Agrega una nota interna con la clasificación y el resumen LLM.
     * POST /api/bot/conversacion/derivar
     */
    public function derivarConversacion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contacto'    => 'required|string|max:100',
            'area'        => 'nullable|in:atencion,administracion,ovodonacion',
            'codigo'      => 'required|string|max:50',
            'resumen_llm' => 'nullable|string|max:1000',
        ]);
        $area = $data['area'] ?? 'atencion';

        $conv = ConversacionWA::where('contacto', $data['contacto'])->where('area', $area)->first();
        if (!$conv) {
            return response()->json(['ok' => false, 'error' => 'Conversación no encontrada'], 404);
        }

        // Actualizar resumen LLM en la conversación
        if ($data['resumen_llm'] ?? null) {
            $conv->update(['resumen_llm' => $data['resumen_llm']]);
        }

        // Nota interna con la clasificación del bot
        $etiqueta = Derivacion::$etiquetas[$data['codigo']] ?? $data['codigo'];
        $nota     = "🤖 Bot clasificó como: {$etiqueta}";
        if ($data['resumen_llm'] ?? null) {
            $nota .= "\n{$data['resumen_llm']}";
        }

        MensajeWA::create([
            'conversacion_id' => $conv->id,
            'direccion'       => 'nota_interna',
            'tipo'            => 'texto',
            'contenido'       => $nota,
            'leido'           => false,
        ]);

        return response()->json(['ok' => true]);
    }

    // ── Historial LLM ───────────────────────────────────────────────

    /**
     * Recuperar historial de conversación para el modelo LLM.
     * GET /api/bot/conversacion/historial?contacto=xxx
     */
    public function obtenerHistorial(Request $request): JsonResponse
    {
        $contacto = $request->query('contacto');
        $area     = $request->query('area', 'atencion');
        $conv = ConversacionWA::where('contacto', $contacto)->where('area', $area)->first();
        return response()->json(['ok' => true, 'historial' => $conv?->historial_llm ?? '']);
    }

    /**
     * Persistir historial de conversación actualizado.
     * PATCH /api/bot/conversacion/historial
     */
    public function guardarHistorial(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contacto'  => 'required|string|max:100',
            'area'      => 'nullable|in:atencion,administracion,ovodonacion',
            'historial' => 'required|string|max:5000',
        ]);
        $area = $data['area'] ?? 'atencion';

        ConversacionWA::where('contacto', $data['contacto'])->where('area', $area)
            ->update(['historial_llm' => $data['historial']]);

        return response()->json(['ok' => true]);
    }

    // ── Mensajes WA (inbox) ──────────────────────────────────────────

    /**
     * El bot llama este endpoint cada vez que recibe un mensaje de WhatsApp.
     * POST /api/bot/mensajes
     */
    public function mensajeEntrante(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contacto'    => 'required|string|max:100',
            'area'        => 'nullable|in:atencion,administracion,ovodonacion',
            'tipo'        => 'required|in:texto,audio,imagen,documento,video,sticker',
            'contenido'   => 'nullable|string|max:10000',
            'archivo_url' => 'nullable|string|max:500',
            'wa_id'       => 'nullable|string|max:150',
            // Reply (solo lectura): si el mensaje cita otro, el bot manda el wa_id +
            // preview del original para que el panel renderee el bubble citado arriba.
            'quoted_wa_id'   => 'nullable|string|max:150',
            'quoted_autor'   => 'nullable|string|max:80',
            'quoted_preview' => 'nullable|string|max:300',
            'timestamp'   => 'required|string',
        ]);
        $area = $data['area'] ?? 'atencion';

        // Deduplicar por wa_id
        if (($data['wa_id'] ?? null) && MensajeWA::where('wa_id', $data['wa_id'])->exists()) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        // Buscar o crear conversación para este contacto (por área = número de WA)
        $conv = ConversacionWA::firstOrCreate(
            ['contacto' => $data['contacto'], 'area' => $area],
            ['estado' => 'activa', 'ultima_actividad' => now()]
        );

        // Re-activar si estaba archivada (el contacto vuelve a escribir)
        if ($conv->estado === 'archivada') {
            $conv->update(['estado' => 'activa']);
        }

        // Resolver contacto del directorio. Lo buscamos siempre (no solo cuando la
        // conv no tiene nombre) porque también lo usamos abajo para indexar al
        // legajo del paciente. Si lo limitamos a "conv sin nombre", los archivos
        // de conversaciones recurrentes quedan en documentos_paciente con
        // contacto_id=NULL — bug 06/05/2026.
        //
        // Para @lid NO se llama al bot inline (resolverNumeroDesdeJid). Cada
        // mensaje entrante de un @lid huérfano disparaba una call CDP al bot
        // atención, saturándolo cuando había ráfagas (incidente 19/05). El
        // matching de @lid sin contacto previo se hace ahora SOLO via el cron
        // diario `contactos:mapear-wa` (con --limit). El primer mensaje queda
        // con contacto_id=null hasta que el cron lo resuelva (24h máximo).
        $contacto = Contacto::buscarPorContacto($data['contacto']);
        if ($contacto) {
            // Si la conv todavía no tiene nombre, poblarlo desde el directorio.
            if (!$conv->nombre) {
                $conv->update(['nombre' => $contacto->nombre]);
            }
            // Si el contacto matcheó pero no tiene avatar (o expiró), agendar sync.
            // No bloqueante: lo intentamos inline pero con timeout corto via el helper.
            if ($contacto->avatarNecesitaSync()) {
                Contacto::sincronizarAvatar($contacto);
            }
        }

        $msg = MensajeWA::create([
            'conversacion_id' => $conv->id,
            'direccion'       => 'entrante',
            'tipo'            => $data['tipo'],
            'contenido'       => $data['contenido'] ?? null,
            'archivo_url'     => $data['archivo_url'] ?? null,
            'wa_id'           => $data['wa_id'] ?? null,
            'quoted_wa_id'    => $data['quoted_wa_id']   ?? null,
            'quoted_autor'    => $data['quoted_autor']   ?? null,
            'quoted_preview'  => $data['quoted_preview'] ?? null,
            'leido'           => false,
        ]);

        // Auto-indexar al legajo si trae archivo. Las URLs del bot tienen forma
        // http://.../media/<filename>; el archivo está en /bot-media (bind mount RO).
        if (!empty($data['archivo_url']) && in_array($data['tipo'], ['imagen', 'documento', 'audio', 'video'], true)) {
            $filename = basename(parse_url($data['archivo_url'], PHP_URL_PATH) ?? '');
            $srcAbs   = '/bot-media/' . $filename;
            if ($filename && file_exists($srcAbs)) {
                try {
                    \App\Services\LegajoStorage::indexar($srcAbs, [
                        'contacto_id'     => $contacto?->id,
                        'conversacion_id' => $conv->id,
                        'mensaje_id'      => $msg->id,
                        'direccion'       => 'entrante',
                        'mime'            => mime_content_type($srcAbs) ?: 'application/octet-stream',
                        'nombre_original' => $filename,
                    ]);
                } catch (\Exception $e) {
                    \Log::warning('Legajo indexar entrante fallo', ['msg' => $msg->id, 'err' => $e->getMessage()]);
                }
            }
        }

        $conv->update([
            'ultima_actividad' => now(),
            'no_leidos'        => $conv->no_leidos + 1,
        ]);

        // Invalidar cache de la cola para que la nueva conversación / mensaje aparezca al toque.
        ConversacionWA::invalidarColaCache();

        // Despachar resumen LLM si la conv amerita. Asincrónico (queue 'resumen' separada),
        // no bloquea esta request. ameritaResumen filtra saludos sueltos y "ok/gracias".
        $conv->refresh()->despacharResumenSiAmerita();

        return response()->json(['ok' => true], 201);
    }

    /**
     * El bot respondió automáticamente: marcar mensajes entrantes como leídos.
     * Así la conversación no aparece como "nueva" en la cola.
     * POST /api/bot/mensajes/marcar-leido
     */
    public function marcarLeido(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contacto' => 'required|string|max:100',
            'area'     => 'nullable|in:atencion,administracion,ovodonacion',
        ]);
        $area = $data['area'] ?? 'atencion';

        $conv = ConversacionWA::where('contacto', $data['contacto'])->where('area', $area)->first();
        if ($conv) {
            MensajeWA::where('conversacion_id', $conv->id)
                ->where('leido', false)
                ->update(['leido' => true]);
            $conv->update(['no_leidos' => 0]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * El bot llama este endpoint cuando envía una respuesta automática en producción.
     * POST /api/bot/mensajes/saliente
     */
    public function mensajeSaliente(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contacto'    => 'required|string|max:100',
            'area'        => 'nullable|in:atencion,administracion,ovodonacion',
            'tipo'        => 'nullable|in:texto,audio,imagen,documento,video,sticker',
            'contenido'   => 'nullable|string|max:10000',
            'archivo_url' => 'nullable|string|max:500',
            'wa_id'       => 'nullable|string|max:150',
            'quoted_wa_id'   => 'nullable|string|max:150',
            'quoted_autor'   => 'nullable|string|max:80',
            'quoted_preview' => 'nullable|string|max:300',
            'timestamp'   => 'required|string',
        ]);
        $area = $data['area'] ?? 'atencion';

        // Dedup: si llega el mismo wa_id 2 veces (ej: Laravel guardó al enviar
        // via /enviar y ahora vuelve por message_create del bot), no duplicamos.
        if (($data['wa_id'] ?? null) && MensajeWA::where('wa_id', $data['wa_id'])->exists()) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        $conv = ConversacionWA::firstOrCreate(
            ['contacto' => $data['contacto'], 'area' => $area],
            ['estado' => 'activa', 'ultima_actividad' => now()]
        );

        MensajeWA::create([
            'conversacion_id' => $conv->id,
            'direccion'       => 'saliente',
            'tipo'            => $data['tipo']        ?? 'texto',
            'contenido'       => $data['contenido']   ?? null,
            'archivo_url'     => $data['archivo_url'] ?? null,
            'wa_id'           => $data['wa_id']       ?? null,
            'quoted_wa_id'    => $data['quoted_wa_id']   ?? null,
            'quoted_autor'    => $data['quoted_autor']   ?? null,
            'quoted_preview'  => $data['quoted_preview'] ?? null,
            'leido'           => true,
        ]);

        $conv->update(['ultima_actividad' => now()]);
        ConversacionWA::invalidarColaCache();

        return response()->json(['ok' => true], 201);
    }
}
