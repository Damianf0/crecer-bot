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
}
