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
     * Turnos del día de hoy (zona Argentina) para un paciente, filtrando
     * sobre el array de pendientes que devuelve la API.
     */
    public function turnosHoy(int|string $pacienteId): array
    {
        $url  = $this->externalBase() . "/patients/{$pacienteId}/appointments/pending";
        $data = $this->get($url);

        if (!is_array($data)) return [];

        $tz       = 'America/Argentina/Buenos_Aires';
        $hoyStart = now($tz)->startOfDay()->utc()->timestamp;
        $hoyEnd   = now($tz)->endOfDay()->utc()->timestamp;

        $turnos = [];
        foreach ($data as $t) {
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
