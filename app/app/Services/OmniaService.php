<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente de la API oficial de Omnia Salud (turnos).
 *
 * Auth: POST {fhir_base}/auth/signin → JWT (expira 1800s = 30 min). El mismo
 * token sirve para los endpoints FHIR y los propietarios (/api/v1/external/*).
 *
 * Documentación de referencia: collection Postman "API de turnos 1.4.73-RC26"
 * (C:\atencion-bot\docs\omnia\). Validado 2026-05-03.
 */
class OmniaService
{
    private string $baseUrl;
    private string $email;
    private string $password;

    /** Mapeo nombre de servicio (Omnia) → planta física en la clínica. */
    private array $plantaPorServicio;

    private const CACHE_KEY_TOKEN   = 'omnia_token';
    private const CACHE_KEY_REFRESH = 'omnia_refresh_token';
    private const TOKEN_TTL_SEC     = 1500;   // margen 5min sobre los 1800s reales
    private const REFRESH_TTL_SEC   = 86400;  // el refreshToken vive mucho más que el access
    private const HTTP_TIMEOUT      = 8;

    /** Reintentos ante errores transitorios del lado de Omnia (502/503/504). */
    private const RETRY_STATUSES = [502, 503, 504];
    private const RETRY_MAX      = 2;
    private const RETRY_BASE_MS  = 800;

    public function __construct()
    {
        $cfg = config('services.omnia');
        $this->baseUrl  = rtrim($cfg['base_url'] ?? 'https://apiturnos.apps.omniasalud.com', '/');
        $this->email    = $cfg['user']     ?? '';
        $this->password = $cfg['password'] ?? '';
        $this->plantaPorServicio = $cfg['planta_por_servicio'] ?? [];
    }

    private function fhirBase(): string     { return $this->baseUrl . '/api/fhir'; }
    private function externalBase(): string { return $this->baseUrl . '/api/v1/external'; }

    // ── Auth ──────────────────────────────────────────────────

    private function token(): ?string
    {
        return Cache::remember(self::CACHE_KEY_TOKEN, self::TOKEN_TTL_SEC, function () {
            return $this->signin();
        });
    }

    private function signin(): ?string
    {
        if (!$this->email || !$this->password) {
            Log::error('[Omnia] Credenciales no configuradas (OMNIA_USER / OMNIA_PASS)');
            return null;
        }

        try {
            $r = Http::timeout(self::HTTP_TIMEOUT)->asJson()->post(
                $this->fhirBase() . '/auth/signin',
                ['user' => $this->email, 'password' => $this->password]
            );
        } catch (\Throwable $e) {
            Log::error('[Omnia] signin exception', ['msg' => $e->getMessage()]);
            return null;
        }

        if (!$r->successful()) {
            Log::error('[Omnia] signin fallido', [
                'status' => $r->status(),
                'body'   => substr($r->body(), 0, 300),
            ]);
            return null;
        }

        $token = $r->json('accessToken');
        if (!$token) {
            Log::error('[Omnia] signin OK pero sin accessToken');
            return null;
        }

        // Guardamos el refreshToken para renovar sin re-enviar credenciales.
        if ($rt = $r->json('refreshToken')) {
            Cache::put(self::CACHE_KEY_REFRESH, $rt, self::REFRESH_TTL_SEC);
        }

        return $token;
    }

    /**
     * Renueva el accessToken con el refreshToken cacheado. Devuelve null si no
     * hay refresh guardado o si Omnia lo rechaza — el caller cae a signin().
     */
    private function refresh(): ?string
    {
        $rt = Cache::get(self::CACHE_KEY_REFRESH);
        if (!$rt) return null;

        try {
            $r = Http::timeout(self::HTTP_TIMEOUT)->asJson()->post(
                $this->fhirBase() . '/auth/refreshToken',
                ['refreshToken' => $rt]
            );
        } catch (\Throwable $e) {
            Log::warning('[Omnia] refresh exception', ['msg' => $e->getMessage()]);
            return null;
        }

        if (!$r->successful()) {
            Log::info('[Omnia] refreshToken rechazado, cae a signin', ['status' => $r->status()]);
            Cache::forget(self::CACHE_KEY_REFRESH);
            return null;
        }

        $token = $r->json('accessToken');
        if (!$token) return null;

        // Omnia puede rotar el refreshToken en la respuesta.
        if ($nuevoRt = $r->json('refreshToken')) {
            Cache::put(self::CACHE_KEY_REFRESH, $nuevoRt, self::REFRESH_TTL_SEC);
        }

        return $token;
    }

    /**
     * GET autenticado. Ante 401 renueva el token (refresh, y si falla signin) y
     * reintenta; ante 502/503/504 reintenta con backoff exponencial.
     *
     * @param int $retries  Reintentos por error transitorio. Ojo con los pedidos
     *                      de timeout largo (reporte ambulatorio): cada reintento
     *                      puede costar el timeout completo.
     */
    private function get(string $url, array $query = [], int $timeout = self::HTTP_TIMEOUT, int $retries = self::RETRY_MAX): mixed
    {
        $token = $this->token();
        if (!$token) return null;

        $r = $this->doGet($url, $query, $token, $timeout, $retries);

        if ($r && $r->status() === 401) {
            Log::info('[Omnia] Token rechazado, renovando');
            Cache::forget(self::CACHE_KEY_TOKEN);

            $token = $this->refresh() ?? $this->signin();
            if (!$token) return null;

            Cache::put(self::CACHE_KEY_TOKEN, $token, self::TOKEN_TTL_SEC);
            $r = $this->doGet($url, $query, $token, $timeout, $retries);
        }

        if (!$r || !$r->successful()) {
            Log::warning('[Omnia] GET fallido', [
                'url'    => $url,
                'status' => $r?->status(),
                'body'   => $r ? substr($r->body(), 0, 300) : null,
            ]);
            return null;
        }

        return $r->json();
    }

    private function doGet(string $url, array $query, string $token, int $timeout = self::HTTP_TIMEOUT, int $retries = self::RETRY_MAX)
    {
        $intento = 0;

        while (true) {
            try {
                $r = Http::timeout($timeout)
                    ->withToken($token)
                    ->acceptJson()
                    ->get($url, $query);
            } catch (\Throwable $e) {
                if ($intento >= $retries) {
                    Log::error('[Omnia] GET exception', ['url' => $url, 'msg' => $e->getMessage()]);
                    return null;
                }
                $this->esperarBackoff($intento, $url, 'exception: ' . $e->getMessage());
                $intento++;
                continue;
            }

            // 502/503/504 suelen ser transitorios del lado de Omnia. Ojo: el
            // reporte ambulatorio del 10/09/2025 da 502 SIEMPRE (registro
            // podrido en Omnia), así que el reintento no siempre salva.
            if (in_array($r->status(), self::RETRY_STATUSES, true) && $intento < $retries) {
                $this->esperarBackoff($intento, $url, 'HTTP ' . $r->status());
                $intento++;
                continue;
            }

            return $r;
        }
    }

    private function esperarBackoff(int $intento, string $url, string $motivo): void
    {
        $ms = self::RETRY_BASE_MS * (2 ** $intento);
        Log::info('[Omnia] reintento', ['url' => $url, 'motivo' => $motivo, 'espera_ms' => $ms]);
        usleep($ms * 1000);
    }

    /**
     * Sonda de salud: signin + healthcheck FHIR. La usa el comando omnia:status
     * y cualquier monitoreo externo. No usa el token cacheado a propósito —
     * prueba el circuito de autenticación completo.
     *
     * @return array{ok:bool, signin:bool, healthcheck:bool, ms:int, error:?string}
     */
    public function estado(): array
    {
        $t0  = microtime(true);
        $out = ['ok' => false, 'signin' => false, 'healthcheck' => false, 'ms' => 0, 'error' => null];

        $token = $this->signin();
        if (!$token) {
            $out['error'] = 'signin fallido (ver laravel.log)';
            $out['ms']    = (int) ((microtime(true) - $t0) * 1000);
            return $out;
        }
        $out['signin'] = true;
        Cache::put(self::CACHE_KEY_TOKEN, $token, self::TOKEN_TTL_SEC);

        $r = $this->doGet($this->fhirBase() . '/healthcheck', [], $token, self::HTTP_TIMEOUT, 1);
        $out['healthcheck'] = (bool) ($r && $r->successful());
        if (!$out['healthcheck']) {
            $out['error'] = 'healthcheck HTTP ' . ($r?->status() ?? 'sin respuesta');
        }

        $out['ok'] = $out['signin'] && $out['healthcheck'];
        $out['ms'] = (int) ((microtime(true) - $t0) * 1000);

        return $out;
    }

    /**
     * Reporte ambulatorio crudo del centro para un rango [start, end] (Unix seg UTC).
     * Devuelve el array de turnos tal como lo da Omnia, o null si falló.
     *
     * Ojo: Omnia tarda proporcional al rango (~110s para 6 meses) — para rangos
     * largos pedir por mes y pasar un timeout generoso.
     */
    public function reporteAmbulatorio(int $start, int $end, int $timeout = self::HTTP_TIMEOUT): ?array
    {
        $data = $this->get(
            $this->externalBase() . '/reports/appointments/ambulatory',
            ['start' => $start, 'end' => $end],
            $timeout,
            1   // un solo reintento: acá el timeout es largo (hasta 180s por tramo)
        );

        return is_array($data) ? $data : null;
    }

    /**
     * Financiador y plan con los que se dio ESTE turno. Puede no ser la obra
     * social de la ficha del paciente (ej: paciente con OSFATLYF que vino como
     * Particular), y es lo que manda para el checklist de recepción.
     *
     * appointments/pending no lo trae; el reporte ambulatorio sí. Se pide el del
     * día una vez cada 10 min (cache) con timeout corto: es el camino del
     * tablet y no puede colgarlo. Un turno que no está en el cache (se dio
     * después) fuerza un refresco, uno solo. null = no se pudo saber.
     *
     * @return array{financiador:?string, plan:?string}|null
     */
    public function financiadorDelTurno(int|string $turnoId): ?array
    {
        $clave = 'omnia.financiador_turnos.' . now('America/Argentina/Buenos_Aires')->format('Ymd');
        $armar = function () {
            $tz = 'America/Argentina/Buenos_Aires';
            $r = $this->reporteAmbulatorio(now($tz)->startOfDay()->timestamp, now($tz)->endOfDay()->timestamp, 8);
            if (!is_array($r)) return null;
            $mapa = [];
            foreach ($r as $t) {
                if (!isset($t['Id'])) continue;
                $mapa[(string) $t['Id']] = [
                    'financiador' => trim($t['FinanciadorDelTurno'] ?? '') ?: null,
                    'plan'        => trim($t['PlanDelFinanciador'] ?? '') ?: null,
                ];
            }
            return $mapa;
        };

        $mapa = Cache::get($clave);
        if (!is_array($mapa) || !isset($mapa[(string) $turnoId])) {
            $mapa = $armar();
            if (!is_array($mapa)) return null;   // Omnia no respondió: no cachear el fallo
            Cache::put($clave, $mapa, 600);
        }
        return $mapa[(string) $turnoId] ?? null;
    }

    // ── API pública ───────────────────────────────────────────

    /**
     * Busca un paciente por DNI. Devuelve array normalizado para Tablet,
     * o null si no se encuentra.
     */
    public function buscarPaciente(string $dni, string $tipo = 'DNI'): ?array
    {
        $data = $this->get($this->externalBase() . '/patients/by-personal-id', [
            'personal_id'      => $dni,
            'personal_id_type' => $tipo,
        ]);

        // El endpoint devuelve un objeto único o null si no encuentra
        if (!is_array($data) || empty($data['id'])) {
            Log::info('[Omnia] Paciente no encontrado', ['dni' => $dni]);
            return null;
        }

        $person = $data['person'] ?? [];

        return [
            'id'          => $data['id'],
            'nombre'      => $person['firstName'] ?? '',
            'apellido'    => $person['lastName']  ?? '',
            'obra_social' => $data['healthcareProviderShortName']
                          ?? $data['healthcareProviderName']
                          ?? null,
            'plan'        => $data['healthcareProviderPlan'] ?? null,
            // Nombre completo, igual que FinanciadorDelTurno del reporte: es contra
            // lo que matchean las reglas del checklist ("OSDE" no matchea "Osde Binario").
            'financiador' => $data['healthcareProviderName'] ?? null,
            'primera_vez' => false,   // Omnia no expone este flag
        ];
    }

    /**
     * Agenda del día de un profesional (filtra en cliente sobre el reporte
     * ambulatorio del centro, que devuelve TODOS los turnos del rango).
     *
     * @param string $nombreOmnia  Nombre tal como aparece en Omnia en el
     *                              campo `NombreDelProfesional` (sin "Dr."/"Dra.").
     *                              Ej: "Ignacio Cruz", "Mauro Javier García Aurelio".
     * @param \Carbon\Carbon|null $dia  Día a consultar (default: hoy en zona AR).
     * @param bool $soloPendientes  Si true (default), filtra estado=pendiente
     *                               (descarta cancelados, atendidos, etc.).
     */
    public function turnosDelDiaPorMedico(string $nombreOmnia, ?\Carbon\Carbon $dia = null, bool $soloPendientes = true): array
    {
        $nombreOmnia = trim($nombreOmnia);
        if ($nombreOmnia === '') return [];

        $tz   = 'America/Argentina/Buenos_Aires';
        $dia  = $dia ? $dia->copy()->setTimezone($tz) : now($tz);
        $start = $dia->copy()->startOfDay()->utc()->timestamp;
        $end   = $dia->copy()->endOfDay()->utc()->timestamp;

        // Cache por rango (compartido entre médicos del mismo día) — el reporte
        // devuelve TODO el centro, así que pegar una sola vez por día y filtrar
        // localmente es mucho más eficiente que un GET por médico.
        $cacheKey = "omnia_ambulatory_{$start}_{$end}";
        $reporte  = Cache::remember($cacheKey, 60, function () use ($start, $end) {
            return $this->reporteAmbulatorio($start, $end) ?? [];
        });

        if (empty($reporte)) return [];

        // Normalización del nombre para match laxo (Omnia puede tener
        // "García" vs nuestra DB "Garcia" sin tilde, etc.)
        $norm = fn(string $s) => mb_strtolower(strtr(
            $s,
            ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
             'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u',
             'ñ' => 'n', 'Ñ' => 'n']
        ));
        $needle = $norm($nombreOmnia);

        $turnos = [];
        foreach ($reporte as $t) {
            $nomProf = $t['NombreDelProfesional'] ?? '';
            if ($norm($nomProf) !== $needle) continue;

            $estado = $t['Estado'] ?? '';
            if ($soloPendientes && $estado !== 'pendiente') continue;

            // FechaYHora viene como "23/4/2026 11:15" (string, hora local AR)
            $fh = $t['FechaYHora'] ?? '';
            $hora = null; $tsOrden = 0;
            try {
                $dt = \Carbon\Carbon::createFromFormat('j/n/Y H:i', $fh, $tz);
                $hora = $dt->format('H:i');
                $tsOrden = $dt->timestamp;
            } catch (\Throwable $e) {
                $hora = $fh ?: '—';
            }

            $turnos[] = [
                'id'          => $t['Id'] ?? null,
                'hora'        => $hora,
                'paciente'    => trim(($t['Nombre'] ?? '') . ' ' . ($t['ApellidoPaterno'] ?? '')),
                'dni'         => $t['NúmeroDeDocumento'] ?? null,
                'practica'    => is_array($t['Prácticas'] ?? null) ? implode(', ', $t['Prácticas']) : ($t['Prácticas'] ?? ''),
                'servicio'    => $t['Servicio'] ?? '',
                'estado'      => $estado,
                'modalidad'   => $t['Modalidad'] ?? '',
                'primera_vez' => !empty($t['PrimeraVez']),
                'notas'       => $t['Notas'] ?? '',
                'obra_social' => $t['ObraSocialDelPaciente'] ?? '',
                '_ts'         => $tsOrden,
            ];
        }

        // Orden por hora
        usort($turnos, fn($a, $b) => $a['_ts'] <=> $b['_ts']);
        foreach ($turnos as &$t) unset($t['_ts']);

        return $turnos;
    }

    /**
     * Turnos del día de hoy (zona Argentina) para un paciente, filtrando
     * sobre el array que devuelve la API.
     *
     * Ojo: el endpoint `/appointments/pending` NO filtra por día y trae los
     * turnos en estado `pendiente` Y `confirmado` (confirmado por Omnia el
     * 2026-06-16). El filtro por fecha y por estado lo hacemos acá en cliente.
     *
     * @param bool $soloPendientes  Si true (default), descarta todo lo que no
     *                              sea estado `pendiente` (p.ej. confirmados).
     */
    public function turnosHoy(int|string $pacienteId, bool $soloPendientes = true): array
    {
        $url  = $this->externalBase() . "/patients/{$pacienteId}/appointments/pending";
        $data = $this->get($url);

        if (!is_array($data)) return [];

        $tz       = 'America/Argentina/Buenos_Aires';
        $hoyStart = now($tz)->startOfDay()->utc()->timestamp;
        $hoyEnd   = now($tz)->endOfDay()->utc()->timestamp;

        $turnos = [];
        foreach ($data as $t) {
            // Solo pendientes: descarta confirmados/otros (el campo `state`
            // del turno viene como "pendiente" en español).
            if ($soloPendientes && ($t['state'] ?? '') !== 'pendiente') continue;

            $begins = (int) ($t['begins'] ?? 0);
            if ($begins < $hoyStart || $begins > $hoyEnd) continue;

            $hora = now()->setTimestamp($begins)->setTimezone($tz)->format('H:i');

            $prof = $t['professional'] ?? [];
            $profesional = trim(($prof['firstName'] ?? '') . ' ' . ($prof['lastName'] ?? ''))
                ?: 'Ver en recepción';

            // /external devuelve practices ya como nombres legibles ("Consulta",
            // "Electrocardiograma", ...) — no hay que mapear IDs.
            $practica = is_array($t['practices'] ?? null) && !empty($t['practices'])
                ? (string) $t['practices'][0]
                : 'Consulta';

            $servicio = $t['service'] ?? '';
            $planta   = $this->plantaPorServicio[$servicio] ?? 'baja';

            $turnos[] = [
                'id'          => $t['id'],
                'hora'        => $hora,
                'practica'    => $practica,
                // Todas: un turno puede traer varias y el checklist se arma con cualquiera.
                'practicas'   => array_values(array_map('strval', (array) ($t['practices'] ?? []))),
                'profesional' => $profesional,
                'estado'      => $t['state'] ?? 'pendiente',
                'planta'      => $planta,
            ];
        }

        usort($turnos, fn($a, $b) => strcmp($a['hora'] ?? '', $b['hora'] ?? ''));

        return $turnos;
    }
}
