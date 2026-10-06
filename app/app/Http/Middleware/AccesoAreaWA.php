<?php

namespace App\Http\Middleware;

use App\Models\ConversacionWA;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Áreas de WhatsApp restringidas (config/lineas.php): solo quien tiene el
 * permiso del área puede ver su cola, abrir sus conversaciones o actuar sobre
 * ellas. Corre en todas las rutas web y mira a qué área apunta el pedido:
 *   - el área en la URL o en el cuerpo (/v2/atencion/{area}, { area })
 *   - la conversación (/atencion/conversacion/{id}, { conv_id },
 *     { conversacion_id }, o { id, tipo: 'wa' })
 * Sin áreas restringidas activas no hace nada.
 */
class AccesoAreaWA
{
    public function handle(Request $request, Closure $next): Response
    {
        $restringidas = ConversacionWA::areasRestringidas();
        $user = Auth::user();
        if (!$restringidas || !$user) return $next($request);

        $areas = [];
        foreach ([$request->route('area'), $request->input('area')] as $a) {
            if (is_string($a) && isset($restringidas[$a])) $areas[] = $a;
        }

        $convIds = [];
        if ($request->is('atencion/conversacion/*')) $convIds[] = $request->route('id');
        $convIds[] = $request->input('conv_id');
        $convIds[] = $request->input('conversacion_id');
        if ($request->input('tipo') === 'wa') $convIds[] = $request->input('id');
        $convIds = array_values(array_unique(array_filter(array_map(
            fn ($v) => is_scalar($v) && ctype_digit((string) $v) ? (int) $v : null, $convIds))));
        if ($convIds) {
            $deConvs = ConversacionWA::whereIn('id', $convIds)->whereIn('area', array_keys($restringidas))->pluck('area')->all();
            $areas = array_merge($areas, $deConvs);
        }

        foreach (array_unique($areas) as $area) {
            if (!ConversacionWA::puedeVer($user, $area)) {
                if ($request->expectsJson()) {
                    return response()->json(['ok' => false, 'error' => 'No tenés acceso a esa área.'], 403);
                }
                abort(403, 'No tenés acceso a esa área.');
            }
        }

        return $next($request);
    }
}
