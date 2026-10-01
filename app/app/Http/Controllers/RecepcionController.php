<?php

namespace App\Http\Controllers;

use App\Models\ColaAtencion;
use App\Services\ChecklistRecepcion;
use App\Models\Derivacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recepción / Turnos de sala en el shell V2. Reescribe a JS + endpoints los
 * Livewire de producción ColaSecretaria (/secretaria) y ColaBot (/cola-bot),
 * que no exponían REST. Misma DB y mismas transiciones de estado:
 *
 *   cola_atencion:  esperando → en_atencion → liberado | resuelto
 *   derivaciones:   pendiente → en_atencion → resuelto
 *
 * InboxWA (/inbox-wa) NO se porta: se solapa 1:1 con /v2/atencion (mismas tablas
 * ConversacionWA/MensajeWA/TareaWA). La gestión de chat WA vive en Atención V2.
 */
class RecepcionController extends Controller
{
    /** Minutos de espera a partir de los cuales se marca alerta (igual que ColaSecretaria::$umbralEspera). */
    private const UMBRAL_ESPERA = 20;

    public function indexV2()
    {
        return view('v2.recepcion', [
            'modulo'    => 'Recepción',
            'title'     => 'Recepción',
            'navActive' => 'recepcion',
            'motivosMostrador' => self::MOTIVOS_MOSTRADOR,
        ]);
    }

    // ── Cola de recepción (ColaSecretaria) ────────────────────────────

    private function mapPaciente(ColaAtencion $p): array
    {
        return [
            'id'             => $p->id,
            'nombre'         => $p->nombre_completo,
            'dni'            => $p->dni,
            'obra_social'    => $p->obra_social,
            'plan'           => $p->plan,
            'financiador'    => $p->financiador,
            'practica'       => $p->practica,
            'profesional'    => $p->profesional,
            'turno_hora'     => $p->turno_hora ? substr($p->turno_hora, 0, 5) : null,
            'planta'         => $p->planta,
            'motivo'         => $p->motivo,
            'estado'         => $p->estado,
            'orden'          => $p->orden,
            'primera_vez'    => $p->primera_vez,
            'sin_turno'      => $p->sin_turno,
            'derivado_bot'   => $p->derivado_bot,
            'alerta_espera'  => $p->alerta_espera,
            'minutos_espera' => $p->minutos_espera,
            'flags'          => $p->getFlags(),
            'checklist'      => $p->checklist ?? [],
            'nota'           => $p->nota,
            'hora_llegada'   => $p->hora_llegada?->format('H:i'),
            'hora_llamado'   => $p->hora_llamado?->format('H:i'),
            'presente_at'    => $p->presente_at?->format('H:i'),
        ];
    }

    /**
     * Lista de la cola (esperando + en_atencion). De paso marca la alerta de
     * espera larga en lote, igual que ColaSecretaria::revisarAlertas() — así el
     * polling del front no necesita un endpoint aparte.
     */
    public function cola(): JsonResponse
    {
        ColaAtencion::activos()
            ->where('alerta_espera', false)
            ->where('hora_llegada', '<=', now()->subMinutes(self::UMBRAL_ESPERA))
            ->update(['alerta_espera' => true]);

        $cola = ColaAtencion::activos()->get();

        // En la clínica: liberados al consultorio hoy, en las últimas EN_CLINICA_HORAS.
        $enClinica = ColaAtencion::where('estado', 'liberado')->whereNull('salio_at')
            ->whereDate('hora_llegada', today())
            ->where(fn ($q) => $q->where('hora_liberado', '>=', now()->subHours(self::EN_CLINICA_HORAS))
                ->orWhere(fn ($q) => $q->whereNull('hora_liberado')->where('hora_llegada', '>=', now()->subHours(self::EN_CLINICA_HORAS))))
            ->orderByDesc('hora_liberado')->get();

        return response()->json([
            'ok'   => true,
            'cola' => $cola->map(fn ($p) => $this->mapPaciente($p))->values(),
            'en_clinica' => $enClinica->map(fn ($p) => [
                'id'          => $p->id,
                'nombre'      => $p->nombre_completo,
                'dni'         => $p->dni,
                'nombre_solo' => $p->nombre,
                'apellido'    => $p->apellido,
                'obra_social' => $p->obra_social,
                'profesional' => $p->profesional,
                'practica'    => $p->practica,
                'desde'       => ($p->hora_liberado ?? $p->hora_llegada)?->format('H:i'),
                'minutos'     => (int) ($p->hora_liberado ?? $p->hora_llegada)?->diffInMinutes(now()),
                'atendido'    => $p->atendido_at?->format('H:i'),   // el médico ya lo marcó atendido
            ])->values(),
            'en_clinica_horas' => self::EN_CLINICA_HORAS,
            'stats' => [
                'total'       => $cola->count(),
                'esperando'   => $cola->where('estado', 'esperando')->count(),
                'en_atencion' => $cola->where('estado', 'en_atencion')->count(),
                'alertas'     => $cola->where('alerta_espera', true)->count(),
                // Registro del día: cuántos llegaron por el tablet y cuántos se cargaron en el mostrador.
                'hoy_tablet'    => ColaAtencion::whereDate('hora_llegada', today())->where('origen', 'tablet')->count(),
                'hoy_mostrador' => ColaAtencion::whereDate('hora_llegada', today())->where('origen', 'mostrador')->count(),
            ],
        ]);
    }

