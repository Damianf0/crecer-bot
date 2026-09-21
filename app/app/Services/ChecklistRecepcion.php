<?php

namespace App\Services;

use App\Models\ColaAtencion;
use App\Models\RecepcionRegla;
use Illuminate\Support\Collection;

/**
 * Arma el checklist de recepción de un paciente según su obra social, plan y
 * las prácticas del turno, a partir de recepcion_reglas.
 *
 * Una regla aplica si cada campo que fija (financiador, plan, práctica) coincide
 * con el paciente; un campo vacío vale para todos. Si varias reglas aplican al
 * MISMO requisito gana la más específica, con este peso:
 *
 *   financiador 4 · práctica 2 · plan 1
 *
 * O sea: lo que se pacta por obra social le gana a la regla general de una
 * práctica ("IOMA pide autorización" pisa a "Consulta no pide autorización"),
 * y financiador + práctica le gana a las dos. Empate: la editada más reciente.
 * Si la ganadora es "no_pedir", el requisito no va.
 *
 * Mientras no haya ninguna regla cargada se usa el checklist fijo histórico
 * (ColaAtencion::checklistDefault), así la recepción no queda con la lista vacía.
 */
class ChecklistRecepcion
{
    /**
     * @param  string[]  $practicas  todas las prácticas del turno (un turno puede traer varias)
     * @return array<int, array{id:string, requisito_id:int, label:string, obligatorio:bool, nota:?string, instruccion:?string, done:bool}>
     */
    public static function para(?string $financiador, ?string $plan, array $practicas = []): array
    {
        $reglas = RecepcionRegla::with('requisito')
            ->where('activo', true)
            ->whereHas('requisito', fn ($q) => $q->where('activo', true))
            ->get();

        if ($reglas->isEmpty() && !RecepcionRegla::exists()) {
            return ColaAtencion::checklistDefault();
        }

        return self::resolver($reglas, $financiador, $plan, $practicas);
    }

    /** Checklist para un paciente ya en la cola. */
    public static function paraPaciente(ColaAtencion $p): array
    {
        $practicas = $p->practicas ?: array_filter([$p->practica]);
        return self::para($p->financiador ?: $p->obra_social, $p->plan, $practicas);
    }

    /**
     * Resolución pura (sin BD), separada para poder testearla.
     *
     * @param  Collection<int, RecepcionRegla>  $reglas  con requisito cargado
     */
    public static function resolver(Collection $reglas, ?string $financiador, ?string $plan, array $practicas = []): array
    {
        $f  = self::norm($financiador);
        $pl = self::norm($plan);
        $ps = array_values(array_filter(array_map([self::class, 'norm'], $practicas)));

        $ganadoras = [];
        foreach ($reglas as $r) {
            $rf = self::norm($r->financiador);
            $rp = self::norm($r->plan);
            $rr = self::norm($r->practica);

            if ($rf !== null && $rf !== $f) continue;
            if ($rp !== null && $rp !== $pl) continue;
            if ($rr !== null && !in_array($rr, $ps, true)) continue;

            $peso = ($rf !== null ? 4 : 0) + ($rr !== null ? 2 : 0) + ($rp !== null ? 1 : 0);
            $actual = $ganadoras[$r->requisito_id] ?? null;
            if ($actual === null
                || $peso > $actual['peso']
                || ($peso === $actual['peso'] && $r->updated_at > $actual['regla']->updated_at)) {
                $ganadoras[$r->requisito_id] = ['peso' => $peso, 'regla' => $r];
            }
        }

        $items = [];
        foreach ($ganadoras as $g) {
            $r = $g['regla'];
            if ($r->modo === 'no_pedir') continue;
            $items[] = [
                'id'           => 'req:' . $r->requisito_id,
                'requisito_id' => $r->requisito_id,
                'label'        => $r->requisito->nombre,
                'obligatorio'  => $r->modo === 'obligatorio',
                'nota'         => $r->nota,
                'instruccion'  => $r->requisito->instruccion,
                'orden'        => $r->requisito->orden,
                'done'         => false,
            ];
        }

        // Obligatorios arriba; después el orden del catálogo.
        usort($items, fn ($a, $b) => [$b['obligatorio'], $a['orden'], $a['label']] <=> [$a['obligatorio'], $b['orden'], $b['label']]);

        return array_map(function ($i) { unset($i['orden']); return $i; }, $items);
    }

    private static function norm(?string $s): ?string
    {
        $s = trim((string) $s);
        return $s === '' ? null : mb_strtolower(preg_replace('/\s+/', ' ', $s));
    }
}
