<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ficha del CRM de ovodonación (ver la migración 2026_11_12_100000).
 *
 * Los tableros y etapas copian los que el equipo usaba en Bigin, para que el
 * cambio no les cambie la forma de trabajar. Las etapas "terminales" no
 * generan avisos y en el tablero se muestran plegadas (hay cientos).
 */
class OvoFicha extends Model
{
    use SoftDeletes;

    protected $table = 'ovo_fichas';

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'etapa_desde'           => 'datetime',
        'fecha_nacimiento'      => 'date',
        'peso'                  => 'float',
        'tiene_hijos'           => 'boolean',
        'amamantando'           => 'boolean',
        'tatuaje_reciente'      => 'boolean',
        'fecha_tatuaje'         => 'date',
        'planea_tatuaje'        => 'boolean',
        'proximo_turno'         => 'datetime',
        'fecha_puncion'         => 'date',
        'recontactar_desde'     => 'date',
        'fecha_ingreso'         => 'date',
        'fecha_orden_entregada' => 'date',
        'vence_autorizacion'    => 'date',
        'fecha_transferencia'   => 'date',
    ];

    public const TIPOS = ['donante' => 'Donante', 'receptora' => 'Receptora'];

    /**
     * Tableros. 'tipo': quién vive ahí. 'terminales': no generan avisos.
     * 'espera': etapas donde la ficha está quieta a propósito (no "estancada").
     */
    public const PROCESOS = [
        'donantes' => [
            'label' => 'Seguimiento donantes',
            'tipo'  => 'donante',
            'etapas' => [
                'primer_contacto' => 'Primer contacto',
                'consulta'        => 'Consulta',
                'ecografia'       => 'Ecografía',
                'genetista'       => 'Genetista',
                'psicologa'       => 'Psicóloga',
                'estudios'        => 'Estudios',
                'prequirurgicos'  => 'Consulta + prequirúrgicos',
                'estimulacion'    => 'Estimulación',
                'puncion'         => 'Punción',
                'completada'      => 'Completada',
                'cancelado'       => 'Cancelado',
                'no_cumple'       => 'No cumple',
            ],
            'terminales' => ['completada', 'cancelado', 'no_cumple'],
            'espera'     => [],
        ],
        'ya_donaron' => [
            'label' => 'Ya donaron',
            'tipo'  => 'donante',
            'etapas' => [
                'esperar'    => 'Esperar',
                'contactada' => 'Contactada',
                'vuelve'     => 'Vuelve a donación',
                'cancelado'  => 'Cancelado',
            ],
            'terminales' => ['vuelve', 'cancelado'],
            'espera'     => ['esperar'],
        ],
        'tatuaje' => [
            'label' => 'Tatuaje',
            'tipo'  => 'donante',
            'etapas' => [
                'esperando'  => 'Esperando',
                'contactada' => 'Contactada',
                'vuelve'     => 'Vuelve a donación',
                'cancelado'  => 'Cancelado',
            ],
            'terminales' => ['vuelve', 'cancelado'],
            'espera'     => ['esperando'],
        ],
        'receptoras' => [
            'label' => 'Receptoras',
            'tipo'  => 'receptora',
            'etapas' => [
                'esperando'          => 'Esperando',
                'actualiza_estudios' => 'Actualiza estudios',
                'orden_entregada'    => 'Orden entregada',
                'orden_autorizada'   => 'Orden autorizada',
                'ovocitos_asignados' => 'Ovocitos asignados',
                'consulta_previa'    => 'Consulta previa',
                'fertilizacion'      => 'Fertilización',
                'transferencia'      => 'Transferencia',
                'cancelado'          => 'Cancelado',
            ],
            'terminales' => ['transferencia', 'cancelado'],
            'espera'     => ['esperando'],
        ],
    ];

    /** Etapas en las que se pide la fecha del próximo turno al mover (como en Bigin). */
    public const ETAPAS_CON_TURNO = ['consulta', 'ecografia', 'genetista', 'psicologa', 'estudios', 'prequirurgicos', 'consulta_previa'];

    /** Requisitos de ingreso de donantes (los workflows de Bigin, en el mismo orden). */
    public const REQUISITOS = [
        'imc_min'       => 18,
        'imc_max'       => 31,
        'edad_min'      => 20,
        'edad_max'      => 32,
        'altura_min'    => 153,
        'tatuaje_meses' => 6,
    ];

    /** Días sin cambio de etapa ni turno por delante para marcar una ficha como estancada. */
    public const DIAS_ESTANCADA = 21;

    // ── Relaciones ──────────────────────────────────────────

    public function movimientos()
    {
        return $this->hasMany(OvoMovimiento::class, 'ficha_id')->orderByDesc('id');
    }

    public function notas()
    {
        return $this->hasMany(OvoNota::class, 'ficha_id')->orderByDesc('id');
    }

    public function contacto()
    {
        return $this->belongsTo(Contacto::class, 'contacto_id');
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    // ── Derivados ───────────────────────────────────────────

    public static function etapaValida(string $proceso, string $etapa): bool
    {
        return isset(self::PROCESOS[$proceso]['etapas'][$etapa]);
    }

    public static function esTerminal(string $proceso, string $etapa): bool
    {
        return in_array($etapa, self::PROCESOS[$proceso]['terminales'] ?? [], true);
    }

    public function edadCalculada(): ?int
    {
        return $this->fecha_nacimiento ? $this->fecha_nacimiento->age : $this->edad;
    }

    public function imc(): ?float
    {
        if (!$this->peso || !$this->altura) return null;
        $m = $this->altura / 100;
        return round($this->peso / ($m * $m), 1);
    }

    public function nombreCompleto(): string
    {
        return trim($this->nombre . ' ' . $this->apellido);
    }

    /**
     * Evalúa los requisitos de ingreso en el orden de Bigin. Devuelve la
     * lista de controles (para mostrar en la ficha) y el primer fallo.
     * Un dato que falta no hace fallar: queda como "sin dato".
     *
     * @return array{controles: array<int, array{clave:string, label:string, estado:string, detalle:string}>, falla: ?array}
     */
    public function evaluarRequisitos(): array
    {
        $r = self::REQUISITOS;
        $controles = [];

        $imc = $this->imc();
        $controles[] = ['clave' => 'imc', 'label' => 'IMC',
            'estado'  => $imc === null ? 'sin_dato' : (($imc >= $r['imc_min'] && $imc <= $r['imc_max']) ? 'ok' : 'falla'),
            'detalle' => $imc === null ? 'Falta peso o altura' : "IMC {$imc} (pedido {$r['imc_min']}–{$r['imc_max']})"];

        $edad = $this->edadCalculada();
        $controles[] = ['clave' => 'edad', 'label' => 'Edad',
            'estado'  => $edad === null ? 'sin_dato' : (($edad >= $r['edad_min'] && $edad <= $r['edad_max']) ? 'ok' : 'falla'),
            'detalle' => $edad === null ? 'Falta la edad o la fecha de nacimiento' : "{$edad} años (pedido {$r['edad_min']}–{$r['edad_max']})"];

        $tat = $this->estadoTatuaje();
        $controles[] = ['clave' => 'tatuaje', 'label' => 'Tatuajes / piercings',
            'estado' => $tat['estado'], 'detalle' => $tat['detalle']];

        $controles[] = ['clave' => 'altura', 'label' => 'Altura',
            'estado'  => $this->altura === null ? 'sin_dato' : ($this->altura >= $r['altura_min'] ? 'ok' : 'falla'),
            'detalle' => $this->altura === null ? 'Falta la altura' : "{$this->altura} cm (mínimo {$r['altura_min']})"];

        $falla = collect($controles)->firstWhere('estado', 'falla');

        return ['controles' => $controles, 'falla' => $falla];
    }

    /** Tatuaje o piercing en los últimos N meses, o planea hacerse uno. */
    private function estadoTatuaje(): array
    {
        $meses = self::REQUISITOS['tatuaje_meses'];
        if ($this->planea_tatuaje) {
            return ['estado' => 'falla', 'detalle' => 'Planea hacerse un tatuaje o piercing'];
        }
        if ($this->tatuaje_reciente) {
            if ($this->fecha_tatuaje && $this->fecha_tatuaje->copy()->addMonths($meses)->isPast()) {
                return ['estado' => 'ok', 'detalle' => 'Ya pasaron ' . $meses . ' meses desde el ' . $this->fecha_tatuaje->format('d/m/Y')];
            }
            return ['estado' => 'falla', 'detalle' => $this->fecha_tatuaje
                ? 'Hecho el ' . $this->fecha_tatuaje->format('d/m/Y') . ': puede volver desde el ' . $this->fecha_tatuaje->copy()->addMonths($meses)->format('d/m/Y')
                : "Se hizo uno en los últimos {$meses} meses (falta preguntar cuándo)"];
        }
        if ($this->tatuaje_reciente === null && $this->planea_tatuaje === null) {
            return ['estado' => 'sin_dato', 'detalle' => 'Sin responder'];
        }
        return ['estado' => 'ok', 'detalle' => 'No'];
    }

    /**
     * Avisos de la tarjeta. Solo para etapas no terminales.
     * @return array<int, array{clave:string, texto:string, nivel:string}>
     */
    public function avisos(?Carbon $ahora = null): array
    {
        $ahora ??= now();
        if (self::esTerminal($this->proceso, $this->etapa)) return [];
        $av = [];
        $espera = in_array($this->etapa, self::PROCESOS[$this->proceso]['espera'] ?? [], true);

        if ($this->proximo_turno && $this->proximo_turno->lt($ahora->copy()->startOfDay())) {
            $av[] = ['clave' => 'turno_pasado', 'nivel' => 'espera',
                'texto' => 'Turno del ' . $this->proximo_turno->format('d/m') . ' pasado: ¿vino?'];
        }
        if ($this->fecha_puncion && $this->fecha_puncion->between($ahora->copy()->startOfDay(), $ahora->copy()->addDays(7))) {
            $av[] = ['clave' => 'puncion', 'nivel' => 'accent', 'texto' => 'Punción el ' . $this->fecha_puncion->format('d/m')];
        }
        if ($espera && $this->recontactar_desde && $this->recontactar_desde->lte($ahora)) {
            $av[] = ['clave' => 'recontactar', 'nivel' => 'nueva', 'texto' => 'Lista para recontactar'];
        }
        if ($this->proceso === 'tatuaje' && $this->etapa === 'esperando' && !$this->fecha_tatuaje) {
            $av[] = ['clave' => 'fecha_tatuaje', 'nivel' => 'neutral', 'texto' => 'Preguntar cuándo se hizo el tatuaje'];
        }
        if ($this->vence_autorizacion && $this->vence_autorizacion->lte($ahora->copy()->addDays(15))) {
            $av[] = ['clave' => 'autorizacion', 'nivel' => 'urgente', 'texto' => $this->vence_autorizacion->isPast()
                ? 'Autorización vencida' : 'Autorización vence el ' . $this->vence_autorizacion->format('d/m')];
        }
        $turnoPorDelante = $this->proximo_turno && $this->proximo_turno->gte($ahora->copy()->startOfDay());
        if (!$espera && !$turnoPorDelante && $this->etapa_desde && $this->etapa_desde->lt($ahora->copy()->subDays(self::DIAS_ESTANCADA))) {
            $av[] = ['clave' => 'estancada', 'nivel' => 'urgente',
                'texto' => 'Sin movimiento hace ' . (int) $this->etapa_desde->diffInDays($ahora) . ' días'];
        }

        return $av;
    }
}