    /** Abre la ficha: esperando → en_atencion + hora_llamado. Inicializa checklist si faltaba. */
    public function abrirPaciente(int $id): JsonResponse
    {
        $p = ColaAtencion::findOrFail($id);

        $cambios = [];
        if ($p->estado === 'esperando') {
            $cambios['estado']       = 'en_atencion';
            $cambios['hora_llamado'] = now();
        }
        if (empty($p->checklist)) {
            $cambios['checklist'] = ChecklistRecepcion::paraPaciente($p);
        }
        if ($cambios) $p->update($cambios);

        return response()->json(['ok' => true, 'paciente' => $this->mapPaciente($p->fresh())]);
    }

    /** Toggle de un ítem del checklist de recepción. */
    public function toggleChecklist(Request $r, int $id): JsonResponse
    {
        $data = $r->validate(['item_id' => 'required|string|max:50']);
        $p = ColaAtencion::findOrFail($id);

        $checklist = $p->checklist ?: ChecklistRecepcion::paraPaciente($p);
        foreach ($checklist as &$item) {
            if ($item['id'] === $data['item_id']) {
                $item['done'] = !$item['done'];
                break;
            }
        }
        $p->update(['checklist' => $checklist]);

        return response()->json(['ok' => true, 'checklist' => $checklist, 'completo' => $p->fresh()->checklistCompleto()]);
    }

    /** Guarda la nota interna del paciente. */
    public function notaPaciente(Request $r, int $id): JsonResponse
    {
        $data = $r->validate(['nota' => 'nullable|string|max:2000']);
        ColaAtencion::findOrFail($id)->update(['nota' => $data['nota'] ?? null]);
        return response()->json(['ok' => true]);
    }

    /**
     * Da el presente y libera a sala. Los obligatorios sin tildar NO frenan
     * (decisión 21/09: solo se avisa): el front pide confirmación y acá quedan
     * registrados en presente_faltantes, con quién y cuándo dio el presente.
     * El presente es solo local: la API de Omnia no permite pasar el turno a
     * "recepcionado" (única escritura disponible: cancelar).
     */
    public function liberar(int $id): JsonResponse
    {
        $p = ColaAtencion::findOrFail($id);
        $faltantes = collect($p->checklist ?? [])
            ->filter(fn ($i) => !empty($i['obligatorio']) && empty($i['done']))
            ->pluck('label')->values()->all();

        $p->update([
            'estado'             => 'liberado',
            'hora_liberado'      => now(),
            'presente_at'        => $p->presente_at ?? now(),
            'presente_por'       => $p->presente_por ?? auth()->id(),
            'presente_faltantes' => $faltantes ?: null,
        ]);
        return response()->json(['ok' => true, 'faltantes' => $faltantes]);
    }

    /**
     * Vuelve a armar el checklist con las reglas vigentes (por si se cargaron
     * o cambiaron después de que el paciente llegó). Conserva lo ya tildado.
     */
    public function recalcularChecklist(int $id): JsonResponse
    {
        $p = ColaAtencion::findOrFail($id);
        $hechos = collect($p->checklist ?? [])->filter(fn ($i) => !empty($i['done']))->pluck('id')->all();
        $nuevo = array_map(function ($i) use ($hechos) {
            $i['done'] = in_array($i['id'], $hechos, true);
            return $i;
        }, ChecklistRecepcion::paraPaciente($p));
        $p->update(['checklist' => $nuevo]);
        return response()->json(['ok' => true, 'paciente' => $this->mapPaciente($p->fresh())]);
    }

