<?php

namespace App\Services\Difusion;

use App\Models\Contacto;
use App\Models\ConversacionWA;
use App\Models\DifusionBaja;
use App\Models\DifusionDestinatario;
use App\Models\MensajeWA;
use Illuminate\Support\Facades\DB;

class Difusiones
{
    /** Canal por su clave de config/difusion.php ('simulado', 'difusion', 'meta'…). */
    public static function canal(?string $clave = null): Canal
    {
        $cfg = config('difusion.canales.' . ($clave ?? config('difusion.canal_por_defecto'))) ?? ['tipo' => 'simulado'];
        return match ($cfg['tipo']) {
            'wwebjs'   => new CanalWwebjs($cfg['bot_url'], $cfg['nombre'] ?? 'Bot'),
            'cloudapi' => new CanalMeta($cfg),
            default    => new CanalSimulado(),
        };
    }

    public static function nombreCanal(?string $clave): string
    {
        return config("difusion.canales.{$clave}.nombre") ?? (string) $clave;
    }

    /**
     * Canales que se pueden elegir en una campaña, con su estado en vivo.
     * Una llamada corta a /status por bot (4 s máx.), solo al abrir la pantalla.
     */
    public static function canales(): array
    {
        $out = [];
        foreach (config('difusion.canales_habilitados') as $clave) {
            $cfg = config("difusion.canales.{$clave}");
            if (!$cfg) continue;
            $out[] = [
                'clave'  => $clave,
                'nombre' => $cfg['nombre'],
                'tipo'   => $cfg['tipo'],
                'riesgo' => $cfg['riesgo'] ?? null,
                'area'   => $cfg['area'] ?? null,
            ] + self::canal($clave)->estado();
        }
        return $out;
    }

    // ── Audiencias ────────────────────────────────────────────────────

    /**
     * Arma la lista de destinatarios a partir de filtros:
     *  - origen: 'contactos' | 'lista'
     *  - lista: texto con un número por línea (origen 'lista'; se puede poner "número, nombre")
     *  - area: solo quienes tienen conversación en esa área ('' = cualquiera)
     *  - solo_con_conversacion: solo quienes alguna vez escribieron (default sí:
     *    mandarle a quien nunca nos escribió es lo que más reportes de spam trae)
     *  - activos_dias: con actividad en los últimos N días
     *  - inactivos_dias: sin actividad en los últimos N días
     *  - excluir_recientes_dias: saltear a quien recibió una difusión hace menos de N días
     *
     * Siempre excluye bajas, números inválidos y repetidos.
     * @return array{destinatarios: array<int, array{contacto_id:?int, telefono:string, nombre:?string}>, descartados: array<string,int>}
     */
    public static function armarAudiencia(array $f): array
    {
        $descartados = ['invalidos' => 0, 'bajas' => 0, 'repetidos' => 0, 'recientes' => 0];
        $candidatos = [];

        if (($f['origen'] ?? 'contactos') === 'lista') {
            foreach (preg_split('/\r?\n/', (string) ($f['lista'] ?? '')) as $linea) {
                $linea = trim($linea);
                if ($linea === '') continue;
                [$num, $nombre] = array_pad(array_map('trim', preg_split('/[,;\t]/', $linea, 2)), 2, null);
                $tel = Contacto::normalizarTelefono((string) $num);
                if ($tel === '') { $descartados['invalidos']++; continue; }
                $c = Contacto::where('telefono', $tel)->first(['id', 'nombre']);
                $candidatos[] = ['contacto_id' => $c?->id, 'telefono' => $tel, 'nombre' => $nombre ?: $c?->nombre];
            }
        } else {
            $q = Contacto::query()->whereNotNull('telefono')->where('telefono', '!=', '');
            $conAct = (bool) ($f['solo_con_conversacion'] ?? true) || !empty($f['area'])
                || !empty($f['activos_dias']) || !empty($f['inactivos_dias']);
            if ($conAct) {
                $q->whereExists(function ($s) use ($f) {
                    $s->select(DB::raw(1))->from('conversaciones_wa as cv')
                      ->where(function ($w) {
                          $w->whereColumn('cv.contacto', 'contactos.wa_id')
                            ->orWhereRaw("cv.contacto = " . self::concatSql('contactos.telefono', "'@c.us'"));
                      });
                    if (!empty($f['area'])) $s->where('cv.area', $f['area']);
                    if (!empty($f['activos_dias'])) $s->where('cv.ultima_actividad', '>=', now()->subDays((int) $f['activos_dias']));
                });
                if (!empty($f['inactivos_dias'])) {
                    $q->whereNotExists(function ($s) use ($f) {
                        $s->select(DB::raw(1))->from('conversaciones_wa as cv')
                          ->where(function ($w) {
                              $w->whereColumn('cv.contacto', 'contactos.wa_id')
                                ->orWhereRaw("cv.contacto = " . self::concatSql('contactos.telefono', "'@c.us'"));
                          })
                          ->where('cv.ultima_actividad', '>=', now()->subDays((int) $f['inactivos_dias']));
                    });
                }
            }
            foreach ($q->orderBy('id')->cursor(['id', 'telefono', 'nombre']) as $c) {
                $tel = Contacto::normalizarTelefono((string) $c->telefono);
                if ($tel === '') { $descartados['invalidos']++; continue; }
                $candidatos[] = ['contacto_id' => $c->id, 'telefono' => $tel, 'nombre' => $c->nombre];
            }
        }

        $bajas = DifusionBaja::pluck('telefono')->flip();
        $recientes = collect();
        if (!empty($f['excluir_recientes_dias'])) {
            $recientes = DifusionDestinatario::where('enviado_at', '>=', now()->subDays((int) $f['excluir_recientes_dias']))
                ->pluck('telefono')->flip();
        }

        $vistos = [];
        $out = [];
        foreach ($candidatos as $d) {
            if (isset($vistos[$d['telefono']])) { $descartados['repetidos']++; continue; }
            $vistos[$d['telefono']] = true;
            if (isset($bajas[$d['telefono']])) { $descartados['bajas']++; continue; }
            if (isset($recientes[$d['telefono']])) { $descartados['recientes']++; continue; }
            $out[] = $d;
        }
        return ['destinatarios' => $out, 'descartados' => $descartados];
    }

