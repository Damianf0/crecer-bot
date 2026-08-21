<?php

namespace App\Services;

use App\Models\ArchivadoLote;
use App\Models\ConversacionEvento;
use App\Models\ConversacionWA;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Archivado masivo de conversaciones WhatsApp.
 *
 * Dos modos:
 *   - dias:  todo lo que no tiene actividad desde hace N días (limpieza de rutina).
 *   - rango: todo lo que quedó con última actividad DENTRO de un rango de fechas.
 *            Es el caso "la clínica no atendió esa semana": se archiva el período
 *            entero de una y la cola queda solo con lo que llegó después.
 *
 * Semántica del archivado = la misma del botón "Resolver" del panel:
 * estado=archivada, asignada_a=null, urgente=false + evento en el historial.
 * NO toca no_leidos ni borra nada: todas las colas y badges filtran por
 * estado='activa', así que archivar ya las saca de la vista, y si el paciente
 * vuelve a escribir, el mensaje entrante reabre la conversación solo
 * (BotController::mensajeEntrante) con su contador intacto.
 *
 * Cada corrida con aplicar() deja un ArchivadoLote con el estado previo de cada
 * conversación → revertir() deshace la corrida completa.
 */
class ArchivadoConversaciones
{
    /** Fecha que define "actividad": si la conv nunca tuvo, vale su fecha de alta. */
    private const FECHA_EFECTIVA = 'COALESCE(ultima_actividad, created_at)';

    /**
     * Completa defaults y normaliza tipos. Devuelve el criterio canónico que se
     * guarda en el lote (así el historial muestra exactamente qué se corrió).
     */
    public static function normalizarCriterio(array $c): array
    {
        $modo = in_array($c['modo'] ?? 'dias', ['dias', 'rango'], true) ? $c['modo'] : 'dias';
        $area = $c['area'] ?? null;
        if ($area === '' || !isset(ConversacionWA::AREAS[$area])) $area = null;

        return [
            'modo'              => $modo,
            'dias'              => $modo === 'dias' ? max(1, (int) ($c['dias'] ?? 7)) : null,
            'desde'             => $modo === 'rango' ? (string) ($c['desde'] ?? '') : null,
            'hasta'             => $modo === 'rango' ? (string) ($c['hasta'] ?? '') : null,
            'area'              => $area,
            'excluir_asignadas' => (bool) ($c['excluir_asignadas'] ?? true),
        ];
    }

    /**
     * Query de las conversaciones que matchean el criterio.
     * Solo toca activas: las ya archivadas quedan como están.
     */
    public static function query(array $c): Builder
    {
        $c = self::normalizarCriterio($c);

        $q = ConversacionWA::where('estado', 'activa');

        if ($c['modo'] === 'rango') {
            [$desde, $hasta] = self::rango($c);
            $q->whereRaw(self::FECHA_EFECTIVA . ' BETWEEN ? AND ?', [$desde, $hasta]);
        } else {
            $q->whereRaw(self::FECHA_EFECTIVA . ' < ?', [now()->subDays($c['dias'])]);
        }

        if ($c['area'])              $q->where('area', $c['area']);
        if ($c['excluir_asignadas']) $q->whereNull('asignada_a');

        return $q;
    }

    /** Rango [inicio, fin] como Carbon, con el día completo en ambas puntas. */
    public static function rango(array $c): array
    {
        return [
            Carbon::parse($c['desde'])->startOfDay(),
            Carbon::parse($c['hasta'])->endOfDay(),
        ];
    }

    /**
     * Conteo por área + muestra, sin escribir nada. Es lo que alimenta la
     * previsualización del panel (y el dry-run del comando de consola).
     */
    public static function previsualizar(array $c, int $muestra = 15): array
    {
        $c = self::normalizarCriterio($c);

        $porArea = self::query($c)
            ->selectRaw('area, COUNT(*) total, SUM(no_leidos > 0) con_no_leidos, SUM(asignada_a IS NOT NULL) asignadas')
            ->groupBy('area')->get()
            ->map(fn($r) => [
                'area'          => $r->area,
                'area_label'    => ConversacionWA::AREAS[$r->area] ?? $r->area,
                'total'         => (int) $r->total,
                'con_no_leidos' => (int) $r->con_no_leidos,
                'asignadas'     => (int) $r->asignadas,
            ])->values()->all();

        $items = $muestra > 0
            ? self::query($c)->orderByDesc('ultima_actividad')->limit($muestra)->get()
                ->map(fn($conv) => [
                    'id'               => $conv->id,
                    'area'             => $conv->area,
                    'nombre'           => $conv->nombre_o_telefono,
                    'ultima_actividad' => optional($conv->ultima_actividad)->format('d/m/Y H:i'),
                    'no_leidos'        => (int) $conv->no_leidos,
                ])->all()
            : [];

        return [
            'criterio' => $c,
            'total'    => array_sum(array_column($porArea, 'total')),
            'por_area' => $porArea,
            'muestra'  => $items,
            'corte'    => self::descripcionCorte($c),
        ];
    }

