<?php

namespace App\Services;

use App\Models\ColaAtencion;
use App\Models\RecepcionChecklist;

/**
 * Arma el checklist de recepción de un paciente (lógica del 08/10): cada obra
 * social tiene su propia lista de pasos, cargada en /v2/recepcion/reglas.
 *
 *   1. Si la obra social del paciente tiene un checklist activo con pasos, es
 *      ese. Se compara por el nombre exacto con el que llega de Omnia (sin
 *      mirar mayúsculas ni espacios de más): "OSDE" y "Osde Binario" son dos
 *      obras sociales distintas, cada una con su lista.
 *   2. Si no, el General.
 *   3. Si tampoco hay General con pasos, la lista fija histórica
 *      (ColaAtencion::checklistDefault), para que la ficha nunca quede vacía.
 *
 * El plan y las prácticas del turno ya no cambian el checklist (el motor de
 * reglas del 21/09 los usaba); se siguen recibiendo para no tocar a quienes
 * llaman.
 */
class ChecklistRecepcion
{
    /**
     * @param  string[]  $practicas  sin uso desde el 08/10
     * @return array<int, array{id:string, label:string, obligatorio:bool, nota:?string, instruccion:?string, done:bool}>
     */
    public static function para(?string $financiador, ?string $plan = null, array $practicas = []): array
    {
        $clave = RecepcionChecklist::clave($financiador);

        $candidatos = RecepcionChecklist::with('items')
            ->where('activo', true)
            ->whereIn('clave', array_unique([$clave, '']))
            ->get()
            ->filter(fn (RecepcionChecklist $c) => $c->items->isNotEmpty())
            ->keyBy('clave');

        $elegido = ($clave !== '' ? $candidatos->get($clave) : null) ?? $candidatos->get('');
        if (!$elegido) return ColaAtencion::checklistDefault();

        return $elegido->items->map(fn ($i) => [
            'id'          => 'item:' . $i->id,
            'label'       => $i->texto,
            'obligatorio' => (bool) $i->obligatorio,
            'nota'        => $i->nota,
            'instruccion' => null,
            'done'        => false,
        ])->values()->all();
    }

    /** Checklist para un paciente ya en la cola. */
    public static function paraPaciente(ColaAtencion $p): array
    {
        return self::para($p->financiador ?: $p->obra_social, $p->plan);
    }
}