    private static function concatSql(string $a, string $b): string
    {
        return DB::connection()->getDriverName() === 'sqlite' ? "({$a} || {$b})" : "CONCAT({$a}, {$b})";
    }

    // ── Respuestas y bajas ────────────────────────────────────────────

    /** "No más" → "NO MAS": mayúsculas, sin acentos ni puntuación. */
    public static function normalizarPalabra(?string $texto): string
    {
        $t = mb_strtoupper(trim((string) $texto));
        $t = strtr($t, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U']);
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^\p{L}\p{N} ]/u', '', $t)));
    }

    /**
     * ¿El mensaje pide la baja? Exacto para todas las palabras de la config
     * ("No más", "Baja."); además, las de una sola palabra (BAJA, STOP) valen
     * al principio de un mensaje corto ("Baja gracias"). Las frases no: "no más
     * dudas, gracias" no es una baja.
     */
    public static function esPedidoDeBaja(?string $texto): bool
    {
        $t = self::normalizarPalabra($texto);
        if ($t === '') return false;
        $palabras = array_map([self::class, 'normalizarPalabra'], config('difusion.palabras_baja'));
        if (in_array($t, $palabras, true)) return true;
        $tokens = explode(' ', $t);
        return count($tokens) <= 4
            && in_array($tokens[0], array_filter($palabras, fn($p) => !str_contains($p, ' ')), true);
    }