    /** Resuelve sin liberar (gestión pura, no pasa a sala). */
    // ── Atención en mostrador (pacientes que no se anotaron en el tablet) ──

    /**
     * Horas que un paciente liberado al consultorio sigue en la lista "En la
     * clínica" de recepción (por si vuelve al mostrador). Hasta que el médico
     * marque la salida desde su panel (etapa futura), sale sola por tiempo.
     */
    public const EN_CLINICA_HORAS = 4;

    public const MOTIVOS_MOSTRADOR = [
        'regreso'        => 'Vuelve del consultorio',
        'turno'          => 'Viene a su turno',
        'turnos'         => 'Pedir o cambiar un turno',
        'recetas'        => 'Recetas',
        'muestras'       => 'Muestras / estudios',
        'consulta'       => 'Consulta / información',
        'administrativo' => 'Pagos / trámites',
        'otro'           => 'Otro',
    ];

    /**
     * GET /v2/recepcion/mostrador/buscar?dni= — como el tablet: el paciente en
     * Omnia y sus turnos de hoy; si Omnia no lo tiene, la ficha del directorio.
     */
    public function buscarMostrador(Request $r): JsonResponse
    {
        $dni = preg_replace('/\D/', '', (string) $r->query('dni'));
        if (strlen($dni) < 7) return response()->json(['ok' => false, 'error' => 'DNI incompleto'], 422);

        $omnia = app(\App\Services\OmniaService::class);
        $p = $omnia->buscarPaciente($dni);
        if ($p) {
            return response()->json(['ok' => true, 'origen' => 'omnia', 'paciente' => $p, 'turnos' => $omnia->turnosHoy($p['id'])]);
        }
        $c = \App\Models\Contacto::where('dni', $dni)->first();
        return response()->json(['ok' => true, 'origen' => $c ? 'contactos' : null,
            'paciente' => $c ? ['id' => null, 'nombre' => $c->nombre, 'apellido' => '', 'obra_social' => null, 'plan' => null, 'financiador' => null] : null,
            'turnos' => []]);
    }

    /**
     * POST /v2/recepcion/mostrador — registra una atención en el mostrador.
     * accion=atendido: queda como resuelta (se atendió ahí mismo).
     * accion=sala: entra a la cola como si se hubiera anotado en el tablet.
     */
    public function registrarMostrador(Request $r): JsonResponse
    {
        $d = $r->validate([
            'dni'         => 'nullable|string|max:20',
            'nombre'      => 'required|string|max:100',
            'apellido'    => 'nullable|string|max:100',
            'obra_social' => 'nullable|string|max:150',
            'plan'        => 'nullable|string|max:100',
            'financiador' => 'nullable|string|max:191',
            'motivo'      => 'required|in:' . implode(',', array_keys(self::MOTIVOS_MOSTRADOR)),
            'turno'       => 'nullable|array',
            'nota'        => 'nullable|string|max:1000',
            'accion'      => 'required|in:atendido,sala',
            'vuelve_de_id' => 'nullable|integer|exists:cola_atencion,id',
        ]);
        $turno = $d['turno'] ?? null;
        $practicas = $turno ? ($turno['practicas'] ?? array_filter([$turno['practica'] ?? null])) : [];
        $financiador = $d['financiador'] ?? $d['obra_social'] ?? null;

        $fila = ColaAtencion::create([
            'dni'            => preg_replace('/\D/', '', (string) ($d['dni'] ?? '')),   // columna NOT NULL: sin DNI queda ''
            'nombre'         => $d['nombre'],
            'apellido'       => $d['apellido'] ?? '',
            'obra_social'    => $d['obra_social'] ?? null,
            'plan'           => $d['plan'] ?? null,
            'financiador'    => $financiador,
            'omnia_turno_id' => $turno['id'] ?? null,
            'profesional'    => $turno['profesional'] ?? null,
            'practica'       => $turno['practica'] ?? null,
            'practicas'      => $practicas,
            'turno_hora'     => $turno['hora'] ?? null,
            'planta'         => $turno['planta'] ?? null,
            'motivo'         => $d['motivo'],
            'origen'         => 'mostrador',
            'registrado_por' => auth()->id(),
            'vuelve_de_id'   => $d['vuelve_de_id'] ?? null,
            'sin_turno'      => !$turno,
            'checklist'      => ChecklistRecepcion::para($financiador, $d['plan'] ?? null, $practicas),
            'nota'           => $d['nota'] ?? null,
            'estado'         => $d['accion'] === 'sala' ? 'esperando' : 'resuelto',
            'hora_llegada'   => now(),
            'hora_llamado'   => $d['accion'] === 'atendido' ? now() : null,
            'orden'          => ColaAtencion::max('orden') + 1,
        ]);

        // Volvió al mostrador: la visita anterior sale de "En la clínica" (la representa la nueva).
        if (!empty($d['vuelve_de_id'])) {
            ColaAtencion::where('id', $d['vuelve_de_id'])->whereNull('salio_at')->update(['salio_at' => now()]);
        }

        // Igual que el tablet: la obra social del TURNO (no la de la ficha), después de responder.
        if (!empty($turno['id'])) {
            $turnoId = $turno['id'];
            \Illuminate\Support\defer(fn () => \App\Livewire\Tablet::corregirConFinanciadorDelTurno($fila, $turnoId, $practicas));
        }

        return response()->json(['ok' => true, 'id' => $fila->id, 'estado' => $fila->estado]);
    }

