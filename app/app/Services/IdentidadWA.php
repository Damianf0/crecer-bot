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
                $out[$d['jid']] = ['telefono' => $d['telefono'] ?? null, 'nombre' => ($d['agenda'] ?? null) ?: ($d['nombre'] ?? null),
                                   'agenda' => $d['agenda'] ?? null];
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
            $ficha = Contacto::where('telefono', $tel)->whereNull('wa_id')->whereNull('wa_id_rechazado')->first();
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

    /**
     * Fichas atadas al WhatsApp de OTRA persona. Regla conservadora: al menos
     * un celular de la clínica tiene ese WhatsApp agendado y NINGUNO lo tiene
     * con el nombre de la ficha (ni la inicial de un nombre en común: apodos y
     * "Mamá de X" no cuentan como distinto). La agenda la carga el equipo: es el
     * dato más confiable de quién es un número.
     *
     * @return array{casos: array, sin_bot: array}  casos = [['ficha' => Contacto, 'agendas' => [area => nombre]]]
     */
    public static function detectarAjenos(): array
    {
        $casos = []; $sinBot = [];
        Contacto::whereNotNull('wa_id')->where('wa_id', 'not like', '%@g.us')->orderBy('id')
            ->chunkById(400, function ($lote) use (&$casos, &$sinBot) {
                $agendas = [];
                foreach (array_keys(ConversacionWA::AREAS) as $area) {
                    $info = self::consultar($area, $lote->pluck('wa_id')->all());
                    if ($info === null) { $sinBot[$area] = true; continue; }
                    foreach ($info as $jid => $i) if (!empty($i['agenda'])) $agendas[$jid][$area] = $i['agenda'];
                }
                foreach ($lote as $f) {
                    $ag = $agendas[$f->wa_id] ?? [];
                    if (!$ag) continue;
                    $coincideAlguna = collect($ag)->contains(fn($n) => !ConversacionWA::nombresDistintos($f->nombre, $n));
                    if (!$coincideAlguna) $casos[] = ['ficha' => $f, 'agendas' => $ag];
                }
                usleep(300_000);   // lotes espaciados: lectura local del bot, pero sin ráfagas
            });
        return ['casos' => $casos, 'sin_bot' => array_keys($sinBot)];
    }

    /**
     * Desata una ficha del WhatsApp que no es suyo:
     *  - la ficha pierde ese wa_id (queda en wa_id_rechazado para que nada la
     *    vuelva a atar) y la foto, que es del otro;
     *  - sus conversaciones pasan a llamarse como las agendó la clínica;
     *  - los documentos que ESE WhatsApp mandó salen del legajo de la paciente
     *    (quedan en el sistema, sin paciente asignado).
     * @return array registro de lo hecho (para revertir si hiciera falta)
     */
    public static function desatar(Contacto $f, array $agendas): array
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($f, $agendas) {
            $jid = $f->wa_id;
            $nombre = reset($agendas);
            $jids = array_filter([$jid, $f->telefono ? $f->telefono . '@c.us' : null]);
            $convs = ConversacionWA::whereIn('contacto', $jids)->get();
            $antes = [];
            foreach ($convs as $c) {
                $antes[$c->id] = $c->nombre;
                $n = $agendas[$c->area] ?? $nombre;
                $c->update(['nombre' => mb_substr($n, 0, 255), 'nombre_wa' => mb_substr($n, 0, 100)]);
            }
            $docs = \App\Models\DocumentoPaciente::where('contacto_id', $f->id)
                ->whereIn('conversacion_id', $convs->pluck('id'))->pluck('id')->all();
            if ($docs) \App\Models\DocumentoPaciente::whereIn('id', $docs)->update(['contacto_id' => null]);
            $f->update(['wa_id' => null, 'avatar_path' => null, 'wa_id_rechazado' => $jid,
                        'wa_rechazo_nombre' => mb_substr($nombre, 0, 100), 'wa_rechazado_at' => now()]);

            // Re-vincular a quien realmente escribe, si tiene ficha propia inequívoca:
            // la conversación toma su nombre y los documentos pasan a su legajo.
            $real = null;
            foreach (array_unique($agendas) as $n) { if ($real = self::fichaPorNombreDeAgenda($n, $f->id)) break; }
            if ($real) {
                $real->update(['wa_id' => $jid]);
                foreach ($convs as $c) $c->update(['nombre' => $real->nombre]);
                if ($docs) \App\Models\DocumentoPaciente::whereIn('id', $docs)->update(['contacto_id' => $real->id]);
            }
            ConversacionWA::invalidarColaCache();
            return ['ficha_id' => $f->id, 'ficha_nombre' => $f->nombre, 'jid' => $jid, 'agendas' => $agendas,
                    'conversaciones' => $antes, 'documentos_desvinculados' => $docs,
                    'revinculada_a' => $real ? ['id' => $real->id, 'nombre' => $real->nombre] : null];
        });
    }

    /**
     * La ficha de quien REALMENTE escribe, por el nombre con que la clínica lo
     * agendó. Estricto: todas las palabras del nombre (al menos dos: nombre y
     * apellido) en la ficha, una sola ficha que cumpla y sin WhatsApp asignado.
     * Caso típico: la ficha del marido tenía el celular de la mujer; ella tiene
     * su propia ficha y ahora sus mensajes y estudios van a su legajo.
     */
    public static function fichaPorNombreDeAgenda(string $agenda, int $excluirId): ?Contacto
    {
        $palabras = array_values(array_filter(
            preg_split('/\s+/', trim(preg_replace('/[^\p{L} ]/u', ' ', $agenda))),
            fn($w) => mb_strlen($w) >= 3
        ));
        if (count($palabras) < 2) return null;
        $q = Contacto::whereNull('wa_id')->whereNull('wa_id_rechazado')->where('id', '!=', $excluirId);
        foreach ($palabras as $w) $q->where('nombre', 'like', '%' . $w . '%');
        $candidatas = $q->limit(2)->get();
        return $candidatas->count() === 1 ? $candidatas->first() : null;
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
