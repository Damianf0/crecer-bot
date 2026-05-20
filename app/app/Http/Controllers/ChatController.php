<?php

namespace App\Http\Controllers;

use App\Events\ChatMensajeEliminado;
use App\Events\ChatMensajeEnviado;
use App\Models\ChatCanal;
use App\Models\ChatMensaje;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    /** Umbral en segundos para considerar a un usuario "online" (visto recientemente). */
    private const PRESENCIA_TTL = 90;

    /**
     * Asegura que el usuario actual sea miembro del canal Equipo y devuelve el canal.
     */
    private function asegurarEquipo(): ChatCanal
    {
        $equipo = ChatCanal::equipo();
        $equipo->agregarMiembro(Auth::id());
        return $equipo;
    }

    /** Marca al usuario actual como "visto ahora". Llamado en endpoints de polling. */
    private function tocarPresencia(): void
    {
        if (!Auth::id()) return;
        DB::table('users')->where('id', Auth::id())->update(['last_seen_at' => now()]);
    }

    /** Set de IDs de usuarios online según TTL. */
    private function usuariosOnline(): array
    {
        return DB::table('users')
            ->where('last_seen_at', '>=', now()->subSeconds(self::PRESENCIA_TTL))
            ->pluck('id')
            ->all();
    }

    /**
     * GET /chat/canales — Lista los canales del usuario con preview del último mensaje
     * y cantidad de no leídos. Excluye los canales que el usuario "ocultó" SALVO que
     * tengan mensajes nuevos (esos los desocultamos automáticamente).
     */
    public function canales(): JsonResponse
    {
        $uid = Auth::id();
        $this->asegurarEquipo();

        $canales = ChatCanal::whereHas('miembros', fn($q) => $q->where('user_id', $uid))
            ->with(['miembros:id,nombre_completo'])
            ->get();

        $online = array_flip($this->usuariosOnline());

        $data = $canales->map(function ($canal) use ($uid, $online) {
            $pivot = $canal->miembros->firstWhere('id', $uid)?->pivot;
            $ultimoLeido = $pivot?->ultimo_leido_id ?? 0;
            $oculto      = (bool) ($pivot?->oculto ?? false);

            $ultimoMsg = ChatMensaje::where('canal_id', $canal->id)
                ->orderByDesc('id')->first();

            $noLeidos = ChatMensaje::where('canal_id', $canal->id)
                ->where('id', '>', $ultimoLeido)
                ->where('user_id', '!=', $uid)
                ->count();

            // Auto-reaparecer un canal oculto si entró un mensaje nuevo.
            if ($oculto && $noLeidos > 0) {
                DB::table('chat_canal_user')
                    ->where('canal_id', $canal->id)
                    ->where('user_id', $uid)
                    ->update(['oculto' => false, 'updated_at' => now()]);
                $oculto = false;
            }

            $nombre = $canal->nombre;
            $otroId = null;
            $otroOnline = null;
            if ($canal->tipo === 'dm') {
                $otro = $canal->miembros->firstWhere('id', '!=', $uid);
                $nombre = $otro?->nombre_completo ?? 'DM';
                $otroId = $otro?->id;
                $otroOnline = $otroId ? isset($online[$otroId]) : false;
            }

            return [
                'id'          => $canal->id,
                'tipo'        => $canal->tipo,
                'nombre'      => $nombre,
                'otro_id'     => $otroId,
                'otro_online' => $otroOnline,
                'oculto'      => $oculto,
                'no_leidos'   => $noLeidos,
                'ultimo_msg'  => $ultimoMsg ? [
                    'texto'   => mb_strimwidth((string) $ultimoMsg->texto, 0, 80, '…'),
                    'user_id' => $ultimoMsg->user_id,
                    'hora'    => $ultimoMsg->created_at?->format('H:i'),
                    'fecha'   => $ultimoMsg->created_at?->format('d/m'),
                    'ts'      => $ultimoMsg->created_at?->timestamp ?? 0,
                ] : null,
            ];
        })
        ->reject(fn($c) => $c['oculto']) // ocultos quedan fuera del listado
        // Ordenar: equipo primero, después DMs por última actividad descendente
        ->sortBy(fn($c) => $c['tipo'] === 'equipo' ? 0 : 1 - (($c['ultimo_msg']['ts'] ?? 0) / 1e12))
        ->values();

        return response()->json(['ok' => true, 'data' => $data]);
    }

    /**
     * GET /chat/canales/{id}/mensajes?since=ID
     * Devuelve los mensajes posteriores a `since` (default 0 → últimos 50).
     * Verifica que el usuario sea miembro del canal.
     * Mensajes eliminados (soft) se devuelven con `texto=null` y `eliminado=true`.
     */
    public function mensajes(int $canalId, Request $request): JsonResponse
    {
        $uid = Auth::id();
        $this->verificarMiembro($canalId, $uid);

        $since = (int) $request->input('since', 0);
        // withTrashed: mostramos los eliminados como placeholder para que la conversación no quede inconsistente.
        $q = ChatMensaje::withTrashed()->where('canal_id', $canalId);

        if ($since > 0) {
            $msgs = $q->where('id', '>', $since)->orderBy('id')->limit(200)->get();
        } else {
            $msgs = $q->orderByDesc('id')->limit(50)->get()->reverse()->values();
        }

        $autores = User::whereIn('id', $msgs->pluck('user_id')->unique())
            ->pluck('nombre_completo', 'id');

        $data = $msgs->map(fn($m) => [
            'id'        => $m->id,
            'user_id'   => $m->user_id,
            'autor'     => $autores[$m->user_id] ?? '?',
            'texto'     => $m->trashed() ? null : $m->texto,
            'eliminado' => $m->trashed(),
            'hora'      => $m->created_at?->format('H:i'),
            'fecha'     => $m->created_at?->format('d/m/Y'),
            'ts'        => $m->created_at?->timestamp ?? 0,
        ]);

        return response()->json(['ok' => true, 'data' => $data]);
    }

    /** POST /chat/canales/{id}/mensajes  body: { texto } */
    public function enviar(int $canalId, Request $request): JsonResponse
    {
        $uid = Auth::id();
        $this->verificarMiembro($canalId, $uid);

        $data = $request->validate(['texto' => 'required|string|max:4000']);

        $msg = ChatMensaje::create([
            'canal_id' => $canalId,
            'user_id'  => $uid,
            'texto'    => trim($data['texto']),
        ]);

        DB::table('chat_canal_user')
            ->where('canal_id', $canalId)
            ->where('user_id', $uid)
            ->update(['ultimo_leido_id' => $msg->id, 'updated_at' => now()]);

        // Broadcast a los demás miembros del canal. El emisor ya recibe el
        // mensaje en la respuesta HTTP, no necesita el evento (->toOthers()).
        $autor = User::find($uid)?->nombre_completo ?? '?';
        broadcast(new ChatMensajeEnviado($canalId, [
            'id'        => $msg->id,
            'user_id'   => $uid,
            'autor'     => $autor,
            'texto'     => $msg->texto,
            'eliminado' => false,
            'hora'      => $msg->created_at?->format('H:i'),
            'fecha'     => $msg->created_at?->format('d/m/Y'),
            'ts'        => $msg->created_at?->timestamp ?? 0,
        ]))->toOthers();

        return response()->json(['ok' => true, 'id' => $msg->id]);
    }

    /**
     * DELETE /chat/mensajes/{id} — soft-delete de un mensaje propio.
     * Solo el autor puede borrar.
     */
    public function eliminarMensaje(int $msgId): JsonResponse
    {
        $uid = Auth::id();
        $msg = ChatMensaje::findOrFail($msgId);
        if ($msg->user_id !== $uid) {
            abort(403, 'Solo podés eliminar tus propios mensajes.');
        }
        $canalId = $msg->canal_id;
        $msg->delete();

        // Notificar a los miembros del canal que el mensaje fue eliminado,
        // así actualizan su UI al placeholder gris en vivo.
        broadcast(new ChatMensajeEliminado($canalId, $msgId));

        return response()->json(['ok' => true]);
    }

    /** POST /chat/canales/{id}/marcar-leido */
    public function marcarLeido(int $canalId): JsonResponse
    {
        $uid = Auth::id();
        $this->verificarMiembro($canalId, $uid);

        $ultimoId = ChatMensaje::withTrashed()->where('canal_id', $canalId)->max('id') ?? 0;

        DB::table('chat_canal_user')
            ->where('canal_id', $canalId)
            ->where('user_id', $uid)
            ->update(['ultimo_leido_id' => $ultimoId, 'updated_at' => now()]);

        return response()->json(['ok' => true, 'ultimo_leido_id' => $ultimoId]);
    }

    /**
     * POST /chat/canales/{id}/cerrar — oculta el canal de la lista del usuario actual.
     * No afecta a otros miembros. Si llega un mensaje nuevo, se desoculta solo.
     */
    public function cerrar(int $canalId): JsonResponse
    {
        $uid = Auth::id();
        $this->verificarMiembro($canalId, $uid);

        DB::table('chat_canal_user')
            ->where('canal_id', $canalId)
            ->where('user_id', $uid)
            ->update(['oculto' => true, 'updated_at' => now()]);

        return response()->json(['ok' => true]);
    }

    /**
     * POST /chat/canales/{id}/reabrir — desoculta un canal previamente cerrado
     * por el usuario. Inverso de cerrar(). Usado por la tab "Archivadas" del
     * widget para que el operador pueda volver a una conversación archivada
     * sin esperar a que la otra persona escriba.
     */
    public function reabrir(int $canalId): JsonResponse
    {
        $uid = Auth::id();
        $this->verificarMiembro($canalId, $uid);

        DB::table('chat_canal_user')
            ->where('canal_id', $canalId)
            ->where('user_id', $uid)
            ->update(['oculto' => false, 'updated_at' => now()]);

        return response()->json(['ok' => true]);
    }

    /**
     * GET /chat/canales/archivados — Lista los canales que el usuario cerró
     * (oculto = true). Mismo shape que canales(), pero sin la lógica de
     * auto-desocultar: el operador eligió cerrarlos, no los desocultamos
     * automáticamente solo porque haya mensajes nuevos. Cuando hace click en
     * uno desde la tab "Archivadas", el frontend llama a /reabrir explícito.
     */
    public function archivados(): JsonResponse
    {
        $uid = Auth::id();

        $canales = ChatCanal::whereHas('miembros', fn($q) => $q
            ->where('user_id', $uid)
            ->where('oculto', true))
            ->with(['miembros:id,nombre_completo'])
            ->get();

        $online = array_flip($this->usuariosOnline());

        $data = $canales->map(function ($canal) use ($uid, $online) {
            $pivot = $canal->miembros->firstWhere('id', $uid)?->pivot;
            $ultimoLeido = $pivot?->ultimo_leido_id ?? 0;

            $ultimoMsg = ChatMensaje::where('canal_id', $canal->id)
                ->orderByDesc('id')->first();

            $noLeidos = ChatMensaje::where('canal_id', $canal->id)
                ->where('id', '>', $ultimoLeido)
                ->where('user_id', '!=', $uid)
                ->count();

            $nombre = $canal->nombre;
            $otroId = null;
            $otroOnline = null;
            if ($canal->tipo === 'dm') {
                $otro = $canal->miembros->firstWhere('id', '!=', $uid);
                $nombre = $otro?->nombre_completo ?? 'DM';
                $otroId = $otro?->id;
                $otroOnline = $otroId ? isset($online[$otroId]) : false;
            }

            return [
                'id'          => $canal->id,
                'tipo'        => $canal->tipo,
                'nombre'      => $nombre,
                'otro_id'     => $otroId,
                'otro_online' => $otroOnline,
                'oculto'      => true,
                'no_leidos'   => $noLeidos,
                'ultimo_msg'  => $ultimoMsg ? [
                    'texto'   => mb_strimwidth((string) $ultimoMsg->texto, 0, 80, '…'),
                    'user_id' => $ultimoMsg->user_id,
                    'hora'    => $ultimoMsg->created_at?->format('H:i'),
                    'fecha'   => $ultimoMsg->created_at?->format('d/m'),
                    'ts'      => $ultimoMsg->created_at?->timestamp ?? 0,
                ] : null,
            ];
        })
        // El canal "equipo" nunca se debería archivar (no aplica), pero por
        // las dudas lo excluimos. DMs archivados por última actividad desc.
        ->reject(fn($c) => $c['tipo'] === 'equipo')
        ->sortByDesc(fn($c) => $c['ultimo_msg']['ts'] ?? 0)
        ->values();

        return response()->json(['ok' => true, 'data' => $data]);
    }

    /**
     * GET /chat/canales/{id}/buscar?q=...
     * Busca en los mensajes de un canal. Hasta 100 hits ordenados del más reciente al más viejo.
     */
    public function buscar(int $canalId, Request $request): JsonResponse
    {
        $uid = Auth::id();
        $this->verificarMiembro($canalId, $uid);

        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['ok' => true, 'data' => []]);
        }

        $msgs = ChatMensaje::where('canal_id', $canalId)
            ->where('texto', 'like', '%' . $q . '%')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $autores = User::whereIn('id', $msgs->pluck('user_id')->unique())
            ->pluck('nombre_completo', 'id');

        $data = $msgs->map(fn($m) => [
            'id'      => $m->id,
            'user_id' => $m->user_id,
            'autor'   => $autores[$m->user_id] ?? '?',
            'texto'   => $m->texto,
            'hora'    => $m->created_at?->format('H:i'),
            'fecha'   => $m->created_at?->format('d/m/Y'),
        ]);

        return response()->json(['ok' => true, 'data' => $data]);
    }

    /** GET /chat/usuarios — lista de usuarios activos para iniciar DMs, con presencia online. */
    public function usuarios(): JsonResponse
    {
        $uid = Auth::id();
        $online = array_flip($this->usuariosOnline());
        $users = User::where('activo', true)
            ->where('id', '!=', $uid)
            ->orderBy('nombre_completo')
            ->get(['id', 'nombre_completo'])
            ->map(fn($u) => [
                'id'              => $u->id,
                'nombre_completo' => $u->nombre_completo,
                'online'          => isset($online[$u->id]),
            ]);
        return response()->json(['ok' => true, 'data' => $users]);
    }

    /** POST /chat/dm  body: { user_id } — abre DM (lo desoculta si estaba oculto). */
    public function abrirDm(Request $request): JsonResponse
    {
        $uid = Auth::id();
        $data = $request->validate(['user_id' => 'required|integer|exists:users,id|different:' . $uid]);

        $canal = ChatCanal::dmEntre($uid, (int) $data['user_id']);

        // Si lo tenía oculto, lo reabrimos.
        DB::table('chat_canal_user')
            ->where('canal_id', $canal->id)
            ->where('user_id', $uid)
            ->update(['oculto' => false, 'updated_at' => now()]);

        return response()->json(['ok' => true, 'canal_id' => $canal->id]);
    }

    /**
     * GET /chat/no-leidos — endpoint liviano para el badge del navbar + heartbeat de presencia.
     * También dispara las notificaciones del browser (ver layout app.blade).
     */
    public function noLeidos(): JsonResponse
    {
        $uid = Auth::id();
        $this->asegurarEquipo();
        $this->tocarPresencia();   // este endpoint se llama cada 6s desde el widget de chat

        $rows = DB::table('chat_canal_user as cu')
            ->join('chat_canales as c', 'c.id', '=', 'cu.canal_id')
            ->where('cu.user_id', $uid)
            ->select('cu.canal_id', 'cu.ultimo_leido_id', 'c.tipo', 'c.nombre')
            ->get();

        $count = 0;
        $canales = [];

        foreach ($rows as $row) {
            $ultimoNoLeido = ChatMensaje::where('canal_id', $row->canal_id)
                ->where('user_id', '!=', $uid)
                ->where('id', '>', $row->ultimo_leido_id ?? 0)
                ->orderByDesc('id')
                ->first();

            if (!$ultimoNoLeido) continue;

            $cantidad = ChatMensaje::where('canal_id', $row->canal_id)
                ->where('user_id', '!=', $uid)
                ->where('id', '>', $row->ultimo_leido_id ?? 0)
                ->count();

            $count += $cantidad;

            $autor = User::find($ultimoNoLeido->user_id)?->nombre_completo ?? '—';
            $nombre = $row->nombre;
            if ($row->tipo === 'dm') {
                $nombre = $autor;
            }

            $canales[] = [
                'canal_id'   => $row->canal_id,
                'nombre'     => $nombre,
                'tipo'       => $row->tipo,
                'ultimo_id'  => (int) $ultimoNoLeido->id,
                'autor'      => $autor,
                'texto'      => mb_strimwidth((string) $ultimoNoLeido->texto, 0, 120, '…'),
                'no_leidos'  => $cantidad,
            ];
        }

        return response()->json(['ok' => true, 'count' => $count, 'canales' => $canales]);
    }

    private function verificarMiembro(int $canalId, int $uid): void
    {
        $esMiembro = DB::table('chat_canal_user')
            ->where('canal_id', $canalId)
            ->where('user_id', $uid)
            ->exists();
        if (!$esMiembro) {
            abort(403, 'No sos miembro de este canal.');
        }
    }
}
