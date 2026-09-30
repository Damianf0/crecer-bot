<?php

namespace App\Support;

use App\Models\ConversacionWA;
use App\Models\Derivacion;
use App\Models\Tarea;
use Illuminate\Support\Facades\Cache;

/**
 * Números del menú lateral V2: pendientes sin tomar por cola, mis
 * conversaciones y mis tareas. Los usa el layout al cargar y /bot-pulso para
 * refrescarlos sin recargar la página (antes solo se calculaban al cargar y
 * quedaban congelados mientras la secretaria se quedaba en la misma cola).
 * Caché de 5 s por usuario: con varias pestañas no se multiplican las consultas.
 */
class ContadoresNavbar
{
    /** @return array{mis_conv:int, mis_tareas:int, por_area:array<string,int>} */
    public static function para(int $uid): array
    {
        return Cache::remember("navbar.counts.v2.{$uid}", 5, fn() => [
            'mis_conv'   => ConversacionWA::where('estado', 'activa')->where('asignada_a', $uid)->count(),
            'mis_tareas' => Derivacion::where('estado', 'en_atencion')->where('asignada_a', $uid)->count()
                          + Tarea::where('estado', '!=', 'completada')->where('asignada_a', $uid)->count(),
            'por_area'   => ConversacionWA::where('estado', 'activa')->whereNull('asignada_a')->where('no_leidos', '>', 0)
                ->selectRaw('area, count(*) as n')->groupBy('area')->pluck('n', 'area')
                ->map(fn($n) => (int) $n)->toArray(),
        ]);
    }
}
