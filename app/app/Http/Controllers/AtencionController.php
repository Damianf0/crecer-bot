<?php

namespace App\Http\Controllers;

use App\Models\ConversacionEvento;
use App\Models\ConversacionWA;
use App\Models\Derivacion;
use App\Models\MensajeWA;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AtencionController extends Controller
{
    // Whitelist de mimetypes aceptados al enviar adjuntos por WhatsApp.
    public const MIMETYPES_PERMITIDOS = [
        // Imágenes
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        // Audio
        'audio/mpeg', 'audio/mp3', 'audio/ogg', 'audio/wav', 'audio/x-wav',
        'audio/mp4', 'audio/x-m4a', 'audio/aac', 'audio/webm',
        // Video
        'video/mp4', 'video/webm', 'video/quicktime', 'video/3gpp',
        // Documentos
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain', 'text/csv',
    ];

    // Extensiones bloqueadas aunque el mimetype haya pasado (defensa en profundidad)
    public const EXTENSIONES_BLOQUEADAS = [
        'exe', 'bat', 'cmd', 'com', 'msi', 'scr', 'vbs', 'js', 'jar',
        'ps1', 'psm1', 'sh', 'bash', 'app', 'dmg', 'apk', 'dll', 'so',
        'php', 'phtml', 'phar', 'asp', 'aspx', 'jsp',
    ];

    public static function mimetypesPermitidos(): array
    {
        return self::MIMETYPES_PERMITIDOS;
    }

    public static function extensionBloqueada(string $nombre): ?string
    {
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        return in_array($ext, self::EXTENSIONES_BLOQUEADAS, true) ? $ext : null;
    }

    public function index(string $area = 'atencion')
    {
        $area = isset(ConversacionWA::AREAS[$area]) ? $area : 'atencion';
        $usuarios  = User::where('activo', true)->orderBy('nombre_completo')->get(['id', 'nombre_completo']);
        $itemsData = $this->buildItems($area);
        $areaLabel = ConversacionWA::AREAS[$area];
        return view('atencion.index', compact('usuarios', 'itemsData', 'area', 'areaLabel'));
    }

    public function items(Request $request, string $area = 'atencion')
    {
        $area    = isset(ConversacionWA::AREAS[$area]) ? $area : 'atencion';
        $payload = $this->buildItems($area);
        $json    = json_encode($payload);
        $etag    = '"' . substr(md5($json), 0, 16) . '"';

        // 304 Not Modified si el cliente ya tiene el mismo payload — evita re-render del frontend.
        if ($request->header('If-None-Match') === $etag) {
            return response('', 304)->header('ETag', $etag);
        }

        return response($json, 200, [
            'Content-Type'  => 'application/json',
            'ETag'          => $etag,
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    private function buildItems(string $area = 'atencion'): array
    {
        // Cache 3s del payload completo: con N secretarias polleando cada 8s,
        // amortiza ~70% de las queries sin que se note la latencia.
        // Clave por área (la cola de cada número es independiente).
        return \Illuminate\Support\Facades\Cache::remember("atencion.items.{$area}", 3, function () use ($area) {
            return $this->buildItemsRaw($area);
        });
    }

    private function buildItemsRaw(string $area = 'atencion'): array
    {
        $qNuevas = ConversacionWA::where('estado', 'activa')
            ->where('area', $area)
            ->whereNull('asignada_a')
            ->where('no_leidos', '>', 0);

        $qProceso = ConversacionWA::where('estado', 'activa')
            ->where('area', $area)
            ->whereNotNull('asignada_a');

        $totalNuevas  = (clone $qNuevas)->count();
        $totalProceso = (clone $qProceso)->count();

        $nuevasCol = $qNuevas
            ->with(['ultimoMensaje', 'asignadaA:id,nombre_completo'])
            ->orderByDesc('urgente')
            ->orderByDesc('ultima_actividad')
            ->limit(300)
            ->get();

        $procesoCol = $qProceso
            ->with(['ultimoMensaje', 'asignadaA:id,nombre_completo'])
            ->orderByDesc('urgente')
            ->orderByDesc('ultima_actividad')
            ->limit(300)
            ->get();

        // Lookup bulk: JID → Contacto. Cubre los dos paths que hace buscarPorContacto:
        // (a) match por wa_id directo, (b) fallback por teléfono cuando es @c.us.
        $lookup = $this->resolverContactosBulk(
            $nuevasCol->pluck('contacto')->merge($procesoCol->pluck('contacto'))->unique()->all()
        );

        return [
            'nuevas'        => $nuevasCol->map(fn($c) => $this->mapWA($c, $lookup))->values(),
            'enProceso'     => $procesoCol->map(fn($c) => $this->mapWA($c, $lookup))->values(),
            'total_nuevas'  => $totalNuevas,
            'total_proceso' => $totalProceso,
        ];
    }

    /**
     * Resuelve un set de JIDs a contactos en a lo sumo 2 queries.
     * Devuelve un array indexado por JID con `[id, avatar_path]`.
     */
    private function resolverContactosBulk(array $jids): array
    {
        if (empty($jids)) return [];

        $lookup = [];

        // Path 1: match por wa_id (cubre @lid y @c.us con wa_id poblado)
        \App\Models\Contacto::whereIn('wa_id', $jids)
            ->get(['id', 'wa_id', 'avatar_path'])
            ->each(function ($c) use (&$lookup) {
                $lookup[$c->wa_id] = ['id' => $c->id, 'avatar_path' => $c->avatar_path];
            });

        // Path 2: fallback para @c.us no resueltos arriba — buscar por teléfono
        $pendientes = collect($jids)
            ->filter(fn($j) => str_ends_with($j, '@c.us') && !isset($lookup[$j]))
            ->mapWithKeys(fn($j) => [str_replace('@c.us', '', $j) => $j])
            ->all();

        if (!empty($pendientes)) {
            \App\Models\Contacto::whereIn('telefono', array_keys($pendientes))
                ->get(['id', 'telefono', 'avatar_path'])
                ->each(function ($c) use (&$lookup, $pendientes) {
                    $jid = $pendientes[$c->telefono] ?? null;
                    if ($jid) $lookup[$jid] = ['id' => $c->id, 'avatar_path' => $c->avatar_path];
                });
        }

        return $lookup;
    }

    public function conversacion(int $id, Request $request): JsonResponse
    {
        $conv = ConversacionWA::findOrFail($id);

        // Paginación: por default últimos 100 mensajes. Si llega ?before_id=N, cargar
        // 100 anteriores (para "Ver más antiguos").
        $beforeId = (int) $request->input('before_id', 0);
        $limit    = 100;

        $q = MensajeWA::where('conversacion_id', $id);
        if ($beforeId > 0) {
            $q->where('id', '<', $beforeId);
        }

        // Trae los últimos N en orden DESC (más recientes primero) y los invierte para
        // mantener el orden cronológico ASC en la respuesta.
        $msgsCol = $q->orderByDesc('id')->limit($limit)->get();
        $hasOlder = MensajeWA::where('conversacion_id', $id)
            ->when($msgsCol->isNotEmpty(), fn($qq) => $qq->where('id', '<', $msgsCol->last()->id))
            ->exists();

        // Resolver nombres de usuarios en una sola query (evita N+1).
        $userIds = $msgsCol->pluck('usuario_id')->filter()->unique();
        $userMap = $userIds->isNotEmpty()
            ? \App\Models\User::whereIn('id', $userIds)->pluck('nombre_completo', 'id')
            : collect();

        $msgs = $msgsCol->reverse()->values()->map(fn($m) => [
            'id'          => $m->id,
            'direccion'   => $m->direccion,
            'tipo'        => $m->tipo,
            'contenido'   => $m->contenido,
            'archivo_url' => $m->archivo_url,
            'hora'        => $m->created_at->format('H:i'),
            'fecha'       => $m->created_at->format('d/m/Y'),
            'ts'          => $m->created_at->timestamp,
            'usuario'     => $m->usuario_id ? ($userMap[$m->usuario_id] ?? null) : null,
            'wa_id'       => $m->wa_id,
        ]);

        // Cuando se piden mensajes anteriores (scroll back), no es necesario re-mandar
        // los eventos ni los datos de la conv — devolver solo el array de mensajes.
        if ($beforeId > 0) {
            return response()->json([
                'mensajes'  => $msgs,
                'has_older' => $hasOlder,
            ]);
        }

        $eventos = ConversacionEvento::where('conversacion_id', $id)
            ->with(['usuario:id,nombre_completo', 'usuarioDestino:id,nombre_completo'])
            ->orderBy('created_at')
            ->get()
            ->map(fn($e) => [
                'tipo'    => $e->tipo,
                'usuario' => $e->usuario?->nombre_completo,
                'destino' => $e->usuarioDestino?->nombre_completo,
                'fecha'   => $e->created_at->format('d/m/Y H:i'),
                'hora'    => $e->created_at->format('H:i'),
                'ts'      => $e->created_at->timestamp,
                'hace'    => $e->created_at->diffForHumans(),
            ]);

        // Determinar si la conversación está vinculada con un contacto del directorio.
        // Si NO, el frontend muestra un botón "Agregar a contactos".
        $contactoMatch = \App\Models\Contacto::buscarPorContacto($conv->contacto);
        $esHuerfana    = !$contactoMatch;
        $avatarUrl     = $contactoMatch?->avatar_path
            ? asset('storage/' . $contactoMatch->avatar_path)
            : null;

        // Para sugerir el número en el form de agregado: si el JID es @c.us extraemos
        // el número directamente; si es @lid, intentamos resolverlo via bot.
        $telefonoSugerido = null;
        if ($esHuerfana) {
            if (str_ends_with($conv->contacto, '@c.us')) {
                $telefonoSugerido = str_replace('@c.us', '', $conv->contacto);
            } else if (str_ends_with($conv->contacto, '@lid')) {
                $num = \App\Models\Contacto::resolverNumeroDesdeJid($conv->contacto);
                if ($num) $telefonoSugerido = \App\Models\Contacto::normalizarTelefono($num);
            }
        }

        return response()->json([
            'conv'      => [
                'id'                => $conv->id,
                'contacto'          => $conv->nombreOTelefono,
                'telefono'          => $conv->telefono,
                'asig_id'           => $conv->asignada_a,
                'asig_name'         => $conv->asignadaA?->nombre_completo,
                'resumen'           => $conv->resumen_llm,
                'es_huerfana'       => $esHuerfana,
                'jid'               => $conv->contacto,
                'telefono_sugerido' => $telefonoSugerido,
                'avatar_url'        => $avatarUrl,
                'contacto_id'       => $contactoMatch?->id,
            ],
            'mensajes'  => $msgs,
            'has_older' => $hasOlder,
            'eventos'   => $eventos,
        ]);
    }

    /**
     * Agrega un contacto al directorio desde una conversación huérfana y vincula
     * la conv (escribe nombre y wa_id en su lugar).
     * POST /atencion/conversacion/{id}/agregar-contacto  body: { nombre, telefono, dni? }
     */
    public function agregarContactoDesdeConv(int $id, Request $request): JsonResponse
    {
        $conv = ConversacionWA::findOrFail($id);

        $data = $request->validate([
            'nombre'   => 'required|string|max:150',
            'telefono' => 'required|string|max:30',
            'dni'      => 'nullable|string|max:20',
        ]);

        $tel = preg_replace('/\D/', '', $data['telefono']);
        if (!$tel) {
            return response()->json(['ok' => false, 'error' => 'Teléfono inválido'], 422);
        }

        // Evitar duplicados
        if (\App\Models\Contacto::where('telefono', $tel)->exists()) {
            return response()->json(['ok' => false, 'error' => 'Ya existe un contacto con ese teléfono'], 422);
        }

        $contacto = \App\Models\Contacto::create([
            'nombre'   => $data['nombre'],
            'telefono' => $tel,
            'dni'      => $data['dni'] ?: null,
            'wa_id'    => $conv->contacto,  // ya tenemos el JID exacto
        ]);

        // Vincular la conversación: poblar el nombre para que deje de ser huérfana
        $conv->update(['nombre' => $contacto->nombre]);

        ConversacionWA::invalidarColaCache();

        return response()->json(['ok' => true, 'contacto_id' => $contacto->id]);
    }

    public function derivacion(int $id): JsonResponse
    {
        $d = Derivacion::findOrFail($id);

        // Buscar conversación WA asociada al mismo contacto
        $conv = ConversacionWA::where('contacto', $d->contacto)->first();

        return response()->json([
            'id'         => $d->id,
            'contacto'   => $d->telefono,
            'texto'      => $d->texto,
            'etiqueta'   => $d->etiqueta,
            'codigo'     => $d->codigo,
            'resumen'    => $d->resumen_llm,
            'en_horario' => $d->en_horario,
            'es_prueba'  => $d->es_prueba,
            'hace'       => $d->bot_at?->diffForHumans(),
            'conv_id'    => $conv?->id,
        ]);
    }

    public function tomar(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => 'required|integer', 'tipo' => 'required|in:bot,wa']);
        $uid  = Auth::id();

        if ($data['tipo'] === 'bot') {
            $item = Derivacion::findOrFail($data['id']);
            $item->update(['asignada_a' => $uid, 'estado' => 'en_atencion']);
        } else {
            $item = ConversacionWA::findOrFail($data['id']);
            $item->update(['asignada_a' => $uid]);
            MensajeWA::where('conversacion_id', $item->id)->update(['leido' => true]);
            $item->update(['no_leidos' => 0]);
            $this->logEvento($item->id, 'tomada');
        }

        return response()->json(['ok' => true, 'asig_name' => Auth::user()->nombre_completo]);
    }

    public function delegar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id'      => 'required|integer',
            'tipo'    => 'required|in:bot,wa',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        if ($data['tipo'] === 'bot') {
            Derivacion::findOrFail($data['id'])->update([
                'asignada_a' => $data['user_id'],
                'estado'     => 'en_atencion',
            ]);
        } else {
            ConversacionWA::findOrFail($data['id'])->update(['asignada_a' => $data['user_id']]);
            $this->logEvento($data['id'], 'delegada', $data['user_id']);
        }

        $nombre = User::find($data['user_id'])?->nombre_completo ?? '';
        return response()->json(['ok' => true, 'asig_name' => $nombre]);
    }

    public function urgente(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => 'required|integer', 'tipo' => 'required|in:bot,wa']);

        $item = $data['tipo'] === 'bot'
            ? Derivacion::findOrFail($data['id'])
            : ConversacionWA::findOrFail($data['id']);

        $item->update(['urgente' => !$item->urgente]);

        if ($data['tipo'] === 'wa') {
            $this->logEvento($item->id, $item->urgente ? 'urgente_on' : 'urgente_off');
        }

        return response()->json(['ok' => true, 'urgente' => $item->urgente]);
    }

    public function resolver(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => 'required|integer', 'tipo' => 'required|in:bot,wa']);

        if ($data['tipo'] === 'bot') {
            Derivacion::findOrFail($data['id'])->update(['estado' => 'resuelto', 'atendido_at' => now()]);
        } else {
            $this->logEvento($data['id'], 'resuelta');
            ConversacionWA::findOrFail($data['id'])->update([
                'estado'     => 'archivada',
                'asignada_a' => null,
                'urgente'    => false,
            ]);
        }

        return response()->json(['ok' => true]);
    }

    public function misTareas()
    {
        $uid = Auth::id();

        $items = ConversacionWA::where('estado', 'activa')
            ->where('asignada_a', $uid)
            ->with(['ultimoMensaje', 'asignadaA:id,nombre_completo', 'contactoVinculado:id,wa_id,avatar_path'])
            ->orderByDesc('urgente')
            ->orderByDesc('ultima_actividad')
            ->limit(300)
            ->get()
            ->map(fn($c) => $this->mapWA($c))
            ->values();

        $usuarios = User::where('activo', true)->orderBy('nombre_completo')->get(['id', 'nombre_completo']);

        $conversaciones = ConversacionWA::where('estado', 'activa')
            ->orderByDesc('ultima_actividad')
            ->limit(50)
            ->get()
            ->map(fn($c) => ['id' => $c->id, 'label' => $c->nombreOTelefono . ' — ' . $c->telefono]);

        return view('atencion.mis-tareas', compact('items', 'usuarios', 'conversaciones'));
    }

    public function misTareasData(): JsonResponse
    {
        $uid = Auth::id();

        $items = ConversacionWA::where('estado', 'activa')
            ->where('asignada_a', $uid)
            ->with(['ultimoMensaje', 'asignadaA:id,nombre_completo', 'contactoVinculado:id,wa_id,avatar_path'])
            ->orderByDesc('urgente')
            ->orderByDesc('ultima_actividad')
            ->limit(300)
            ->get()
            ->map(fn($c) => $this->mapWA($c))
            ->values();

        return response()->json(['ok' => true, 'data' => $items]);
    }

    public function reabrir(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => 'required|integer', 'tipo' => 'required|in:bot,wa']);

        if ($data['tipo'] === 'bot') {
            Derivacion::findOrFail($data['id'])->update(['estado' => 'pendiente', 'atendido_at' => null, 'asignada_a' => null]);
        } else {
            ConversacionWA::findOrFail($data['id'])->update(['estado' => 'activa', 'asignada_a' => null]);
            $this->logEvento($data['id'], 'reabierta');
        }

        return response()->json(['ok' => true]);
    }

    public function historial(Request $request)
    {
        $desde = $request->input('desde')
            ? \Carbon\Carbon::parse($request->input('desde'))->startOfDay()
            : null;
        $hasta = $request->input('hasta')
            ? \Carbon\Carbon::parse($request->input('hasta'))->endOfDay()
            : null;
        $tipo  = $request->input('tipo', 'todos'); // todos | bot | wa | tarea
        $q     = $request->input('q', '');

        $items = collect();

        if ($tipo === 'tarea' || $tipo === 'todos') {
            $query = Tarea::where('estado', 'completada')
                ->with(['asignadaA:id,nombre_completo', 'creadaPor:id,nombre_completo', 'comentarios.user:id,nombre_completo'])
                ->orderByDesc('updated_at');
            if ($desde) $query->where('updated_at', '>=', $desde);
            if ($hasta) $query->where('updated_at', '<=', $hasta);
            if ($q)     $query->where('titulo', 'like', "%{$q}%");

            $items = $items->concat(
                $query->limit(200)->get()->map(fn($t) => [
                    'id'          => $t->id,
                    'tipo'        => 'tarea',
                    'contacto'    => $t->titulo,
                    'etiqueta'    => 'Tarea',
                    'resumen'     => $t->descripcion ? Str::limit($t->descripcion, 120) : '—',
                    'asig_name'   => $t->asignadaA?->nombre_completo,
                    'creado_por'  => $t->creadaPor?->nombre_completo,
                    'prioridad'   => $t->prioridad,
                    'resuelto_at' => $t->updated_at?->format('d/m/Y H:i'),
                    'ts'          => $t->updated_at?->timestamp ?? 0,
                    'comentarios' => $t->comentarios->map(fn($c) => [
                        'usuario'   => $c->user?->nombre_completo,
                        'contenido' => $c->contenido,
                        'hora'      => $c->created_at->format('d/m H:i'),
                    ])->toArray(),
                ])
            );
        }

        if ($tipo !== 'wa' && $tipo !== 'tarea') {
            $query = Derivacion::where('estado', 'resuelto')
                ->with('asignadaA:id,nombre_completo')
                ->orderByDesc('atendido_at');
            if ($desde) $query->where('atendido_at', '>=', $desde);
            if ($hasta) $query->where('atendido_at', '<=', $hasta);
            if ($q)     $query->where('contacto', 'like', "%{$q}%");

            $items = $items->concat(
                $query->limit(200)->get()->map(fn($d) => [
                    'id'          => $d->id,
                    'tipo'        => 'bot',
                    'contacto'    => $d->telefono,
                    'etiqueta'    => $d->etiqueta,
                    'resumen'     => $d->resumen_llm ?: Str::limit($d->texto, 120),
                    'texto'       => $d->texto,
                    'asig_name'   => $d->asignadaA?->nombre_completo,
                    'resuelto_at' => $d->atendido_at?->format('d/m/Y H:i'),
                    'ts'          => $d->atendido_at?->timestamp ?? 0,
                ])
            );
        }

        if ($tipo !== 'bot' && $tipo !== 'tarea') {
            $query = ConversacionWA::where('estado', 'archivada')
                ->with(['ultimoMensaje', 'asignadaA:id,nombre_completo', 'contactoVinculado:id,wa_id,avatar_path'])
                ->orderByDesc('updated_at');
            if ($desde) $query->where('updated_at', '>=', $desde);
            if ($hasta) $query->where('updated_at', '<=', $hasta);
            if ($q)     $query->where('contacto', 'like', "%{$q}%");

            $items = $items->concat(
                $query->limit(200)->get()->map(fn($c) => [
                    'id'          => $c->id,
                    'tipo'        => 'wa',
                    'contacto'    => $c->nombreOTelefono,
                    'etiqueta'    => 'WhatsApp',
                    'resumen'     => $c->resumen_llm ?: ($c->ultimoMensaje?->snippet ?? '—'),
                    'asig_name'   => $c->asignadaA?->nombre_completo,
                    'resuelto_at' => $c->updated_at?->format('d/m/Y H:i'),
                    'ts'          => $c->updated_at?->timestamp ?? 0,
                ])
            );
        }

        $items = $items->sortByDesc('ts')->values();

        // Paginación en memoria sobre la colección mergeada (3 fuentes con tope 200 cada una).
        $perPage = max(10, min((int) $request->input('per_page', 50), 200));
        $page    = max(1, (int) $request->input('page', 1));
        $total   = $items->count();
        $pages   = max(1, (int) ceil($total / $perPage));
        $items   = $items->slice(($page - 1) * $perPage, $perPage)->values();

        if ($request->wantsJson()) {
            return response()->json([
                'ok'    => true,
                'data'  => $items,
                'total' => $total,
                'page'  => $page,
                'pages' => $pages,
            ]);
        }

        $usuarios = User::where('activo', true)->orderBy('nombre_completo')->get(['id', 'nombre_completo']);

        return view('atencion.historial', compact('items', 'desde', 'hasta', 'tipo', 'q', 'usuarios', 'page', 'pages', 'total', 'perPage'));
    }

    /**
     * Inicia una conversación nueva con un contacto.
     * Acepta { telefono, texto } o { contacto_id, texto }.
     * Verifica con el bot que el número tenga WhatsApp antes de mandar.
     * Si ya existe ConversacionWA activa con ese contacto, la reutiliza.
     */
    public function iniciarConversacion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'telefono'    => 'nullable|string|max:30',
            'contacto_id' => 'nullable|integer|exists:contactos,id',
            'texto'       => 'required|string|max:5000',
            'area'        => 'nullable|in:atencion,administracion,ovodonacion',
        ]);
        $area = $data['area'] ?? 'atencion';

        if (!($data['telefono'] ?? null) && !($data['contacto_id'] ?? null)) {
            return response()->json(['ok' => false, 'error' => 'Indicá teléfono o contacto'], 422);
        }

        // Resolver el teléfono a partir del contacto o del input directo
        $contactoModel = null;
        if (!empty($data['contacto_id'])) {
            $contactoModel = \App\Models\Contacto::find($data['contacto_id']);
            $telefonoRaw   = $contactoModel?->telefono ?? '';
        } else {
            $telefonoRaw = $data['telefono'];
        }

        $telefonoNorm = \App\Models\Contacto::normalizarTelefono($telefonoRaw);
        if (!$telefonoNorm) {
            return response()->json(['ok' => false, 'error' => 'Número inválido — debe ser argentino con 10 dígitos (ej: 1123456789 o 549...)'], 422);
        }

        // Verificar con el bot del área que el número tiene WhatsApp y obtener el ID normalizado.
        $botUrl = ConversacionWA::botUrlPara($area);
        $botTok = config('app.bot_ingress_token');
        try {
            $check = Http::timeout(15)
                ->withToken($botTok)
                ->post("{$botUrl}/check-numero", ['numero' => $telefonoNorm]);
            if (!$check->ok() || !$check->json('ok')) {
                return response()->json(['ok' => false, 'error' => 'No se pudo verificar el número con WhatsApp'], 502);
            }
            if (!$check->json('registered')) {
                return response()->json(['ok' => false, 'error' => 'Ese número no está registrado en WhatsApp'], 422);
            }
            $contactoWA = $check->json('normalizedId');
        } catch (\Exception) {
            return response()->json(['ok' => false, 'error' => 'No se pudo contactar al bot. Reintentá en unos segundos.'], 502);
        }

        // Reusar o crear conversación en el área desde la que se inicia (cada área = su número).
        $conv = ConversacionWA::firstOrNew(['contacto' => $contactoWA, 'area' => $area]);
        $esNueva = !$conv->exists;
        $conv->fill([
            'estado'           => 'activa',
            'asignada_a'       => Auth::id(),
            'ultima_actividad' => now(),
        ]);
        if ($esNueva) {
            $conv->no_leidos = 0;
        }
        if ($contactoModel?->nombre && empty($conv->nombre)) {
            $conv->nombre = $contactoModel->nombre;
        }
        $conv->save();

        // Mandar mensaje al bot — si falla, marcamos error sin guardar mensaje
        try {
            $resp = Http::timeout(30)
                ->withToken($botTok)
                ->post("{$botUrl}/enviar", [
                    'contacto' => $contactoWA,
                    'texto'    => $data['texto'],
                ]);
            if (!$resp->ok() || $resp->json('ok') !== true) {
                return response()->json([
                    'ok'    => false,
                    'error' => 'No se pudo enviar el primer mensaje. La conversación quedó creada y podés reintentar desde Atención.',
                    'conv_id' => $conv->id,
                ], 502);
            }
        } catch (\Exception) {
            return response()->json([
                'ok'    => false,
                'error' => 'No se pudo contactar al bot al enviar.',
                'conv_id' => $conv->id,
            ], 502);
        }

        MensajeWA::create([
            'conversacion_id' => $conv->id,
            'direccion'       => 'saliente',
            'tipo'            => 'texto',
            'contenido'       => $data['texto'],
            'usuario_id'      => Auth::id(),
            'leido'           => true,
        ]);

        $this->logEvento($conv->id, 'iniciada');

        return response()->json([
            'ok'      => true,
            'conv_id' => $conv->id,
            'reusada' => !$esNueva,
        ]);
    }

    /**
     * Deriva una conversación a otra área (otro número de WhatsApp).
     * Avisa al paciente por el número actual ("te van a responder desde +X"),
     * mueve la conversación a la cola del área destino (fusionando si ya existía
     * una conversación de ese contacto en esa área).
     * POST /atencion/conversacion/{id}/derivar-area  { area }
     */
    public function derivarArea(int $id, Request $request): JsonResponse
    {
        $data = $request->validate([
            'area' => 'required|in:atencion,administracion,ovodonacion',
        ]);
        $destino = $data['area'];
        $conv = ConversacionWA::findOrFail($id);

        if ($destino === $conv->area) {
            return response()->json(['ok' => false, 'error' => 'La conversación ya está en esa área.'], 422);
        }

        $origenLabel  = ConversacionWA::AREAS[$conv->area] ?? $conv->area;
        $destinoLabel = ConversacionWA::AREAS[$destino];
        $botTok       = config('app.bot_ingress_token');

        // Teléfono del bot destino, en vivo. Si no responde → mensaje genérico sin número.
        $telDestino = null;
        try {
            $r = Http::timeout(8)->withToken($botTok)->get(ConversacionWA::botUrlPara($destino) . '/status');
            if ($r->ok() && $r->json('phone')) $telDestino = $r->json('phone');
        } catch (\Throwable) {}

        $texto = $telDestino
            ? "Hola 👋 Tu consulta corresponde al área de *{$destinoLabel}*. Te derivamos con ese equipo — te van a responder desde el número +{$telDestino}. ¡Gracias!"
            : "Hola 👋 Tu consulta corresponde al área de *{$destinoLabel}*. Te derivamos con ese equipo y te van a responder a la brevedad. ¡Gracias!";

        // Avisar al paciente POR EL NÚMERO ACTUAL. Si esto falla, no movemos nada.
        $waId = null;
        try {
            $resp = Http::timeout(12)->withToken($botTok)->post($conv->botUrl() . '/enviar', [
                'contacto' => $conv->contacto,
                'texto'    => $texto,
            ]);
            if (!$resp->ok() || $resp->json('ok') !== true) {
                return response()->json(['ok' => false, 'error' => "No se pudo avisar al paciente (el bot de {$origenLabel} no respondió). No se derivó."], 502);
            }
            $waId = $resp->json('wa_id');
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'error' => "No se pudo contactar al bot de {$origenLabel}. No se derivó."], 502);
        }

        // Registrar el aviso como saliente (la conv todavía está en su área original).
        MensajeWA::create([
            'conversacion_id' => $conv->id,
            'direccion'       => 'saliente',
            'tipo'            => 'texto',
            'contenido'       => $texto,
            'wa_id'           => $waId,
            'usuario_id'      => Auth::id(),
            'leido'           => true,
        ]);

        // Mover al área destino. Si ya existe una conv (contacto, destino), fusionar.
        $existente = ConversacionWA::where('contacto', $conv->contacto)
            ->where('area', $destino)
            ->where('id', '!=', $conv->id)
            ->first();

        if ($existente) {
            MensajeWA::where('conversacion_id', $conv->id)->update(['conversacion_id' => $existente->id]);
            \App\Models\TareaWA::where('conversacion_id', $conv->id)->update(['conversacion_id' => $existente->id]);
            ConversacionEvento::where('conversacion_id', $conv->id)->update(['conversacion_id' => $existente->id]);
            $existente->update([
                'estado'           => 'activa',
                'no_leidos'        => $existente->no_leidos + $conv->no_leidos,
                'ultima_actividad' => now(),
                'asignada_a'       => null,
            ]);
            $conv->delete();
            $destinoConvId = $existente->id;
        } else {
            $conv->update([
                'area'             => $destino,
                'asignada_a'       => null,
                'estado'           => 'activa',
                'ultima_actividad' => now(),
            ]);
            $destinoConvId = $conv->id;
        }

        $this->logEvento($destinoConvId, 'derivada_area');
        ConversacionWA::invalidarColaCache();

        return response()->json([
            'ok'      => true,
            'destino' => $destino,
            'destino_label' => $destinoLabel,
            'mensaje' => "Derivada a {$destinoLabel}",
        ]);
    }

    public function enviarMensaje(Request $request): JsonResponse
    {
        $data = $request->validate([
            'conv_id' => 'required|integer',
            'texto'   => 'required|string|max:5000',
            'modo'    => 'required|in:mensaje,nota',
        ]);

        $conv = ConversacionWA::findOrFail($data['conv_id']);

        if ($data['modo'] === 'nota') {
            MensajeWA::create([
                'conversacion_id' => $conv->id,
                'direccion'       => 'nota_interna',
                'tipo'            => 'texto',
                'contenido'       => $data['texto'],
                'usuario_id'      => Auth::id(),
                'leido'           => true,
            ]);
        } else {
            // Enviar al bot del área de la conversación — si falla, no guardamos el
            // mensaje para que el usuario sepa que no llegó y pueda reintentar.
            $botUrl = $conv->botUrl();
            $botTok = config('app.bot_ingress_token');
            $waIdEnviado = null;
            try {
                $resp = Http::timeout(10)
                    ->withToken($botTok)
                    ->post("{$botUrl}/enviar", [
                        'contacto' => $conv->contacto,
                        'texto'    => $data['texto'],
                    ]);
                if (!$resp->ok() || $resp->json('ok') !== true) {
                    return response()->json([
                        'ok'    => false,
                        'error' => 'El bot no pudo enviar el mensaje. Verificá que esté conectado a WhatsApp.',
                    ], 502);
                }
                $waIdEnviado = $resp->json('wa_id');
            } catch (\Exception $e) {
                return response()->json([
                    'ok'    => false,
                    'error' => 'No se pudo contactar al bot. Reintentá en unos segundos.',
                ], 502);
            }

            MensajeWA::create([
                'conversacion_id' => $conv->id,
                'direccion'       => 'saliente',
                'tipo'            => 'texto',
                'contenido'       => $data['texto'],
                'wa_id'           => $waIdEnviado,
                'usuario_id'      => Auth::id(),
                'leido'           => true,
            ]);
            $conv->update(['ultima_actividad' => now()]);
        }

        return response()->json(['ok' => true]);
    }

    public function enviarArchivo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'conv_id' => 'required|integer',
            'caption' => 'nullable|string|max:1000',
        ]);
        $request->validate([
            'archivo' => [
                'required', 'file', 'max:20480',
                // Whitelist explícita de mimetypes — bloquea ejecutables y formatos riesgosos
                'mimetypes:' . implode(',', self::MIMETYPES_PERMITIDOS),
            ],
        ]);

        $conv    = ConversacionWA::findOrFail($data['conv_id']);
        $archivo = $request->file('archivo');
        $mime    = $archivo->getMimeType();
        $nombre  = $archivo->getClientOriginalName();

        // Bloquear extensiones peligrosas aunque el mimetype haya pasado
        $extPeligrosa = self::extensionBloqueada($nombre);
        if ($extPeligrosa) {
            return response()->json(['ok' => false, 'error' => "Extensión .{$extPeligrosa} no permitida"], 422);
        }

        $base64  = base64_encode(file_get_contents($archivo->getRealPath()));
        $caption = trim($data['caption'] ?? '');

        $tipo = match(true) {
            str_starts_with($mime, 'image/') => 'imagen',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            default                          => 'documento',
        };

        $localName  = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $nombre);
        $archivo->storeAs('public/wa-media', $localName);
        $archivoUrl = asset("storage/wa-media/{$localName}");

        $botUrl = $conv->botUrl();
        $botTok = config('app.bot_ingress_token');
        try {
            $resp = Http::timeout(30)
                ->withToken($botTok)
                ->post("{$botUrl}/enviar-archivo", [
                    'contacto' => $conv->contacto,
                    'base64'   => $base64,
                    'mimetype' => $mime,
                    'filename' => $nombre,
                    'caption'  => $caption,
                ]);
            if (!$resp->ok() || $resp->json('ok') !== true) {
                return response()->json([
                    'ok'    => false,
                    'error' => 'El bot no pudo enviar el archivo. Verificá la conexión a WhatsApp.',
                ], 502);
            }
        } catch (\Exception $e) {
            return response()->json([
                'ok'    => false,
                'error' => 'No se pudo contactar al bot. Reintentá en unos segundos.',
            ], 502);
        }

        $msg = MensajeWA::create([
            'conversacion_id' => $conv->id,
            'direccion'       => 'saliente',
            'tipo'            => $tipo,
            'contenido'       => $caption ?: $nombre,
            'archivo_url'     => $archivoUrl,
            'usuario_id'      => Auth::id(),
            'leido'           => true,
        ]);
        $conv->update(['ultima_actividad' => now()]);

        // Auto-indexar al legajo del paciente
        try {
            $contacto = \App\Models\Contacto::buscarPorContacto($conv->contacto);
            $srcAbs   = storage_path('app/public/wa-media/' . $localName);
            if (file_exists($srcAbs)) {
                \App\Services\LegajoStorage::indexar($srcAbs, [
                    'contacto_id'     => $contacto?->id,
                    'conversacion_id' => $conv->id,
                    'mensaje_id'      => $msg->id,
                    'direccion'       => 'saliente',
                    'usuario_id'      => Auth::id(),
                    'mime'            => $mime,
                    'nombre_original' => $nombre,
                ]);
            }
        } catch (\Exception $e) {
            \Log::warning('Legajo indexar saliente fallo', ['msg' => $msg->id, 'err' => $e->getMessage()]);
        }

        return response()->json(['ok' => true]);
    }

    // ── Helpers ────────────────────────────────────────────────────────

    private function logEvento(int $convId, string $tipo, ?int $destId = null): void
    {
        ConversacionEvento::create([
            'conversacion_id'    => $convId,
            'tipo'               => $tipo,
            'usuario_id'         => Auth::id(),
            'usuario_destino_id' => $destId,
        ]);
        // Cualquier evento sobre conversación → invalidar cache para que las demás
        // secretarias vean el cambio en su próximo poll (en lugar de esperar 3s).
        ConversacionWA::invalidarColaCache();
    }

    private function mapDer(Derivacion $d): array
    {
        return [
            'id'         => $d->id,
            'tipo'       => 'bot',
            'contacto'   => $d->telefono,
            'etiqueta'   => $d->etiqueta,
            'resumen'    => $d->resumen_llm ?: Str::limit($d->texto, 120),
            'urgente'    => (bool) $d->urgente,
            'asig_id'    => $d->asignada_a,
            'asig_name'  => $d->asignadaA?->nombre_completo,
            'hace'       => $d->bot_at?->diffForHumans(),
            'ts'         => $d->bot_at?->timestamp ?? 0,
            'es_prueba'  => (bool) $d->es_prueba,
            'estado'     => $d->estado,
        ];
    }

    private function mapWA(ConversacionWA $c, array $lookup = []): array
    {
        $ultimo = $c->ultimoMensaje;
        $hit    = $lookup[$c->contacto] ?? null;
        return [
            'id'          => $c->id,
            'tipo'        => 'wa',
            'contacto'    => $c->nombreOTelefono,
            'contacto_id' => $hit['id'] ?? null,
            'telefono'    => $c->telefono,
            'etiqueta'    => 'WhatsApp',
            'resumen'     => $c->resumen_llm ?: ($ultimo?->snippet ?? '—'),
            'urgente'     => (bool) $c->urgente,
            'asig_id'     => $c->asignada_a,
            'asig_name'   => $c->asignadaA?->nombre_completo,
            'hace'        => $c->ultima_actividad?->diffForHumans(),
            'ts'          => $c->ultima_actividad?->timestamp ?? 0,
            'no_leidos'   => $c->no_leidos,
            'estado'      => $c->estado,
            'avatar_url'  => !empty($hit['avatar_path']) ? asset('storage/' . $hit['avatar_path']) : null,
        ];
    }

    private function generarResumenSiNecesario($item, string $tipo): void
    {
        // Para conversaciones WA delegamos al despachador del modelo (job asincrónico).
        // No bloquea la apertura de la conv.
        if ($tipo === 'wa' && $item instanceof ConversacionWA) {
            $item->despacharResumenSiAmerita();
            return;
        }
        if ($item->resumen_llm) return;
        try {
            if ($tipo === 'bot') {
                $texto = $item->texto;
            } else {
                // Solo mensajes entrantes del paciente — el resumen no incluye salientes.
                $msgs  = MensajeWA::where('conversacion_id', $item->id)
                    ->where('direccion', 'entrante')
                    ->orderBy('created_at')->take(20)->get();
                $texto = $msgs->map(fn($m) => 'Paciente: ' . ($m->contenido ?? '[audio]'))->implode("\n");
            }
            if (!trim($texto)) return;
            $resp = Http::timeout(15)
                ->withToken(config('app.bot_ingress_token'))
                ->post(config('app.bot_url') . '/resumir', ['texto' => $texto]);
            if ($resp->ok() && $resp->json('resumen')) {
                $item->update(['resumen_llm' => $resp->json('resumen')]);
            }
        } catch (\Exception) {}
    }
}
