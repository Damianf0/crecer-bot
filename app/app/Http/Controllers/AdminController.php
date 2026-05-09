<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AdminController extends Controller
{
    private function botUrl(): string { return rtrim(config('app.bot_url'), '/'); }
    private function botTok(): string { return (string) config('app.bot_ingress_token'); }

    private function bot()
    {
        return Http::timeout(10)->withToken($this->botTok());
    }

    // ── Dashboard ─────────────────────────────────────────────────────

    public function dashboard()
    {
        return view('admin.dashboard');
    }

    /**
     * GET /bot-pulso — endpoint liviano para el badge del navbar.
     * Accesible para cualquier secretaria autenticada (no requiere permiso:admin).
     * Devuelve solo el estado, sin el QR data-url (para no exponer a usuarios sin admin).
     */
    public function pulso(): JsonResponse
    {
        try {
            $r = $this->bot()->get($this->botUrl() . '/status');
            if (!$r->ok()) {
                return response()->json(['ok' => false, 'estado' => 'sin_respuesta']);
            }
            $j = $r->json();
            return response()->json([
                'ok'     => true,
                'estado' => $j['status'] ?? 'desconocido',
                'has_qr' => !empty($j['qrDataUrl']),
            ]);
        } catch (\Exception) {
            return response()->json(['ok' => false, 'estado' => 'sin_respuesta']);
        }
    }

    /** GET /admin/bot/status — proxy del status del bot (sin exponer token al browser) */
    public function botStatus(): JsonResponse
    {
        try {
            $r = $this->bot()->get($this->botUrl() . '/status');
            if (!$r->ok()) {
                return response()->json(['ok' => false, 'error' => 'Bot no responde'], 502);
            }
            return response()->json(['ok' => true] + $r->json());
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 502);
        }
    }

    // ── Textos ────────────────────────────────────────────────────────

    public function textos()
    {
        return view('admin.textos');
    }

    public function textosGet(): JsonResponse
    {
        try {
            $r = $this->bot()->get($this->botUrl() . '/textos');
            return response()->json($r->json());
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 502);
        }
    }

    public function textosSave(Request $request): JsonResponse
    {
        try {
            $r = $this->bot()->post($this->botUrl() . '/textos', $request->all());
            return response()->json($r->json());
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 502);
        }
    }

    // ── Pruebas ──────────────────────────────────────────────────────

    public function pruebas()
    {
        return view('admin.pruebas');
    }

    public function pruebasModo(Request $request): JsonResponse
    {
        try {
            $r = $this->bot()->post($this->botUrl() . '/pruebas/modo', $request->all());
            return response()->json($r->json());
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 502);
        }
    }

    /**
     * Proxy SSE: el browser NO puede mandar Authorization en EventSource.
     * Acá Laravel valida la sesión (permiso:admin) y reenvía con Bearer al bot.
     */
    public function pruebasStream()
    {
        return $this->proxySse('/pruebas/stream');
    }

    // ── Logs ─────────────────────────────────────────────────────────

    public function logs()
    {
        return view('admin.logs');
    }

    public function logsStream()
    {
        return $this->proxySse('/logs');
    }

    // ── Tareas Windows programadas ───────────────────────────────────

    /**
     * GET /admin/tareas — estado de las tareas programadas que respaldan/mantienen
     * el sistema. Lee el filesystem montado en /backups (bind mount read-only del
     * directorio C:\crecer\backups del host).
     *
     * Cada tarea tiene un script .ps1 en C:\crecer\docker, una entrada en Task
     * Scheduler de Windows, y produce uno o más artefactos (archivo de backup, log).
     */
    public function tareas(): JsonResponse
    {
        $base = '/backups';
        if (!is_dir($base)) {
            return response()->json([
                'ok' => false,
                'error' => 'El directorio de backups no está montado en el container.',
            ], 503);
        }

        $tareas = [
            $this->statusTarea('backup_mysql', 'Backup MySQL diario', '03:00', [
                'patron'  => $base . '/auto/daily/clinica-*.sql.gz',
                'ventana' => 26 * 3600,   // <26 h desde el último archivo: OK
            ]),
            $this->statusTarea('clean_bot_cache', 'Limpieza cache bot', '04:00', [
                'log'     => $base . '/auto/clean-cache.log',
                'ventana' => 26 * 3600,
            ]),
            $this->statusTarea('mapear_wa', 'Sync wa_id de contactos', '04:30', [
                'log'     => $base . '/auto/mapear-wa.log',
                'ventana' => 26 * 3600,
            ]),
            $this->statusTarea('sync_avatares', 'Sync avatares WhatsApp', '05:00', [
                'log'     => $base . '/auto/sync-avatares.log',
                'ventana' => 26 * 3600,
            ]),
            $this->statusTarea('watchdog_bot', 'Watchdog del bot WA', 'cada 5 min', [
                'log'     => $base . '/auto/watchdog.log',
                'ventana' => 15 * 60,   // <15 min: corrió hace poco
            ]),
        ];

        return response()->json(['ok' => true, 'tareas' => $tareas, 'now' => now()->toIso8601String()]);
    }

    /** Resuelve el estado de una tarea según la fecha del último artefacto producido. */
    private function statusTarea(string $key, string $titulo, string $hora, array $opts): array
    {
        $ultimo = null;
        $ultimoMtime = 0;

        if (!empty($opts['patron'])) {
            $files = glob($opts['patron']) ?: [];
            foreach ($files as $f) {
                $m = filemtime($f);
                if ($m > $ultimoMtime) { $ultimoMtime = $m; $ultimo = $f; }
            }
        } elseif (!empty($opts['log']) && file_exists($opts['log'])) {
            $ultimoMtime = filemtime($opts['log']);
            $ultimo = $opts['log'];
        }

        $now      = time();
        $edadSeg  = $ultimoMtime ? ($now - $ultimoMtime) : null;
        $ventana  = $opts['ventana'] ?? 26 * 3600;

        $estado = match (true) {
            $ultimoMtime === 0       => 'nunca_corrio',
            $edadSeg <= $ventana     => 'ok',
            default                  => 'atrasado',
        };

        return [
            'key'           => $key,
            'titulo'        => $titulo,
            'hora_diaria'   => $hora,
            'estado'        => $estado,
            'ultimo_run'    => $ultimoMtime ? date('c', $ultimoMtime) : null,
            'edad_segundos' => $edadSeg,
            'artefacto'     => $ultimo ? basename($ultimo) : null,
            'tamanio_bytes' => $ultimo ? @filesize($ultimo) : null,
        ];
    }

    // ── Legajo (config storage path) ─────────────────────────────────

    public function legajoConfig()
    {
        $base = \App\Services\LegajoStorage::basePath();
        $totalDocs = \App\Models\DocumentoPaciente::count();
        $tamanioTotal = (int) \App\Models\DocumentoPaciente::sum('tamanio_bytes');
        return view('admin.legajo', [
            'pathActual'   => $base,
            'pathDefault'  => storage_path('app/private/documentos'),
            'totalDocs'    => $totalDocs,
            'tamanioTotal' => $tamanioTotal,
            'esEscribible' => is_dir($base) && is_writable($base),
        ]);
    }

    public function legajoConfigSave(Request $request): JsonResponse
    {
        $data = $request->validate([
            'path' => 'required|string|max:500',
        ]);
        $path = rtrim($data['path'], '/\\');

        // Validar absoluto
        if (!preg_match('#^(/|[A-Z]:\\\\)#', $path)) {
            return response()->json(['ok' => false, 'error' => 'El path debe ser absoluto.'], 422);
        }

        // Crear si no existe + verificar escritura
        if (!is_dir($path)) @mkdir($path, 0775, true);
        if (!is_dir($path) || !is_writable($path)) {
            return response()->json(['ok' => false, 'error' => 'El path no es accesible o no se puede escribir.'], 422);
        }

        // Persistir en .env (LEGAJO_STORAGE_PATH)
        $envFile = base_path('.env');
        $envText = file_exists($envFile) ? file_get_contents($envFile) : '';
        if (str_contains($envText, 'LEGAJO_STORAGE_PATH=')) {
            $envText = preg_replace('/^LEGAJO_STORAGE_PATH=.*$/m', 'LEGAJO_STORAGE_PATH=' . $path, $envText);
        } else {
            $envText .= (str_ends_with($envText, "\n") ? '' : "\n") . "LEGAJO_STORAGE_PATH=" . $path . "\n";
        }
        @file_put_contents($envFile, $envText);

        // Limpiar config cache para que tome el nuevo path en la próxima request
        \Illuminate\Support\Facades\Artisan::call('config:clear');

        return response()->json(['ok' => true, 'aviso' => 'Path guardado. Los documentos existentes NO se mueven automáticamente — afecta solo a los nuevos.']);
    }

    // ── Usuarios ─────────────────────────────────────────────────────

    public function usuarios()
    {
        return view('admin.usuarios');
    }

    public function usuariosData(): JsonResponse
    {
        $users = User::orderBy('nombre_completo')->get([
            'id', 'nombre_completo', 'email', 'rol', 'activo', 'permisos',
        ]);
        return response()->json([
            'ok'              => true,
            'data'            => $users,
            'roles'           => User::ROLES,
            'permisos_labels' => User::PERMISOS_LABELS,
            'permisos_default'=> User::PERMISOS_DEFAULT,
        ]);
    }

    public function usuariosSave(Request $request, int $id = null): JsonResponse
    {
        $data = $request->validate([
            'nombre_completo' => 'required|string|max:120',
            'email'           => 'required|email|max:150',
            'rol'             => 'required|in:secretaria,supervisora,admin,tecnico',
            'activo'          => 'required|boolean',
            'permisos'        => 'nullable|array',
            'permisos.*'      => 'string',
            'password'        => [
                'nullable',
                'string',
                new \App\Rules\PasswordSegura(
                    ['nombre_completo', 'email'],
                    $request->only(['nombre_completo', 'email'])
                ),
            ],
        ]);

        if ($id) {
            $user = User::findOrFail($id);
            $user->fill([
                'nombre_completo' => $data['nombre_completo'],
                'email'           => $data['email'],
                'rol'             => $data['rol'],
                'activo'          => $data['activo'],
                'permisos'        => $data['permisos'] ?? null,
            ]);
            if (!empty($data['password'])) {
                $user->password = bcrypt($data['password']);
            }
            $user->save();
        } else {
            if (empty($data['password'])) {
                return response()->json(['ok' => false, 'error' => 'Contraseña requerida para nuevo usuario'], 422);
            }
            $user = User::create([
                'nombre_completo' => $data['nombre_completo'],
                'email'           => $data['email'],
                'password'        => bcrypt($data['password']),
                'rol'             => $data['rol'],
                'activo'          => $data['activo'],
                'permisos'        => $data['permisos'] ?? null,
            ]);
        }

        return response()->json(['ok' => true, 'id' => $user->id]);
    }

    // ── Helper SSE proxy ─────────────────────────────────────────────

    private function proxySse(string $endpoint)
    {
        $url   = $this->botUrl() . $endpoint;
        $token = $this->botTok();

        return response()->stream(function () use ($url, $token) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token, 'Accept: text/event-stream'],
                CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) {
                    echo $chunk;
                    @ob_flush();
                    @flush();
                    return connection_aborted() ? -1 : strlen($chunk);
                },
                CURLOPT_TIMEOUT        => 0,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_BUFFERSIZE     => 4096,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            ]);
            curl_exec($ch);
            curl_close($ch);
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }
}
