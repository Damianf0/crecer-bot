<?php

namespace App\Http\Controllers;

use App\Models\Contacto;
use App\Models\ConversacionWA;
use App\Models\OvoFicha;
use App\Models\OvoMovimiento;
use App\Models\OvoNota;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * CRM de ovodonación (/v2/crm-ovo): donantes y receptoras por tablero y etapa.
 * Reemplaza Zoho Bigin. Solo entra quien tiene el permiso 'crm_ovodonacion'
 * (nadie lo tiene por defecto, tampoco supervisión).
 *
 * Criterio de automatización: lo administrativo se mueve solo (filtros de
 * ingreso, "vuelve a donación" → Primer contacto); lo clínico solo se avisa
 * y una persona decide.
 */
class OvoCrmController extends Controller
{
    /** Cuántas fichas de cada etapa terminal se mandan al tablero (el resto se cuenta). */
    private const TOPE_TERMINAL = 40;

    public function index()
    {
        return view('v2.crm-ovo', [
            'modulo'    => 'Ovodonación',
            'title'     => 'CRM de ovodonación',
            'navActive' => 'crm-ovo',
        ]);
    }

    /** GET /crm-ovo/tablero?proceso=donantes&q= */
    public function tablero(Request $request): JsonResponse
    {
        $proceso = array_key_exists($request->query('proceso'), OvoFicha::PROCESOS) ? $request->query('proceso') : 'donantes';
        $def = OvoFicha::PROCESOS[$proceso];
        $q = trim((string) $request->query('q', ''));

        // Cada palabra tiene que aparecer en algún campo ("ana lopez" encuentra nombre + apellido).
        $base = OvoFicha::where('proceso', $proceso);
        foreach (preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY) as $palabra) {
            $base->where(fn ($x) => $x
                ->where('nombre', 'like', "%{$palabra}%")->orWhere('apellido', 'like', "%{$palabra}%")
                ->orWhere('dni', 'like', "%{$palabra}%")->orWhere('telefono', 'like', "%{$palabra}%")
                ->orWhere('codigo_donante', 'like', "%{$palabra}%"));
        }

        $conteos = (clone $base)->selectRaw('etapa, COUNT(*) n')->groupBy('etapa')->pluck('n', 'etapa');

        $columnas = [];
        foreach ($def['etapas'] as $clave => $label) {
            $terminal = in_array($clave, $def['terminales'], true);
            $fichas = (clone $base)->where('etapa', $clave)
                ->orderBy($terminal ? 'etapa_desde' : 'proximo_turno', $terminal ? 'desc' : 'asc')
                ->orderBy('etapa_desde')
                ->when($terminal, fn ($w) => $w->limit(self::TOPE_TERMINAL))
                ->get();
            $columnas[] = [
                'etapa'     => $clave,
                'label'     => $label,
                'terminal'  => $terminal,
                'total'     => (int) ($conteos[$clave] ?? 0),
                'fichas'    => $fichas->map(fn ($f) => $this->tarjeta($f))->values(),
            ];
        }

        // Cantidad de fichas con avisos en cada tablero, para las pestañas.
        $alertas = [];
        foreach (OvoFicha::PROCESOS as $p => $d) {
            $alertas[$p] = OvoFicha::where('proceso', $p)->whereNotIn('etapa', $d['terminales'])->get()
                ->filter(fn ($f) => count($f->avisos()) > 0)->count();
        }

