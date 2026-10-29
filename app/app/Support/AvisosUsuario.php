<?php

namespace App\Support;

use App\Models\ConversacionWA;
use App\Models\FavoritoWA;
use App\Models\PreferenciaAviso;
use App\Models\Tarea;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Lo que hoy está 'en' una persona, para que el panel avise las novedades en
 * cualquier pantalla (crecer-avisos.js compara contra lo que ya conocía). Viaja
 * en /bot-pulso, que corre cada 15 s y al instante con cada cambio de una cola.
 * Antes los avisos vivían en la pantalla de cada cola y solo saltaban ahí.
 * Caché de 5 s por persona: con varias pestañas no se multiplican las consultas.
 */
class AvisosUsuario
{
    private const TOPE = 50;

    /** @param string[] $colas áreas que la persona declaró atender */
    public static function para(int $uid, array $colas): array
    {
        sort($colas);
        // Las preferencias van fuera de la caché: un cambio en "Mis avisos" vale desde el próximo pulso.
        return self::novedades($uid, $colas) + ['prefs' => PreferenciaAviso::para($uid)];
    }

    private static function novedades(int $uid, array $colas): array
    {
        return Cache::remember("avisos.usuario.{$uid}." . implode(',', $colas), 5, function () use ($uid, $colas) {
            $mapConv = fn (ConversacionWA $c) => [
                'id'       => $c->id,
                'area'     => $c->area,
                'contacto' => $c->nombreOTelefono,
                'resumen'  => Str::limit((string) $c->resumen_llm, 90),
            ];

            $misConvs = ConversacionWA::where('estado', 'activa')->where('asignada_a', $uid)
                ->orderByDesc('ultima_actividad')->limit(self::TOPE)->get()->map($mapConv)->values();

            $misTareas = Tarea::where('estado', '!=', 'completada')->where('asignada_a', $uid)
                ->with('creadaPor:id,nombre_completo')->orderByDesc('id')->limit(self::TOPE)->get()
                // Las de un área que tomé yo no avisan "te asignó" (el aviso fue al llegar al área).
                ->map(fn ($t) => ['id' => $t->id, 'titulo' => $t->titulo, 'de' => $t->creada_por !== $uid && !$t->area ? $t->creadaPor?->nombre_completo : null])
                ->values();

            // Tareas nuevas para las áreas que atiendo (nadie las tomó todavía).
            $areasTarea = array_values(array_diff($colas, [ConversacionWA::AREA_DIFUSION]));
            $tareasArea = Tarea::sinTomarEn($areasTarea)->where('creada_por', "!=", $uid)
                ->with("creadaPor:id,nombre_completo")->orderByDesc('id')->limit(self::TOPE)->get()
                ->map(fn ($t) => ['id' => $t->id, 'titulo' => $t->titulo, 'area' => $t->area_label, 'de' => $t->creadaPor?->nombre_completo])
                ->values();

            // Lo que pedí a un área: aviso cuando la completan o la comentan.
            $misPedidos = Tarea::whereNotNull('area')->where('creada_por', $uid)
                ->where('updated_at', ">=", now()->subDays(7))
                ->with(["completadaPor:id,nombre_completo", 'comentarios' => fn ($q) => $q->where('user_id', "!=", $uid)->with('user:id,nombre_completo')])
                ->orderByDesc('updated_at')->limit(self::TOPE)->get()
                ->map(function ($t) use ($uid) {
                    $ult = $t->comentarios->last();
                    return [
                        'id'     => $t->id,
                        'titulo' => $t->titulo,
                        'area'   => $t->area_label,
                        'hecha'  => $t->estado === 'completada' && $t->completada_por !== $uid,
                        'por'    => $t->completadaPor?->nombre_completo,
                        'coms'   => $t->comentarios->count(),
                        'ultimo' => $ult ? ($ult->user?->nombre_completo . ": " . Str::limit($ult->contenido, 90)) : null,
                    ];
                })->values();

            $favs = array_keys(FavoritoWA::set());
            $favoritos = !$favs || !$colas ? collect() : ConversacionWA::where('estado', 'activa')
                ->whereIn('area', $colas)->whereIn('contacto', $favs)->where('no_leidos', '>', 0)
                ->limit(self::TOPE)->get()
                ->map(fn ($c) => $mapConv($c) + ['no_leidos' => (int) $c->no_leidos])->values();

            $urgentes = !$colas ? collect() : ConversacionWA::where('estado', 'activa')
                ->whereIn('area', $colas)->where('urgente', true)->whereNull('asignada_a')->where('no_leidos', '>', 0)
                ->limit(self::TOPE)->get()->map($mapConv)->values();

            return [
                'mis_convs'  => $misConvs,
                'mis_tareas' => $misTareas,
                'favoritos'  => $favoritos,
                'urgentes'   => $urgentes,
                'tareas_area' => $tareasArea,
                'mis_pedidos' => $misPedidos,
            ];
        });
    }
}
