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

    private const CACHE_KEY_TOKEN = 'omnia_token';
    private const TOKEN_TTL_SEC   = 1500;   // margen 5min sobre los 1800s reales
    private const HTTP_TIMEOUT    = 8;

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

        return $token;
    }

    /** GET autenticado, con re-login y reintento si la respuesta es 401. */
    private function get(string $url, array $query = []): mixed
    {
        $token = $this->token();
        if (!$token) return null;

        $r = $this->doGet($url, $query, $token);

        if ($r && $r->status() === 401) {
            Log::info('[Omnia] Token rechazado, re-signin');
            Cache::forget(self::CACHE_KEY_TOKEN);
            $token = $this->signin();
            if (!$token) return null;
            Cache::put(self::CACHE_KEY_TOKEN, $token, self::TOKEN_TTL_SEC);
            $r = $this->doGet($url, $query, $token);
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

    private function doGet(string $url, array $query, string $token)
    {
        try {
            return Http::timeout(self::HTTP_TIMEOUT)
                ->withToken($token)
                ->acceptJson()
                ->get($url, $query);
        } catch (\Throwable $e) {
            Log::error('[Omnia] GET exception', ['url' => $url, 'msg' => $e->getMessage()]);
            return null;
        }
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
            $url  = $this->externalBase() . '/reports/appointments/ambulatory';
            $data = $this->get($url, ['start' => $start, 'end' => $end]);
            return is_array($data) ? $data : [];
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
                'profesional' => $profesional,
                'estado'      => $t['state'] ?? 'pendiente',
                'planta'      => $planta,
            ];
        }

        usort($turnos, fn($a, $b) => strcmp($a['hora'] ?? '', $b['hora'] ?? ''));

        return $turnos;
    }
}