    /**
     * Un paciente escribió al número de difusiones. Marca "respondió" en su
     * última campaña (dentro de la ventana) y, si el texto es un pedido de
     * baja, lo da de baja y deja una nota en la conversación.
     */
    public static function registrarRespuesta(string $contactoWA, ?string $texto, int $convId, string $area = 'difusion'): void
    {
        // Colas de las áreas: solo si le llegó una difusión reciente (una
        // campaña puede salir por el número de un área). Un índice por
        // mensaje; sin campañas recientes no hay nada que buscar.
        $esColaDifusion = $area === ConversacionWA::AREA_DIFUSION;
        if (!$esColaDifusion && !DifusionDestinatario::where('enviado_at', '>=', now()->subHours((int) config('difusion.ventana_respuesta_horas')))->exists()) {
            return;
        }

        $tel = str_ends_with($contactoWA, '@c.us') ? Contacto::normalizarTelefono(str_replace('@c.us', '', $contactoWA)) : '';
        $contacto = Contacto::buscarPorContacto($contactoWA);
        if ($tel === '' && $contacto?->telefono) $tel = Contacto::normalizarTelefono($contacto->telefono);

        $dest = DifusionDestinatario::query()
            ->whereNotNull('enviado_at')
            ->where('enviado_at', '>=', now()->subHours((int) config('difusion.ventana_respuesta_horas')))
            ->where(function ($w) use ($contactoWA, $tel) {
                $w->where('chat_id', $contactoWA);
                if ($tel !== '') $w->orWhere('telefono', $tel);
            })
            ->orderByDesc('enviado_at')->first();

        if (!$dest && !$esColaDifusion) return;

        $esBaja = self::esPedidoDeBaja($texto);
        if ($dest && !$dest->respondio_at) {
            $dest->update(['respondio_at' => now()]);
            // Contexto para quien atiende: sin esto, en la cola de un área la
            // respuesta a una difusión parece un mensaje salido de la nada.
            if (!$esBaja) {
                MensajeWA::create([
                    'conversacion_id' => $convId, 'direccion' => 'nota_interna', 'tipo' => 'texto', 'leido' => false,
                    'contenido' => '📣 Responde a la difusión «' . ($dest->campania?->nombre ?? 'sin nombre') . '».',
                ]);
            }
        }

        if (!$esBaja) return;

        $telBaja = $dest?->telefono ?: $tel;
        if ($telBaja === '') {
            // @lid sin contacto asociado: no hay número que excluir. Queda la
            // nota para que lo resuelva una persona.
            $nota = '📣 Pidió la BAJA de las difusiones, pero no se pudo identificar su número: darlo de baja a mano desde Difusiones → Bajas.';
        } else {
            DifusionBaja::firstOrCreate(['telefono' => $telBaja], [
                'contacto_id' => $dest?->contacto_id ?? $contacto?->id,
                'origen'      => 'respuesta',
                'detalle'     => mb_substr((string) $texto, 0, 255),
            ]);
            if ($dest && !$dest->baja_at) $dest->update(['baja_at' => now()]);
            $nota = '📣 Pidió la BAJA: no va a recibir más difusiones.';
        }

        MensajeWA::create([
            'conversacion_id' => $convId,
            'direccion'       => 'nota_interna',
            'tipo'            => 'texto',
            'contenido'       => $nota,
            'leido'           => false,
        ]);
    }

    /** Acuse del bot (wwebjs): 2 entregado, 3/4 leído/escuchado, -1 error. */
    public static function aplicarAckWwebjs(string $waId, int $ack): bool
    {
        $dest = DifusionDestinatario::where('mensaje_id', $waId)->first();
        if (!$dest) return false;
        if ($ack < 0)      $dest->aplicarAcuse('fallido', 'WhatsApp no pudo entregarlo');
        elseif ($ack >= 3) $dest->aplicarAcuse('leido');
        elseif ($ack === 2) $dest->aplicarAcuse('entregado');
        return true;
    }

    // ── Límites de envío ──────────────────────────────────────────────

    /**
     * ¿Se puede mandar ahora? Devuelve null si sí, o el momento desde el que
     * vale la pena reintentar (fuera de horario o tope diario alcanzado).
     * El simulado no tiene límites.
     */
    public static function proximaVentana(string $canal): ?\Carbon\Carbon
    {
        if (config("difusion.canales.{$canal}.tipo", 'simulado') === 'simulado') return null;
        $ahora = now();
        [$desde, $hasta] = config('difusion.horario');
        $ini = $ahora->copy()->setTimeFromTimeString($desde);
        $fin = $ahora->copy()->setTimeFromTimeString($hasta);

        // El tope es por canal: dos campañas por el mismo número suman.
        $enviadosHoy = DifusionDestinatario::whereDate('enviado_at', $ahora->toDateString())
            ->whereHas('campania', fn($q) => $q->where('canal', $canal))->count();

        if ($ahora->lt($ini)) return $ini;
        if ($ahora->gte($fin) || $enviadosHoy >= (int) config('difusion.tope_diario')) {
            $sig = $ini->copy()->addDay();
            // Domingo afuera: nadie quiere una difusión de la clínica un domingo.
            if ($sig->isSunday()) $sig->addDay();
            return $sig;
        }
        if ($ahora->isSunday()) return $ini->copy()->addDay();
        return null;
    }
}
