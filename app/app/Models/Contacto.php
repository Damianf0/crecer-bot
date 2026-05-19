<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Contacto extends Model
{
    protected $table = 'contactos';

    protected $fillable = ['telefono', 'wa_id', 'avatar_path', 'avatar_actualizado_at', 'nombre', 'dni', 'email', 'fecha_nacimiento', 'omnia_patient_id', 'notas'];

    protected $casts = [
        'avatar_actualizado_at' => 'datetime',
        'fecha_nacimiento'      => 'date',
    ];

    /** TTL del cache de avatar en días — pasado este lapso se re-sincroniza. */
    public const AVATAR_TTL_DAYS = 7;

    /** URL pública del avatar, o null si no hay. */
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path ? asset('storage/' . $this->avatar_path) : null;
    }

    /**
     * Busca un contacto a partir del JID que devuelve WhatsApp.
     * Estrategia:
     *   1. Match exacto por wa_id (cubre @lid y @c.us con migración hecha).
     *   2. Fallback histórico: extraer dígitos de @c.us y buscar por telefono.
     */
    public static function buscarPorContacto(string $contactoWA): ?self
    {
        // 1) Match exacto por JID
        $hit = static::where('wa_id', $contactoWA)->first();
        if ($hit) return $hit;

        // 2) Fallback: solo funciona para @c.us porque deriva del número
        if (str_ends_with($contactoWA, '@c.us')) {
            $telefono = str_replace('@c.us', '', $contactoWA);
            return static::where('telefono', $telefono)->first();
        }

        return null;
    }

    public static function guardar(string $contactoWA, string $nombre): void
    {
        // Si es @c.us, podemos extraer el teléfono. Si es @lid, no — guardamos solo wa_id.
        if (str_ends_with($contactoWA, '@c.us')) {
            $telefono = str_replace('@c.us', '', $contactoWA);
            static::updateOrCreate(
                ['telefono' => $telefono],
                ['nombre' => $nombre, 'wa_id' => $contactoWA]
            );
        } else {
            static::updateOrCreate(
                ['wa_id' => $contactoWA],
                ['nombre' => $nombre]
            );
        }
    }

    /** Código de área asumido cuando el teléfono viene en formato viejo sin área (Mar del Plata). */
    public const AREA_DEFAULT = '223';

    /**
     * Normaliza un número argentino al formato WhatsApp (549 + 10 dígitos).
     * Acepta formatos modernos (con área completa) y viejos (con prefijo `15`,
     * sin código de área) — para estos últimos asume $areaDefault.
     * Devuelve solo los dígitos sin sufijo @c.us, o '' si no se pudo normalizar.
     */
    public static function normalizarTelefono(string $entrada, string $areaDefault = self::AREA_DEFAULT): string
    {
        $digitos = preg_replace('/\D/', '', $entrada);
        if ($digitos === '') return '';
        if (str_starts_with($digitos, '0')) {
            $digitos = substr($digitos, 1);
        }

        // Ya viene normalizado.
        if (str_starts_with($digitos, '549') && strlen($digitos) === 13) {
            return $digitos;
        }
        // 54 + 10 dígitos (sin el 9 móvil).
        if (str_starts_with($digitos, '54') && strlen($digitos) === 12) {
            return '549' . substr($digitos, 2);
        }
        // 9 + área + número, sin 54.
        if (strlen($digitos) === 11 && str_starts_with($digitos, '9')) {
            return '54' . $digitos;
        }
        // Formato viejo "15XXXXXXX" (9 dígitos): cel sin código de área. Asumir área default.
        // Ej: "156004294" → "549" + "223" + "6004294".
        if (strlen($digitos) === 9 && str_starts_with($digitos, '15')) {
            return '549' . $areaDefault . substr($digitos, 2);
        }
        // 10 dígitos: área (3-4) + número.
        if (strlen($digitos) === 10) {
            return '549' . $digitos;
        }
        // 8 dígitos: número local sin 15 (cel moderno MdP). Asumir área default.
        if (strlen($digitos) === 8) {
            return '549' . $areaDefault . $digitos;
        }
        // 7 dígitos: fijo viejo. Asumir área default.
        if (strlen($digitos) === 7) {
            return '549' . $areaDefault . $digitos;
        }
        // Caso "área + 15 + número" embebido (11-12 dígitos), ej "2266154-64020".
        if (strlen($digitos) >= 11 && strlen($digitos) <= 12) {
            foreach ([3, 4] as $areaLen) {
                if (substr($digitos, $areaLen, 2) === '15') {
                    $sin15 = substr($digitos, 0, $areaLen) . substr($digitos, $areaLen + 2);
                    if (strlen($sin15) === 10) return '549' . $sin15;
                }
            }
        }

        return '';
    }

    /**
     * Llama al bot /check-numero para resolver el JID real (@c.us o @lid) de un teléfono normalizado.
     * Devuelve null si el bot no responde o el número no está en WhatsApp.
     * Tolera fallos: nunca tira excepción al caller.
     *
     * Cacheado: hits positivos 24h, hits negativos (no en WA) 5 min. Las excepciones
     * (timeout / conn refused) NO se cachean para que el reintento ocurra cuando el
     * bot vuelva. Timeout corto (3s) porque esto se llama desde flujos sincrónicos
     * (apertura de conv, modal agregar contacto) y un bot colgado no debe bloquearlos.
     */
    public static function resolverWaId(string $telefonoNormalizado): ?string
    {
        if (!$telefonoNormalizado) return null;

        $cacheKey = "wa:check:{$telefonoNormalizado}";
        $cached   = Cache::get($cacheKey, '__MISS__');
        if ($cached !== '__MISS__') return $cached;

        try {
            $r = Http::timeout(3)
                ->withToken(config('app.bot_ingress_token'))
                ->post(config('app.bot_url') . '/check-numero', ['numero' => $telefonoNormalizado]);
            if (!$r->ok() || !$r->json('ok')) return null;     // bot rechazó: no cacheo (reintenta)
            if (!$r->json('registered')) {
                Cache::put($cacheKey, null, now()->addMinutes(5));
                return null;
            }
            $val = $r->json('normalizedId');
            Cache::put($cacheKey, $val, now()->addHours(24));
            return $val;
        } catch (\Exception $e) {
            Log::warning('Contacto::resolverWaId fallo', ['tel' => $telefonoNormalizado, 'err' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Resuelve el número telefónico real desde un JID @lid usando /resolve-jid del bot.
     * Devuelve null si no se pudo. Tolera fallos.
     *
     * Cacheado igual que resolverWaId (24h positivos, 5min negativos). Sin esta caché,
     * el polling cada 8s del panel de atención llamaba al bot 7.5 veces/min por cada
     * conversación huérfana @lid abierta → saturaba el CDP de Chromium y colgaba el
     * bot atención (incidente 19/05).
     */
    public static function resolverNumeroDesdeJid(string $jid): ?string
    {
        if (!$jid) return null;

        $cacheKey = "wa:jid:{$jid}";
        $cached   = Cache::get($cacheKey, '__MISS__');
        if ($cached !== '__MISS__') return $cached;

        try {
            $r = Http::timeout(3)
                ->withToken(config('app.bot_ingress_token'))
                ->post(config('app.bot_url') . '/resolve-jid', ['jid' => $jid]);
            if (!$r->ok() || !$r->json('ok')) return null;     // bot rechazó: no cacheo (reintenta)
            $val = $r->json('numero');
            $ttl = $val ? now()->addHours(24) : now()->addMinutes(5);
            Cache::put($cacheKey, $val, $ttl);
            return $val;
        } catch (\Exception $e) {
            Log::warning('Contacto::resolverNumeroDesdeJid fallo', ['jid' => $jid, 'err' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Sincroniza la foto de perfil del contacto:
     *   1. Pide la URL temporal al bot.
     *   2. Descarga la imagen.
     *   3. Guarda en storage/app/public/wa-avatars/<hash>.jpg.
     *   4. Actualiza avatar_path + avatar_actualizado_at.
     *
     * Marca avatar_actualizado_at incluso si NO obtiene foto (privacidad / sin foto)
     * para evitar reintentar antes del TTL.
     *
     * Devuelve true si descargó imagen nueva, false si no.
     */
    public static function sincronizarAvatar(self $contacto): bool
    {
        if (!$contacto->wa_id) return false;

        try {
            $r = Http::timeout(15)
                ->withToken(config('app.bot_ingress_token'))
                ->post(config('app.bot_url') . '/profile-pic', ['jid' => $contacto->wa_id]);

            if (!$r->ok() || !$r->json('ok')) {
                // Bot caído u otro error — no actualizamos timestamp para reintentar pronto.
                return false;
            }

            $url = $r->json('url');
            if (!$url) {
                // Sin foto / privacidad — marcamos timestamp para no reintentar antes del TTL.
                $contacto->update(['avatar_actualizado_at' => now()]);
                return false;
            }

            // Descargar imagen (la URL temporal de WA dura horas).
            $img = Http::timeout(20)->get($url);
            if (!$img->ok()) {
                $contacto->update(['avatar_actualizado_at' => now()]);
                return false;
            }

            $hash = substr(md5($contacto->wa_id), 0, 16);
            $path = "wa-avatars/{$hash}.jpg";
            \Illuminate\Support\Facades\Storage::disk('public')->put($path, $img->body());

            $contacto->update([
                'avatar_path'           => $path,
                'avatar_actualizado_at' => now(),
            ]);
            return true;
        } catch (\Exception $e) {
            Log::warning('Contacto::sincronizarAvatar fallo', ['jid' => $contacto->wa_id, 'err' => $e->getMessage()]);
            return false;
        }
    }

    /** ¿Necesita re-sincronizar avatar? (sin path o pasó el TTL) */
    public function avatarNecesitaSync(): bool
    {
        if (!$this->wa_id) return false;
        if (!$this->avatar_actualizado_at) return true;
        return $this->avatar_actualizado_at->lt(now()->subDays(self::AVATAR_TTL_DAYS));
    }
}