    /** Texto legible del criterio, para el panel y el historial. */
    public static function descripcionCorte(array $c): string
    {
        $c   = self::normalizarCriterio($c);
        $ambito = $c['area'] ? (ConversacionWA::AREAS[$c['area']] ?? $c['area']) : 'las 3 áreas';

        if ($c['modo'] === 'rango') {
            [$desde, $hasta] = self::rango($c);
            return sprintf('Actividad entre %s y %s · %s', $desde->format('d/m/Y'), $hasta->format('d/m/Y'), $ambito);
        }

        return sprintf('Sin actividad desde hace más de %d días (antes del %s) · %s',
            $c['dias'], now()->subDays($c['dias'])->format('d/m/Y H:i'), $ambito);
    }

    /**
     * Archiva todo lo que matchea y devuelve el lote (con el snapshot para deshacer).
     * Procesa de a 200 para no armar un UPDATE gigante ni cargar todo en memoria.
     */
    public static function aplicar(array $c, ?int $usuarioId, string $origen = 'panel'): ArchivadoLote
    {
        $c        = self::normalizarCriterio($c);
        $ahora    = now();
        $snapshot = [];

        self::query($c)->select(['id', 'estado', 'asignada_a', 'urgente'])->orderBy('id')
            ->chunkById(200, function ($chunk) use (&$snapshot, $ahora) {
                $ids = [];
                foreach ($chunk as $conv) {
                    $ids[] = $conv->id;
                    $snapshot[] = [
                        'id'         => $conv->id,
                        'estado'     => $conv->estado,
                        'asignada_a' => $conv->asignada_a,
                        'urgente'    => (int) $conv->urgente,
                    ];
                }

                ConversacionWA::whereIn('id', $ids)->update([
                    'estado' => 'archivada', 'asignada_a' => null, 'urgente' => false,
                ]);

                ConversacionEvento::insert(array_map(fn($id) => [
                    'conversacion_id'    => $id,
                    'tipo'               => 'archivada_auto',
                    'usuario_id'         => null,
                    'usuario_destino_id' => null,
                    'created_at'         => $ahora,
                    'updated_at'         => $ahora,
                ], $ids));
            });

        ConversacionWA::invalidarColaCache();

        return ArchivadoLote::create([
            'usuario_id' => $usuarioId,
            'origen'     => $origen,
            'criterio'   => $c,
            'total'      => count($snapshot),
            'snapshot'   => $snapshot,
        ]);
    }

    /**
     * Deshace un lote: devuelve cada conversación a su estado previo.
     *
     * Solo toca las que SIGUEN archivadas. Si el paciente volvió a escribir, el
     * entrante ya la reabrió y esa conversación está viva de nuevo — revertirla
     * sería pisarle el estado actual a la secretaria.
     */
    public static function revertir(ArchivadoLote $lote, ?int $usuarioId): int
    {
        $revertidas = 0;

        foreach (array_chunk($lote->snapshot ?? [], 200) as $chunk) {
            foreach ($chunk as $prev) {
                $revertidas += ConversacionWA::whereKey($prev['id'])
                    ->where('estado', 'archivada')
                    ->update([
                        'estado'     => $prev['estado'],
                        'asignada_a' => $prev['asignada_a'],
                        'urgente'    => (bool) $prev['urgente'],
                    ]);
            }
        }

        ConversacionWA::invalidarColaCache();

        $lote->update([
            'revertido_at'  => now(),
            'revertido_por' => $usuarioId,
            'revertidas'    => $revertidas,
        ]);

        return $revertidas;
    }
}
