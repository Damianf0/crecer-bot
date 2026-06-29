<?php

namespace App\Http\Controllers;

use App\Models\ColaAtencion;
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

        return response()->json([
            'ok'   => true,
            'cola' => $cola->map(fn ($p) => $this->mapPaciente($p))->values(),
            'stats' => [
                'total'       => $cola->count(),
                'esperando'   => $cola->where('estado', 'esperando')->count(),
                'en_atencion' => $cola->where('estado', 'en_atencion')->count(),
                'alertas'     => $cola->where('alerta_espera', true)->count(),
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
            $cambios['checklist'] = ColaAtencion::checklistDefault();
        }
        if ($cambios) $p->update($cambios);

        return response()->json(['ok' => true, 'paciente' => $this->mapPaciente($p->fresh())]);
    }

    /** Toggle de un ítem del checklist de recepción. */
    public function toggleChecklist(Request $r, int $id): JsonResponse
    {
        $data = $r->validate(['item_id' => 'required|string|max:50']);
        $p = ColaAtencion::findOrFail($id);

        $checklist = $p->checklist ?: ColaAtencion::checklistDefault();
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

    /** Libera a sala: valida checklist obligatorio → estado liberado + hora_liberado. */
    public function liberar(int $id): JsonResponse
    {
        $p = ColaAtencion::findOrFail($id);
        if (!$p->checklistCompleto()) {
            return response()->json(['ok' => false, 'error' => 'Completá los ítems obligatorios primero'], 422);
        }
        $p->update(['estado' => 'liberado', 'hora_liberado' => now()]);
        return response()->json(['ok' => true]);
    }

    /** Resuelve sin liberar (gestión pura, no pasa a sala). */
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