        return response()->json([
            'ok'        => true,
            'proceso'   => $proceso,
            'columnas'  => $columnas,
            'alertas'   => $alertas,
            'procesos'  => collect(OvoFicha::PROCESOS)->map(fn ($d) => [
                'label' => $d['label'], 'tipo' => $d['tipo'], 'etapas' => $d['etapas'], 'terminales' => $d['terminales'],
            ]),
            'etapas_con_turno' => OvoFicha::ETAPAS_CON_TURNO,
            'requisitos'       => OvoFicha::REQUISITOS,
            'responsables'     => $this->responsables(),
        ]);
    }

    /** GET /crm-ovo/ficha/{id} */
    public function ficha(int $id): JsonResponse
    {
        $f = OvoFicha::with(['notas.usuario:id,nombre_completo', 'movimientos.usuario:id,nombre_completo'])->findOrFail($id);

        return response()->json(['ok' => true, 'ficha' => $this->detalle($f)]);
    }

    /** POST /crm-ovo/ficha — alta. Las donantes nuevas pasan por los filtros de ingreso. */
    public function store(Request $request): JsonResponse
    {
        $d = $this->validar($request, null);
        $proceso = $request->input('proceso', $d['tipo'] === 'receptora' ? 'receptoras' : 'donantes');
        abort_unless(isset(OvoFicha::PROCESOS[$proceso]) && OvoFicha::PROCESOS[$proceso]['tipo'] === $d['tipo'], 422, 'Tablero inválido para ese tipo.');
        $etapa = $request->input('etapa') ?: array_key_first(OvoFicha::PROCESOS[$proceso]['etapas']);
        abort_unless(OvoFicha::etapaValida($proceso, $etapa), 422, 'Etapa inválida.');

        if ($dup = $this->duplicada($d, null)) {
            return response()->json(['ok' => false, 'error' => "Ya hay una ficha de {$dup->nombreCompleto()} con ese DNI.", 'ficha_id' => $dup->id], 422);
        }

        $f = DB::transaction(function () use ($d, $proceso, $etapa) {
            $f = OvoFicha::create($d + [
                'proceso' => $proceso, 'etapa' => $etapa, 'etapa_desde' => now(),
                'origen' => 'panel', 'creado_por' => Auth::id(),
            ]);
            OvoMovimiento::create(['ficha_id' => $f->id, 'proceso_hasta' => $proceso, 'etapa_hasta' => $etapa,
                'motivo' => 'Alta', 'user_id' => Auth::id()]);

            if ($proceso === 'donantes' && $etapa === 'primer_contacto') $this->aplicarFiltrosIngreso($f);
            return $f;
        });

        return response()->json(['ok' => true, 'ficha' => $this->detalle($f->fresh(['notas', 'movimientos.usuario:id,nombre_completo']))], 201);
    }

    /** POST /crm-ovo/ficha/{id} — datos de la ficha (no cambia la etapa). */
    public function update(Request $request, int $id): JsonResponse
    {
        $f = OvoFicha::findOrFail($id);
        $d = $this->validar($request, $f);
        unset($d['tipo']); // el tipo no cambia: una donante no pasa a receptora

        if ($dup = $this->duplicada($d + ['tipo' => $f->tipo], $f->id)) {
            return response()->json(['ok' => false, 'error' => "Ya hay una ficha de {$dup->nombreCompleto()} con ese DNI.", 'ficha_id' => $dup->id], 422);
        }

        // Si cambia la fecha del tatuaje mientras espera, se recalcula cuándo recontactarla.
        $f->fill($d);
        if ($f->proceso === 'tatuaje' && $f->isDirty('fecha_tatuaje') && $f->fecha_tatuaje && !$request->has('recontactar_desde')) {
            $f->recontactar_desde = $f->fecha_tatuaje->copy()->addMonths(OvoFicha::REQUISITOS['tatuaje_meses']);
        }
        $f->save();

        return response()->json(['ok' => true, 'ficha' => $this->detalle($f->fresh(['notas.usuario:id,nombre_completo', 'movimientos.usuario:id,nombre_completo']))]);
    }

    /** POST /crm-ovo/ficha/{id}/mover {proceso?, etapa, motivo?, proximo_turno?, fecha_puncion?} */
    public function mover(Request $request, int $id): JsonResponse
    {
        $f = OvoFicha::findOrFail($id);
        $d = $request->validate([
            'proceso'       => ['nullable', Rule::in(array_keys(OvoFicha::PROCESOS))],
            'etapa'         => 'required|string|max:30',
            'motivo'        => 'nullable|string|max:255',
            'proximo_turno' => 'nullable|date',
            'fecha_puncion' => 'nullable|date',
            'recontactar_desde' => 'nullable|date',
        ]);
        $proceso = $d['proceso'] ?? $f->proceso;
        abort_unless(OvoFicha::PROCESOS[$proceso]['tipo'] === $f->tipo, 422, 'Esa ficha no puede ir a ese tablero.');
        abort_unless(OvoFicha::etapaValida($proceso, $d['etapa']), 422, 'Etapa inválida.');
        $motivo = trim((string) ($d['motivo'] ?? '')) ?: null;
        if (in_array($d['etapa'], ['cancelado', 'no_cumple'], true) && !$motivo) {
            return response()->json(['ok' => false, 'error' => 'Indicá el motivo.'], 422);
        }
        if ($proceso === $f->proceso && $d['etapa'] === $f->etapa) {
            return response()->json(['ok' => true, 'ficha' => $this->tarjeta($f)]);
        }

        DB::transaction(function () use ($f, $proceso, $d, $motivo) {
            $extra = array_filter([
                'proximo_turno'     => $d['proximo_turno'] ?? null,
                'fecha_puncion'     => $d['fecha_puncion'] ?? null,
                'recontactar_desde' => $d['recontactar_desde'] ?? null,
            ], fn ($v) => $v !== null);
            if ($d['etapa'] === 'cancelado') $extra['motivo_cancelacion'] = $motivo;
            if ($d['etapa'] === 'no_cumple') $extra['motivo_no_cumple'] = $motivo;

            $this->moverA($f, $proceso, $d['etapa'], $motivo, false, $extra);

            // "Vuelve a donación": la ficha vuelve sola al primer paso del seguimiento.
            if ($d['etapa'] === 'vuelve') {
                $this->moverA($f, 'donantes', 'primer_contacto', 'Vuelve a donación', true,
                    ['proximo_turno' => null, 'fecha_puncion' => null, 'recontactar_desde' => null,
                     'motivo_cancelacion' => null, 'motivo_no_cumple' => null]);
            }
        });

        return response()->json(['ok' => true, 'ficha' => $this->tarjeta($f->fresh())]);
    }

    /** POST /crm-ovo/ficha/{id}/nota {texto} */
    public function nota(Request $request, int $id): JsonResponse
    {
        $f = OvoFicha::findOrFail($id);
        $d = $request->validate(['texto' => 'required|string|max:4000']);
        OvoNota::create(['ficha_id' => $f->id, 'user_id' => Auth::id(), 'texto' => trim($d['texto'])]);
        $f->touch();

        return response()->json(['ok' => true, 'ficha' => $this->detalle($f->fresh(['notas.usuario:id,nombre_completo', 'movimientos.usuario:id,nombre_completo']))]);
    }

    /** POST /crm-ovo/ficha/{id}/borrar — baja lógica (se puede recuperar desde la base). */
    public function destroy(int $id): JsonResponse
    {
        OvoFicha::findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    // ── Internos ────────────────────────────────────────────

    private function moverA(OvoFicha $f, string $proceso, string $etapa, ?string $motivo, bool $automatico, array $extra = []): void
    {
        OvoMovimiento::create([
            'ficha_id' => $f->id, 'proceso_desde' => $f->proceso, 'etapa_desde' => $f->etapa,
            'proceso_hasta' => $proceso, 'etapa_hasta' => $etapa, 'motivo' => $motivo,
            'automatico' => $automatico, 'user_id' => $automatico ? null : Auth::id(),
        ]);
        $f->fill($extra + ['proceso' => $proceso, 'etapa' => $etapa, 'etapa_desde' => now()])->save();
    }

    /**
     * Filtros de ingreso (los workflows de Bigin, en cascada). El primer
     * requisito que no se cumple decide: tatuaje → tablero Tatuaje a esperar
     * los meses que falten; el resto → No cumple con el motivo.
     */
    private function aplicarFiltrosIngreso(OvoFicha $f): void
    {
        $falla = $f->evaluarRequisitos()['falla'];
        if (!$falla) return;

        if ($falla['clave'] === 'tatuaje') {
            $desde = ($f->tatuaje_reciente && $f->fecha_tatuaje)
                ? $f->fecha_tatuaje->copy()->addMonths(OvoFicha::REQUISITOS['tatuaje_meses']) : null;
            $this->moverA($f, 'tatuaje', 'esperando', 'Requisito de ingreso: ' . $falla['detalle'], true,
                ['recontactar_desde' => $desde]);
            return;
        }
        $motivo = $falla['label'] . ': ' . $falla['detalle'];
        $this->moverA($f, 'donantes', 'no_cumple', 'Requisito de ingreso — ' . $motivo, true, ['motivo_no_cumple' => $motivo]);
    }

    private function validar(Request $request, ?OvoFicha $f): array
    {
        $d = $request->validate([
            'tipo'              => [$f ? 'nullable' : 'required', Rule::in(array_keys(OvoFicha::TIPOS))],
            'nombre'            => 'required|string|max:80',
            'apellido'          => 'nullable|string|max:80',
            'dni'               => 'nullable|string|max:15',
            'email'             => 'nullable|email|max:120',
            'telefono'          => 'nullable|string|max:30',
            'fecha_nacimiento'  => 'nullable|date|before:today',
            'edad'              => 'nullable|integer|min:14|max:70',
            'peso'              => 'nullable|numeric|min:30|max:250',
            'altura'            => 'nullable|integer|min:100|max:220',
            'tiene_hijos'       => 'nullable|boolean',
            'amamantando'       => 'nullable|boolean',
            'tatuaje_reciente'  => 'nullable|boolean',
            'fecha_tatuaje'     => 'nullable|date|before_or_equal:today',
            'planea_tatuaje'    => 'nullable|boolean',
            'tatuaje_detalle'   => 'nullable|string|max:160',
            'medicacion'        => 'nullable|string|max:255',
            'fuente'            => 'nullable|string|max:60',
            'codigo_donante'    => 'nullable|string|max:30',
            'proximo_turno'     => 'nullable|date',
            'fecha_puncion'     => 'nullable|date',
            'estudios'          => 'nullable|string|max:4000',
            'recontactar_desde' => 'nullable|date',
            'medico'            => 'nullable|string|max:80',
            'cobertura'         => 'nullable|string|max:80',
            'fecha_ingreso'     => 'nullable|date',
            'fecha_orden_entregada' => 'nullable|date',
            'vence_autorizacion'    => 'nullable|date',
            'fecha_transferencia'   => 'nullable|date',
            'descripcion'       => 'nullable|string|max:4000',
            'responsable_id'    => 'nullable|integer|exists:users,id',
        ]);

        foreach ($d as $k => $v) {
            if (is_string($v)) $d[$k] = trim($v) === '' ? null : trim($v);
        }
        $d['dni'] = preg_replace('/\D/', '', (string) ($d['dni'] ?? '')) ?: null;

        // Vínculo con la ficha de Contactos si ya existe (solo lectura).
        $contacto = null;
        if ($d['dni']) $contacto = Contacto::where('dni', $d['dni'])->first();
        if (!$contacto && !empty($d['telefono']) && ($tel = Contacto::normalizarTelefono($d['telefono']))) {
            $contacto = Contacto::where('telefono', $tel)->first();
        }
        $d['contacto_id'] = $contacto?->id ?? $f?->contacto_id;

        return $d;
    }

    private function duplicada(array $d, ?int $excepto): ?OvoFicha
    {
        if (empty($d['dni'])) return null;
        return OvoFicha::where('dni', $d['dni'])->where('tipo', $d['tipo'] ?? 'donante')
            ->when($excepto, fn ($q) => $q->where('id', '!=', $excepto))->first();
    }

    /** Usuarios con acceso al CRM (para elegir responsable). */
    private function responsables(): array
    {
        return User::where('activo', true)->orderBy('nombre_completo')->get(['id', 'nombre_completo', 'rol', 'permisos'])
            ->filter(fn ($u) => $u->hasPermiso('crm_ovodonacion'))
            ->map(fn ($u) => ['id' => $u->id, 'nombre' => $u->nombre_completo])->values()->all();
    }

    private function tarjeta(OvoFicha $f): array
    {
        return [
            'id'             => $f->id,
            'tipo'           => $f->tipo,
            'proceso'        => $f->proceso,
            'etapa'          => $f->etapa,
            'nombre'         => $f->nombreCompleto(),
            'edad'           => $f->edadCalculada(),
            'codigo_donante' => $f->codigo_donante,
            'telefono'       => $f->telefono,
            'proximo_turno'  => $f->proximo_turno?->format('Y-m-d H:i'),
            'fecha_puncion'  => $f->fecha_puncion?->toDateString(),
            'dias_en_etapa'  => $f->etapa_desde ? (int) $f->etapa_desde->diffInDays(now()) : null,
            'motivo'         => $f->etapa === 'cancelado' ? $f->motivo_cancelacion : ($f->etapa === 'no_cumple' ? $f->motivo_no_cumple : null),
            'avisos'         => $f->avisos(),
        ];
    }

    private function detalle(OvoFicha $f): array
    {
        $datos = $f->only([
            'id', 'tipo', 'proceso', 'etapa', 'nombre', 'apellido', 'dni', 'email', 'telefono', 'edad', 'peso', 'altura',
            'tiene_hijos', 'amamantando', 'tatuaje_reciente', 'planea_tatuaje', 'tatuaje_detalle', 'medicacion', 'fuente',
            'codigo_donante', 'estudios', 'motivo_cancelacion', 'motivo_no_cumple', 'medico', 'cobertura', 'descripcion',
            'responsable_id', 'contacto_id', 'origen',
        ]);
        foreach (['fecha_nacimiento', 'fecha_tatuaje', 'fecha_puncion', 'recontactar_desde', 'fecha_ingreso',
                  'fecha_orden_entregada', 'vence_autorizacion', 'fecha_transferencia'] as $c) {
            $datos[$c] = $f->{$c}?->toDateString();
        }
        $datos['proximo_turno'] = $f->proximo_turno?->format('Y-m-d\TH:i');

        return $datos + [
            'nombre_completo' => $f->nombreCompleto(),
            'edad_calculada'  => $f->edadCalculada(),
            'imc'             => $f->imc(),
            'requisitos'      => $f->tipo === 'donante' ? $f->evaluarRequisitos()['controles'] : [],
            'avisos'          => $f->avisos(),
            'etapa_desde'     => $f->etapa_desde?->format('d/m/Y'),
            'conversacion'    => $this->conversacion($f),
            'notas'           => $f->notas->map(fn ($n) => [
                'id' => $n->id, 'texto' => $n->texto, 'por' => $n->usuario?->nombre_completo,
                'fecha' => $n->created_at?->format('d/m/Y H:i'),
            ])->values(),
            'movimientos'     => $f->movimientos->map(fn ($m) => [
                'desde'  => $m->etapa_desde ? $this->etiqueta($m->proceso_desde, $m->etapa_desde) : null,
                'hasta'  => $this->etiqueta($m->proceso_hasta, $m->etapa_hasta),
                'motivo' => $m->motivo, 'automatico' => $m->automatico,
                'por'    => $m->usuario?->nombre_completo, 'fecha' => $m->created_at?->format('d/m/Y H:i'),
            ])->values(),
        ];
    }

    private function etiqueta(?string $proceso, ?string $etapa): string
    {
        $p = OvoFicha::PROCESOS[$proceso] ?? null;
        if (!$p) return (string) $etapa;
        return $p['label'] . ' › ' . ($p['etapas'][$etapa] ?? $etapa);
    }

    /** Conversación del WhatsApp de ovodonación de esta persona, si quien mira puede verla. */
    private function conversacion(OvoFicha $f): ?array
    {
        if (!ConversacionWA::puedeVer(Auth::user(), 'ovodonacion')) return null;
        $tel = $f->telefono ? Contacto::normalizarTelefono($f->telefono) : null;
        $waId = $f->contacto?->wa_id;
        if (!$tel && !$waId) return null;

        $c = ConversacionWA::where('area', 'ovodonacion')
            ->where(function ($w) use ($tel, $waId) {
                if ($waId) $w->orWhere('contacto', $waId);
                if ($tel) $w->orWhere('contacto', $tel . '@c.us')->orWhere('telefono_wa', $tel);
            })
            ->orderByDesc('ultima_actividad')->first(['id', 'estado', 'ultima_actividad']);

        return $c ? ['id' => $c->id, 'estado' => $c->estado,
            'ultima' => $c->ultima_actividad ? Carbon::parse($c->ultima_actividad)->format('d/m/Y H:i') : null,
            'url' => '/v2/atencion/ovodonacion?conv=' . $c->id] : null;
    }
}
