<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Vistas de la PoC UI V2 (/v2/*). Corren en paralelo a producción consumiendo
 * los endpoints existentes — acá solo se arma la data inicial de cada pantalla.
 * La cola de atención (/v2/atencion/{area}) vive en AtencionController::indexV2
 * porque reusa buildItems().
 */
class V2Controller extends Controller
{
    private function usuarios()
    {
        return User::where('activo', true)->orderBy('nombre_completo')->get(['id', 'nombre_completo']);
    }

    public function misConversaciones()
    {
        $items = \App\Models\ConversacionWA::where('estado', 'activa')
            ->where('asignada_a', Auth::id())
            ->with(['ultimoMensaje', 'asignadaA:id,nombre_completo'])
            ->orderByDesc('urgente')
            ->orderByDesc('ultima_actividad')
            ->limit(300)
            ->get()
            ->map(fn($c) => [
                'id'        => $c->id,
                'area'      => $c->area,
                'contacto'  => $c->nombreOTelefono,
                'telefono'  => $c->telefono,
                'resumen'   => $c->resumen_llm ?: ($c->ultimoMensaje?->snippet ?? '—'),
                'urgente'   => (bool) $c->urgente,
                'no_leidos' => $c->no_leidos,
                'hace'      => $c->ultima_actividad?->diffForHumans(),
                'ts'        => $c->ultima_actividad?->timestamp ?? 0,
                'avatar_url'=> null,
            ])->values();

        return view('v2.mis-conversaciones', [
            'items'     => $items,
            'usuarios'  => $this->usuarios(),
            'modulo'    => 'Atención',
            'title'     => 'Mis conversaciones',
            'navActive' => 'mis-conversaciones',
        ]);
    }

    public function centroTareas()
    {
        return view('v2.centro-tareas', [
            'usuarios'  => $this->usuarios(),
            'modulo'    => 'Trabajo',
            'title'     => 'Tareas',
            'navActive' => 'tareas',
        ]);
    }

    /**
     * Mismo funcionamiento que /historial (filtros GET + tabla + detalle
     * expandible + paginación server-side): reusa la query de
     * AtencionController::historial y solo cambia la vista al shell V2.
     */
    public function historial(\Illuminate\Http\Request $request)
    {
        $resp = app(AtencionController::class)->historial($request);
        if ($resp instanceof \Illuminate\Http\JsonResponse) return $resp;

        return view('v2.historial', $resp->getData() + [
            'modulo'    => 'Trabajo',
            'title'     => 'Historial',
            'navActive' => 'historial',
        ]);
    }

    public function contactos()
    {
        return view('v2.contactos', [
            'modulo'    => 'Trabajo',
            'title'     => 'Contactos',
            'navActive' => 'contactos',
        ]);
    }

    public function agenda()
    {
        return view('v2.agenda', [
            'usuarios'  => $this->usuarios(),
            'modulo'    => 'Trabajo',
            'title'     => 'Agenda',
            'navActive' => 'agenda',
        ]);
    }

    /**
     * Mi día: home con saludo + KPIs clickeables y los pendientes accionables
     * del usuario (tareas que vencen, conversaciones asignadas). Disponible
     * para cualquier autenticado; cada bloque respeta los permisos del que mira.
     */
    public function miDia()
    {
        $u   = Auth::user();
        $uid = $u->id;

        $d = [
            'modulo'        => 'Mi día',
            'title'         => 'Mi día',
            'navActive'     => 'mi-dia',
            'tieneAtencion' => $u->hasPermiso('atencion'),
            'tieneAgenda'   => $u->hasPermiso('agenda'),
        ];

        if ($d['tieneAtencion']) {
            $mias = \App\Models\ConversacionWA::where('estado', 'activa')->where('asignada_a', $uid);

            $d['convTotal']    = (clone $mias)->count();
            $d['convUrgentes'] = (clone $mias)->where('urgente', true)->count();
            $d['convNoLeidos'] = (int) (clone $mias)->sum('no_leidos');
            $d['misConvs']     = (clone $mias)
                ->with('ultimoMensaje')
                ->orderByDesc('urgente')
                ->orderByDesc('ultima_actividad')
                ->limit(6)
                ->get()
                ->map(fn($c) => [
                    'id'        => $c->id,
                    'area'      => $c->area,
                    'contacto'  => $c->nombreOTelefono,
                    'resumen'   => $c->resumen_llm ?: ($c->ultimoMensaje?->snippet ?? '—'),
                    'urgente'   => (bool) $c->urgente,
                    'no_leidos' => $c->no_leidos,
                    'hace'      => $c->ultima_actividad?->diffForHumans(),
                ])->values();

            $d['sinAsignar'] = \App\Models\ConversacionWA::where('estado', 'activa')
                ->whereNull('asignada_a')->where('no_leidos', '>', 0)->count();

            $d['tareasPend'] = \App\Models\Derivacion::where('estado', 'en_atencion')->where('asignada_a', $uid)->count()
                + \App\Models\Tarea::where('estado', '!=', 'completada')->where('asignada_a', $uid)->count();

            // Para hoy: mis tareas vencidas o que vencen hoy, en orden de vencimiento.
            $d['tareasHoy'] = \App\Models\Tarea::where('estado', '!=', 'completada')
                ->where('asignada_a', $uid)
                ->whereNotNull('vence_at')
                ->where('vence_at', '<=', now()->endOfDay())
                ->orderBy('vence_at')
                ->limit(10)
                ->get()
                ->map(fn($t) => [
                    'id'        => $t->id,
                    'titulo'    => $t->titulo,
                    'prioridad' => $t->prioridad,
                    'vencida'   => $t->vence_at->lt(now()->startOfDay()),
                    'hora'      => $t->vence_at->format('H:i'),
                    'fecha'     => $t->vence_at->format('d/m'),
                ])->values();
        }

        return view('v2.mi-dia', $d);
    }

    /**
     * Admin en el shell V2: reusa las vistas de producción (admin/*) tal cual
     * via layout dinámico (@extends($layout ?? 'layouts.app')) — solo cambia
     * el cascarón. Los tokens de producción que usan esas vistas resuelven por
     * el puente de variables de crecer-v2.css. $v2Wrap hace que el layout las
     * envuelva en un contenedor con scroll y padding (emula el <main> de prod).
     */
    public function admin(string $pagina = '')
    {
        $mapa = [
            ''                   => 'dashboard',
            'textos'             => 'textos',
            'pruebas'            => 'pruebas',
            'logs'               => 'logs',
            'legajo'             => 'legajoConfig',
            'usuarios'           => 'usuarios',
            'medicos'            => 'medicos',
            'respuestas-rapidas' => 'respuestasRapidas',
            'tunnel'             => 'tunnel',
        ];

        if ($pagina === 'estadisticas') {
            $vista = app(EstadisticasController::class)->index();
        } else {
            abort_unless(isset($mapa[$pagina]), 404);
            $vista = app(AdminController::class)->{$mapa[$pagina]}();
        }

        return $vista->with([
            'layout'    => 'layouts.v2',
            'v2Wrap'    => true,
            'modulo'    => 'Admin',
            'title'     => $pagina === 'estadisticas' ? 'Reportes' : 'Admin',
            'navActive' => $pagina === 'estadisticas' ? 'reportes' : 'admin',
        ]);
    }
}