    /** POST /v2/recepcion/cola/{id}/salio — se fue de la clínica: sale de "En la clínica". */
    public function salio(int $id): JsonResponse
    {
        ColaAtencion::findOrFail($id)->update(['salio_at' => now()]);
        return response()->json(['ok' => true]);
    }

    public function resolverPaciente(int $id): JsonResponse
    {
        ColaAtencion::findOrFail($id)->update(['estado' => 'resuelto']);
        return response()->json(['ok' => true]);
    }

    /** Reordena la cola: recibe los IDs en el nuevo orden y reescribe `orden`. */
    public function reordenar(Request $r): JsonResponse
    {
        $data = $r->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        foreach (array_values($data['ids']) as $i => $id) {
            ColaAtencion::where('id', (int) $id)->update(['orden' => $i + 1]);
        }
        // Update por query: no pasa por el hook del modelo, hay que avisar a mano.
        \App\Support\AvisoRecepcion::marcar('sala');
        return response()->json(['ok' => true]);
    }

    // ── Cola del bot (ColaBot / derivaciones) ─────────────────────────

    private function mapDerivacion(Derivacion $d): array
    {
        return [
            'id'          => $d->id,
            'telefono'    => $d->telefono,
            'codigo'      => $d->codigo,
            'etiqueta'    => $d->etiqueta,
            'texto'       => $d->texto,
            'resumen_llm' => $d->resumen_llm,
            'en_horario'  => $d->en_horario,
            'es_prueba'   => $d->es_prueba,
            'estado'      => $d->estado,
            'nota'        => $d->nota,
            'hace'        => $d->created_at?->diffForHumans(),
            'fecha'       => $d->created_at?->format('d/m H:i'),
        ];
    }

    /** Derivaciones pendientes del bot. ?prueba=0 oculta las de testing. */
    public function bot(Request $r): JsonResponse
    {
        $mostrarPrueba = $r->boolean('prueba', true);

        $cola = Derivacion::where('estado', '!=', 'resuelto')
            ->when(!$mostrarPrueba, fn ($q) => $q->where('es_prueba', false))
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'ok'   => true,
            'cola' => $cola->map(fn ($d) => $this->mapDerivacion($d))->values(),
            'total' => $cola->count(),
        ]);
    }

    /** Abre la derivación: pendiente → en_atencion. */
    public function abrirBot(int $id): JsonResponse
    {
        $d = Derivacion::findOrFail($id);
        if ($d->estado === 'pendiente') {
            $d->update(['estado' => 'en_atencion']);
        }
        return response()->json(['ok' => true, 'derivacion' => $this->mapDerivacion($d->fresh())]);
    }

    /** Guarda la nota de la derivación. */
    public function notaBot(Request $r, int $id): JsonResponse
    {
        $data = $r->validate(['nota' => 'nullable|string|max:2000']);
        Derivacion::findOrFail($id)->update(['nota' => $data['nota'] ?? null]);
        return response()->json(['ok' => true]);
    }

    /** Marca la derivación como resuelta + atendido_at, guardando la nota. */
    public function resolverBot(Request $r, int $id): JsonResponse
    {
        $data = $r->validate(['nota' => 'nullable|string|max:2000']);
        Derivacion::findOrFail($id)->update([
            'estado'      => 'resuelto',
            'atendido_at' => now(),
            'nota'        => ($data['nota'] ?? '') ?: null,
        ]);
        return response()->json(['ok' => true]);
    }
}
