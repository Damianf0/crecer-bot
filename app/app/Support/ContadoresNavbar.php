<?php

namespace App\Support;

use App\Models\ConversacionWA;
use App\Models\Derivacion;
use App\Models\Tarea;
use Illuminate\Support\Facades\Cache;

/**
 * Números del menú lateral V2: pendientes sin tomar por cola, en proceso por
 * cola (tomadas por alguien del equipo, desde el 06/10), mis conversaciones y
 * mis tareas. Los usa el layout al cargar y /bot-pulso para
 * refrescarlos sin recargar la página (antes solo se calculaban al cargar y
 * quedaban congelados mientras la secretaria se quedaba en la misma cola).
 * Caché de 5 s por usuario: con varias pestañas no se multiplican las consultas.
 */
class ContadoresNavbar
{
    /** @return array{mis_conv:int, mis_tareas:int, por_area:array<string,int>, en_proceso:array<string,int>} */
    public static function para(int $uid): array
    {
        // Solo las colas que la persona puede ver (áreas restringidas: config/lineas.php).
        $mias = array_keys(ConversacionWA::areasPara(\App\Models\User::find($uid)));
        return Cache::remember("navbar.counts.v3.{$uid}", 5, fn() => [
            'mis_conv'   => ConversacionWA::where('estado', 'activa')->where('asignada_a', $uid)->count(),
            'mis_tareas' => Derivacion::where('estado', 'en_atencion')->where('asignada_a', $uid)->count()
                          + Tarea::where('estado', '!=', 'completada')->where('asignada_a', $uid)->count(),
            'por_area'   => ConversacionWA::where('estado', 'activa')->whereNull('asignada_a')->where('no_leidos', '>', 0)->whereIn('area', $mias)
                ->selectRaw('area, count(*) as n')->groupBy('area')->pluck('n', 'area')
                ->map(fn($n) => (int) $n)->toArray(),
            'en_proceso' => ConversacionWA::where('estado', 'activa')->whereNotNull('asignada_a')->whereIn('area', $mias)
                ->selectRaw('area, count(*) as n')->groupBy('area')->pluck('n', 'area')
                ->map(fn($n) => (int) $n)->toArray(),
        ]);
    }
}
