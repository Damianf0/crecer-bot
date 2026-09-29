<?php

namespace App\Services;

use App\Models\Contacto;
use App\Models\ConversacionWA;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Identidad de WhatsApp de las conversaciones: el teléfono real detrás de un
 * @lid y el nombre de perfil, según WhatsApp Web (POST /contactos-info del bot
 * del área: una lectura local por lote, sin pedidos a la red).
 *
 * Vinculación con el directorio SOLO en la dirección segura: si el teléfono que
 * da WhatsApp coincide con una ficha que todavía no tiene WhatsApp asignado. Si
 * la ficha ya tiene otro wa_id no se toca (un conflicto lo resuelve una persona).
 */
class IdentidadWA
{
    /** @return array<string, array{telefono:?string, nombre:?string}>|null  null si el bot no respondió */
    public static function consultar(string $area, array $jids): ?array
    {
        if (!$jids) return [];
        try {
            $r = Http::timeout(30)->withToken((string) config('app.bot_ingress_token'))
                ->post(ConversacionWA::botUrlPara($area) . '/contactos-info', ['jids' => array_values($jids)]);
            if (!$r->ok() || !$r->json('ok')) return null;
            $out = [];
            foreach ((array) $r->json('data') as $d) {
                // El nombre con que la clínica agendó el número gana sobre el de
                // perfil (lo pone el equipo, no la persona: "Dr Elena" vs un apodo).
                $out[$d['jid']] = ['telefono' => $d['telefono'] ?? null, 'nombre' => ($d['agenda'] ?? null) ?: ($d['nombre'] ?? null)];
            }
            return $out;
        } catch (\Throwable $e) {
            // El log tampoco puede romper nada (incidente 23/09: log de root que no se podía escribir).
            try { Log::warning('IdentidadWA: el bot no respondió', ['area' => $area, 'err' => $e->getMessage()]); } catch (\Throwable) {}
            return null;
        }
    }

    /**
     * Guarda la identidad en la conversación y, si corresponde, la vincula.
     * @return string|null 'vinculada' si se asoció a una ficha en esta pasada
     */
    public static function aplicar(ConversacionWA $c, array $info): ?string
    {
        $tel = $info['telefono'] ? Contacto::normalizarTelefono($info['telefono']) : '';
        // Números de otros países: normalizarTelefono solo conoce Argentina; se guarda tal cual.
        if ($tel === '' && $info['telefono']) $tel = preg_replace('/\D/', '', $info['telefono']);
        $nombreWa = $info['nombre'] ? mb_substr(trim($info['nombre']), 0, 100) : null;

        $c->fill(['telefono_wa' => $tel ?: null, 'nombre_wa' => $nombreWa ?: null, 'wa_info_at' => now()]);

        $resultado = null;
        if ($tel !== '' && str_ends_with($c->contacto, '@lid')
            && !Contacto::where('wa_id', $c->contacto)->exists()) {
            $ficha = Contacto::where('telefono', $tel)->whereNull('wa_id')->first();
            if ($ficha) {
                $ficha->update(['wa_id' => $c->contacto]);
                $resultado = 'vinculada';
            }
        }
        if (!$c->nombre) {
            $ficha ??= Contacto::buscarPorContacto($c->contacto);
            if ($ficha?->nombre) $c->nombre = $ficha->nombre;
        }
        $c->save();
        return $resultado;
    }

    /** Identifica una sola conversación (al crearse). Nunca tira excepción. */
    public static function identificar(ConversacionWA $c): void
    {
        try {
            $info = self::consultar($c->area, [$c->contacto]);
            if ($info && isset($info[$c->contacto])) self::aplicar($c, $info[$c->contacto]);
        } catch (\Throwable) {
            // Es un agregado: si falla, la conversación queda como antes y la completa el comando nocturno.
        }
    }
}
